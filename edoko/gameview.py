"""What the game page shows: the table with its four players, the tricks.

The four players sit at fixed spots (bottom, left, top, right, clockwise)
for a given viewer, whatever seat they have in a game: the viewer is at the
bottom (a viewer who did not play: the player with the lowest id), and the
others follow in seat order. The seat order of a table never changes, so
everybody keeps the same spot from game to game.
"""

from collections import Counter

from flask import abort
from flask_babel import gettext as _

from . import rules
from .db import query, query_one

OVER = "gameover"
SPOTS = ("bottom", "left", "top", "right")

SOLO_ICONS = {
    "queen": ("queensolo", _("Queen solo")),
    "jack": ("jacksolo", _("Jack solo")),
    "club": ("clubsolo", _("Club solo")),
    "spade": ("spadesolo", _("Spade solo")),
    "heart": ("heartsolo", _("Heart solo")),
    "trumpless": ("notrumpsolo", _("Trumpless solo")),
    "trump": ("trumpsolo", _("Trump solo")),
}


def spot_of_seat(hands, anchor_user: int) -> dict[int, str]:
    anchor_seat = next(h["seat"] for h in hands if h["user_id"] == anchor_user)
    return {h["seat"]: SPOTS[(h["seat"] - anchor_seat) % 4] for h in hands}


def badges(game, hand, trump_back: bool, over: bool) -> list[tuple[str, str]]:
    """The icons of a player: wedding, solo, poverty, calls (as on the PHP site)."""
    kind, party, sick = game["type"], hand["party"], hand["sickness"]
    if kind == "solo" and game["solo"] == "silent" and not over:
        kind = "normal"  # a silent solo is not announced
    poverty = sick == "poverty" or (
        game["lowtrump"] == "poverty" and sick == "lowtrump"
    )
    icons = []
    if kind in ("poverty", "dpoverty") and party == "re":
        if poverty:
            icons.append(
                ("poverty_trump", _("Poverty: trump given back"))
                if trump_back
                else ("poverty_notrump", _("Poverty: no trump given back"))
            )
        else:
            icons.append(("poverty_partner", _("Poverty partner")))
    elif kind == "dpoverty":
        if poverty:
            icons.append(
                ("poverty2_trump", _("Second poverty: trump given back"))
                if trump_back
                else ("poverty2_notrump", _("Second poverty: no trump given back"))
            )
        else:
            icons.append(("poverty2_partner", _("Second poverty partner")))
    elif kind == "wedding" and party == "re":
        icons.append(
            ("wedding", _("Wedding"))
            if sick == "wedding"
            else ("wedding_partner", _("Wedding partner"))
        )
    elif kind == "solo" and party == "re" and game["solo"] in SOLO_ICONS:
        icons.append(SOLO_ICONS[game["solo"]])
    call = hand["point_call"]
    if call is not None:
        icons.append(("re", _("Re")) if party == "re" else ("contra", _("Contra")))
        if call in ("0", "30", "60", "90"):
            icons.append((call, _("Call %(n)s", n=call)))
    return icons


# the circle of arrows that opens at the spot of the player who leads
RING = {"left": 0, "top": 1, "right": 2, "bottom": 3}


def build(
    game_id: int, viewer_id: int, *, full: bool, trick: int | None = None
) -> dict:
    """The context of the game page.

    full: the game is over and the viewer may see everything; otherwise the
    viewer plays in it and sees the tricks so far and only their own cards.
    trick: the number of the trick shown on the table; by default the latest
    one of a game in progress, the first one of a finished game.
    """
    game = query_one(
        """SELECT game.*, ruleset.lowtrump FROM game
           JOIN ruleset ON ruleset.id = game.ruleset WHERE game.id = ?""",
        (game_id,),
    )
    over = game["status"] == OVER
    hands = query(
        """SELECT hand.id, hand.seat, hand.party, hand.sickness, hand.point_call,
                  user.id AS user_id, user.name
           FROM hand JOIN user ON user.id = hand.user
           WHERE hand.game = ? ORDER BY hand.seat""",
        (game_id,),
    )
    if len(hands) != 4:
        abort(404)  # not a game
    mine = next((h for h in hands if h["user_id"] == viewer_id), None)
    anchor = mine["user_id"] if mine else min(h["user_id"] for h in hands)
    spot = spot_of_seat(hands, anchor)

    tricks = tricks_so_far(game_id)
    won = Counter(t["winner_seat"] for t in tricks if t["winner_seat"])
    queen_played = {
        p["seat"] for t in tricks for p in t["plays"] if p["card_id"] in (3, 4)
    }
    trump_back = {
        r["seat"]: bool(r["trump"])
        for r in query(
            """SELECT hand.seat, max(hand_card.card < 27) AS trump FROM hand_card
               JOIN hand ON hand.id = hand_card.hand WHERE hand.game = ?
               GROUP BY hand.seat""",
            (game_id,),
        )
    }
    cards = {h["seat"]: [] for h in hands}
    if full:
        for row in query(
            """SELECT hand.seat, card.id, card.suit, card.rank
               FROM hand_card JOIN card ON card.id = hand_card.card
               JOIN hand ON hand.id = hand_card.hand
               LEFT JOIN play ON play.hand_card = hand_card.id
               LEFT JOIN trick ON trick.id = play.trick
               WHERE hand.game = ?
               ORDER BY trick.number, play.sequence, card.id""",
            (game_id,),
        ):
            cards[row["seat"]].append(row)
    elif mine:  # the cards they still hold
        cards[mine["seat"]] = query(
            """SELECT card.id, card.suit, card.rank FROM hand_card
               JOIN card ON card.id = hand_card.card
               LEFT JOIN play ON play.hand_card = hand_card.id
               WHERE hand_card.hand = ? AND play.id IS NULL ORDER BY card.id""",
            (mine["id"],),
        )

    seats = []
    for h in hands:
        party_known = over or h["point_call"] is not None or h["seat"] in queen_played
        label = None
        if party_known and h["party"] and (over or game["type"] != "solo"):
            label = _("Re") if h["party"] == "re" else _("Contra")
        seats.append(
            {
                "seat": h["seat"],
                "spot": spot[h["seat"]],
                "name": h["name"],
                "is_me": h is mine,
                "starts": h["seat"] == game["start_seat"],
                "label": label,
                "badges": badges(game, h, trump_back.get(h["seat"], False), over),
                "tricks": won[h["seat"]],
                "cards": cards[h["seat"]],
                "on_turn": game["status"] in ("pre", "play")
                and h["seat"] == game["turn_seat"],
            }
        )
    seats.sort(key=lambda s: SPOTS.index(s["spot"]))

    if tricks:
        if trick is None or not 1 <= trick <= len(tricks):
            trick = len(tricks) if not over else 1
        selected = tricks[trick - 1]
        lead = spot[selected["plays"][0]["seat"]] if selected["plays"] else "bottom"
    else:
        selected, lead = None, "bottom"
    context = {
        "game": game,
        "selected": selected,
        "ring": RING[lead],
        "seats": seats,
        "spot_of": spot,
        "names": {h["seat"]: h["name"] for h in hands},
        "tricks": tricks,
        "full": full,
        "over": over,
        "hands": hands,
    }
    if full:
        context.update(result(game_id, hands, tricks))
    return context


def tricks_so_far(game_id: int) -> list[dict]:
    """All tricks played so far, with the public comments on the moves."""
    comments: dict[int, list] = {}
    for row in query(
        """SELECT comment.play, comment.body, user.name
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
                "plays": [dict(p) for p in plays],
                "comments": [c for p in plays for c in comments.get(p["id"], [])],
            }
        )
    return tricks


def result(game_id: int, hands, tricks) -> dict:
    """Comments before the game, the scoring items and the card points."""
    parties = {h["seat"]: h["party"] for h in hands}
    points = Counter()
    for t in tricks:
        if t["winner_seat"] and parties.get(t["winner_seat"]):
            points[parties[t["winner_seat"]]] += sum(
                rules.POINTS[p["card_id"]] for p in t["plays"]
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
    return {
        "before": before,
        "scores": scores,
        "card_points": {"re": points["re"], "contra": points["contra"]},
    }


def neighbours(game_id: int, session_id: int, allowed) -> tuple[int | None, int | None]:
    """The previous and next game of the table that allowed(id) lets through."""
    found = []
    for compare, order in (("<", "DESC"), (">", "ASC")):
        ids = query(
            f"SELECT id FROM game WHERE session = ? AND id {compare} ?"
            f" ORDER BY id {order} LIMIT 100",
            (session_id, game_id),
        )
        found.append(next((r["id"] for r in ids if allowed(r["id"])), None))
    return found[0], found[1]
