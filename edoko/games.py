"""Games: who may see what, and the read-only game pages.

Visibility (the rule of the PHP site, and the reason for it): a player
never plays a deal they have already seen. So a game's details are shown
only if the game is over AND the viewer has finished a game on the same
deal. That covers the viewer's own games, and the games other tables
played on the same deal (the fun part: how did the others play my hand?).
Games in progress are never shown to anybody else, not even their hands.
Everybody else gets a 404, as if the game didn't exist.
"""

from flask import Blueprint, abort, g, render_template
from flask_babel import lazy_gettext as _l
from werkzeug.routing import BaseConverter

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


def load_game(game_id: int) -> dict:
    game = query_one(
        """SELECT game.*, ruleset.dullen, ruleset.schweinchen, ruleset.lowtrump,
                  ruleset.call
           FROM game JOIN ruleset ON ruleset.id = game.ruleset WHERE game.id = ?""",
        (game_id,),
    )
    hands = query(
        """SELECT hand.id, hand.seat, hand.party, hand.sickness, hand.solo,
                  hand.point_call, hand.points, user.id AS user_id, user.name
           FROM hand JOIN user ON user.id = hand.user
           WHERE hand.game = ? ORDER BY hand.seat""",
        (game_id,),
    )
    return {"game": game, "hands": hands}


def load_details(game_id: int, hands) -> dict:
    """Cards per hand (in the order they were played), tricks, comments, scores."""
    seat_of = {h["id"]: h["seat"] for h in hands}
    cards = {h["seat"]: [] for h in hands}
    for row in query(
        """SELECT hand_card.hand, card.id, card.suit, card.rank, card.points
           FROM hand_card JOIN card ON card.id = hand_card.card
           JOIN hand ON hand.id = hand_card.hand
           LEFT JOIN play ON play.hand_card = hand_card.id
           LEFT JOIN trick ON trick.id = play.trick
           WHERE hand.game = ?
           ORDER BY trick.number, play.sequence, card.id""",
        (game_id,),
    ):
        cards[seat_of[row["hand"]]].append(row)

    comments: dict[int, list] = {}
    for row in query(
        """SELECT comment.play, comment.body, comment.created_at, user.name
           FROM comment JOIN user ON user.id = comment.user
           WHERE comment.game = ? AND comment.kind = 'move'
             AND comment.deleted_at IS NULL
           ORDER BY comment.id""",
        (game_id,),
    ):
        comments.setdefault(row["play"], []).append(row)

    tricks = []
    for trick in query(
        "SELECT id, number, winner_seat FROM trick WHERE game = ? ORDER BY number",
        (game_id,),
    ):
        plays = query(
            """SELECT play.id, play.sequence, hand.seat, card.id AS card_id,
                      card.suit, card.rank
               FROM play JOIN hand_card ON hand_card.id = play.hand_card
               JOIN hand ON hand.id = hand_card.hand
               JOIN card ON card.id = hand_card.card
               WHERE play.trick = ? ORDER BY play.sequence""",
            (trick["id"],),
        )
        tricks.append(
            {
                "number": trick["number"],
                "winner_seat": trick["winner_seat"],
                "plays": [dict(p, comments=comments.get(p["id"], [])) for p in plays],
            }
        )

    before = query(
        """SELECT comment.kind, comment.body, user.name FROM comment
           JOIN user ON user.id = comment.user
           WHERE comment.game = ? AND comment.kind != 'move'
             AND comment.deleted_at IS NULL ORDER BY comment.id""",
        (game_id,),
    )
    scores = query(
        """SELECT score_item.party, score_item.kind, w.name AS winner, l.name AS loser
           FROM score_item LEFT JOIN user w ON w.id = score_item.winner
           LEFT JOIN user l ON l.id = score_item.loser
           WHERE score_item.game = ? ORDER BY score_item.id""",
        (game_id,),
    )
    return {"cards": cards, "tricks": tricks, "before": before, "scores": scores}


def render_game(game_id: int, user_id: int, *, details: bool, logged_in: bool = True):
    """The page of a game for one of its players or for a qualified viewer."""
    shown = load_game(game_id)
    context = {
        "user_id": user_id,
        "details": details,
        "logged_in": logged_in,
        "names": {h["seat"]: h["name"] for h in shown["hands"]},
        **shown,
    }
    if details:
        context.update(load_details(game_id, shown["hands"]))
        # the links to other tables need a login
        context["others"] = other_tables(user_id, game_id) if logged_in else []
        context["others_hidden"] = not logged_in
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


@bp.route("/game/<int:game_id>/")
@login_required
def game(game_id):
    if query_one("SELECT 1 FROM game WHERE id = ?", (game_id,)) is None:
        abort(404)
    user_id = g.user["id"]
    details = can_see_details(user_id, game_id)
    if not details and not is_player(user_id, game_id):
        abort(404)
    return render_game(game_id, user_id, details=details)


@bp.route("/<token:token>")
def seat(token):
    """A player's own link (from the mails): no login, but only this seat.

    The token is a secret that stands for one seat of one game, so it shows
    that game and nothing else. Whoever has it can play that seat, as on
    the PHP site.
    """
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
        details=hand["status"] == OVER,
        logged_in=logged_in,
    )


class TokenConverter(BaseConverter):
    """Seat tokens: 32 hex digits (the PHP site) or 22 url-safe characters."""

    regex = r"[0-9a-f]{32}|[A-Za-z0-9_-]{22}"


def init_app(app) -> None:
    app.url_map.converters["token"] = TokenConverter
    app.register_blueprint(bp)
    app.jinja_env.globals.update(card_name=card_name, image_number=image_number)
