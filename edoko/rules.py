"""The rules of Doppelkopf: a pure module, no Flask and no database.

Ported from the PHP site (include/functions.php: set_gametype,
compare_cards, get_winner; include/game.php: the scoring), so old games
score exactly as they did. Cards are the ids 1..48 of the card table; the
two copies of a card are 2k-1 and 2k, and a card is compared by its odd id.

Kinds of games ("modes"): normal, wedding, poverty, dpoverty, silent (a
solo played like a normal game), and the solos trump, club, spade, heart,
queen, jack, trumpless.
"""

from collections import Counter
from dataclasses import dataclass, field

FOXES = (19, 20)  # the aces of diamonds
KARLCHEN = (11, 12)  # the jacks of clubs
HEART_TEN = 1

# card points by card id
POINTS = {}
for _first, _points in (
    (1, 10),  # ten of hearts
    (3, 3),  # queens: clubs, spades, hearts, diamonds
    (5, 3),
    (7, 3),
    (9, 3),
    (11, 2),  # jacks
    (13, 2),
    (15, 2),
    (17, 2),
    (19, 11),  # diamonds: ace, ten, king, nine
    (21, 10),
    (23, 4),
    (25, 0),
    (27, 11),  # clubs
    (29, 10),
    (31, 4),
    (33, 0),
    (35, 11),  # spades
    (37, 10),
    (39, 4),
    (41, 0),
    (43, 11),  # hearts
    (45, 4),
    (47, 0),
):
    POINTS[_first] = POINTS[_first + 1] = _points


def _ids(*numbers: int) -> list[int]:
    """Both copies of each odd id, in the order of the arguments."""
    return [n for first in numbers for n in (first, first + 1)]


NORMAL_TRUMP = _ids(1, 3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 23, 25)
PLAIN = {
    "clubs": _ids(27, 29, 31, 33),
    "spades": _ids(35, 37, 39, 41),
    "hearts": _ids(43, 45, 47),
    "diamonds": [],
}


def layout(mode: str, dullen: str) -> dict[str, list[int]]:
    """Trump and the plain suits for a kind of game, strongest first.

    Strength of trump is the numeric id; in a plain suit it is the
    position in the list.
    """
    low_hearts = _ids(43, 1, 45, 47) if dullen == "none" else _ids(43, 45, 47)
    queens = _ids(3, 5, 7, 9)
    jacks = _ids(11, 13, 15, 17)
    if mode in ("normal", "wedding", "poverty", "dpoverty", "trump", "silent"):
        trump = [c for c in NORMAL_TRUMP if dullen != "none" or c > 2]
        hearts = low_hearts if dullen == "none" else PLAIN["hearts"]
        return {**PLAIN, "trump": trump, "hearts": hearts}
    if mode == "queen":
        return {
            "trump": queens,
            "clubs": _ids(27, 29, 31) + [11, 12] + [33, 34],
            "spades": _ids(35, 37, 39) + [13, 14] + [41, 42],
            "hearts": _ids(43, 1, 45) + [15, 16] + [47, 48],
            "diamonds": _ids(19, 21, 23) + [17, 18] + [25, 26],
        }
    if mode == "jack":
        return {
            "trump": jacks,
            "clubs": _ids(27, 29, 31) + [3, 4] + [33, 34],
            "spades": _ids(35, 37, 39) + [5, 6] + [41, 42],
            "hearts": _ids(43, 1, 45) + [7, 8] + [47, 48],
            "diamonds": _ids(19, 21, 23) + [9, 10] + [25, 26],
        }
    if mode == "trumpless":
        return {
            "trump": [],
            "clubs": _ids(27, 29, 31, 3, 11, 33),
            "spades": _ids(35, 37, 39, 5, 13, 41),
            "hearts": _ids(43, 1, 45, 7, 15, 47),
            "diamonds": _ids(19, 21, 23, 9, 17, 25),
        }
    if mode in ("club", "spade", "heart"):
        suit = {"club": "clubs", "spade": "spades", "heart": "hearts"}[mode]
        plain = {k: list(v) for k, v in PLAIN.items()}
        plain["diamonds"] = _ids(19, 21, 23, 25)
        trump = _ids(1, 3, 5, 7, 9, 11, 13, 15, 17) + plain.pop(suit)
        plain[suit] = []
        # with the ten of hearts as a plain card (dullen none) it leaves the
        # trump, except in a heart solo where it stays (as on the PHP site)
        if dullen == "none" and suit != "hearts":
            trump.remove(1)
            trump.remove(2)
            plain["hearts"] = _ids(43, 1, 45, 47)
        return {**plain, "trump": trump}
    raise ValueError(f"unknown kind of game: {mode}")


def odd(card: int) -> int:
    return card if card % 2 else card - 1


@dataclass(frozen=True)
class Rules:
    """A ruleset of the site."""

    dullen: str = "secondwins"  # none, firstwins, secondwins
    schweinchen: str = "second"  # none, both, second, secondaftercall
    lowtrump: str = "poverty"
    call: str = "1st-own-card"


@dataclass
class Foxes:
    """Schweinchen: one player has both foxes (aces of diamonds).

    holder: the seat that has them, or None. When they become the highest
    trump depends on the rule: from the start ("both"), when the second
    is played ("second"), or then and only if the holder or the partner
    has called ("secondaftercall").
    """

    holder: int | None = None
    first_played: bool = False
    second_played: bool = False
    called: bool = False

    def active(self, rules: Rules) -> bool:
        if self.holder is None:
            return False
        if rules.schweinchen == "both":
            return True
        if rules.schweinchen == "second":
            return self.second_played
        if rules.schweinchen == "secondaftercall":
            return self.second_played and self.called
        return False

    def played(self, card: int) -> None:
        if card in FOXES:
            if self.first_played:
                self.second_played = True
            else:
                self.first_played = True


def foxes_for(hands: dict[int, list[int]], mode: str, rules: Rules) -> Foxes:
    """Who has both foxes, if Schweinchen counts in this kind of game.

    hands: seat -> the cards of the hand at the start of the game.
    """
    foxes = Foxes()
    counts = mode in ("normal", "wedding", "trump", "silent")
    if counts and rules.schweinchen != "none":
        for seat, cards in hands.items():
            if 19 in cards and 20 in cards:
                foxes.holder = seat
    return foxes


def beats(a: int, b: int, mode: str, rules: Rules, foxes: Foxes | None = None) -> bool:
    """Does card a, the best so far, keep the trick against card b?

    a is the card played first (or the best one before b).
    """
    a, b = odd(a), odd(b)
    if (
        mode in ("normal", "silent", "trump", "wedding")
        and foxes
        and foxes.active(rules)
    ):
        if a in FOXES:
            return True
        if b in FOXES:
            return False
    second_ten = rules.dullen == "secondwins" and a == HEART_TEN == b
    if second_ten and mode not in ("trumpless", "jack", "queen"):
        return False  # the second ten of hearts wins
    cards = layout(mode, rules.dullen)
    trump = cards["trump"]
    if a in trump and b in trump:
        return a <= b
    if a in trump:
        return True
    if b in trump:
        return False
    for suit in ("clubs", "spades", "hearts", "diamonds"):
        if a in cards[suit] and b in cards[suit]:
            return cards[suit].index(a) <= cards[suit].index(b)
    return True  # another suit: the first card keeps the trick


def trick_winner(
    plays: list[tuple[int, int]], mode: str, rules: Rules, foxes: Foxes | None = None
) -> int:
    """The seat that wins a trick: plays are (seat, card) in the order played."""
    best_seat, best = plays[0]
    for seat, card in plays[1:]:
        if not beats(best, card, mode, rules, foxes):
            best_seat, best = seat, card
    return best_seat


@dataclass
class Item:
    """One scoring item (a row of score_item)."""

    party: str
    kind: str
    winner_seat: int | None = None
    loser_seat: int | None = None

    def key(self) -> tuple[str, str]:
        return (self.party, self.kind)


@dataclass
class Result:
    winners: list[int] = field(default_factory=list)  # seat that won each trick
    items: list[Item] = field(default_factory=list)
    card_points: dict[str, int] = field(default_factory=dict)  # per party
    winning_party: str | None = None
    game_points: int = 0  # items of re minus items of contra


def replay(
    tricks: list[list[tuple[int, int]]],
    *,
    mode: str,
    solo: str | None,
    rules: Rules,
    parties: dict[int, str],
    calls: dict[int, int | None],
    foxes: Foxes | None = None,
) -> Result:
    """Score a finished game.

    tricks: for each trick the plays (seat, card) in the order played.
    mode:   the kind of game ("solo" is replaced by solo, e.g. "queen").
    parties: seat -> "re" / "contra".
    calls:  seat -> the lowest call the player made (120, 90, 60, 30, 0) or None.
    """
    foxes = foxes or Foxes()
    game_type = solo if mode == "solo" and solo else mode
    result = Result(card_points={"re": 0, "contra": 0})
    last = len(tricks) - 1
    for number, plays in enumerate(tricks):
        for _seat, card in plays:
            foxes.played(card)
        winner = trick_winner(plays, game_type, rules, foxes)
        result.winners.append(winner)
        party = parties[winner]
        points = sum(POINTS[card] for _seat, card in plays)
        result.card_points[party] += points

        # a fox caught by the other party (not in the solos where they don't count)
        if mode != "solo" or solo in ("trump", "silent"):
            for seat, card in plays:
                if card in FOXES and seat != winner and parties[seat] != party:
                    result.items.append(Item(party, "fox", winner, seat))
        # Karlchen: the jacks of clubs win the last trick
        if (
            number == last
            and solo not in ("trumpless", "jack", "queen")
            and any(card in KARLCHEN and seat == winner for seat, card in plays)
        ):
            result.items.append(Item(party, "karlchen", winner))
        # doppelkopf: 40 points or more in one trick
        if points > 39:
            result.items.append(Item(party, "doko", winner))
    _final_items(result, parties, calls)
    return result


def _final_items(result: Result, parties: dict[int, str], calls: dict[int, int | None]):
    """The items for the result of the game: won, calls, against (as in PHP)."""
    re, contra = result.card_points["re"], result.card_points["contra"]
    call = {"re": -1, "contra": -1}
    for seat, party in parties.items():
        value = calls.get(seat)
        if value is not None and (call[party] == -1 or value < call[party]):
            call[party] = value

    winning = None
    if call["re"] == -1 and call["contra"] == -1:
        winning = "re" if re > 120 else "contra"
    else:
        for party, points, other in (("re", re, "contra"), ("contra", contra, "re")):
            if call[party] != -1:
                offset = 120 - call[party]
                if call[party] == 0:
                    offset -= 1
                if points > 120 + offset:
                    winning = party
                elif call[other] == -1:
                    winning = other
    result.winning_party = winning

    def add(party: str, kind: str) -> None:
        result.items.append(Item(party, kind))

    for party, other in (("re", "contra"), ("contra", "re")):
        if winning != other and call[other] != -1:
            # calls of the other party that were not made count against it
            for p in range(call[other], 121, 30):
                add(party, f"against{p}")
            for p in range(call[other], 120, 30):
                if (re if party == "re" else contra) >= p:
                    add(party, f"made{p}")
    if winning == "contra":
        add("contra", "againstqueens")
    if winning:
        other = "contra" if winning == "re" else "re"
        points = re if winning == "re" else contra
        for p in (120, 150, 180, 210, 240):
            offset = 1 if p == 240 or call[other] != -1 else 0
            if points > p - offset:
                add(winning, str(240 - p))
        for p in (0, 30, 60, 90, 120):
            if call[winning] != -1 and call[winning] < p + 1:
                add(winning, f"call{p}")
    result.game_points = sum(1 if item.party == "re" else -1 for item in result.items)


def player_points(
    result: Result, parties: dict[int, str], *, solo: bool
) -> dict[int, int]:
    """Points per seat: re gets the game points (times 3 for a solo), contra minus."""
    points = {}
    for seat, party in parties.items():
        if party == "re":
            points[seat] = result.game_points * (3 if solo else 1)
        else:
            points[seat] = -result.game_points
    return points


def item_counts(items: list[Item]) -> Counter:
    return Counter(item.key() for item in items)
