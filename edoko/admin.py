"""The admin pages (for users with is_admin; several are fine)."""

from flask import Blueprint, render_template, request

from .auth import admin_required
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
        sort=sort,
        direction=direction,
        links=links,
    )


def init_app(app) -> None:
    app.register_blueprint(bp)
