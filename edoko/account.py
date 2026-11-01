"""The account page: your preferences, your picture and your passkeys."""

import re
import secrets
from datetime import UTC, datetime, timedelta
from zoneinfo import available_timezones

from flask import (
    Blueprint,
    current_app,
    flash,
    g,
    redirect,
    render_template,
    request,
    url_for,
)
from flask_babel import gettext as _
from flask_babel import lazy_gettext as _l

from . import avatars, bounces, mail
from .auth import login_required, now, rate_limited, sha256
from .db import get_db, query_one

bp = Blueprint("account", __name__)


DIGEST_HOURS = (1, 2, 3, 4, 6, 12, 24)
MAIL_MODES = ("normal", "fewer", "none")


@bp.route("/account")
@login_required
def index():
    return render_template(
        "account.html",
        kept=avatars.kept(g.user["id"]),
        zones=sorted(available_timezones()),
        digest_hours=DIGEST_HOURS,
    )


@bp.post("/account/preferences")
@login_required
def preferences():
    form = request.form
    language = form.get("language") or None
    zone = form.get("timezone", "").strip()
    mode = form.get("mail_mode", "")
    digest = form.get("digest_hours", "")
    if (
        language not in (None, "en", "de")
        or zone not in available_timezones()
        or mode not in MAIL_MODES
        or digest not in ("", *map(str, DIGEST_HOURS))
    ):
        flash(_("Something in the form was not right, nothing was saved."))
        return redirect(url_for("account.index"))
    get_db().execute(
        """UPDATE user SET language = ?, timezone = ?, mail_mode = ?, digest_hours = ?,
                  open_for_games = ?, autosetup = ? WHERE id = ?""",
        (
            language, zone, mode, int(digest) if digest else None,
            int("open_for_games" in form), int("autosetup" in form), g.user["id"],
        ),
    )
    flash(_("Your settings were saved."))
    return redirect(url_for("account.index"))


@bp.post("/account/avatar/upload")
@login_required
def avatar_upload():
    upload = request.files.get("picture")
    data = upload.read(avatars.MAX_UPLOAD + 1) if upload else b""
    if not data:
        flash(_("Please choose a picture."))
    elif len(data) > avatars.MAX_UPLOAD:
        flash(_("The picture is too big (at most 2 MB)."))
    else:
        try:
            avatars.store(g.user["id"], "upload", avatars.process(data))
            flash(_("Your picture was saved."))
        except avatars.BadPicture as error:
            flash(str(error))
    return redirect(url_for("account.index"))


@bp.post("/account/avatar/gravatar")
@login_required
def avatar_gravatar():
    """Fetch the Gravatar of the account's address: the user asked for it."""
    if rate_limited(f"gravatar:{g.user['id']}", avatars.GRAVATAR_TRIES):
        flash(_("Too many tries. Please wait a little."))
        return redirect(url_for("account.index"))
    try:
        image = avatars.process(avatars.fetch_gravatar(g.user["email"]))
    except avatars.NoGravatar:
        flash(_("Gravatar has no picture for your address."))
    except avatars.GravatarUnavailable, avatars.BadPicture:
        flash(_("Could not get your picture from Gravatar. Please try again later."))
    else:
        avatars.store(g.user["id"], "gravatar", image)
        flash(_("Your Gravatar was saved."))
    return redirect(url_for("account.index"))


@bp.post("/account/avatar/choose")
@login_required
def avatar_choose():
    if avatars.choose(g.user["id"], request.form.get("source", "")):
        flash(_("Your picture was changed."))
    return redirect(url_for("account.index"))


@bp.post("/account/avatar/delete")
@login_required
def avatar_delete():
    source = request.form.get("source", "")
    if source in ("upload", "gravatar"):
        avatars.delete(g.user["id"], source)
        flash(_("The copy of the picture was deleted."))
    return redirect(url_for("account.index"))


CHANGE_HOURS = 24
CHANGE_TRIES = 3  # requests per user and hour
ADDRESS = re.compile(r"[^@\s]+@[^@\s]+\.[^@\s]+")


def _contact() -> str:
    return current_app.config.get("CONTACT_EMAIL") or ""


@bp.post("/account/email")
@login_required
def email_request():
    """Step 1: a link goes to the NEW address; the old one is told about it."""
    address = " ".join((request.form.get("email") or "").split()).lower()
    if not (ADDRESS.fullmatch(address) and len(address) <= 254):
        flash(_("That does not look like an email address."))
        return redirect(url_for("account.index"))
    if address == g.user["email"].lower():
        flash(_("That is already your address."))
        return redirect(url_for("account.index"))
    if rate_limited(f"email-change:{g.user['id']}", CHANGE_TRIES):
        flash(_("Too many tries. Please wait a little."))
        return redirect(url_for("account.index"))
    db = get_db()
    db.execute("DELETE FROM email_change WHERE expires_at < datetime('now', '-1 day')")
    # the same answer whatever the reason, so nobody can probe for addresses
    if (
        not query_one("SELECT 1 FROM user WHERE email = ?", (address,))
        and not bounces_for(address)
    ):
        db.execute(
            "UPDATE email_change SET used_at = ? WHERE user = ? AND used_at IS NULL",
            (now(), g.user["id"]),
        )
        token = secrets.token_urlsafe(32)
        expires = datetime.now(UTC) + timedelta(hours=CHANGE_HOURS)
        db.execute(
            "INSERT INTO email_change (user, new_email, token_hash, expires_at) VALUES (?, ?, ?, ?)",
            (g.user["id"], address, sha256(token), expires.strftime("%Y-%m-%d %H:%M:%S")),
        )
        link = current_app.config["ORIGIN"] + url_for("account.email_confirm", token=token)
        subject, body = mail.render(
            g.user, _l("Confirm your new email address"), "email_new",
            link=link, hours=CHANGE_HOURS, contact=_contact(),
        )
        mail.send(address, subject, body)
        # the old address learns of it now, so a wrong request can be stopped
        mail.send_template(
            g.user, _l("Somebody asked to change your email address"), "email_old",
            new=address, contact=_contact(), done=False,
        )
    flash(_("If that address can be used, we have sent a link to it. The change happens when you open that link."))
    return redirect(url_for("account.index"))


def bounces_for(address: str) -> bool:
    return (
        query_one(
            "SELECT 1 FROM mail_bounce WHERE address = ? AND cleared_at IS NULL"
            " AND kind IN ('permanent', 'complaint')",
            (address,),
        )
        is not None
    )


def _pending(token: str):
    return query_one(
        """SELECT * FROM email_change WHERE token_hash = ? AND used_at IS NULL
           AND expires_at > ? AND user = ?""",
        (sha256(token), now(), g.user["id"]),
    )


@bp.route("/account/email/<token>")
@login_required
def email_confirm(token):
    change = _pending(token)
    if change is None:
        return render_template("email_change_invalid.html"), 404
    return render_template("email_change.html", change=change, token=token)


@bp.post("/account/email/<token>")
@login_required
def email_apply(token):
    """Step 2: the link was opened while logged in as this user."""
    change = _pending(token)
    if change is None:
        return render_template("email_change_invalid.html"), 404
    db = get_db()
    new = change["new_email"]
    if query_one("SELECT 1 FROM user WHERE email = ? AND id != ?", (new, g.user["id"])):
        return render_template("email_change_invalid.html"), 409
    old = dict(g.user)
    db.execute("UPDATE email_change SET used_at = ? WHERE id = ?", (now(), change["id"]))
    # links mailed to the old address must not work any more
    db.execute("DELETE FROM login_token WHERE user = ?", (g.user["id"],))
    db.execute("UPDATE user SET email = ? WHERE id = ?", (new, g.user["id"]))
    bounces.reset(g.user["id"])
    mail.send_template(
        old | {"mail_status": "ok"}, _l("Your email address was changed"), "email_old",
        new=new, contact=_contact(), done=True,
    )
    flash(_("Your email address was changed."))
    return redirect(url_for("account.index"))


def init_app(app) -> None:
    app.register_blueprint(bp)
