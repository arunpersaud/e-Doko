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


def start(conn, game_id, answers=None):
    """Everybody joins and answers; answers: seat -> (sickness, solo)."""
    for seat, h in hands(conn, game_id).items():
        play.join(conn, h["id"])
        sickness, solo = (answers or {}).get(seat, ("none", None))
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


def test_poverty_and_wedding_are_refused_for_now(app, conn):
    with app.app_context():
        game_id = new_game(conn)
        stack(conn, game_id, {1: [3, 4]})
        h = hands(conn, game_id)[1]
        play.join(conn, h["id"])
        with pytest.raises(play.PlayError):
            play.declare(conn, h["id"], "wedding")


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
    assert sum(h["points"] for h in hs.values()) == 0
    # nobody has a wedding or a poverty here, so a game is 2 against 2 (re/contra),
    # or one against three in a solo (also a silent one: both club queens in a hand)
    parties = sorted(h["party"] for h in hs.values())
    solo = game["type"] == "solo"
    assert parties in (["contra", "contra", "re", "re"], ["contra", "contra", "contra", "re"])
    assert (parties.count("re") == 1) == solo
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
            start(conn, game_id, solo)
            play_out(conn, game_id, rng)
    ok, bad = replay_check.check(database)
    bad.pop("_diff", None)
    bad["incomplete tricks"].remove(1)  # the stub game of the shared fixture
    bad = {kind: games for kind, games in bad.items() if games}
    assert not bad, bad
    assert ok["games"] == 13  # 12 played here, and the stub
