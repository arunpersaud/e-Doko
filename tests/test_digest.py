from datetime import UTC, datetime

import pytest

from edoko import digest, play
from test_play import RS, conn, hands, new_game, start  # noqa: F401 (conn is a fixture)


def at(hour, day=1):
    """A time in winter (London is on UTC then)."""
    return datetime(2026, 12, day, hour, 0, tzinfo=UTC)


def queue(conn, user):
    return conn.execute("SELECT * FROM mail_queue WHERE user = ? ORDER BY id", (user,)).fetchall()


def test_game_mails_wait_in_the_queue_for_a_digest(app, conn):
    conn.execute("UPDATE user SET digest_hours = 24 WHERE id = 2")
    new_game(conn)
    assert sorted(m["To"] for m in app.outbox) == ["a@x.org", "u3@x.org", "u4@x.org"]
    (row,) = queue(conn, 2)
    assert (row["kind"], "invited" in row["subject"]) == ("other", True)
    assert "Hello User2" in row["body"] and "/" in row["body"]


def test_bounced_users_and_users_without_mail_get_no_queue(app, conn):
    conn.execute("UPDATE user SET digest_hours = 24, mail_status = 'bounced' WHERE id = 2")
    conn.execute("UPDATE user SET digest_hours = 24, mail_mode = 'none' WHERE id = 3")
    new_game(conn)
    assert queue(conn, 2) == [] and queue(conn, 3) == []


@pytest.mark.parametrize(
    "every, tz, hours_due",
    [
        (24, "Europe/London", [18]),
        (12, "Europe/London", [6, 18]),
        (6, "Europe/London", [0, 6, 12, 18]),
        (1, "Europe/London", list(range(24))),
        (24, "America/Los_Angeles", [2]),  # 18:00 there is 02:00 UTC (December)
        (24, "Nowhere/Land", [18]),  # an unknown zone counts as UTC
    ],
)
def test_the_hours_a_digest_is_due(every, tz, hours_due):
    user = {"digest_hours": every, "timezone": tz}
    assert [h for h in range(24) if digest.is_due(user, at(h))] == hours_due


def test_the_digest_combines_the_mails_and_empties_the_queue(app, conn):
    conn.execute("UPDATE user SET digest_hours = 24 WHERE id = 1")
    game_id = new_game(conn)
    start(conn, game_id)  # seat 1 starts: "your turn" for user 1 is queued
    kinds = [r["kind"] for r in queue(conn, 1)]
    assert kinds == ["other", "turn"]
    app.outbox.clear()
    assert digest.run(conn, at(17)) == 0  # not the hour
    assert digest.run(conn, at(18)) == 1
    (message,) = app.outbox
    assert message["To"] == "a@x.org" and "Digest" in message["Subject"]
    body = message.get_content()
    assert "invited to game" in body and "it is your turn" in body and body.count(digest.SEPARATOR) == 3
    assert queue(conn, 1) == []
    assert digest.run(conn, at(18)) == 0  # nothing left


def test_a_turn_mail_is_dropped_when_it_is_not_your_turn_any_more(app, conn):
    conn.execute("UPDATE user SET digest_hours = 24 WHERE id = 1")
    game_id = new_game(conn)
    start(conn, game_id)
    hs = hands(conn, game_id)
    state = play.load(conn, game_id)
    play.play_card(conn, hs[1]["id"], play.legal_cards(state, 1)[0])  # seat 2 is on turn now
    app.outbox.clear()
    digest.run(conn, at(18))
    body = app.outbox[0].get_content()
    assert "invited to game" in body and "it is your turn" not in body


def test_what_is_left_after_turning_the_digest_off_goes_out_at_the_next_run(app, conn):
    conn.execute("UPDATE user SET digest_hours = 24 WHERE id = 2")
    new_game(conn)
    conn.execute("UPDATE user SET digest_hours = NULL WHERE id = 2")
    app.outbox.clear()
    assert digest.run(conn, at(11)) == 1  # any hour
    assert app.outbox[0]["To"] == "u2@x.org" and queue(conn, 2) == []


def test_fewer_mails_means_no_turn_mails(app, conn):
    conn.execute("UPDATE user SET mail_mode = 'fewer' WHERE id = 1")
    game_id = new_game(conn)
    assert any(m["To"] == "a@x.org" for m in app.outbox)  # the invitation still comes
    app.outbox.clear()
    start(conn, game_id)
    assert not any(m["To"] == "a@x.org" for m in app.outbox)  # seat 1 starts, but no mail
    assert any(m["To"] == "u2@x.org" for m in app.outbox) is False  # (nobody else is on turn)


def test_autosetup_users_are_not_asked_whether_they_want_to_play(app, conn):
    conn.execute("UPDATE user SET autosetup = 1 WHERE id IN (1, 2)")
    game_id = new_game(conn)
    hs = hands(conn, game_id)
    assert [hs[s]["status"] for s in (1, 2, 3, 4)] == ["init", "init", "start", "start"]
    assert conn.execute("SELECT turn_seat FROM game WHERE id = ?", (game_id,)).fetchone()[0] == 3
    assert play.controls(conn, game_id, 1, "/x")["stage"] == "declare"
    assert play.controls(conn, game_id, 3, "/x")["stage"] == "join"


def logged_in(app, user_id):
    client = app.test_client()
    with client.session_transaction() as session:
        session["user_id"] = user_id
        session["csrf"] = "t"
    return client


def test_the_preferences_form(app, conn):
    client = logged_in(app, 1)
    page = client.get("/account").get_data(as_text=True)
    assert "Games and mail" in page and 'value="Europe/London"' in page
    data = {"csrf": "t", "language": "de", "timezone": "Europe/Berlin", "mail_mode": "fewer",
            "digest_hours": "12", "autosetup": "on"}
    assert "Your settings were saved." in client.post("/account/preferences", data=data, follow_redirects=True).get_data(as_text=True)
    user = conn.execute("SELECT * FROM user WHERE id = 1").fetchone()
    assert (user["language"], user["timezone"], user["mail_mode"], user["digest_hours"]) == ("de", "Europe/Berlin", "fewer", 12)
    assert (user["autosetup"], user["open_for_games"]) == (1, 0)  # a box that is not ticked is off
    # back to the defaults
    data = {"csrf": "t", "language": "", "timezone": "Europe/London", "mail_mode": "normal",
            "digest_hours": "", "open_for_games": "on"}
    client.post("/account/preferences", data=data)
    user = conn.execute("SELECT * FROM user WHERE id = 1").fetchone()
    assert (user["language"], user["digest_hours"], user["open_for_games"], user["autosetup"]) == (None, None, 1, 0)


@pytest.mark.parametrize("bad", [{"timezone": "Nowhere/Land"}, {"mail_mode": "all"}, {"digest_hours": "5"}, {"language": "fr"}])
def test_the_preferences_form_refuses_nonsense(app, conn, bad):
    data = {"csrf": "t", "language": "", "timezone": "Europe/London", "mail_mode": "normal", "digest_hours": "", **bad}
    page = logged_in(app, 1).post("/account/preferences", data=data, follow_redirects=True).get_data(as_text=True)
    assert "nothing was saved" in page
