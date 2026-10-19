"""The admin pages (for users with is_admin; several are fine)."""

from flask import Blueprint, render_template

from .auth import admin_required
from .db import query

bp = Blueprint("admin", __name__)


@bp.route("/admin")
@admin_required
def index():
    users = query(
        """SELECT user.id, user.name, user.email, user.is_admin, user.disabled_at,
                  user.last_login, user.created_at, user.mail_status,
                  (SELECT count(*) FROM passkey WHERE passkey.user = user.id) AS keys,
                  (SELECT count(*) FROM hand WHERE hand.user = user.id) AS games
           FROM user ORDER BY user.last_login DESC, user.id"""
    )
    return render_template("admin.html", users=users)


def init_app(app) -> None:
    app.register_blueprint(bp)
