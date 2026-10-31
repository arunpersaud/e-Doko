"""The forms of live play: join, declare, play a card.

A seat link (/<token>/...) is the capability of that seat, as in the mails;
a logged-in player can do the same on /game/<id>/... Both end up at the same
page again (post, redirect, get), with a message if something was refused.
"""

from flask import Blueprint, abort, flash, g, redirect, render_template, request, url_for
from flask_babel import gettext as _
from flask_babel import lazy_gettext as _l

from . import play, rules
from .auth import login_required
from .db import get_db, query, query_one

bp = Blueprint("playviews", __name__)

ACTIONS = ("join", "decline", "declare", "play", "remind", "cancel")


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
        elif action == "remind":
            play.remind(db, hand["game"], hand["user"])
            flash(_("An email has been sent out."))
        elif action == "cancel":
            play.cancel_idle(db, hand["game"], hand["user"])
            flash(_("The game has been canceled."))
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


# the words of the rule choices (as on the PHP site); poverty is not offered yet
RULE_LABELS = {
    "dullen": {
        "none": _l("just normal non-trump"),
        "firstwins": _l("first ten of hearts wins the trick"),
        "secondwins": _l("second ten of hearts wins the trick"),
    },
    "schweinchen": {
        "none": _l("none"),
        "both": _l("both become highest trump (automatic call at beginning of the game)"),
        "second": _l("first one normal, second one becomes highest (call during the game)"),
        "secondaftercall": _l("second one become highest only in case re/contra was announced"),
    },
    "call": {
        "1st-own-card": _l("Can call re/contra on the first own card played, 90 on the second, etc."),
        "5th-card": _l("Can call re/contra until 5th card is played, 90 until 9th card is played, etc."),
        "9-cards": _l("Can call re/contra until 5th card is played, 90 if player still has 9 cards, etc."),
    },
    "lowtrump": {
        "cancel": _l("The game will be canceled unless there is a solo."),
        "none": _l("Bad luck, the player needs to play a normal game."),
    },
}
DEFAULT_RULES = {
    "dullen": "secondwins",
    "schweinchen": "none",
    "call": "1st-own-card",
    "lowtrump": "cancel",
}


def candidates():
    return query(
        "SELECT id, name FROM user WHERE open_for_games = 1 AND disabled_at IS NULL"
        " AND id != ? ORDER BY name",
        (g.user["id"],),
    )


@bp.route("/new", methods=["GET", "POST"])
@login_required
def new():
    chosen = dict(DEFAULT_RULES)
    picked, max_games = [], ""
    if request.method == "POST":
        picked = request.form.getlist("player", type=int)
        chosen = {k: request.form.get(k, v) for k, v in DEFAULT_RULES.items()}
        max_games = request.form.get("max_games", "").strip()
        try:
            if chosen["lowtrump"] not in RULE_LABELS["lowtrump"]:
                raise play.PlayError("Unknown rules.")
            limit = int(max_games) if max_games else None
            game_id = play.new_table(
                get_db(), g.user["id"], picked,
                rules.Rules(chosen["dullen"], chosen["schweinchen"], chosen["lowtrump"], chosen["call"]),
                limit,
            )
        except (play.PlayError, ValueError) as error:
            flash(str(error) if isinstance(error, play.PlayError) else "Please enter a number of games.")
        else:
            flash(_("The game is set up, the invitations are sent out."))
            return redirect(url_for("games.game", game_id=game_id, trick=None, origin=None))
    return render_template(
        "new.html", players=candidates(), labels=RULE_LABELS, chosen=chosen,
        picked=picked + [None] * (3 - len(picked)), max_games=max_games,
    )


@bp.post("/game/<int:game_id>/next")
@login_required
def keep_playing(game_id):
    try:
        new_id = play.next_game(get_db(), game_id, g.user["id"])
    except play.PlayError as error:
        flash(str(error))
        return redirect(url_for("games.game", game_id=game_id, trick=None, origin=None))
    return redirect(url_for("games.game", game_id=new_id, trick=None, origin=None))


@bp.post("/table/<int:session_id>/again")
@login_required
def table_again(session_id):
    try:
        new_id = play.same_table_again(get_db(), session_id, g.user["id"])
    except play.PlayError as error:
        flash(str(error))
        return redirect(url_for("games.table", session_id=session_id))
    return redirect(url_for("games.game", game_id=new_id, trick=None, origin=None))
