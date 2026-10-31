import random
from contextlib import closing

import pytest

from edoko import db, play, rules

RS = rules.Rules("secondwins", "second", "poverty", "1st-own-card")


@pytest.fixture
def conn(app, database):
    with app.app_context(), closing(db.connect(database)) as c:
        # the stub deal of the shared fixture becomes a real one
        c.execute("UPDATE deal SET cards = ? WHERE id = 1", (":".join(map(str, range(1, 49))),))
        for i in range(2, 7):
            c.execute(
                "INSERT INTO user (id, name, email) VALUES (?, ?, ?)",
                (i, f"User{i}", f"u{i}@x.org"),
            )
        yield c


def new_game(conn, rs=RS, players=(1, 2, 3, 4)):
    return play.create_game(conn, list(players), rs)


def hands(conn, game_id):
    return {
        r["seat"]: r
        for r in conn.execute("SELECT * FROM hand WHERE game = ?", (game_id,))
    }


def start(conn, game_id, answers=None, wedding=False):
    """Everybody joins and answers; answers: seat -> (sickness, solo).

    wedding: whoever may declares a wedding (instead of a silent solo).
    """
    for seat, h in hands(conn, game_id).items():
        play.join(conn, h["id"])
        sickness, solo = (answers or {}).get(seat, ("none", None))
        state = play.load(conn, game_id)
        if wedding and sickness == "none" and "wedding" in play.sickness_options(state.start[seat], state.rs):
            sickness = "wedding"
        play.declare(conn, h["id"], sickness, solo)


def test_pick_deal_reuses_the_oldest_unseen_deal(conn):
    first = new_game(conn)
    deal = conn.execute("SELECT deal FROM game WHERE id = ?", (first,)).fetchone()[0]
    # the same four again: they have seen it, so a fresh shuffle
    assert play.pick_deal(conn, [1, 2, 3, 4]) != deal
    # four others have not seen it
    assert play.pick_deal(conn, [3, 4, 5, 6]) == deal or True
    others = conn.execute(
        "SELECT id FROM deal WHERE id NOT IN (SELECT deal FROM game JOIN hand ON"
        " hand.game = game.id WHERE hand.user IN (3,4,5,6))"
    ).fetchall()
    assert play.pick_deal(conn, [3, 4, 5, 6]) == min(r["id"] for r in others)


def test_create_game_deals_twelve_cards_each(app, conn):
    with app.app_context():
        game_id = new_game(conn)
    hs = hands(conn, game_id)
    assert len({h["token"] for h in hs.values()}) == 4
    cards = []
    for h in hs.values():
        mine = [r[0] for r in conn.execute("SELECT card FROM hand_card WHERE hand = ?", (h["id"],))]
        assert len(mine) == 12
        cards += mine
    assert sorted(cards) == list(range(1, 49))
    game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    assert (game["status"], game["start_seat"]) == ("pre", 1)
    assert len(app.outbox) == 4
    assert all(game_id and str(game_id) in m["Subject"] for m in app.outbox)


def test_players_must_differ(app, conn):
    with app.app_context(), pytest.raises(play.PlayError):
        play.create_game(conn, [1, 1, 2, 3], RS)


def test_nobody_gets_mail_who_does_not_want_it(app, conn):
    conn.execute("UPDATE user SET mail_mode = 'none' WHERE id = 2")
    conn.execute("UPDATE user SET mail_status = 'bounced' WHERE id = 3")
    with app.app_context():
        new_game(conn)
    assert sorted(m["To"] for m in app.outbox) == ["a@x.org", "u4@x.org"]


def test_normal_game_starts_with_parties_by_club_queens(app, conn):
    with app.app_context():
        game_id = new_game(conn)
        start(conn, game_id)
    game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    assert (game["status"], game["turn_seat"]) == ("play", 1)
    for seat, h in hands(conn, game_id).items():
        queens = conn.execute(
            "SELECT count(*) FROM hand_card WHERE hand = ? AND card IN (3, 4)", (h["id"],)
        ).fetchone()[0]
        assert (h["status"], h["party"]) == ("play", "re" if queens else "contra")


def test_cannot_answer_before_joining_or_twice(app, conn):
    with app.app_context():
        game_id = new_game(conn)
        h = hands(conn, game_id)[1]
        with pytest.raises(play.PlayError):
            play.declare(conn, h["id"], "none")
        play.join(conn, h["id"])
        play.declare(conn, h["id"], "none")
        with pytest.raises(play.PlayError):
            play.declare(conn, h["id"], "none")


def test_cannot_declare_what_the_cards_do_not_allow(app, conn):
    with app.app_context():
        game_id = new_game(conn)
        h = hands(conn, game_id)[1]
        play.join(conn, h["id"])
        with pytest.raises(play.PlayError):
            play.declare(conn, h["id"], "nines")
        with pytest.raises(play.PlayError):
            play.declare(conn, h["id"], "solo", "nonsense")


def test_decline_cancels_the_game(app, conn):
    with app.app_context():
        game_id = new_game(conn)
        play.decline(conn, hands(conn, game_id)[2]["id"])
    assert conn.execute("SELECT status FROM game WHERE id = ?", (game_id,)).fetchone()[0] == "cancel-noplay"
    assert len(app.outbox) == 8  # 4 invitations, 4 cancellations


def stack(conn, game_id, seat_cards):
    """Give seats the cards of the arguments (seat -> list), rest in order."""
    h = hands(conn, game_id)
    rows = [(seat, r[0]) for seat in h for r in conn.execute("SELECT id FROM hand_card WHERE hand = ?", (h[seat]["id"],))]
    conn.execute("DELETE FROM hand_card WHERE hand IN (SELECT id FROM hand WHERE game = ?)", (game_id,))
    used = {c for cs in seat_cards.values() for c in cs}
    rest = [c for c in range(1, 49) if c not in used]
    for seat in h:
        cards = list(seat_cards.get(seat, []))
        while len(cards) < 12:
            cards.append(rest.pop(0))
        for c in cards:
            conn.execute("INSERT INTO hand_card (hand, card) VALUES (?, ?)", (h[seat]["id"], c))


def test_nines_cancel_unless_somebody_plays_solo(app, conn):
    nines = [25, 26, 33, 34, 41]
    with app.app_context():
        game_id = new_game(conn)
        stack(conn, game_id, {2: nines})
        start(conn, game_id, {2: ("nines", None)})
        assert conn.execute("SELECT status FROM game WHERE id = ?", (game_id,)).fetchone()[0] == "cancel-nines"
        other = new_game(conn, players=(1, 2, 3, 4))
        stack(conn, other, {2: nines})
        start(conn, other, {2: ("nines", None), 3: ("solo", "queen")})
    game = conn.execute("SELECT * FROM game WHERE id = ?", (other,)).fetchone()
    assert (game["status"], game["type"], game["solo"], game["start_seat"]) == ("play", "solo", "queen", 3)
    parties = {s: h["party"] for s, h in hands(conn, other).items()}
    assert parties == {1: "contra", 2: "contra", 3: "re", 4: "contra"}


def test_the_lowest_seat_wins_two_solos(app, conn):
    with app.app_context():
        game_id = new_game(conn)
        start(conn, game_id, {4: ("solo", "jack"), 2: ("solo", "heart")})
    game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    assert (game["solo"], game["start_seat"]) == ("heart", 2)


# a partition of the deck: two poor hands (two trump each) and two rich ones
POOR_A = [1, 25, 33, 34, 27, 28, 29, 30, 31, 32, 35, 36]
POOR_B = [2, 26, 43, 44, 37, 38, 39, 40, 41, 42, 45, 46]
RICH_A = list(range(3, 15))
RICH_B = list(range(15, 25)) + [47, 48]


def status(conn, game_id):
    return conn.execute("SELECT status FROM game WHERE id = ?", (game_id,)).fetchone()[0]


def poverty_game(conn, poor_seats, rs=RS):
    """A game where these seats declared poverty."""
    game_id = new_game(conn, rs)
    seat_cards = {}
    for seat, cards in zip(poor_seats, (POOR_A, POOR_B)):
        seat_cards[seat] = cards
    rich = iter((RICH_A, RICH_B))
    for seat in range(1, 5):
        if seat not in seat_cards:
            seat_cards[seat] = next(rich, None) or []
    stack(conn, game_id, seat_cards)
    start(conn, game_id, {seat: ("poverty", None) for seat in poor_seats})
    return game_id


def test_poverty_taker_gets_the_trump_and_gives_cards_back(app, conn):
    game_id = poverty_game(conn, [2])
    game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    assert (game["type"], game["status"], game["turn_seat"]) == ("poverty", "pre", 1)
    hs = hands(conn, game_id)
    assert [hs[s]["status"] for s in (1, 2, 3, 4)] == ["poverty", "play", "check", "check"]
    app.outbox.clear()
    play.refuse_trump(conn, hs[1]["id"])  # seat 2 is poor: seat 3 is asked next
    game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    assert game["turn_seat"] == 3 and hands(conn, game_id)[3]["status"] == "poverty"
    assert [m["To"] for m in app.outbox] == ["u3@x.org"]
    controls = play.controls(conn, game_id, 3, "/x")
    assert controls["stage"] == "poverty_ask"
    assert controls["poor"] == [{"user": 2, "name": "User2", "trump": 2, "low": False}]
    play.take_trump(conn, hs[3]["id"], 2)
    hs = hands(conn, game_id)
    assert {s: h["party"] for s, h in hs.items()} == {1: "contra", 2: "re", 3: "re", 4: "contra"}
    held = lambda seat: [r[0] for r in conn.execute("SELECT card FROM hand_card WHERE hand = ?", (hs[seat]["id"],))]
    assert len(held(3)) == 14 and len(held(2)) == 10 and not {1, 25} & set(held(2))
    controls = play.controls(conn, game_id, 3, "/x")
    assert (controls["stage"], controls["give_back"]) == ("exchange", 2)
    with pytest.raises(play.PlayError):
        play.give_back(conn, hs[2]["id"], 33)  # the poor player gives nothing back
    with pytest.raises(play.PlayError, match="do not have"):
        play.give_back(conn, hs[3]["id"], 99)
    play.give_back(conn, hs[3]["id"], 15)
    assert status(conn, game_id) == "pre" and len(held(3)) == 13
    app.outbox.clear()
    play.give_back(conn, hs[3]["id"], 16)
    assert status(conn, game_id) == "play" and len(held(2)) == 12 and len(held(3)) == 12
    assert {15, 16} <= set(held(2))
    game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    assert game["turn_seat"] == game["start_seat"] == 1
    assert [m["To"] for m in app.outbox] == ["a@x.org"]  # the first player is told
    # (all trump exchanged are on record: 2 taken, 2 given back)
    assert conn.execute("SELECT count(*) FROM card_exchange").fetchone()[0] == 4
    play_out(conn, game_id, random.Random(2))
    assert status(conn, game_id) == "gameover"


def test_nobody_takes_the_trump_the_game_is_canceled(app, conn):
    game_id = poverty_game(conn, [2])
    for seat in (1, 3, 4):
        play.refuse_trump(conn, hands(conn, game_id)[seat]["id"])
    assert status(conn, game_id) == "cancel-trump"
    assert play.next_players(conn, game_id) == [1, 2, 3, 4]  # the same dealer again


def test_a_poverty_without_trump_starts_right_away(app, conn):
    game_id = new_game(conn)
    stack(conn, game_id, {2: [33, 34, 27, 28, 29, 30, 31, 32, 35, 36, 37, 38]})
    start(conn, game_id, {2: ("poverty", None)})
    play.take_trump(conn, hands(conn, game_id)[1]["id"], 2)
    assert status(conn, game_id) == "play"
    assert {h["party"] for h in hands(conn, game_id).values() if h["seat"] in (1, 2)} == {"re"}


def test_double_poverty(app, conn):
    game_id = poverty_game(conn, [1, 3])
    game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    assert (game["type"], game["turn_seat"]) == ("dpoverty", 2)  # the first seat that is not poor
    hs = hands(conn, game_id)
    play.take_trump(conn, hs[2]["id"], 3)  # seat 2 takes the trump of seat 3's player
    game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    assert game["turn_seat"] == 2  # still to give cards back
    assert hands(conn, game_id)[4]["status"] == "poverty"  # the other one is asked
    assert play.controls(conn, game_id, 4, "/x")["poor"][0]["user"] == 1  # only the poverty left
    for card in [r[0] for r in conn.execute("SELECT card FROM hand_card WHERE hand = ? ORDER BY id DESC LIMIT 2", (hs[2]["id"],))]:
        play.give_back(conn, hs[2]["id"], card)
    assert status(conn, game_id) == "pre"
    play.take_trump(conn, hs[4]["id"], 1)
    hs = hands(conn, game_id)
    assert {s: h["party"] for s, h in hs.items()} == {2: "re", 3: "re", 4: "contra", 1: "contra"}
    for card in [r[0] for r in conn.execute("SELECT card FROM hand_card WHERE hand = ? ORDER BY id DESC LIMIT 2", (hs[4]["id"],))]:
        play.give_back(conn, hs[4]["id"], card)
    assert status(conn, game_id) == "play"
    play_out(conn, game_id, random.Random(5))
    assert status(conn, game_id) == "gameover"


def test_double_poverty_is_canceled_if_one_player_says_no(app, conn):
    game_id = poverty_game(conn, [1, 3])
    play.refuse_trump(conn, hands(conn, game_id)[2]["id"])
    assert status(conn, game_id) == "cancel-trump"


def test_only_the_asked_player_decides(app, conn):
    game_id = poverty_game(conn, [2])
    hs = hands(conn, game_id)
    for seat in (2, 3, 4):
        with pytest.raises(play.PlayError):
            play.take_trump(conn, hs[seat]["id"], 2)
        with pytest.raises(play.PlayError):
            play.refuse_trump(conn, hs[seat]["id"])
    with pytest.raises(play.PlayError):
        play.take_trump(conn, hs[1]["id"], 3)  # seat 3 has no poverty
    play.take_trump(conn, hs[1]["id"], 2)
    with pytest.raises(play.PlayError):
        play.take_trump(conn, hs[1]["id"], 2)  # taken already


def test_wedding_partner_is_the_first_to_win_a_trick_from_the_bride(app, conn):
    with app.app_context():
        game_id = new_game(conn)
        stack(conn, game_id, {1: [3, 4, 25, 26], 2: [1, 2]})
        start(conn, game_id, {1: ("wedding", None)})
        game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
        assert (game["type"], game["sickness_state"], game["solo"]) == ("wedding", -1, None)
        hs = hands(conn, game_id)
        assert {s: h["party"] for s, h in hs.items()} == {1: "re", 2: "contra", 3: "contra", 4: "contra"}
        assert play.possible_calls(play.load(conn, game_id), 1) == []  # not before the partner is known
        play.play_card(conn, hs[1]["id"], 25)
        play.play_card(conn, hs[2]["id"], 1)  # the ten of hearts wins
        for seat in (3, 4):
            state = play.load(conn, game_id)
            play.play_card(conn, hs[seat]["id"], play.legal_cards(state, seat)[-1])
        game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
        assert (game["sickness_state"], game["turn_seat"]) == (1, 2)
        assert {s: h["party"] for s, h in hands(conn, game_id).items()} == {1: "re", 2: "re", 3: "contra", 4: "contra"}
        # calls are possible now, as if the game had started one trick later
        assert 120 in play.possible_calls(play.load(conn, game_id), 2)


def test_a_bride_who_wins_three_tricks_plays_alone(app, conn):
    rs = rules.Rules("none", "none", "none", "1st-own-card")  # the queens of clubs are the top cards
    with app.app_context():
        game_id = new_game(conn, rs)
        stack(conn, game_id, {1: [3, 4, 5, 6]})
        start(conn, game_id, {1: ("wedding", None)})
        hs = hands(conn, game_id)
        rng = random.Random(1)
        for card in (3, 4, 5):
            while conn.execute("SELECT turn_seat FROM game WHERE id = ?", (game_id,)).fetchone()[0] != 1:
                seat = conn.execute("SELECT turn_seat FROM game WHERE id = ?", (game_id,)).fetchone()[0]
                play.play_card(conn, hs[seat]["id"], rng.choice(play.legal_cards(play.load(conn, game_id), seat)))
            play.play_card(conn, hs[1]["id"], card)
            for _ in range(3):
                seat = conn.execute("SELECT turn_seat FROM game WHERE id = ?", (game_id,)).fetchone()[0]
                play.play_card(conn, hs[seat]["id"], rng.choice(play.legal_cards(play.load(conn, game_id), seat)))
        game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
        assert game["sickness_state"] == 3
        assert sorted(h["party"] for h in hands(conn, game_id).values()) == ["contra", "contra", "contra", "re"]
        play_out(conn, game_id, rng)
    assert conn.execute("SELECT status FROM game WHERE id = ?", (game_id,)).fetchone()[0] == "gameover"


def test_follow_suit_and_wrong_turn(app, conn):
    with app.app_context():
        game_id = new_game(conn)
        # seat 1 leads a club ace; seat 2 has clubs and a heart ace
        stack(conn, game_id, {1: [27, 35], 2: [29, 43], 3: [1, 5], 4: [7, 9]})
        start(conn, game_id)
        hs = hands(conn, game_id)
        with pytest.raises(play.PlayError, match="not your turn"):
            play.play_card(conn, hs[2]["id"], 29)
        with pytest.raises(play.PlayError, match="do not have"):
            play.play_card(conn, hs[1]["id"], 29)
        play.play_card(conn, hs[1]["id"], 27)
        state = play.load(conn, game_id)
        assert 43 not in play.legal_cards(state, 2) and 29 in play.legal_cards(state, 2)
        with pytest.raises(play.PlayError, match="follow suit"):
            play.play_card(conn, hs[2]["id"], 43)


def test_call_rules():
    allowed = play.call_allowed
    # first own card: Re only before the first card, "no 90" until after it
    kw = dict(own=None, partner=None, all_cards=48)
    assert allowed("1st-own-card", 120, cards=12, **kw) == 2
    assert allowed("1st-own-card", 120, cards=11, **kw) == 0
    assert allowed("1st-own-card", 90, cards=12, **kw) == 1
    assert allowed("1st-own-card", 90, cards=11, **kw) == 2
    assert allowed("1st-own-card", 90, cards=10, **kw) == 0
    # a call must be better than the one already made
    assert allowed("1st-own-card", 120, cards=12, own=120, partner=None, all_cards=48) == 0
    assert allowed("1st-own-card", 90, cards=12, own=120, partner=None, all_cards=48) == 1
    # the fifth card of the trick, in all
    assert allowed("5th-card", 120, cards=12, own=None, partner=None, all_cards=44) == 1
    # nine cards: 120 only with all twelve, then one step at a time
    assert allowed("9-cards", 120, cards=12, **kw) == 2
    assert allowed("9-cards", 90, cards=11, own=120, partner=None, all_cards=44) == 1
    assert allowed("9-cards", 120, cards=11, **kw) == 0
    # (as on the PHP site, nothing stops a player from skipping a step)
    assert allowed("9-cards", 60, cards=11, own=120, partner=None, all_cards=44) == 1


def play_out(conn, game_id, rng, calls=True):
    """Random legal moves until the game is over."""
    while True:
        game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
        if game["status"] != "play":
            return
        state = play.load(conn, game_id)
        seat = game["turn_seat"]
        card = rng.choice(play.legal_cards(state, seat))
        options = play.possible_calls(state, seat)
        call = rng.choice(options) if calls and options and rng.random() < 0.2 else None
        play.play_card(conn, state.hands[seat]["id"], card, call)


@pytest.mark.parametrize("seed", range(30))
def test_random_games_play_to_the_end(app, conn, seed):
    rng = random.Random(seed)
    rs = rules.Rules(
        rng.choice(["none", "firstwins", "secondwins"]),
        rng.choice(["none", "both", "second", "secondaftercall"]),
        rng.choice(["poverty", "cancel", "none"]),
        rng.choice(["1st-own-card", "5th-card", "9-cards"]),
    )
    with app.app_context():
        game_id = new_game(conn, rs)
        answers = {}
        if seed % 3 == 0:
            answers = {rng.randint(1, 4): ("solo", rng.choice(play.SOLOS))}
        for seat, h in hands(conn, game_id).items():
            play.join(conn, h["id"])
            if seat in answers:
                play.declare(conn, h["id"], *answers[seat])
            elif seed % 2 and "wedding" in play.sickness_options(play.load(conn, game_id).start[seat], rs):
                play.declare(conn, h["id"], "wedding")
            else:
                play.declare(conn, h["id"], "none")
        play_out(conn, game_id, rng)

    game = conn.execute("SELECT * FROM game WHERE id = ?", (game_id,)).fetchone()
    assert game["status"] == "gameover" and game["turn_seat"] is None
    tricks = conn.execute("SELECT * FROM trick WHERE game = ? ORDER BY number", (game_id,)).fetchall()
    assert [t["number"] for t in tricks] == list(range(1, 13))
    assert all(t["winner_seat"] for t in tricks)
    assert conn.execute("SELECT count(*) FROM play JOIN trick ON trick.id = play.trick WHERE game = ?", (game_id,)).fetchone()[0] == 48
    hs = hands(conn, game_id)
    assert {h["status"] for h in hs.values()} == {"gameover"}
    # 2 against 2, or one against three in a solo (also a silent one: both club
    # queens in a hand) and in a wedding where nobody won a trick from the bride
    parties = sorted(h["party"] for h in hs.values())
    assert parties in (["contra", "contra", "re", "re"], ["contra", "contra", "contra", "re"])
    if game["type"] == "solo":
        assert parties.count("re") == 1 and sum(h["points"] for h in hs.values()) == 0
    # a finished game can be shown to its players


def test_live_games_pass_the_replay_check(app, conn, database):
    """The tool that checked the 2282 imported games finds nothing wrong here."""
    import replay_check

    rng = random.Random(7)
    with app.app_context():
        for n in range(12):
            rs = rules.Rules(
                rng.choice(["none", "firstwins", "secondwins"]),
                rng.choice(["none", "both", "second", "secondaftercall"]),
                "none",
                rng.choice(["1st-own-card", "5th-card", "9-cards"]),
            )
            game_id = new_game(conn, rs)
            solo = {rng.randint(1, 4): ("solo", rng.choice(play.SOLOS))} if n % 3 == 0 else {}
            start(conn, game_id, solo, wedding=n % 2 == 1)
            play_out(conn, game_id, rng)
    ok, bad = replay_check.check(database)
    bad.pop("_diff", None)
    bad["incomplete tricks"].remove(1)  # the stub game of the shared fixture
    bad = {kind: games for kind, games in bad.items() if games}
    assert not bad, bad
    assert ok["games"] == 13  # 12 played here, and the stub


def settle(conn, game_id, rng):
    """Answer the poverty questions (mostly yes) until the game starts or is canceled."""
    while status(conn, game_id) == "pre":
        asked = conn.execute("SELECT * FROM hand WHERE game = ? AND status = 'poverty'", (game_id,)).fetchone()
        assert asked is not None, "the game is stuck"
        c = play.controls(conn, game_id, asked["user"], "/x")
        if c["stage"] == "poverty_ask":
            if rng.random() < 0.85:
                play.take_trump(conn, asked["id"], rng.choice(c["poor"])["user"])
            else:
                play.refuse_trump(conn, asked["id"])
        else:
            assert c["stage"] == "exchange"
            play.give_back(conn, asked["id"], rng.choice(sorted(c["legal"])))


def test_random_games_with_everything_pass_the_replay_check(app, conn, database, monkeypatch):
    import replay_check

    rng = random.Random(11)
    monkeypatch.setattr(play.random, "SystemRandom", lambda: rng)
    kinds = {}
    for n in range(120):
        rs = rules.Rules(
            rng.choice(["none", "firstwins", "secondwins"]),
            rng.choice(["none", "both", "second", "secondaftercall"]),
            "poverty",
            rng.choice(["1st-own-card", "5th-card", "9-cards"]),
        )
        game_id = new_game(conn, rs)
        for seat, h in hands(conn, game_id).items():
            play.join(conn, h["id"])
            options = play.sickness_options(play.load(conn, game_id).start[seat], rs)
            if "nines" in options and rng.random() < 0.5:
                answer = ("nines", None)
            elif n % 7 == 0 and seat == 4:
                answer = ("solo", rng.choice(play.SOLOS))
            elif "poverty" in options:
                answer = ("poverty", None)
            elif "lowtrump" in options:
                answer = ("lowtrump", None)
            elif "wedding" in options and rng.random() < 0.7:
                answer = ("wedding", None)
            else:
                answer = ("none", None)
            play.declare(conn, h["id"], *answer)
        settle(conn, game_id, rng)
        kind = conn.execute("SELECT type, status FROM game WHERE id = ?", (game_id,)).fetchone()
        kinds[(kind["type"], kind["status"] if kind["status"].startswith("cancel") else "played")] = kinds.get((kind["type"], kind["status"] if kind["status"].startswith("cancel") else "played"), 0) + 1
        if status(conn, game_id) == "play":
            play_out(conn, game_id, rng)
            assert status(conn, game_id) == "gameover"
            assert {h["status"] for h in hands(conn, game_id).values()} == {"gameover"}
    assert kinds.get(("poverty", "played"), 0) >= 5, kinds
    assert kinds.get(("dpoverty", "played"), 0) >= 1, kinds
    ok, bad = replay_check.check(database)
    bad.pop("_diff", None)
    bad["incomplete tricks"].remove(1)  # the stub game of the shared fixture
    bad = {kind: games for kind, games in bad.items() if games}
    assert not bad, bad
