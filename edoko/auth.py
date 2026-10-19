"""Login: single-use links by email, sessions, CSRF protection.

There are no passwords. A user asks for a login link, gets it by mail
and logs in with it (passkeys are set up from the account page, see
passkeys.py). Only the hash of a link's token is stored; the link opens
a page with a button, and only that button (a POST) uses the token up,
so mail scanners that open links can't spoil it.

Every POST needs the CSRF token of the session (form field "csrf" or
header X-CSRF-Token) and, if the browser sends one, an Origin header that
matches ORIGIN.
"""

import hashlib
import secrets
from datetime import UTC, datetime, timedelta
from functools import wraps
from urllib.parse import urlencode

from flask import (
    Blueprint,
    abort,
    current_app,
    flash,
    g,
    redirect,
    render_template,
    request,
    session,
    url_for,
)
from flask_babel import gettext as _
from flask_babel import lazy_gettext as _l

from . import mail
from .db import get_db, query_one

LINK_HOURS = 1
# login links per address / per client in an hour
LIMIT_ADDRESS = 3
LIMIT_CLIENT = 10

SKIP_COOKIE = "passkey_skip"  # set when the user declines the passkey offer

bp = Blueprint("auth", __name__)


def now() -> str:
    return datetime.now(UTC).strftime("%Y-%m-%d %H:%M:%S")


def sha256(text: str) -> str:
    return hashlib.sha256(text.encode()).hexdigest()


def csrf_token() -> str:
    if "csrf" not in session:
        session["csrf"] = secrets.token_urlsafe(32)
    return session["csrf"]


def client_ip() -> str:
    # behind Cloudflare and Caddy; the app is not reachable directly
    return request.headers.get("CF-Connecting-IP") or request.remote_addr or ""


def log_in(user_id: int) -> None:
    session.clear()
    session.permanent = True
    session["user_id"] = user_id
    csrf_token()
    get_db().execute("UPDATE user SET last_login = ? WHERE id = ?", (now(), user_id))


def load_user() -> None:
    """g.user: the logged in user (or None), for every request."""
    g.user = None
    if "user_id" in session:
        g.user = query_one(
            "SELECT * FROM user WHERE id = ? AND disabled_at IS NULL",
            (session["user_id"],),
        )
        if g.user is None:
            session.clear()


def login_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if g.user is None:
            return redirect(url_for("auth.login", next=request.path))
        return view(*args, **kwargs)

    return wrapped


def admin_required(view):
    @wraps(view)
    @login_required
    def wrapped(*args, **kwargs):
        if not g.user["is_admin"]:
            abort(403)
        return view(*args, **kwargs)

    return wrapped


def avatars_upload_limit() -> int:
    from .avatars import MAX_UPLOAD

    return MAX_UPLOAD + 64 * 1024  # the picture and the rest of the form


def check_post() -> None:
    """CSRF: the session's token, and the request must come from this site.

    Browsers send Sec-Fetch-Site, and "Origin: null" when the referrer
    policy is no-referrer (ours is), so null is fine for a same-origin
    request, and only then.
    """
    if request.method != "POST":
        return
    if request.endpoint == "account.avatar_upload":
        # pictures are bigger than the 64 KB the other forms are limited to
        request.max_content_length = avatars_upload_limit()
    site = request.headers.get("Sec-Fetch-Site")
    if site is not None and site not in ("same-origin", "none"):
        abort(403)
    sent_origin = request.headers.get("Origin")
    if sent_origin == "null":
        if site != "same-origin":
            abort(403)
    elif sent_origin is not None and sent_origin != current_app.config["ORIGIN"]:
        abort(403)
    sent = request.headers.get("X-CSRF-Token") or request.form.get("csrf")
    if not sent or not secrets.compare_digest(sent, session.get("csrf", "")):
        abort(403)


def wants_offer(user_id: int) -> bool:
    """After a login by link: suggest a passkey, unless one exists or was skipped."""
    if request.cookies.get(SKIP_COOKIE):
        return False
    return query_one("SELECT 1 FROM passkey WHERE user = ?", (user_id,)) is None


def safe_next(target: str | None) -> str:
    """Where to go after login: a path on this site, nothing else."""
    if target and target.startswith("/") and not target.startswith("//"):
        return target
    return url_for("site.index")


# --- login links -------------------------------------------------------------


def rate_limited(key: str, limit: int) -> bool:
    """Count an attempt under key; True if there were too many this hour."""
    db = get_db()
    db.execute("DELETE FROM rate_limit WHERE at < datetime('now', '-1 day')")
    since = (datetime.now(UTC) - timedelta(hours=1)).strftime("%Y-%m-%d %H:%M:%S")
    count = db.execute(
        "SELECT count(*) FROM rate_limit WHERE key = ? AND at >= ?", (key, since)
    ).fetchone()[0]
    if count >= limit:
        return True
    db.execute("INSERT INTO rate_limit (key) VALUES (?)", (key,))
    return False


def create_link(user_id: int) -> str:
    """A new single-use login token for the user (only its hash is kept)."""
    token = secrets.token_urlsafe(32)
    expires = datetime.now(UTC) + timedelta(hours=LINK_HOURS)
    get_db().execute(
        "INSERT INTO login_token (user, token_hash, expires_at) VALUES (?, ?, ?)",
        (user_id, sha256(token), expires.strftime("%Y-%m-%d %H:%M:%S")),
    )
    return token


def valid_link(token: str):
    """The user row for an unused, unexpired token, or None."""
    return query_one(
        """SELECT user.*, login_token.id AS token_id FROM login_token
           JOIN user ON user.id = login_token.user
           WHERE login_token.token_hash = ? AND login_token.used_at IS NULL
             AND login_token.expires_at > ? AND user.disabled_at IS NULL""",
        (sha256(token), now()),
    )


@bp.route("/login")
def login():
    if g.user is not None:
        return redirect(safe_next(request.args.get("next")))
    csrf_token()
    return render_template("login.html", next=request.args.get("next", ""))


@bp.post("/login/email")
def login_email():
    address = " ".join((request.form.get("email") or "").split()).lower()
    # the same answer whatever happens, so nobody can probe for addresses
    limited = rate_limited("ip:" + sha256(client_ip()), LIMIT_CLIENT)
    if (
        address
        and not limited
        and not rate_limited("addr:" + sha256(address), LIMIT_ADDRESS)
    ):
        user = query_one(
            "SELECT * FROM user WHERE email = ? AND disabled_at IS NULL", (address,)
        )
        if user is not None:
            token = create_link(user["id"])
            link = current_app.config["ORIGIN"] + url_for("auth.link", token=token)
            if target := request.form.get("next"):
                link += "?" + urlencode({"next": safe_next(target)})
            mail.send_template(
                user, _l("Your login link"), "login", link=link, hours=LINK_HOURS
            )
    return render_template("login_sent.html")


@bp.route("/login/link/<token>")
def link(token):
    user = valid_link(token)
    if user is None:
        return render_template("login_link_invalid.html"), 404
    csrf_token()
    return render_template(
        "login_link.html", user=user, token=token, next=request.args.get("next", "")
    )


@bp.post("/login/link/<token>")
def link_use(token):
    user = valid_link(token)
    if user is None:
        return render_template("login_link_invalid.html"), 404
    get_db().execute(
        "UPDATE login_token SET used_at = ? WHERE id = ?", (now(), user["token_id"])
    )
    log_in(user["id"])
    target = safe_next(request.form.get("next"))
    if wants_offer(user["id"]):
        return redirect(url_for("passkeys.offer", next=target))
    return redirect(target)


@bp.post("/logout")
def logout():
    session.clear()
    flash(_("You are logged out."))
    return redirect(url_for("site.index"))


def init_app(app) -> None:
    app.before_request(load_user)
    app.before_request(check_post)
    app.register_blueprint(bp)

    @app.context_processor
    def auth_context():
        return {"current_user": g.get("user"), "csrf_token": csrf_token}
