"""The account page: your picture and your passkeys."""

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

bp = Blueprint("account", __name__)


@bp.route("/account")
@login_required
def index():
    return render_template("account.html", kept=avatars.kept(g.user["id"]))


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
