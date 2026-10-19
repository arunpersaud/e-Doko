import itertools

import pytest

from edoko import rules
from edoko.rules import Foxes, Rules

MODES = [
    "normal", "wedding", "poverty", "dpoverty", "trump", "silent",
    "club", "spade", "heart", "queen", "jack", "trumpless",
]  # fmt: skip


@pytest.mark.parametrize("dullen", ["none", "firstwins", "secondwins"])
@pytest.mark.parametrize("mode", MODES)
def test_every_card_belongs_to_exactly_one_group(mode, dullen):
    cards = rules.layout(mode, dullen)
    every = list(itertools.chain.from_iterable(cards.values()))
    assert sorted(every) == list(range(1, 49))


@pytest.mark.parametrize(
    "mode, size",
    [("normal", 26), ("queen", 8), ("jack", 8), ("trumpless", 0), ("club", 26)],
)
def test_number_of_trump(mode, size):
    assert len(rules.layout(mode, "secondwins")["trump"]) == size


def test_ten_of_hearts_without_dullen_is_a_plain_heart():
    cards = rules.layout("normal", "none")
    assert 1 not in cards["trump"] and len(cards["trump"]) == 24
    assert cards["hearts"][:4] == [43, 44, 1, 2]


def test_poverty_counts_the_same_trump_as_normal():
    assert rules.layout("poverty", "secondwins") == rules.layout("normal", "secondwins")


def test_card_points_sum_to_240():
    assert sum(rules.POINTS.values()) == 240
    assert rules.POINTS[19] == 11 and rules.POINTS[1] == 10 and rules.POINTS[25] == 0


R = Rules()  # secondwins, schweinchen second


def wins(first, second, mode="normal", rules_=R, foxes=None):
    return rules.beats(first, second, mode, rules_, foxes)


def test_trump_order_in_a_normal_game():
    ten, qc, qd, jc, jd, fox, ten_d, nine_d = 1, 3, 9, 11, 17, 19, 21, 25
    order = [ten, qc, qd, jc, jd, fox, ten_d, nine_d]
    for hi, lo in itertools.combinations(order, 2):
        assert wins(hi, lo) and not wins(lo, hi)


def test_plain_suits_and_following_suit():
    assert wins(27, 29)  # ace of clubs over ten of clubs
    assert not wins(33, 31)  # nine of clubs loses to the king
    assert wins(27, 35)  # another plain suit never wins
    assert not wins(27, 25)  # a trump beats a plain card
    assert wins(25, 27)


def test_the_two_copies_of_a_card_tie_and_the_first_wins():
    assert wins(27, 28) and wins(28, 27)


def test_second_ten_of_hearts_wins_unless_the_rules_say_otherwise():
    assert not wins(1, 2)  # secondwins: the later one wins
    assert wins(1, 2, rules_=Rules(dullen="firstwins"))
    assert wins(1, 2, rules_=Rules(dullen="none"))


def test_no_second_ten_rule_in_a_queen_solo():
    assert wins(1, 2, mode="queen")


def test_schweinchen_both_makes_the_foxes_the_top_trump_from_the_start():
    rs = Rules(schweinchen="both")
    foxes = Foxes(holder=1)
    assert wins(19, 1, rules_=rs, foxes=foxes)
    assert not wins(1, 20, rules_=rs, foxes=foxes)
    assert wins(1, 20, rules_=rs, foxes=Foxes())  # nobody has both: ordinary trump


def test_schweinchen_second_needs_the_second_fox():
    foxes = Foxes(holder=1)
    assert wins(1, 19, foxes=foxes)  # the first fox is an ordinary trump
    foxes.played(19)
    assert wins(1, 19, foxes=foxes)
    foxes.played(20)
    assert not wins(1, 20, foxes=foxes)  # the second one beats everything


def test_schweinchen_after_a_call():
    rs = Rules(schweinchen="secondaftercall")
    foxes = Foxes(holder=1, first_played=True, second_played=True)
    assert wins(1, 19, rules_=rs, foxes=foxes)
    foxes.called = True
    assert not wins(1, 19, rules_=rs, foxes=foxes)


def test_schweinchen_does_not_count_in_a_poverty():
    foxes = Foxes(holder=1, first_played=True, second_played=True)
    assert wins(1, 19, mode="poverty", foxes=foxes)


def test_foxes_for():
    hands = {1: [19, 20, 3], 2: [4]}
    assert rules.foxes_for(hands, "normal", R).holder == 1
    assert rules.foxes_for(hands, "poverty", R).holder is None
    assert rules.foxes_for(hands, "queen", R).holder is None
    assert rules.foxes_for(hands, "normal", Rules(schweinchen="none")).holder is None


def test_trick_winner():
    # seat 3 starts, a plain suit is led and the queen of diamonds trumps it
    assert rules.trick_winner([(3, 27), (4, 31), (1, 9), (2, 29)], "normal", R) == 1
    # nobody trumps: the highest card of the led suit
    assert rules.trick_winner([(2, 29), (3, 27), (4, 35), (1, 33)], "normal", R) == 3
    # the second ten of hearts
    assert rules.trick_winner([(1, 1), (2, 2), (3, 3), (4, 5)], "normal", R) == 2
    # a trump solo of clubs: the jack of diamonds beats the ace of clubs
    assert rules.trick_winner([(1, 27), (2, 17), (3, 35), (4, 43)], "club", R) == 2
    # trumpless: the ace of the led suit
    assert rules.trick_winner([(1, 29), (2, 27), (3, 3), (4, 35)], "trumpless", R) == 2


# --- scoring ------------------------------------------------------------------

P = {1: "re", 2: "contra", 3: "contra", 4: "re"}


def final(re, calls=None, parties=P):
    result = rules.Result(card_points={"re": re, "contra": 240 - re})
    rules._final_items(result, parties, calls or {})
    return sorted(i.key() for i in result.items), result


def kinds(items, party):
    return sorted(k for p, k in items if p == party)


def test_re_wins_with_more_than_120():
    items, result = final(125)
    assert items == [("re", "120")] and result.winning_party == "re"
    assert result.game_points == 1
    assert kinds(final(151)[0], "re") == ["120", "90"]
    assert kinds(final(240)[0], "re") == ["0", "120", "30", "60", "90"]


def test_contra_wins_with_120_and_gets_the_point_for_beating_the_queens():
    items, result = final(120)
    assert items == [("contra", "againstqueens")] and result.winning_party == "contra"
    assert result.game_points == -1
    assert kinds(final(90)[0], "contra") == ["120", "againstqueens"]
    assert kinds(final(89)[0], "contra") == ["120", "90", "againstqueens"]


def test_a_call_that_is_made():
    items, _ = final(130, {1: 120})
    assert items == [("re", "120"), ("re", "call120")]


def test_a_call_that_is_missed():
    items, result = final(100, {1: 90})
    assert result.winning_party == "contra"
    assert kinds(items, "contra") == [
        "120", "against120", "against90", "againstqueens", "made90",
    ]  # fmt: skip
    assert kinds(items, "re") == []


def test_both_parties_call():
    # re calls 120 and gets 125 (needs more than 120); contra called 90 and has 115:
    # contra's call failed, which counts for re
    items, result = final(125, {1: 120, 2: 90})
    assert result.winning_party == "re"
    assert ("re", "call120") in items and ("re", "against90") in items
    assert ("re", "against120") in items and ("re", "made90") in items


def test_only_the_lowest_call_of_a_party_counts():
    assert final(130, {1: 120, 4: 90})[0] == final(130, {1: 90})[0]
    items, _ = final(200, {1: 120, 4: 90})
    assert ("re", "call90") in items and ("re", "call120") in items


def test_game_points_and_player_points():
    _, result = final(155)
    assert result.game_points == 2
    assert rules.player_points(result, P, solo=False) == {1: 2, 2: -2, 3: -2, 4: 2}
    solo = {1: "re", 2: "contra", 3: "contra", 4: "contra"}
    _, result = final(155, parties=solo)
    assert rules.player_points(result, solo, solo=True) == {1: 6, 2: -2, 3: -2, 4: -2}


def tricks_for(*plays):
    return [list(plays)]


def replay(plays, parties=P, mode="normal", solo=None, calls=None):
    return rules.replay(
        [plays], mode=mode, solo=solo, rules=R, parties=parties, calls=calls or {}
    )


def test_fox_caught_by_the_other_party():
    # seat 4 (re) wins with the queen of clubs; the fox of seat 2 (contra) is caught
    result = replay([(1, 25), (2, 19), (3, 27), (4, 3)])
    fox = [i for i in result.items if i.kind == "fox"]
    assert [(i.party, i.winner_seat, i.loser_seat) for i in fox] == [("re", 4, 2)]


def test_own_fox_is_not_caught():
    result = replay([(1, 25), (4, 19), (3, 27), (2, 3)], parties=P)
    assert result.winners == [2]  # the queen of clubs of seat 2 (contra) wins
    assert [i.kind for i in result.items if i.kind == "fox"] == ["fox"]
    result = replay([(1, 19), (2, 25), (3, 27), (4, 3)])
    assert not [i for i in result.items if i.kind == "fox"]  # 1 and 4 are both re


def test_no_foxes_in_most_solos():
    parties = {1: "re", 2: "contra", 3: "contra", 4: "contra"}
    plays = [(1, 3), (2, 19), (3, 27), (4, 25)]
    assert not [
        i for i in replay(plays, parties, "solo", "queen").items if i.kind == "fox"
    ]
    assert [i for i in replay(plays, parties, "solo", "trump").items if i.kind == "fox"]


def test_karlchen_wins_the_last_trick():
    result = replay([(1, 11), (2, 25), (3, 27), (4, 29)])
    assert [(i.party, i.winner_seat) for i in result.items if i.kind == "karlchen"] == [
        ("re", 1)
    ]
    # but not in a jack solo
    parties = {1: "re", 2: "contra", 3: "contra", 4: "contra"}
    result = replay([(1, 11), (2, 25), (3, 27), (4, 29)], parties, "solo", "jack")
    assert not [i for i in result.items if i.kind == "karlchen"]


def test_doppelkopf_is_a_trick_with_40_points():
    result = replay([(1, 1), (2, 19), (3, 21), (4, 20)])  # 10 + 11 + 10 + 11
    assert [(i.party, i.winner_seat) for i in result.items if i.kind == "doko"] == [
        ("re", 1)
    ]
    assert not [
        i for i in replay([(1, 1), (2, 25), (3, 3), (4, 5)]).items if i.kind == "doko"
    ]


# --- sorting a hand ------------------------------------------------------------------


def test_sorting_a_hand_in_a_normal_game():
    hand = [25, 1, 27, 3, 19, 35, 43, 9, 36]
    # trump (10 of hearts, queens, the ace of diamonds, nine of diamonds), then
    # clubs, hearts, spades
    assert rules.sort_hand(hand, "normal", R) == [1, 3, 9, 19, 25, 27, 43, 35, 36]


def test_sorting_without_dullen_puts_the_ten_of_hearts_among_the_hearts():
    hand = [1, 3, 43, 45, 27]
    assert rules.sort_hand(hand, "normal", Rules(dullen="none")) == [3, 27, 43, 1, 45]


def test_sorting_in_solos():
    hand = [3, 11, 27, 19, 43, 1]
    # queen solo: the queens are trump, then diamonds (the ace of diamonds ...),
    # clubs (with the jack of clubs between king and nine), hearts (10 first)
    assert rules.sort_hand(hand, "queen", R) == [3, 19, 27, 11, 43, 1]
    assert rules.sort_hand([3, 9, 17, 19], "queen", R) == [3, 9, 19, 17]
    # trumpless: no trump, ace before ten
    assert rules.sort_hand([29, 27, 3], "trumpless", R) == [27, 29, 3]
    # a club solo: the clubs are trump, below the jacks
    assert rules.sort_hand([27, 11, 35, 1], "club", R) == [1, 11, 27, 35]


def test_foxes_come_first_for_a_hand_with_schweinchen():
    hand = [1, 3, 19, 20, 25]
    assert rules.sort_hand(hand, "normal", Rules(schweinchen="both")) == [
        19,
        20,
        1,
        3,
        25,
    ]
    assert rules.sort_hand(hand, "normal", Rules(schweinchen="second")) == [
        19,
        1,
        3,
        20,
        25,
    ]
    assert rules.sort_hand(hand, "normal", Rules(schweinchen="none")) == [
        1,
        3,
        19,
        20,
        25,
    ]
    # one fox is nothing special, nor is it in a poverty
    assert rules.sort_hand([1, 19, 25], "normal", Rules(schweinchen="both")) == [
        1,
        19,
        25,
    ]
    assert rules.sort_hand(hand, "poverty", Rules(schweinchen="both")) == [
        1,
        3,
        19,
        20,
        25,
    ]


def test_the_foxes_count_even_after_one_was_played():
    # the hand had both foxes: the one still held is shown first
    assert rules.sort_hand(
        [1, 20], "normal", Rules(schweinchen="both"), all_cards=[1, 19, 20]
    ) == [19 if False else 20, 1]
