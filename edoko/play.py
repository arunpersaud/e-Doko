"""Live play: a new game, the declaration phase, playing cards, calls, scoring.

Everything here works on one sqlite connection and changes the database in
a transaction; the mails go out after the commit. The rules themselves
(card order, trick winner, scoring) are in rules.py.

The flow of a game (as on the PHP site):

  game.status 'pre'   every hand: start -> init (wants to play) -> check
                      (said whether it is sick: solo, wedding, poverty,
                      nines, low trump). When all four are at 'check' the
                      kind of game is decided (resolve)
  game.status 'play'  the hands are at 'play', game.turn_seat plays a card
  game.status 'gameover' (or cancel-*)

Seat k is dealt cards 12(k-1) .. 12k-1 of the deal.
"""

import random
import secrets
import sqlite3
from collections import defaultdict
from contextlib import contextmanager
from dataclasses import dataclass, field

from flask import current_app
from flask_babel import gettext as _
from flask_babel import lazy_gettext as _l

from . import mail, rules

SOLOS = ("trumpless", "jack", "queen", "trump", "club", "spade", "heart")
NINES = frozenset({25, 26, 33, 34, 41, 42, 47, 48})
GROUPS = ("trump", "clubs", "spades", "hearts", "diamonds")
CALLS = (120, 90, 60, 30, 0)
# the words of an announcement, as the site writes them into the comment on the move
CALL_WORDS = {"re": "Re", "contra": "Contra", 90: "No 90", 60: "No 60", 30: "No 30", 0: "Zero"}
ACTIVE = ("pre", "play")


class PlayError(Exception):
    """Something a player asked for is not possible; the text is for them."""


@contextmanager
def transaction(db: sqlite3.Connection):
    db.execute("BEGIN IMMEDIATE")
    try:
        yield
    except BaseException:
        db.execute("ROLLBACK")
        raise
    db.execute("COMMIT")


# ---------------------------------------------------------------- dealing ---


def pick_deal(db: sqlite3.Connection, users: list[int]) -> int:
    """The oldest deal none of the four has a hand in, else a fresh shuffle.

    A player must never play a deal they have already seen.
    """
    marks = ",".join("?" * len(users))
    row = db.execute(
        f"""SELECT deal.id FROM deal WHERE NOT EXISTS (
              SELECT 1 FROM game JOIN hand ON hand.game = game.id
              WHERE game.deal = deal.id AND hand.user IN ({marks}))
            ORDER BY deal.id LIMIT 1""",
        users,
    ).fetchone()
    if row:
        return row["id"]
    shuffler = random.SystemRandom()
    while True:
        cards = list(range(1, 49))
        shuffler.shuffle(cards)
        cur = db.execute(
            "INSERT OR IGNORE INTO deal (cards) VALUES (?)",
            (":".join(map(str, cards)),),
        )
        if cur.rowcount:
            return cur.lastrowid


def deal_cards(db: sqlite3.Connection, deal_id: int) -> dict[int, list[int]]:
    row = db.execute("SELECT cards FROM deal WHERE id = ?", (deal_id,)).fetchone()
    cards = [int(c) for c in row["cards"].split(":")]
    return {seat: cards[12 * (seat - 1) : 12 * seat] for seat in range(1, 5)}


# ------------------------------------------------------ sickness (pure) ---


def trump_count(cards, rs: rules.Rules) -> int:
    """Trump for the poverty rule: the foxes don't count, unless Schweinchen."""
    cards = set(cards)
    count = sum(1 for c in cards if c < 27) - (19 in cards) - (20 in cards)
    if 19 in cards and 20 in cards:
        if rs.schweinchen == "both":
            count += 2
        elif rs.schweinchen in ("second", "secondaftercall"):
            count += 1
    return count


def has_low_trump(cards, rs: rules.Rules) -> bool:
    """Only diamond trump (nines, kings, tens, aces), and no Schweinchen."""
    if rs.lowtrump == "none":
        return False
    cards = set(cards)
    if any(c < 19 for c in cards):
        return False
    return not (19 in cards and 20 in cards and rs.schweinchen != "none")


def sickness_options(cards, rs: rules.Rules) -> list[str]:
    """What this hand may declare besides a solo (and "nothing")."""
    cards = set(cards)
    options = []
    if 3 in cards and 4 in cards:
        options.append("wedding")
    if trump_count(cards, rs) < 4:
        options.append("poverty")
    if len(cards & NINES) > 4:
        options.append("nines")
    if rs.lowtrump in ("cancel", "poverty") and has_low_trump(cards, rs):
        options.append("lowtrump")
    return options


# ------------------------------------------------------------------ state ---


@dataclass
class State:
    """A game as the rules need it."""

    game: sqlite3.Row
    rs: rules.Rules
    hands: dict[int, sqlite3.Row]  # by seat
    start: dict[int, list[int]]  # seat -> the cards dealt
    held: dict[int, list[int]]  # seat -> cards not played yet
    hand_card: dict[tuple[int, int], int]  # (seat, card) -> hand_card.id
    tricks: list[list[tuple[int, int]]] = field(default_factory=list)  # (seat, card)
    # the Schweinchen came into force (its remark was made); see foxes()
    fox_in_force: bool = False

    @property
    def mode(self) -> str:
        """The kind of game for the rules: normal, wedding, ... or the solo."""
        game = self.game
        if game["type"] == "solo" and game["solo"]:
            return game["solo"]
        return game["type"] or "normal"

    @property
    def current(self) -> list[tuple[int, int]]:
        """The cards of the trick being played (empty if it is complete)."""
        if self.tricks and len(self.tricks[-1]) < 4:
            return self.tricks[-1]
        return []

    def foxes(self) -> rules.Foxes:
        foxes = rules.foxes_for(self.start, self.mode, self.rs)
        if foxes.holder is not None and self.rs.schweinchen == "secondaftercall":
            if sum(c in rules.FOXES for t in self.tricks for _s, c in t) >= 2:
                # both foxes are out: a call made later changes nothing, the
                # rule is in force only if it was when the second one fell
                foxes.called = self.fox_in_force
            else:
                party = self.hands[foxes.holder]["party"]
                foxes.called = any(
                    h["point_call"] is not None and h["party"] == party
                    for h in self.hands.values()
                )
        return foxes


def load(db: sqlite3.Connection, game_id: int) -> State:
    game = db.execute(
        """SELECT game.*, ruleset.dullen, ruleset.schweinchen, ruleset.lowtrump,
                  ruleset.call
           FROM game JOIN ruleset ON ruleset.id = game.ruleset WHERE game.id = ?""",
        (game_id,),
    ).fetchone()
    if game is None:
        raise PlayError(_("There is no such game."))
    rs = rules.Rules(game["dullen"], game["schweinchen"], game["lowtrump"], game["call"])
    hands = {
        h["seat"]: h
        for h in db.execute("SELECT * FROM hand WHERE game = ?", (game_id,))
    }
    seat_of = {h["id"]: seat for seat, h in hands.items()}
    start = defaultdict(list)
    held = defaultdict(list)
    hand_card = {}
    played = {
        r[0]
        for r in db.execute(
            "SELECT play.hand_card FROM play JOIN trick ON trick.id = play.trick"
            " WHERE trick.game = ?",
            (game_id,),
        )
    }
    for r in db.execute(
        "SELECT id, hand, card FROM hand_card WHERE hand IN"
        " (SELECT id FROM hand WHERE game = ?) ORDER BY id",
        (game_id,),
    ):
        seat = seat_of[r["hand"]]
        start[seat].append(r["card"])
        hand_card[(seat, r["card"])] = r["id"]
        if r["id"] not in played:
            held[seat].append(r["card"])
    tricks = []
    for t in db.execute(
        "SELECT id FROM trick WHERE game = ? ORDER BY number", (game_id,)
    ):
        tricks.append(
            [
                (seat_of[r["hand"]], r["card"])
                for r in db.execute(
                    "SELECT hand_card.hand, hand_card.card FROM play"
                    " JOIN hand_card ON hand_card.id = play.hand_card"
                    " WHERE play.trick = ? ORDER BY play.sequence",
                    (t["id"],),
                )
            ]
        )
    in_force = (
        db.execute(
            "SELECT 1 FROM comment WHERE game = ? AND kind = 'move' AND body = 'Schweinchen! '",
            (game_id,),
        ).fetchone()
        is not None
    )
    return State(game, rs, hands, dict(start), dict(held), hand_card, tricks, in_force)


def legal_cards(state: State, seat: int) -> list[int]:
    """The cards this seat may play now: follow the lead if possible."""
    hand = state.held[seat]
    trick = state.current
    if not trick:
        return list(hand)
    layout = rules.layout(state.mode, state.rs.dullen)
    lead = trick[0][1]
    group = next((g for g in GROUPS if lead in layout[g]), None)
    same = [c for c in hand if group and c in layout[group]]
    return same or list(hand)


# ----------------------------------------------------------------- calls ---


def call_allowed(
    rule: str,
    what: int,
    *,
    own: int | None,
    partner: int | None,
    cards: int,
    all_cards: int,
    offset: int = 0,
) -> int:
    """Port of can_call(): 0 not possible, 1 possible, 2 possible for the last time.

    what: 120 (Re/Contra), 90, 60, 30 or 0.
    own, partner: the lowest call already made by this player and by the partner.
    cards: the cards this player still holds; all_cards: the cards of all four.
    offset: delay in a wedding, until the partner is known (tricks played).
    """
    if (partner is not None and what >= partner) or (own is not None and what >= own):
        return 0
    n = cards + offset
    if rule == "1st-own-card":
        if 4 - what // 30 == 12 - n:
            return 2
        if 4 - what // 30 > 12 - n:
            return 1
    elif rule == "5th-card":
        total = all_cards + offset * 4
        if 27 + 4 * (what // 30) == total:
            return 2
        if 27 + 4 * (what // 30) < total:
            return 1
    elif rule == "9-cards":
        made = [c for c in (own, partner) if c is not None]
        lowest = min(made) if made else -1
        # (cards needed, highest earlier call that allows it, the lowest call allowed)
        for need, earlier, limit in ((12, None, 120), (9, 120, 90), (6, 90, 60), (3, 60, 30), (0, 30, 0)):
            if n < need:
                continue
            if earlier is None or (0 <= lowest <= earlier and what <= limit):
                return 2 if n == need else 1
            break
    return 0


def can_call(state: State, seat: int, what: int) -> int:
    hand = state.hands[seat]
    own = int(hand["point_call"]) if hand["point_call"] is not None else None
    partner_calls = [
        int(h["point_call"])
        for s, h in state.hands.items()
        if s != seat and h["party"] == hand["party"] and h["point_call"] is not None
    ]
    offset = 0
    if state.game["type"] == "wedding":
        # calls wait until the partner is known, then count as if the game
        # had started that many tricks later
        offset = state.game["sickness_state"]
        if offset is None or offset < 0:
            return 0
    return call_allowed(
        state.rs.call,
        what,
        own=own,
        partner=min(partner_calls) if partner_calls else None,
        cards=len(state.held[seat]),
        all_cards=sum(len(c) for c in state.held.values()),
        offset=offset,
    )


def possible_calls(state: State, seat: int) -> list[int]:
    """The calls this seat may make now (when it is on turn)."""
    hand = state.hands[seat]
    if state.game["status"] != "play" or hand["party"] is None:
        return []
    return [what for what in CALLS if can_call(state, seat, what)]


def call_words(party: str, what: int) -> str:
    return CALL_WORDS[party] if what == 120 else CALL_WORDS[what]


# ------------------------------------------------------------- new game ---


def ruleset_id(db: sqlite3.Connection, rs: rules.Rules) -> int:
    key = (rs.dullen, rs.schweinchen, rs.lowtrump, rs.call)
    row = db.execute(
        "SELECT id FROM ruleset WHERE dullen = ? AND schweinchen = ? AND lowtrump = ?"
        " AND call = ?",
        key,
    ).fetchone()
    if row:
        return row["id"]
    return db.execute(
        "INSERT INTO ruleset (dullen, schweinchen, lowtrump, call) VALUES (?, ?, ?, ?)",
        key,
    ).lastrowid


def new_token() -> str:
    return secrets.token_urlsafe(16)


def create_game(
    db: sqlite3.Connection,
    players: list[int],
    rs: rules.Rules | None = None,
    *,
    session: int | None = None,
    max_games: int | None = None,
) -> int:
    """A new game in 'pre': players are the user ids in seat order.

    session: another game at the same table (the rules are taken from the last
    game of it, and it must not have a game in progress), else a new table.
    max_games: for a new table, the number of games it is played for (None: no end).
    """
    if len(players) != 4 or len(set(players)) != 4:
        raise PlayError(_("A game needs four different players."))
    with transaction(db):
        marks = ",".join("?" * 4)
        found = db.execute(
            f"SELECT count(*) FROM user WHERE id IN ({marks}) AND disabled_at IS NULL",
            players,
        ).fetchone()[0]
        if found != 4:
            raise PlayError(_("One of the players does not exist."))
        if session is None:
            if rs is None:
                raise PlayError(_("The rules are missing."))
            if max_games is not None and max_games < 1:
                raise PlayError(_("A table needs at least one game."))
            session = db.execute(
                "INSERT INTO session (max_games) VALUES (?)", (max_games,)
            ).lastrowid
            ruleset = ruleset_id(db, rs)
        else:
            if db.execute(
                "SELECT 1 FROM game WHERE session = ? AND status IN ('pre', 'play')",
                (session,),
            ).fetchone():
                raise PlayError(_("There is already a game going on at this table."))
            if table_state(db, session)["closed"]:
                raise PlayError(_("This table is finished."))
            last = db.execute(
                "SELECT ruleset FROM game WHERE session = ? ORDER BY id DESC LIMIT 1",
                (session,),
            ).fetchone()
            if last is None:
                raise PlayError(_("There is no such table."))
            ruleset = last["ruleset"]
        for user in players:
            db.execute(
                "INSERT OR IGNORE INTO session_player (session, user) VALUES (?, ?)",
                (session, user),
            )
        deal = pick_deal(db, players)
        game_id = db.execute(
            "INSERT INTO game (session, deal, ruleset, status, type, start_seat, turn_seat)"
            " VALUES (?, ?, ?, 'pre', 'normal', 1, 1)",
            (session, deal, ruleset),
        ).lastrowid
        auto = {
            r[0]
            for r in db.execute(
                f"SELECT id FROM user WHERE id IN ({marks}) AND autosetup = 1", players
            )
        }
        for seat, cards in deal_cards(db, deal).items():
            user = players[seat - 1]
            hand = db.execute(
                "INSERT INTO hand (game, seat, user, token, status) VALUES (?, ?, ?, ?, ?)",
                (game_id, seat, user, new_token(), "init" if user in auto else "start"),
            ).lastrowid
            db.executemany(
                "INSERT INTO hand_card (hand, card) VALUES (?, ?)",
                [(hand, card) for card in cards],
            )
        db.execute(
            "UPDATE game SET turn_seat = ? WHERE id = ?",
            (_first_open_seat(db, game_id), game_id),
        )
    notify(db, [("invite", game_id)])
    return game_id


# ---------------------------------------------------- declaration phase ---


def _hand(db: sqlite3.Connection, hand_id: int) -> sqlite3.Row:
    hand = db.execute("SELECT * FROM hand WHERE id = ?", (hand_id,)).fetchone()
    if hand is None:
        raise PlayError(_("There is no such seat."))
    return hand


def _first_open_seat(db: sqlite3.Connection, game_id: int) -> int | None:
    """Who is to answer next while the game is being set up: whoever has not said
    yet that they want to play, else whoever has not said if they are sick."""
    for status in ("start", "init"):
        row = db.execute(
            "SELECT min(seat) FROM hand WHERE game = ? AND status = ?", (game_id, status)
        ).fetchone()
        if row[0] is not None:
            return row[0]
    return None


def join(db: sqlite3.Connection, hand_id: int) -> None:
    """"Yes, I want to play this game."""
    with transaction(db):
        hand = _hand(db, hand_id)
        game = db.execute("SELECT * FROM game WHERE id = ?", (hand["game"],)).fetchone()
        if game["status"] != "pre":
            raise PlayError(_("This game is not waiting for players."))
        if hand["status"] == "start":
            db.execute("UPDATE hand SET status = 'init' WHERE id = ?", (hand_id,))
            db.execute(
                "UPDATE game SET turn_seat = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                (_first_open_seat(db, hand["game"]), hand["game"]),
            )


def cancel(db: sqlite3.Connection, game_id: int, status: str) -> list:
    """End a game that is not played: the status says why."""
    db.execute(
        "UPDATE game SET status = ?, turn_seat = NULL, updated_at = CURRENT_TIMESTAMP"
        " WHERE id = ?",
        (status, game_id),
    )
    db.execute("UPDATE hand SET status = 'gameover' WHERE game = ?", (game_id,))
    return [("canceled", game_id, status)]


def decline(db: sqlite3.Connection, hand_id: int) -> None:
    """"No, I don't want to play": the game is canceled."""
    with transaction(db):
        hand = _hand(db, hand_id)
        game = db.execute("SELECT status FROM game WHERE id = ?", (hand["game"],)).fetchone()
        if game["status"] != "pre":
            raise PlayError(_("This game is not waiting for players."))
        notices = cancel(db, hand["game"], "cancel-noplay")
    notify(db, notices)


def declare(
    db: sqlite3.Connection, hand_id: int, sickness: str, solo: str | None = None
) -> None:
    """The answer to "are you sick?": none, solo (with the kind), wedding, ...

    When it was the last of the four answers the game is decided and starts
    (or is canceled).
    """
    notices: list = []
    with transaction(db):
        hand = _hand(db, hand_id)
        state = load(db, hand["game"])
        game = state.game
        if game["status"] != "pre":
            raise PlayError(_("This game is not waiting for players."))
        if hand["status"] not in ("init", "start"):
            raise PlayError(_("You have answered already."))
        if hand["status"] == "start":
            raise PlayError(_("Please say first that you want to play."))
        seat = hand["seat"]
        if sickness == "none":
            sickness = None
        elif sickness == "solo":
            if solo not in SOLOS:
                raise PlayError(_("That kind of solo does not exist."))
        elif sickness not in sickness_options(state.start[seat], state.rs):
            raise PlayError(_("Your cards do not allow that."))
        db.execute(
            "UPDATE hand SET status = 'check', sickness = ?, solo = ? WHERE id = ?",
            (sickness, solo if sickness == "solo" else None, hand_id),
        )
        if sickness == "solo" and not (game["type"] == "solo" and game["start_seat"] < seat):
            # the first solo (the lowest seat) is the one that is played
            db.execute(
                "UPDATE game SET type = 'solo', solo = ?, start_seat = ? WHERE id = ?",
                (solo, seat, game["id"]),
            )
        db.execute(
            "UPDATE game SET turn_seat = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            (_first_open_seat(db, game["id"]), game["id"]),
        )
        if _first_open_seat(db, game["id"]) is None:
            notices = resolve(db, game["id"])
    notify(db, notices)


def resolve(db: sqlite3.Connection, game_id: int) -> list:
    """All four have answered: a solo, a cancelled game or a normal start."""
    state = load(db, game_id)
    game, rs = state.game, state.rs
    sick = {seat: h["sickness"] for seat, h in state.hands.items()}
    if game["type"] == "solo":
        return start_play(db, game_id)
    if any(s == "nines" for s in sick.values()):
        return cancel(db, game_id, "cancel-nines")
    if rs.lowtrump == "cancel" and any(s == "lowtrump" for s in sick.values()):
        return cancel(db, game_id, "cancel-lowtrump")
    poor = poor_seats(state)
    if len(poor) > 2:  # nobody can take that much trump
        return cancel(db, game_id, "cancel-trump")
    if poor:
        return start_poverty(db, state, poor)
    return start_play(db, game_id)


def start_play(db: sqlite3.Connection, game_id: int) -> list:
    state = load(db, game_id)
    game = state.game
    for seat, hand in state.hands.items():
        if game["type"] == "solo":
            party = "re" if seat == game["start_seat"] else "contra"
        else:
            party = "re" if {3, 4} & set(state.start[seat]) else "contra"
        db.execute(
            "UPDATE hand SET status = 'play', party = ? WHERE id = ?", (party, hand["id"])
        )
    if game["type"] == "normal":
        if any(h["sickness"] == "wedding" for h in state.hands.values()):
            # Re is the player with both club queens; the partner is the first one
            # to win a trick (of the first three) from them, see play_card
            db.execute(
                "UPDATE game SET type = 'wedding', sickness_state = -1 WHERE id = ?",
                (game_id,),
            )
        elif any({3, 4} <= set(c) for c in state.start.values()):
            # both club queens in one hand and no wedding: a silent solo, played
            # like a normal game (that player is Re alone and counts triple)
            db.execute(
                "UPDATE game SET type = 'solo', solo = 'silent' WHERE id = ?", (game_id,)
            )
    db.execute(
        "UPDATE game SET status = 'play', turn_seat = start_seat,"
        " updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        (game_id,),
    )
    return [("turn", game_id, True)]


# ------------------------------------------------------------- the play ---


def play_card(
    db: sqlite3.Connection,
    hand_id: int,
    card: int,
    call: int | None = None,
    remark: str | None = None,
) -> None:
    """Play a card (with a call and/or a remark, if the player makes one with it)."""
    remark = clean_remark(remark)
    notices: list = []
    with transaction(db):
        hand = _hand(db, hand_id)
        state = load(db, hand["game"])
        game, seat = state.game, hand["seat"]
        if game["status"] != "play" or hand["status"] != "play":
            raise PlayError(_("This game is not being played."))
        if game["turn_seat"] != seat:
            raise PlayError(_("It is not your turn."))
        if card not in state.held[seat]:
            raise PlayError(_("You do not have that card."))
        if card not in legal_cards(state, seat):
            raise PlayError(_("You have to follow suit."))
        if call is not None and not can_call(state, seat, call):
            raise PlayError(_("You can not make that call now."))
        if call is not None:
            db.execute(
                "UPDATE hand SET point_call = ? WHERE id = ?", (str(call), hand_id)
            )
            state.hands[seat] = _hand(db, hand_id)

        schweinchen = _schweinchen_played(state, card)
        if state.current:
            trick_id = db.execute(
                "SELECT id FROM trick WHERE game = ? ORDER BY number DESC LIMIT 1",
                (game["id"],),
            ).fetchone()[0]
        else:
            trick_id = db.execute(
                "INSERT INTO trick (game, number) VALUES (?, ?)",
                (game["id"], len(state.tricks) + 1),
            ).lastrowid
            state.tricks.append([])
        play_id = db.execute(
            "INSERT INTO play (trick, sequence, hand_card) VALUES (?, ?, ?)",
            (trick_id, len(state.tricks[-1]) + 1, state.hand_card[(seat, card)]),
        ).lastrowid
        state.tricks[-1].append((seat, card))
        state.held[seat].remove(card)
        if schweinchen:
            db.execute(
                "INSERT INTO comment (game, kind, play, user, body)"
                " VALUES (?, 'move', ?, ?, 'Schweinchen! ')",
                (game["id"], play_id, hand["user"]),
            )
            state.fox_in_force = True
        if call is not None:
            db.execute(
                "INSERT INTO comment (game, kind, play, user, body)"
                " VALUES (?, 'move', ?, ?, ?)",
                (game["id"], play_id, hand["user"], call_words(hand["party"], call)),
            )
        if remark:
            db.execute(
                "INSERT INTO comment (game, kind, play, user, body)"
                " VALUES (?, 'move', ?, ?, ?)",
                (game["id"], play_id, hand["user"], remark),
            )

        if len(state.tricks[-1]) < 4:
            db.execute(
                "UPDATE game SET turn_seat = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                (seat % 4 + 1, game["id"]),
            )
            notices.append(("turn", game["id"], False))
        else:
            foxes = state.foxes()
            for trick in state.tricks:
                for _seat, c in trick:
                    foxes.played(c)
            winner = rules.trick_winner(state.tricks[-1], state.mode, state.rs, foxes)
            db.execute("UPDATE trick SET winner_seat = ? WHERE id = ?", (winner, trick_id))
            number = len(state.tricks)
            if game["type"] == "wedding" and game["sickness_state"] == -1:
                wedding = next(s for s, h in state.hands.items() if h["sickness"] == "wedding")
                if number <= 3 and winner != wedding:
                    db.execute(
                        "UPDATE hand SET party = 're' WHERE id = ?", (state.hands[winner]["id"],)
                    )
                    db.execute("UPDATE game SET sickness_state = ? WHERE id = ?", (number, game["id"]))
                elif number == 3:  # the wedding player won all three: alone
                    db.execute("UPDATE game SET sickness_state = 3 WHERE id = ?", (game["id"],))
            if len(state.tricks) < 12:
                db.execute(
                    "UPDATE game SET turn_seat = ?, updated_at = CURRENT_TIMESTAMP"
                    " WHERE id = ?",
                    (winner, game["id"]),
                )
                notices.append(("turn", game["id"], False))
            else:
                notices += finish(db, state)
    notify(db, notices)


def _schweinchen_played(state: State, card: int) -> bool:
    """Does playing this fox now bring the Schweinchen into force (a remark on the move)?"""
    if card not in rules.FOXES:
        return False
    foxes = state.foxes()
    if foxes.holder is None:
        return False
    for trick in state.tricks:
        for _seat, c in trick:
            foxes.played(c)
    foxes.played(card)
    rule = state.rs.schweinchen
    return (
        rule == "both"
        or (rule == "second" and foxes.second_played)
        or (rule == "secondaftercall" and foxes.second_played and foxes.called)
    )


def finish(db: sqlite3.Connection, state: State) -> list:
    """The last card is played: score the game, set the points, close the table."""
    game = state.game
    parties = {seat: h["party"] for seat, h in state.hands.items()}
    calls = {
        seat: int(h["point_call"]) if h["point_call"] is not None else None
        for seat, h in state.hands.items()
    }
    result = rules.replay(
        state.tricks,
        mode=game["type"],
        solo=game["solo"],
        rules=state.rs,
        parties=parties,
        calls=calls,
        foxes=state.foxes(),
    )
    users = {seat: h["user"] for seat, h in state.hands.items()}
    for item in result.items:
        db.execute(
            "INSERT INTO score_item (game, party, kind, winner, loser)"
            " VALUES (?, ?, ?, ?, ?)",
            (
                game["id"],
                item.party,
                item.kind,
                users.get(item.winner_seat),
                users.get(item.loser_seat),
            ),
        )
    points = rules.player_points(result, parties, solo=game["type"] == "solo")
    for seat, value in points.items():
        db.execute(
            "UPDATE hand SET status = 'gameover', points = ? WHERE id = ?",
            (value, state.hands[seat]["id"]),
        )
    db.execute(
        "UPDATE game SET status = 'gameover', turn_seat = NULL,"
        " updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        (game["id"],),
    )
    return [("over", game["id"])]


# ---------------------------------------------------------------- mails ---


def _link(token: str) -> str:
    return f"{current_app.config['ORIGIN']}/{token}"


def _mail(db, user_id: int, subject, template: str, *, turn: bool = False, **context):
    user = db.execute("SELECT * FROM user WHERE id = ?", (user_id,)).fetchone()
    if user["mail_mode"] == "none" or (turn and user["mail_mode"] == "fewer"):
        return
    mail.deliver(
        db, user, subject, template,
        kind="turn" if turn else "other", game=context.get("game_id"), **context,
    )


def notify(db: sqlite3.Connection, notices: list) -> None:
    """Send the mails for what has just happened (after the commit)."""
    for notice in notices:
        kind, game_id = notice[0], notice[1]
        hands = db.execute(
            "SELECT hand.*, user.name FROM hand JOIN user ON user.id = hand.user"
            " WHERE game = ? ORDER BY seat",
            (game_id,),
        ).fetchall()
        names = [h["name"] for h in hands]
        game = db.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
        if kind == "invite":
            for h in hands:
                _mail(
                    db, h["user"], _l("You are invited to game %(id)d", id=game_id),
                    "invite", game_id=game_id, players=names, link=_link(h["token"]),
                )
        elif kind == "turn":
            h = next((h for h in hands if h["seat"] == game["turn_seat"]), None)
            if h is not None:
                _mail(
                    db, h["user"], _l("Game %(id)d: it is your turn", id=game_id),
                    "turn", turn=True, game_id=game_id, first=notice[2],
                    link=_link(h["token"]),
                )
        elif kind == "poverty":
            h = next((h for h in hands if h["seat"] == game["turn_seat"]), None)
            if h is not None:
                _mail(
                    db, h["user"], _l("Game %(id)d: poverty, do you take the trump?", id=game_id),
                    "poverty", game_id=game_id, link=_link(h["token"]),
                )
        elif kind == "reminder":
            h = next((h for h in hands if h["seat"] == game["turn_seat"]), None)
            if h is not None:
                _mail(
                    db, h["user"], _l("Reminder: game %(id)d, it is your turn", id=game_id),
                    "reminder", game_id=game_id, link=_link(h["token"]),
                )
        elif kind == "canceled":
            reasons = {
                "cancel-noplay": _l("one of the players does not want to play"),
                "cancel-nines": _l("a player has five or more nines"),
                "cancel-lowtrump": _l("a player has only low trump"),
                "cancel-trump": _l("nobody wanted to take the trump"),
                "cancel-timedout": _l("it took too long"),
            }
            for h in hands:
                _mail(
                    db, h["user"], _l("Game %(id)d was canceled", id=game_id),
                    "canceled", game_id=game_id, reason=reasons.get(notice[2], ""),
                    link=_link(h["token"]),
                )
        elif kind == "over":
            for h in hands:
                _mail(
                    db, h["user"], _l("Game %(id)d is over", id=game_id),
                    "over", game_id=game_id,
                    scores=[(x["name"], x["points"]) for x in hands],
                    link=_link(h["token"]),
                )


# ------------------------------------------------------ what a page shows ---


def controls(db: sqlite3.Connection, game_id: int, user_id: int, base: str) -> dict | None:
    """What this player can do in the game now (None: nothing, or not a player).

    base: the address the forms post to (".../join", ".../play" are added).
    stage: join (asked whether to play), declare (asked whether sick), wait
    (for the others to answer), play (on turn), wait_turn.
    """
    state = load(db, game_id)
    game = state.game
    seat = next((s for s, h in state.hands.items() if h["user"] == user_id), None)
    if game["status"] not in ACTIVE or seat is None:
        return None
    hand = state.hands[seat]
    found: dict = {
        "base": base, "stage": "wait", "legal": set(), "calls": [],
        "idle_days": idle_days(db, game_id),
    }
    if game["status"] == "pre":
        if hand["status"] == "start":
            found["stage"] = "join"
        elif hand["status"] == "init":
            found["stage"] = "declare"
            options = sickness_options(state.start[seat], state.rs)
            found["sickness"] = options
            found["solos"] = SOLOS
        elif hand["status"] == "poverty":
            if hand["party"] is None:
                found["stage"] = "poverty_ask"
                found["poor"] = [
                    {
                        "user": state.hands[s]["user"],
                        "name": db.execute(
                            "SELECT name FROM user WHERE id = ?", (state.hands[s]["user"],)
                        ).fetchone()[0],
                        "trump": sum(1 for c in state.held[s] if c < 27),
                        "low": state.hands[s]["sickness"] == "lowtrump",
                    }
                    for s in poor_seats(state)
                    if state.hands[s]["party"] is None
                ]
            elif len(state.held[seat]) > 12:
                found["stage"] = "exchange"
                found["give_back"] = len(state.held[seat]) - 12
                found["legal"] = set(state.held[seat])
    elif game["turn_seat"] == seat:
        found["stage"] = "play"
        found["legal"] = set(legal_cards(state, seat))
        found["calls"] = [
            {"value": what, "words": call_words(hand["party"], what)}
            for what in possible_calls(state, seat)
        ]
    else:
        found["stage"] = "wait_turn"
    return found


# ------------------------------------------------------------- tables ---

REPEATED = ("cancel-nines", "cancel-trump", "cancel-lowtrump")
RULE_CHOICES = {
    "dullen": ("none", "firstwins", "secondwins"),
    "schweinchen": ("none", "both", "second", "secondaftercall"),
    "lowtrump": ("poverty", "cancel", "none"),
    "call": ("1st-own-card", "5th-card", "9-cards"),
}


def table_state(db: sqlite3.Connection, session_id: int) -> dict:
    """How far a table is: games played, the end (if it has one), the standings.

    Only finished games count (a canceled game is dealt again). The winner is the
    player with most game points; a tie leaves several.
    """
    row = db.execute("SELECT max_games FROM session WHERE id = ?", (session_id,)).fetchone()
    standings = [
        dict(r)
        for r in db.execute(
            """SELECT user.id, user.name, coalesce(sum(hand.points), 0) AS points
               FROM session_player JOIN user ON user.id = session_player.user
               LEFT JOIN (SELECT hand.user, hand.points FROM hand
                          JOIN game ON game.id = hand.game
                          WHERE game.session = :s AND game.status = 'gameover') hand
                 ON hand.user = user.id
               WHERE session_player.session = :s
               GROUP BY user.id ORDER BY points DESC, user.name""",
            {"s": session_id},
        )
    ]
    played = db.execute(
        "SELECT count(*) FROM game WHERE session = ? AND status = 'gameover'", (session_id,)
    ).fetchone()[0]
    max_games = row["max_games"] if row else None
    closed = max_games is not None and played >= max_games
    best = standings[0]["points"] if standings else 0
    return {
        "max_games": max_games,
        "played": played,
        "closed": closed,
        "standings": standings,
        "winners": [s["name"] for s in standings if s["points"] == best] if closed else [],
    }


def table_summary(db: sqlite3.Connection, session_id: int) -> dict:
    """Statistics of the finished games of a table: per player and per game.

    players: in the order of the standings, with games, points, won games,
    games as Re, solos (and won), best/worst game, caught foxes, Karlchen,
    Doppelkopf. rounds: for each game its number, kind and every player's points.
    """
    standings = table_state(db, session_id)["standings"]
    players = {
        s["id"]: {
            "id": s["id"], "name": s["name"], "points": s["points"], "games": 0, "won": 0,
            "re": 0, "solos": 0, "solos_won": 0, "best": None, "worst": None,
            "fox": 0, "karlchen": 0, "doko": 0,
        }
        for s in standings
    }
    rounds = []
    games = db.execute(
        "SELECT id, type, solo FROM game WHERE session = ? AND status = 'gameover' ORDER BY id",
        (session_id,),
    ).fetchall()
    for number, game in enumerate(games, 1):
        points = {}
        for h in db.execute("SELECT user, party, sickness, solo, points FROM hand WHERE game = ?", (game["id"],)):
            p = players.get(h["user"])
            if p is None or h["points"] is None:
                continue
            points[h["user"]] = h["points"]
            p["games"] += 1
            p["won"] += h["points"] > 0
            p["re"] += h["party"] == "re"
            p["best"] = h["points"] if p["best"] is None else max(p["best"], h["points"])
            p["worst"] = h["points"] if p["worst"] is None else min(p["worst"], h["points"])
            if game["type"] == "solo" and h["sickness"] == "solo" and game["solo"] != "silent":
                p["solos"] += 1
                p["solos_won"] += h["points"] > 0
        rounds.append({"number": number, "id": game["id"], "type": game["type"], "solo": game["solo"], "points": points})
    for item in db.execute(
        """SELECT winner, kind, count(*) AS n FROM score_item
           WHERE kind IN ('fox', 'karlchen', 'doko') AND winner IS NOT NULL
             AND game IN (SELECT id FROM game WHERE session = ? AND status = 'gameover')
           GROUP BY winner, kind""",
        (session_id,),
    ):
        if item["winner"] in players:
            players[item["winner"]][item["kind"]] = item["n"]
    return {"players": [players[s["id"]] for s in standings], "rounds": rounds}


def next_players(db: sqlite3.Connection, game_id: int) -> list[int] | None:
    """The players (seat order) of the next game at this table, if one can follow.

    Seats rotate by one, unless the game was a solo (not a silent one) or was
    canceled because of nines or low trump: then the same player deals again.
    """
    game = db.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    if game is None or game["status"] not in ("gameover", *REPEATED):
        return None
    last = db.execute(
        "SELECT max(id) FROM game WHERE session = ?", (game["session"],)
    ).fetchone()[0]
    if last != game_id or table_state(db, game["session"])["closed"]:
        return None
    users = [
        r[0]
        for r in db.execute("SELECT user FROM hand WHERE game = ? ORDER BY seat", (game_id,))
    ]
    solo = game["type"] == "solo" and game["solo"] != "silent"
    return users if solo or game["status"] in REPEATED else users[1:] + users[:1]


def next_game(db: sqlite3.Connection, game_id: int, user_id: int) -> int:
    """"Keep playing": the next game at the same table, started by one of its players."""
    players = next_players(db, game_id)
    if players is None:
        raise PlayError(_("This table can not continue."))
    if user_id not in players:
        raise PlayError(_("Only the players of the table can continue it."))
    session = db.execute("SELECT session FROM game WHERE id = ?", (game_id,)).fetchone()[0]
    return create_game(db, players, session=session)


def new_table(
    db: sqlite3.Connection,
    creator: int,
    others: list[int],
    rs: rules.Rules,
    max_games: int | None = None,
) -> int:
    """A new table with the creator and three others, in random seats."""
    for key, choices in RULE_CHOICES.items():
        value = rs.call if key == "call" else getattr(rs, key)
        if value not in choices:
            raise PlayError(_("Unknown rules."))
    if creator in others or len(set(others)) != 3:
        raise PlayError(_("Please choose three other players."))
    players = [creator, *others]
    random.SystemRandom().shuffle(players)
    return create_game(db, players, rs, max_games=max_games)


def same_table_again(db: sqlite3.Connection, session_id: int, user_id: int) -> int:
    """A new table with the same players, rules and length (after one has ended)."""
    state = table_state(db, session_id)
    if not state["closed"]:
        raise PlayError(_("This table is not finished yet."))
    players = [s["id"] for s in state["standings"]]
    if user_id not in players:
        raise PlayError(_("Only the players of the table can do that."))
    rs = db.execute(
        """SELECT ruleset.dullen, ruleset.schweinchen, ruleset.lowtrump, ruleset.call
           FROM game JOIN ruleset ON ruleset.id = game.ruleset
           WHERE game.session = ? ORDER BY game.id DESC LIMIT 1""",
        (session_id,),
    ).fetchone()
    return new_table(
        db, user_id, [p for p in players if p != user_id],
        rules.Rules(*tuple(rs)), state["max_games"],
    )


# ------------------------------------------------- idle games: remind, cancel ---

REMIND_AFTER_DAYS = 7  # nothing happened for a week: the others may send a reminder
CANCEL_AFTER_DAYS = 30  # ... for a month: a player may cancel the game


def idle_days(db: sqlite3.Connection, game_id: int) -> int:
    row = db.execute(
        "SELECT cast(julianday('now') - julianday(updated_at) AS INTEGER) FROM game WHERE id = ?",
        (game_id,),
    ).fetchone()
    return row[0] if row else 0


def _player_hand(db: sqlite3.Connection, game_id: int, user_id: int) -> sqlite3.Row:
    hand = db.execute(
        "SELECT * FROM hand WHERE game = ? AND user = ?", (game_id, user_id)
    ).fetchone()
    if hand is None:
        raise PlayError(_("You are not a player of this game."))
    return hand


def remind(db: sqlite3.Connection, game_id: int, user_id: int) -> None:
    """Mail the player whose turn it is, if everybody has waited for a week.

    One reminder per game and week.
    """
    with transaction(db):
        _player_hand(db, game_id, user_id)
        game = db.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
        if game["status"] not in ACTIVE or game["turn_seat"] is None:
            raise PlayError(_("This game is not waiting for anybody."))
        if idle_days(db, game_id) < REMIND_AFTER_DAYS:
            raise PlayError(_("You need to wait longer before you can send out a reminder."))
        target = db.execute(
            "SELECT user FROM hand WHERE game = ? AND seat = ?", (game_id, game["turn_seat"])
        ).fetchone()[0]
        if target == user_id:
            raise PlayError(_("It is your own turn."))
        if db.execute(
            "SELECT 1 FROM reminder WHERE game = ? AND created_at > datetime('now', ?)",
            (game_id, f"-{REMIND_AFTER_DAYS} days"),
        ).fetchone():
            raise PlayError(_("A reminder has already been sent out."))
        db.execute(
            "INSERT INTO reminder (game, from_user, to_user) VALUES (?, ?, ?)",
            (game_id, user_id, target),
        )
    notify(db, [("reminder", game_id)])


def cancel_idle(db: sqlite3.Connection, game_id: int, user_id: int) -> None:
    """Cancel a game nothing has happened in for a month."""
    with transaction(db):
        _player_hand(db, game_id, user_id)
        game = db.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
        if game["status"] not in ACTIVE:
            raise PlayError(_("This game is over already."))
        if idle_days(db, game_id) < CANCEL_AFTER_DAYS:
            raise PlayError(_("You need to wait longer before you can cancel a game."))
        notices = cancel(db, game_id, "cancel-timedout")
    notify(db, notices)


# ---------------------------------------------------------------- poverty ---


def is_poor(rs: rules.Rules, sickness: str | None) -> bool:
    return sickness == "poverty" or (rs.lowtrump == "poverty" and sickness == "lowtrump")


def poor_seats(state: State) -> list[int]:
    return [s for s, h in sorted(state.hands.items()) if is_poor(state.rs, h["sickness"])]


def start_poverty(db: sqlite3.Connection, state: State, poor: list[int]) -> list:
    """One or two players have poverty: their trump is offered to the others in
    seat order, the first seat that is not poor is asked first."""
    game_id = state.game["id"]
    kind = "poverty" if len(poor) == 1 else "dpoverty"
    db.execute("UPDATE game SET type = ? WHERE id = ?", (kind, game_id))
    for seat, hand in state.hands.items():
        db.execute(
            "UPDATE hand SET status = ? WHERE id = ?",
            ("play" if seat in poor else "check", hand["id"]),
        )
    first = next(s for s in sorted(state.hands) if s not in poor)
    return ask_trump(db, game_id, first)


def ask_trump(db: sqlite3.Connection, game_id: int, seat: int) -> list:
    db.execute("UPDATE hand SET status = 'poverty' WHERE game = ? AND seat = ?", (game_id, seat))
    db.execute(
        "UPDATE game SET turn_seat = ?, sickness_state = ?, updated_at = CURRENT_TIMESTAMP"
        " WHERE id = ?",
        (seat, seat, game_id),
    )
    return [("poverty", game_id)]


def _asked(db: sqlite3.Connection, hand_id: int) -> State:
    """The state of a game where this hand has been asked about poverty trump."""
    hand = _hand(db, hand_id)
    state = load(db, hand["game"])
    if (
        state.game["status"] != "pre"
        or state.game["type"] not in ("poverty", "dpoverty")
        or hand["status"] != "poverty"
        or hand["party"] is not None
    ):
        raise PlayError(_("Nobody is asking you about trump now."))
    return state


def maybe_start(db: sqlite3.Connection, game_id: int) -> list:
    """Everybody is ready: the game starts."""
    if db.execute(
        "SELECT 1 FROM hand WHERE game = ? AND status != 'play'", (game_id,)
    ).fetchone():
        return []
    db.execute(
        "UPDATE game SET status = 'play', turn_seat = start_seat,"
        " updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        (game_id,),
    )
    return [("turn", game_id, True)]


def refuse_trump(db: sqlite3.Connection, hand_id: int) -> None:
    """"No way": the next player is asked; nobody left (or a double poverty): canceled."""
    with transaction(db):
        state = _asked(db, hand_id)
        game_id = state.game["id"]
        seat = next(s for s, h in state.hands.items() if h["id"] == hand_id)
        db.execute("UPDATE hand SET status = 'play' WHERE id = ?", (hand_id,))
        poor = poor_seats(state)
        later = [s for s in range(seat + 1, 5) if s not in poor]
        if state.game["type"] == "dpoverty" or not later:
            notices = cancel(db, game_id, "cancel-trump")
        else:
            notices = ask_trump(db, game_id, later[0])
    notify(db, notices)


def take_trump(db: sqlite3.Connection, hand_id: int, poor_user: int) -> None:
    """Take the trump of a poor player; the cards to give back follow."""
    notices: list = []
    with transaction(db):
        state = _asked(db, hand_id)
        game_id = state.game["id"]
        me = next(s for s, h in state.hands.items() if h["id"] == hand_id)
        target = next(
            (
                s for s in poor_seats(state)
                if state.hands[s]["user"] == poor_user and state.hands[s]["party"] is None
            ),
            None,
        )
        if target is None:
            raise PlayError(_("That player has no trump to take."))
        mine, theirs = state.hands[me]["id"], state.hands[target]["id"]
        for row in db.execute(
            "SELECT id, card FROM hand_card WHERE hand = ? AND card < 27", (theirs,)
        ).fetchall():
            db.execute("UPDATE hand_card SET hand = ? WHERE id = ?", (mine, row["id"]))
            db.execute(
                "INSERT INTO card_exchange (from_hand, to_hand, card) VALUES (?, ?, ?)",
                (theirs, mine, row["card"]),
            )
        pair = (me, target)
        if state.game["type"] == "poverty":
            for seat, hand in state.hands.items():
                db.execute(
                    "UPDATE hand SET party = ? WHERE id = ?",
                    ("re" if seat in pair else "contra", hand["id"]),
                )
                if seat not in pair:
                    db.execute("UPDATE hand SET status = 'play' WHERE id = ?", (hand["id"],))
        else:
            first = not any(h["party"] == "re" for h in state.hands.values())
            for seat in pair:
                db.execute(
                    "UPDATE hand SET party = ? WHERE id = ?",
                    ("re" if first else "contra", state.hands[seat]["id"]),
                )
            if first:  # the other player who is not poor decides about the other poverty
                other = next(
                    s for s in sorted(state.hands) if s not in poor_seats(state) and s != me
                )
                notices += ask_trump(db, game_id, other)
        taken = db.execute("SELECT count(*) FROM hand_card WHERE hand = ?", (mine,)).fetchone()[0]
        if taken == 12:  # a poverty without trump
            db.execute("UPDATE hand SET status = 'play' WHERE id = ?", (mine,))
            notices += maybe_start(db, game_id)
        else:
            db.execute("UPDATE game SET turn_seat = ? WHERE id = ?", (me, game_id))
    notify(db, notices)


def give_back(db: sqlite3.Connection, hand_id: int, card: int) -> None:
    """Return one card to the poor player (as many as trump was taken)."""
    notices: list = []
    with transaction(db):
        hand = _hand(db, hand_id)
        state = load(db, hand["game"])
        seat = hand["seat"]
        if (
            state.game["status"] != "pre"
            or hand["status"] != "poverty"
            or hand["party"] is None
            or len(state.held[seat]) <= 12
        ):
            raise PlayError(_("You do not have to give cards back."))
        if card not in state.held[seat]:
            raise PlayError(_("You do not have that card."))
        partner = db.execute(
            "SELECT from_hand FROM card_exchange WHERE to_hand = ? ORDER BY id LIMIT 1",
            (hand_id,),
        ).fetchone()[0]
        db.execute(
            "UPDATE hand_card SET hand = ? WHERE hand = ? AND card = ?",
            (partner, hand_id, card),
        )
        db.execute(
            "INSERT INTO card_exchange (from_hand, to_hand, card) VALUES (?, ?, ?)",
            (hand_id, partner, card),
        )
        if len(state.held[seat]) - 1 == 12:
            db.execute("UPDATE hand SET status = 'play' WHERE id = ?", (hand_id,))
            notices = maybe_start(db, hand["game"])
    notify(db, notices)


# ---------------------------------------------------------------- remarks ---

MAX_REMARK = 500
EDIT_MINUTES = 15  # how long the author may still change a remark


def clean_remark(text: str | None) -> str | None:
    """The text of a remark without surrounding blanks and with single spaces
    between lines; None if there is nothing."""
    text = " ".join((text or "").split())
    if len(text) > MAX_REMARK:
        raise PlayError(_("A remark can have at most %(n)d characters.", n=MAX_REMARK))
    return text or None


def add_remark(db: sqlite3.Connection, game_id: int, user_id: int, play_id: int, text: str) -> None:
    """A remark on a card played in this game (any player may write one)."""
    body = clean_remark(text)
    if body is None:
        raise PlayError(_("Please write something."))
    _player_hand(db, game_id, user_id)
    found = db.execute(
        "SELECT 1 FROM play JOIN trick ON trick.id = play.trick WHERE play.id = ? AND trick.game = ?",
        (play_id, game_id),
    ).fetchone()
    if not found:
        raise PlayError(_("There is no such card in this game."))
    db.execute(
        "INSERT INTO comment (game, kind, play, user, body) VALUES (?, 'move', ?, ?, ?)",
        (game_id, play_id, user_id, body),
    )


def _own_remark(db: sqlite3.Connection, comment_id: int, user_id: int) -> sqlite3.Row:
    row = db.execute("SELECT * FROM comment WHERE id = ?", (comment_id,)).fetchone()
    if row is None or row["deleted_at"] is not None or row["user"] != user_id:
        raise PlayError(_("You can only change your own remarks."))
    return row


def remark_editable(db: sqlite3.Connection, comment_id: int) -> bool:
    row = db.execute(
        "SELECT created_at > datetime('now', ?) FROM comment WHERE id = ?",
        (f"-{EDIT_MINUTES} minutes", comment_id),
    ).fetchone()
    return bool(row and row[0])


def edit_remark(db: sqlite3.Connection, comment_id: int, user_id: int, text: str) -> None:
    """Change your own remark, within a few minutes of writing it."""
    body = clean_remark(text)
    if body is None:
        raise PlayError(_("Please write something."))
    row = _own_remark(db, comment_id, user_id)
    if body == row["body"]:
        return  # nothing changed: not marked as edited
    if not remark_editable(db, comment_id):
        raise PlayError(_("It is too late to change this remark, but you can delete it."))
    db.execute(
        "UPDATE comment SET body = ?, edited_at = CURRENT_TIMESTAMP WHERE id = ?",
        (body, comment_id),
    )


def delete_remark(db: sqlite3.Connection, comment_id: int, user_id: int) -> None:
    """Delete your own remark (it stays in the database, marked as deleted)."""
    _own_remark(db, comment_id, user_id)
    db.execute("UPDATE comment SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?", (comment_id,))
