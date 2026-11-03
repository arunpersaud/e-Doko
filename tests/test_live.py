"""Live mode: the button, the poll, held turn mails, the dots (see edoko/live.py)."""

from contextlib import closing

import pytest

from edoko import db, live, play, rules

RS = rules.Rules("secondwins", "second", "poverty", "1st-own-card")
HX = {"HX-Request": "true"}


@pytest.fixture
def conn(app, database):
    with app.app_context(), closing(db.connect(database)) as c:
        c.execute("UPDATE deal SET cards = ? WHERE id = 1", (":".join(map(str, range(1, 49))),))
        for i in range(2, 5):
            c.execute("INSERT INTO user (id, name, email) VALUES (?, ?, ?)", (i, f"User{i}", f"u{i}@x.org"))
        yield c


@pytest.fixture
def now(monkeypatch):
    """A clock the tests can move."""
    t = [1_800_000_000]
    monkeypatch.setattr(live, "clock", lambda: t[0])
    return t


def started(conn):
    """A game in play, seat 2 on turn; returns the game id and the hands by seat."""
    game_id = play.create_game(conn, [1, 2, 3, 4], RS)
    conn.execute("UPDATE game SET status = 'play', turn_seat = 2 WHERE id = ?", (game_id,))
    conn.execute("UPDATE hand SET status = 'play' WHERE game = ?", (game_id,))
    hands = {h["seat"]: h for h in conn.execute("SELECT * FROM hand WHERE game = ?", (game_id,))}
    return game_id, hands


def hand(conn, hand_id):
    return conn.execute("SELECT * FROM hand WHERE id = ?", (hand_id,)).fetchone()


def held(conn):
    return conn.execute("SELECT count(*) FROM turn_hold").fetchone()[0]


def turn_mails(app, address):
    return [m for m in app.outbox if m["To"] == address and "your turn" in m["Subject"]]


def get(app, token, query="", headers=HX):
    return app.test_client().get(f"/{token}{query}", headers=headers)


def test_the_button_starts_live_mode_but_only_for_the_pages_own_requests(app, conn, now):
    game_id, hands = started(conn)
    token = hands[2]["token"]
    page = get(app, token, headers={}).get_data(as_text=True)
    assert "Live: off" in page and "data-live-poller" not in page
    # a reloaded or shared address with ?live=on is never live
    assert "Live: on" not in get(app, token, "?live=on", headers={}).get_data(as_text=True)
    assert hand(conn, hands[2]["id"])["live_until"] is None
    page = get(app, token, "?live=on").get_data(as_text=True)
    assert "Live: on" in page and "data-live-poller" in page
    row = hand(conn, hands[2]["id"])
    assert row["live_until"] == now[0] + live.HEARTBEAT_SECONDS
    assert row["live_expires"] == now[0] + live.CAP_MINUTES * 60
    # off again: a new load is off, nothing is held
    page = get(app, token, "?live=off").get_data(as_text=True)
    assert "Live: off" in page and hand(conn, hands[2]["id"])["live_until"] is None


def test_a_poll_answers_204_until_something_changes(app, conn, now):
    game_id, hands = started(conn)
    token = hands[2]["token"]
    page = get(app, token, "?live=on").get_data(as_text=True)
    version = live.version(conn, game_id)
    assert f"?live={version}" in page
    now[0] += 10
    answer = get(app, token, f"?live={version}")
    assert answer.status_code == 204 and answer.get_data() == b""
    assert hand(conn, hands[2]["id"])["live_until"] == now[0] + live.HEARTBEAT_SECONDS  # a heartbeat
    conn.execute("INSERT INTO comment (game, kind, user, body) VALUES (?, 'pre', 1, 'hello')", (game_id,))
    answer = get(app, token, f"?live={version}")
    assert answer.status_code == 200 and "hello" in answer.get_data(as_text=True)


def test_live_mode_runs_out_and_the_poll_says_so(app, conn, now):
    game_id, hands = started(conn)
    token = hands[2]["token"]
    get(app, token, "?live=on")
    now[0] += live.CAP_MINUTES * 60 + 1
    page = get(app, token, f"?live={live.version(conn, game_id)}").get_data(as_text=True)
    assert "Live: off" in page and "data-live-poller" not in page


def test_what_the_player_does_renews_live_mode(app, conn):
    game_id, hands = started(conn)
    hid = hands[2]["id"]
    live.start(conn, hid)
    before = hand(conn, hid)["live_expires"]
    conn.execute("UPDATE hand SET live_expires = live_expires - 600 WHERE id = ?", (hid,))
    live.touch(conn, hid)
    assert hand(conn, hid)["live_expires"] >= before
    # but not once it has run out
    conn.execute("UPDATE hand SET live_expires = 1 WHERE id = ?", (hid,))
    live.touch(conn, hid)
    assert hand(conn, hid)["live_expires"] == 1


def test_a_turn_mail_for_a_live_player_is_held(app, conn, now):
    game_id, hands = started(conn)
    live.start(conn, hands[2]["id"])
    app.outbox.clear()
    play.notify(conn, [("turn", game_id, False)])
    assert not app.outbox and held(conn) == 1
    # the others are not affected
    conn.execute("UPDATE game SET turn_seat = 3 WHERE id = ?", (game_id,))
    play.notify(conn, [("turn", game_id, False)])
    assert len(turn_mails(app, "u3@x.org")) == 1 and held(conn) == 0  # (the old hold is out of date)


def test_a_held_mail_goes_out_when_the_heartbeat_stops(app, conn, now):
    game_id, hands = started(conn)
    live.start(conn, hands[2]["id"])
    app.outbox.clear()
    play.notify(conn, [("turn", game_id, False)])
    now[0] += 20  # (the page is still there)
    assert live.release(conn, game_id) == 0 and not app.outbox
    now[0] += 30  # the heartbeat is over
    assert live.release(conn, game_id) == 1
    assert len(turn_mails(app, "u2@x.org")) == 1 and held(conn) == 0
    assert live.release(conn, game_id) == 0  # (only once)


def test_a_held_mail_goes_out_after_the_grace_even_if_the_page_stays_open(app, conn, now):
    game_id, hands = started(conn)
    hid = hands[2]["id"]
    live.start(conn, hid)
    app.outbox.clear()
    play.notify(conn, [("turn", game_id, False)])
    for _ in range(live.GRACE_MINUTES * 6 - 1):  # polls every 10 s, nobody moves
        now[0] += 10
        assert live.beat(conn, hid)
        assert live.release(conn, game_id) == 0
    now[0] += 10
    assert live.beat(conn, hid)
    assert live.release(conn, game_id) == 1 and len(turn_mails(app, "u2@x.org")) == 1


def test_switching_live_mode_off_sends_the_held_mail(app, conn, now):
    game_id, hands = started(conn)
    live.start(conn, hands[2]["id"])
    app.outbox.clear()
    play.notify(conn, [("turn", game_id, True)])
    live.stop(conn, hands[2]["id"])
    assert len(turn_mails(app, "u2@x.org")) == 1 and "starts, and you play the first card" in app.outbox[0].get_content()


def test_a_held_mail_is_dropped_when_it_is_not_your_turn_any_more(app, conn, now):
    game_id, hands = started(conn)
    live.start(conn, hands[2]["id"])
    app.outbox.clear()
    play.notify(conn, [("turn", game_id, False)])
    conn.execute("UPDATE game SET turn_seat = 3 WHERE id = ?", (game_id,))  # (moved without a notice)
    now[0] += 3600
    assert live.release(conn, game_id) == 0 and not app.outbox and held(conn) == 0


@pytest.mark.parametrize("change", ["mail_mode = 'fewer'", "digest_hours = 12", "mail_status = 'bounced'"])
def test_only_mail_that_would_be_sent_is_held(app, conn, now, change):
    game_id, hands = started(conn)
    live.start(conn, hands[2]["id"])
    conn.execute(f"UPDATE user SET {change} WHERE id = 2")
    assert held(conn) == 0
    play.notify(conn, [("turn", game_id, False)])
    assert held(conn) == 0  # (not held: it is sent, queued or dropped as without live mode)


def test_the_dots_show_who_is_live(app, conn, now):
    game_id, hands = started(conn)
    live.start(conn, hands[3]["id"])
    page = get(app, hands[2]["token"], headers={}).get_data(as_text=True)
    assert 'aria-label="User3 is live"' in page and "User3 is watching this game live" in page
    assert "1 of 4 players is live" in page and 'aria-label="User2 is live"' not in page
    now[0] += live.HEARTBEAT_SECONDS + 1  # User3 closed the tab
    assert 'is live"' not in get(app, hands[2]["token"], headers={}).get_data(as_text=True)
    assert "0 of 4 players are live" in get(app, hands[2]["token"], headers={}).get_data(as_text=True)


def test_there_is_no_live_button_for_a_finished_game(app, conn):
    game_id, hands = started(conn)
    conn.execute("UPDATE game SET status = 'gameover', turn_seat = NULL WHERE id = ?", (game_id,))
    page = get(app, hands[2]["token"], "?live=on").get_data(as_text=True)
    assert "Live: o" not in page and "data-live-poller" not in page


def test_the_timer_command_releases_held_mails(app, conn, now):
    game_id, hands = started(conn)
    live.start(conn, hands[2]["id"])
    app.outbox.clear()
    play.notify(conn, [("turn", game_id, False)])
    now[0] += 600
    runner = app.test_cli_runner()
    result = runner.invoke(args=["edoko", "release-holds"])
    assert result.exit_code == 0 and "sent 1 held mails" in result.output
    assert len(turn_mails(app, "u2@x.org")) == 1
