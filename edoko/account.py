"""The account page: your preferences, your picture and your passkeys."""

from zoneinfo import available_timezones

from flask import (
    Blueprint,
    flash,
    g,
    redirect,
    render_template,
    request,
    url_for,
)
from flask_babel import gettext as _

from . import avatars
from .auth import login_required, rate_limited
from .db import get_db

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


def init_app(app) -> None:
    app.register_blueprint(bp)
