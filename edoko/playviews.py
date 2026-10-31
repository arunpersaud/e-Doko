"""The forms of live play: join, declare, play a card.

A seat link (/<token>/...) is the capability of that seat, as in the mails;
a logged-in player can do the same on /game/<id>/... Both end up at the same
page again (post, redirect, get), with a message if something was refused.
"""

from flask import Blueprint, abort, flash, g, redirect, request, url_for

from . import play
from .auth import login_required
from .db import get_db, query_one

bp = Blueprint("playviews", __name__)

ACTIONS = ("join", "decline", "declare", "play")


def act(hand, action: str):
    db = get_db()
    form = request.form
    try:
        if action == "join":
            play.join(db, hand["id"])
        elif action == "decline":
            play.decline(db, hand["id"])
        elif action == "declare":
            play.declare(db, hand["id"], form.get("sickness", "none"), form.get("solo"))
        elif action == "play":
            card = form.get("card", type=int)
            call = form.get("call", type=int)
            if card is None:
                raise play.PlayError("Please choose a card.")
            play.play_card(db, hand["id"], card, call)
    except play.PlayError as error:
        flash(str(error))


@bp.post("/<token:token>/<action>")
def seat_action(token, action):
    hand = query_one("SELECT * FROM hand WHERE token = ?", (token,))
    if hand is None or action not in ACTIONS:
        abort(404)
    act(hand, action)
    return redirect(url_for("games.seat", token=token, trick=None))


@bp.post("/game/<int:game_id>/<action>")
@login_required
def game_action(game_id, action):
    hand = query_one(
        "SELECT * FROM hand WHERE game = ? AND user = ?", (game_id, g.user["id"])
    )
    if hand is None or action not in ACTIONS:
        abort(404)
    act(hand, action)
    return redirect(url_for("games.game", game_id=game_id, trick=None, origin=None))
