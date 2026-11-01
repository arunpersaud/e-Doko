"""The admin pages (for users with is_admin; several are fine)."""

from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_babel import gettext as _

from . import signup
from .auth import admin_required, now
from .db import query

bp = Blueprint("admin", __name__)

# the columns of the user table that can be sorted: name -> (SQL, first direction)
SORTS = {
    "name": ("user.name", "asc"),
    "email": ("user.email", "asc"),
    "games": ("games", "desc"),
    "passkeys": ("passkeys", "desc"),
    "last_login": ("user.last_login", "desc"),
}
DEFAULT_SORT = "last_login"


def sort_order(sort: str | None, direction: str | None) -> tuple[str, str]:
    """The column and direction to sort by; anything unknown means the default."""
    if sort not in SORTS:
        sort, direction = DEFAULT_SORT, None
    if direction not in ("asc", "desc"):
        direction = SORTS[sort][1]
    return sort, direction


def sorted_users(sort: str, direction: str):
    column = SORTS[sort][0]
    # the same words as the SQL, taken from the table above, never from the request;
    # users without a value (never logged in) come last, whatever the direction
    return query(
        f"""SELECT user.id, user.name, user.email, user.is_admin, user.disabled_at,
                   user.last_login, user.created_at, user.mail_status,
                   (SELECT count(*) FROM passkey
                     WHERE passkey.user = user.id) AS passkeys,
                   (SELECT count(*) FROM hand WHERE hand.user = user.id) AS games
            FROM user
            ORDER BY ({column} IS NULL), {column} {direction.upper()}, user.id"""
    )


@bp.route("/admin")
@admin_required
def index():
    sort, direction = sort_order(request.args.get("sort"), request.args.get("dir"))
    # a click on the column that is sorted by reverses it; another column starts
    # with its natural direction (A-Z for text, most first for numbers and dates)
    links = {
        column: {
            "sort": column,
            "dir": ("desc" if direction == "asc" else "asc")
            if column == sort
            else first,
        }
        for column, (_, first) in SORTS.items()
    }
    return render_template(
        "admin.html",
        users=sorted_users(sort, direction),
        bounces=query(
            """SELECT occurred_at, address, kind, subtype, cleared_at FROM mail_bounce
               ORDER BY occurred_at DESC, id DESC LIMIT 20"""
        ),
        sort=sort,
        direction=direction,
        links=links,
        signup_mode=signup.mode(),
        modes=signup.MODES,
        invitations=query(
            """SELECT email, expires_at FROM signup_token
               WHERE invited = 1 AND used_at IS NULL AND expires_at > ? ORDER BY id DESC""",
            (now(),),
        ),
    )


@bp.post("/admin/signup")
@admin_required
def set_signup():
    value = request.form.get("mode", "")
    if value not in signup.MODES:
        flash(_("Unknown setting."))
    else:
        signup.set_mode(value)
        flash(_("Sign-up is now: %(mode)s.", mode=value))
    return redirect(url_for("admin.index") + "#signup")


@bp.post("/admin/invite")
@admin_required
def invite():
    address = request.form.get("email", "")
    try:
        signup.send_invitation(address)
    except ValueError as problem:
        flash(str(problem))
    else:
        flash(_("Invitation sent to %(address)s.", address=address.strip().lower()))
    return redirect(url_for("admin.index") + "#signup")


def init_app(app) -> None:
    app.register_blueprint(bp)
