"""Sign-up: an address, a mailed single-use link, then the choice of a name.

No password and no CAPTCHA: the link proves the address, and requests are
limited per client and per address. Somebody who asks for an account with an
address that already has one gets a login link instead, and the answer is the
same either way, so nobody can find out who has an account.

Protection: limits per client (a keyed hash of the address) and per address, a
cap on all sign-up mails per hour and day (the SES reputation), no mail to an
address that bounced, a hidden field for bots, and a setting an admin can turn:
open, invite (only addresses an admin invited) or closed.
"""

import re
import secrets
import sqlite3
from datetime import UTC, datetime, timedelta

from flask import (
    Blueprint,
    current_app,
    has_request_context,
    render_template,
    request,
    session,
    url_for,
)
from flask_babel import force_locale
from flask_babel import gettext as _
from flask_babel import lazy_gettext as _l

from . import auth, mail
from .db import get_db, query_one

bp = Blueprint("signup", __name__)

LINK_HOURS = 24
LIMIT_ADDRESS = 3  # mails per address and hour
LIMIT_CLIENT = 10  # requests per client and hour
CAP_HOUR = 30  # sign-up and login mails sent through this form, all clients together
CAP_DAY = 200
INVITE_HOURS = 7 * 24
MODES = ("open", "invite", "closed")
NAME_LENGTH = (2, 40)
ADDRESS = re.compile(r"[^@\s]+@[^@\s]+\.[^@\s]+")


def clean_name(text: str | None) -> str:
    return " ".join((text or "").split())


def name_problem(name: str) -> str | None:
    low, high = NAME_LENGTH
    if not low <= len(name) <= high:
        return _("The name needs %(low)d to %(high)d characters.", low=low, high=high)
    if any(not c.isprintable() for c in name) or "<" in name or ">" in name:
        return _("Please use letters, digits and ordinary punctuation in the name.")
    if query_one("SELECT 1 FROM user WHERE name = ?", (name,)):
        return _("Somebody already uses that name.")
    return None


def mode() -> str:
    row = query_one("SELECT value FROM setting WHERE key = 'signup'")
    return row["value"] if row and row["value"] in MODES else "open"


def set_mode(value: str) -> None:
    if value not in MODES:
        raise ValueError(value)
    get_db().execute(
        "INSERT INTO setting (key, value) VALUES ('signup', ?)"
        " ON CONFLICT (key) DO UPDATE SET value = excluded.value",
        (value,),
    )


def valid_token(token: str):
    """The sign-up token if it works now: unused, not expired, and the setting allows it."""
    row = query_one(
        "SELECT * FROM signup_token WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?",
        (auth.sha256(token), auth.now()),
    )
    allowed = {"open": True, "invite": bool(row and row["invited"]), "closed": False}
    return row if row and allowed[mode()] else None


def invite(address: str, hours: int = INVITE_HOURS) -> str:
    """A sign-up token for an address an admin invites (whatever the setting); its path."""
    address = clean_name(address).lower()
    if not ADDRESS.fullmatch(address) or len(address) > 254:
        raise ValueError(_("That is not an email address."))
    if query_one("SELECT 1 FROM user WHERE email = ?", (address,)):
        raise ValueError(_("This address has an account already."))
    token = secrets.token_urlsafe(32)
    expires = datetime.now(UTC) + timedelta(hours=hours)
    get_db().execute(
        "INSERT INTO signup_token (email, token_hash, expires_at, invited) VALUES (?, ?, ?, 1)",
        (address, auth.sha256(token), expires.strftime("%Y-%m-%d %H:%M:%S")),
    )
    return "/signup/" + token


def send_invitation(address: str) -> str:
    """Invite an address and mail the link; returns the link."""
    path = invite(address)
    link = current_app.config["ORIGIN"] + path
    with force_locale(_language()):
        subject = str(_l("You are invited to e-DoKo"))
        body = render_template("mail/signup_invite.txt", link=link, days=INVITE_HOURS // 24)
    mail.send(clean_name(address).lower(), subject, body)
    return link


def _language() -> str:
    if has_request_context():
        return request.accept_languages.best_match(["en", "de"]) or "en"
    return "en"


def bounced(address: str) -> bool:
    """Did mail to this address bounce for good (or draw a complaint)?"""
    return (
        query_one(
            "SELECT 1 FROM mail_bounce WHERE address = ? AND cleared_at IS NULL"
            " AND kind IN ('permanent', 'complaint')",
            (address,),
        )
        is not None
    )


def mail_cap_wait() -> int:
    """Minutes to wait if too many mails went out through the form, else 0."""
    db = get_db()
    for hours, cap in ((1, CAP_HOUR), (24, CAP_DAY)):
        since = (datetime.now(UTC) - timedelta(hours=hours)).strftime("%Y-%m-%d %H:%M:%S")
        rows = db.execute(
            "SELECT at FROM rate_limit WHERE key = 'signup-mail' AND at >= ? ORDER BY at", (since,)
        ).fetchall()
        if len(rows) >= cap:
            oldest = datetime.strptime(rows[0][0], "%Y-%m-%d %H:%M:%S").replace(tzinfo=UTC)
            left = oldest + timedelta(hours=hours) - datetime.now(UTC)
            return max(1, -(-int(left.total_seconds()) // 60))
    return 0


@bp.route("/signup")
def form():
    if mode() != "open":
        return render_template("signup_closed.html", mode=mode()), 403
    auth.csrf_token()
    return render_template("signup.html")


@bp.post("/signup")
def request_link():
    if mode() != "open":
        return render_template("signup_closed.html", mode=mode()), 403
    get_db().execute("DELETE FROM signup_token WHERE expires_at < datetime('now', '-1 day')")
    if request.form.get("website"):  # the hidden field: only bots fill it in
        return render_template("signup_sent.html", demo_mails=[])
    wait = auth.rate_wait("ip:" + auth.ip_key(), LIMIT_CLIENT)
    if wait:
        return render_template("signup_limited.html", minutes=wait), 429
    address = clean_name(request.form.get("email")).lower()
    if not (ADDRESS.fullmatch(address) and len(address) <= 254):
        return render_template("signup_sent.html", demo_mails=[])
    if wait := mail_cap_wait():
        return render_template("signup_limited.html", minutes=wait, busy=True), 503
    if not auth.rate_limited("addr:" + auth.sha256(address), LIMIT_ADDRESS) and not bounced(address):
        user = query_one("SELECT * FROM user WHERE email = ? AND disabled_at IS NULL", (address,))
        if user is not None:  # already has an account: the login link is what they need
            token = auth.create_link(user["id"])
            link = current_app.config["ORIGIN"] + url_for("auth.link", token=token)
            get_db().execute("INSERT INTO rate_limit (key) VALUES ('signup-mail')")
            mail.send_template(
                user, _l("Your login link"), "login", link=link, hours=auth.LINK_HOURS
            )
        elif query_one("SELECT 1 FROM user WHERE email = ?", (address,)) is None:
            token = secrets.token_urlsafe(32)
            expires = datetime.now(UTC) + timedelta(hours=LINK_HOURS)
            get_db().execute(
                "INSERT INTO signup_token (email, token_hash, expires_at) VALUES (?, ?, ?)",
                (address, auth.sha256(token), expires.strftime("%Y-%m-%d %H:%M:%S")),
            )
            link = current_app.config["ORIGIN"] + url_for("signup.choose", token=token)
            with force_locale(_language()):
                subject = str(_l("Welcome to e-DoKo"))
                body = render_template("mail/signup.txt", link=link, hours=LINK_HOURS)
            get_db().execute("INSERT INTO rate_limit (key) VALUES ('signup-mail')")
            mail.send(address, subject, body)
    demo_mails = []
    if current_app.config.get("DEMO"):  # (see demo.py: no mail goes out, show it here)
        from . import demo

        demo_mails = demo.mails_to(address)
    return render_template("signup_sent.html", demo_mails=demo_mails)


@bp.route("/signup/<token>")
def choose(token):
    row = valid_token(token)
    if row is None:
        return render_template("signup_invalid.html"), 404
    auth.csrf_token()
    return render_template("signup_name.html", token=token, name="", problem=None)


@bp.post("/signup/<token>")
def create(token):
    row = valid_token(token)
    if row is None:
        return render_template("signup_invalid.html"), 404
    name = clean_name(request.form.get("name"))
    problem = name_problem(name)
    if problem:
        return render_template("signup_name.html", token=token, name=name, problem=problem), 400
    # the account is made when the passkey has been verified (passkeys.py)
    session["signup"] = {"token": token, "name": name}
    auth.csrf_token()
    return render_template("signup_passkey.html", name=name)


def pending() -> dict | None:
    """The sign-up in this session (name chosen, passkey to come), if its link still works."""
    state = session.get("signup")
    if not state or valid_token(state["token"]) is None:
        return None
    return state


def create_account(state: dict, webauthn_id: bytes, credential: dict, language: str | None) -> int:
    """The user and the first passkey, in one step; raises ValueError if the name is taken."""
    row = valid_token(state["token"])
    if row is None:
        raise ValueError(_("This link does not work any more."))
    db = get_db()
    try:
        db.execute("BEGIN IMMEDIATE")
        db.execute("UPDATE signup_token SET used_at = ? WHERE id = ?", (auth.now(), row["id"]))
        user_id = db.execute(
            "INSERT INTO user (name, email, language, webauthn_id) VALUES (?, ?, ?, ?)",
            (state["name"], row["email"], language, webauthn_id),
        ).lastrowid
        db.execute(
            "INSERT INTO passkey (user, credential_id, public_key, sign_count, name)"
            " VALUES (?, ?, ?, ?, ?)",
            (user_id, *credential),
        )
        db.execute("COMMIT")
    except sqlite3.IntegrityError:
        db.execute("ROLLBACK")
        raise ValueError(_("Somebody already uses that name.")) from None
    return user_id


def init_app(app) -> None:
    app.register_blueprint(bp)
