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
from .auth import login_required

bp = Blueprint("account", __name__)


@bp.route("/account")
@login_required
def index():
    return render_template("account.html", picture=avatars.source_of(g.user["id"]))


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


@bp.post("/account/avatar/reset")
@login_required
def avatar_reset():
    avatars.remove(g.user["id"])
    flash(_("You have the generated picture again."))
    return redirect(url_for("account.index"))


def init_app(app) -> None:
    app.register_blueprint(bp)
