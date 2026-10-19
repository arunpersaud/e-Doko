"""Games: who may see what, and the read-only game pages.

Visibility (the rule of the PHP site, and the reason for it): a player
never plays a deal they have already seen. So a game's details are shown
only if the game is over AND the viewer has finished a game on the same
deal. That covers the viewer's own games, and the games other tables
played on the same deal (the fun part: how did the others play my hand?).
Games in progress are never shown to anybody else, not even their hands.
Everybody else gets a 404, as if the game didn't exist.
"""

from flask import Blueprint, abort, g, redirect, render_template, request, url_for
from flask_babel import lazy_gettext as _l
from werkzeug.routing import BaseConverter

from . import gameview
from .auth import login_required
from .db import query, query_one

bp = Blueprint("games", __name__)

OVER = "gameover"

# pages with hands and tokens: nothing may keep them
PRIVATE = {"Cache-Control": "private, no-store", "X-Robots-Tag": "noindex"}

SUITS = {
    "diamonds": _l("diamonds"),
    "hearts": _l("hearts"),
    "spades": _l("spades"),
    "clubs": _l("clubs"),
}
RANKS = {
    "nine": _l("nine"),
    "ten": _l("ten"),
    "jack": _l("jack"),
    "queen": _l("queen"),
    "king": _l("king"),
    "ace": _l("ace"),
}


def can_see_details(user_id: int, game_id: int) -> bool:
    """May this user see the hands and moves of this game?"""
    row = query_one(
        """SELECT 1 FROM game g
           WHERE g.id = :game AND g.status = :over AND EXISTS (
             SELECT 1 FROM hand h JOIN game mine ON mine.id = h.game
             WHERE h.user = :user AND mine.deal = g.deal AND mine.status = :over)""",
        {"game": game_id, "user": user_id, "over": OVER},
    )
    return row is not None


def is_player(user_id: int, game_id: int) -> bool:
    return (
        query_one("SELECT 1 FROM hand WHERE game = ? AND user = ?", (game_id, user_id))
        is not None
    )


def other_tables(user_id: int, game_id: int):
    """Finished games on the same deal as this one (not this one itself).

    Only for viewers who may see the details of this game, so they have
    finished the deal themselves.
    """
    return query(
        """SELECT o.id, o.session, o.type, o.solo, o.created_at,
                  (SELECT group_concat(user.name, ', ')
                     FROM (SELECT user FROM hand WHERE game = o.id ORDER BY seat) h
                     JOIN user ON user.id = h.user) AS players
           FROM game g JOIN game o ON o.deal = g.deal AND o.id != g.id
           WHERE g.id = ? AND o.status = ?
           ORDER BY o.id""",
        (game_id, OVER),
    )


def card_name(card) -> str:
    return f"{RANKS[card['rank']]} {SUITS[card['suit']]}"


def image_number(card_id: int) -> int:
    """Only odd card ids have an image; the two copies share it."""
    return card_id if card_id % 2 else card_id - 1


def trick_url(number: int) -> str:
    """The address of the same page showing another trick: .../trick/N."""
    return url_for(request.endpoint, **{**request.view_args, "trick": number})


def render_game(
    game_id: int,
    user_id: int,
    *,
    full: bool,
    trick: int | None = None,
    logged_in: bool = True,
    origin: int | None = None,
):
    """The page of a game for one of its players or for a qualified viewer.

    full: the viewer may see all hands (the game is over). Otherwise they see
    the tricks so far and their own cards.
    origin: another game of the same deal; the table is turned so that its
    seats are at the same places as in that game.
    """
    anchor = gameview.viewpoint_seat(origin, user_id) if origin else None
    context = gameview.build(
        game_id, user_id, full=full, trick=trick, anchor_seat=anchor
    )
    context.update(
        origin=origin,
        user_id=user_id,
        logged_in=logged_in,
        others=[],
        previous=None,
        next=None,
        trick_url=trick_url,
    )
    if logged_in:
        # only games this viewer may open: their own, and those of the same deals
        def allowed(other_id: int) -> bool:
            return is_player(user_id, other_id) or can_see_details(user_id, other_id)

        context["previous"], context["next"] = gameview.neighbours(
            game_id, context["game"]["session"], allowed
        )
        if full:
            context["others"] = other_tables(user_id, game_id)
    response = render_template("game.html", **context)
    return response, 200, PRIVATE


ACTIVE = ("pre", "play")


def my_games(user_id: int):
    """All games of the user, newest table and game first."""
    return query(
        """SELECT game.id, game.session, game.status, game.type, game.solo,
                  game.created_at, game.updated_at, game.turn_seat,
                  me.seat AS my_seat, me.party AS my_party,
                  (SELECT user.name FROM hand t JOIN user ON user.id = t.user
                    WHERE t.game = game.id AND t.seat = game.turn_seat) AS turn_name
           FROM hand me JOIN game ON game.id = me.game
           WHERE me.user = ?
           ORDER BY game.session DESC, game.id DESC""",
        (user_id,),
    )


def table_players(session_id: int):
    return query(
        """SELECT user.id, user.name FROM session_player
           JOIN user ON user.id = session_player.user
           WHERE session_player.session = ? ORDER BY user.name""",
        (session_id,),
    )


def dashboard(user_id: int) -> dict:
    """The home page of a logged in player: whose turn it is, and the tables."""
    mine = my_games(user_id)
    your_turn = [
        x for x in mine if x["status"] in ACTIVE and x["turn_seat"] == x["my_seat"]
    ]
    waiting = [
        x for x in mine if x["status"] in ACTIVE and x["turn_seat"] != x["my_seat"]
    ]
    tables: dict[int, dict] = {}
    for x in mine:
        t = tables.setdefault(
            x["session"],
            {"id": x["session"], "games": 0, "last": x["updated_at"], "active": False},
        )
        t["games"] += 1
        t["last"] = max(t["last"], x["updated_at"])
        t["active"] = t["active"] or x["status"] in ACTIVE
    for t in tables.values():
        t["players"] = [p["name"] for p in table_players(t["id"]) if p["id"] != user_id]
    return {
        "your_turn": your_turn,
        "waiting": waiting,
        "tables": sorted(tables.values(), key=lambda t: -t["id"]),
    }


@bp.route("/table/<int:session_id>/")
@login_required
def table(session_id):
    players = table_players(session_id)
    if g.user["id"] not in {p["id"] for p in players}:
        abort(404)
    rows = [x for x in my_games(g.user["id"]) if x["session"] == session_id]
    return (
        render_template(
            "table.html", session_id=session_id, players=players, games=rows
        ),
        200,
        PRIVATE,
    )


def comparable(user_id: int, game_id: int, origin: int) -> bool:
    """Is origin another finished game of the deal of game_id that the user may open?"""
    return (
        origin != game_id
        and query_one(
            "SELECT 1 FROM game a JOIN game b ON b.deal = a.deal"
            " WHERE a.id = ? AND b.id = ?",
            (game_id, origin),
        )
        is not None
        and can_see_details(user_id, origin)
    )


@bp.route("/game/<int:game_id>/", defaults={"trick": None, "origin": None})
@bp.route("/game/<int:game_id>/trick/<int:trick>", defaults={"origin": None})
@bp.route("/game/<int:game_id>/compare/<int:origin>/", defaults={"trick": None})
@bp.route("/game/<int:game_id>/compare/<int:origin>/trick/<int:trick>")
@login_required
def game(game_id, trick, origin):
    if (old := request.args.get("trick", type=int)) is not None:  # an old link
        return redirect(url_for("games.game", game_id=game_id, trick=old))
    if query_one("SELECT 1 FROM game WHERE id = ?", (game_id,)) is None:
        abort(404)
    user_id = g.user["id"]
    details = can_see_details(user_id, game_id)
    if not details and not is_player(user_id, game_id):
        abort(404)
    if origin is not None and not (details and comparable(user_id, game_id, origin)):
        # not the same deal, or not a game this viewer may see: the plain page
        return redirect(url_for("games.game", game_id=game_id, trick=trick))
    return render_game(game_id, user_id, full=details, trick=trick, origin=origin)


@bp.route("/<token:token>", defaults={"trick": None})
@bp.route("/<token:token>/trick/<int:trick>")
def seat(token, trick):
    """A player's own link (from the mails): no login, but only this seat.

    The token is a secret that stands for one seat of one game, so it shows
    that game and nothing else. Whoever has it can play that seat, as on
    the PHP site.
    """
    if (old := request.args.get("trick", type=int)) is not None:  # an old link
        return redirect(url_for("games.seat", token=token, trick=old))
    hand = query_one(
        """SELECT hand.game, hand.user, game.status FROM hand
           JOIN game ON game.id = hand.game WHERE hand.token = ?""",
        (token,),
    )
    if hand is None:
        abort(404)
    logged_in = g.user is not None and g.user["id"] == hand["user"]
    return render_game(
        hand["game"],
        hand["user"],
        full=hand["status"] == OVER,
        trick=trick,
        logged_in=logged_in,
    )


class TokenConverter(BaseConverter):
    """Seat tokens: 32 hex digits (the PHP site) or 22 url-safe characters."""

    regex = r"[0-9a-f]{32}|[A-Za-z0-9_-]{22}"


def init_app(app) -> None:
    app.url_map.converters["token"] = TokenConverter
    app.register_blueprint(bp)
    app.jinja_env.globals.update(card_name=card_name, image_number=image_number)
