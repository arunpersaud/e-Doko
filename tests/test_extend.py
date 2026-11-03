"""Going on at a table: rounds, and the proposal that all players have to agree to."""

from datetime import UTC, datetime
from contextlib import closing

import pytest

from edoko import db, extend, play

from test_play import RS
from test_tables import finish_game, logged_in, users  # noqa: F401  (fixtures and helpers)
from test_tables import conn  # noqa: F401


def session_of(conn, game_id):
    return conn.execute("SELECT session FROM game WHERE id = ?", (game_id,)).fetchone()[0]


def max_games(conn, session):
    return conn.execute("SELECT max_games FROM session WHERE id = ?", (session,)).fetchone()[0]


def table_in_its_last_game(conn, games=2):
    """A table of `games` games with all but the last played; returns (session, last game)."""
    game = play.new_table(conn, 1, [2, 3, 4], RS, max_games=games)
    session = session_of(conn, game)
    for n in range(games - 1):
        finish_game(conn, game, seed=n + 1)
        game = play.next_game(conn, game, 1)
    return session, game


def mails(app, subject_part):
    return [m for m in app.outbox if subject_part in m["Subject"]]


def test_solos_do_not_count_for_the_length_of_a_table(app, conn):
    first = play.new_table(conn, 1, [2, 3, 4], RS, max_games=1)
    session = session_of(conn, first)
    finish_game(conn, first, answers={2: ("solo", "queen")})
    state = play.table_state(conn, session)
    assert (state["played"], state["closed"]) == (0, False)  # (the same dealer goes on)
    second = play.next_game(conn, first, 1)
    finish_game(conn, second, seed=2)
    assert play.table_state(conn, session)["closed"]


def test_a_proposal_needs_the_last_game_of_the_table(app, conn):
    game = play.new_table(conn, 1, [2, 3, 4], RS, max_games=4)
    session = session_of(conn, game)
    with pytest.raises(play.PlayError, match="last game"):
        extend.propose(conn, session, 1, 1)
    open_table = play.new_table(conn, 1, [2, 3, 4], RS)
    with pytest.raises(play.PlayError, match="no end"):
        extend.propose(conn, session_of(conn, open_table), 1, 1)
    last_session, last = table_in_its_last_game(conn, 2)
    for rounds in (0, extend.MAX_ROUNDS + 1):
        with pytest.raises(play.PlayError, match="between"):
            extend.propose(conn, last_session, 1, rounds)
    with pytest.raises(play.PlayError, match="players of the table"):
        extend.propose(conn, last_session, 5, 1)


def test_everybody_agrees_and_the_table_goes_on(app, conn):
    session, last = table_in_its_last_game(conn, 2)
    app.outbox.clear()
    extend.propose(conn, session, 1, 2)
    assert sorted(m["To"] for m in mails(app, "proposes more rounds")) == ["u2@x.org", "u3@x.org", "u4@x.org"]
    with pytest.raises(play.PlayError, match="already"):
        extend.propose(conn, session, 2, 1)  # one proposal at a time
    with pytest.raises(play.PlayError, match="already"):
        extend.vote(conn, session, 1, True)  # (the proposer has agreed)
    assert extend.vote(conn, session, 2, True) == "waiting"
    with pytest.raises(play.PlayError, match="already"):
        extend.vote(conn, session, 2, False)
    assert extend.vote(conn, session, 3, True) == "waiting" and max_games(conn, session) == 2
    app.outbox.clear()
    assert extend.vote(conn, session, 4, True) == "accepted"
    assert max_games(conn, session) == 2 + 8  # two rounds of four games
    assert sorted(m["To"] for m in mails(app, "goes on")) == ["a@x.org", "u2@x.org", "u3@x.org", "u4@x.org"]
    # the table was not over yet: it just does not end after this game
    finish_game(conn, last, seed=5)
    assert not play.table_state(conn, session)["closed"] and play.next_players(conn, last)
    with pytest.raises(play.PlayError, match="no proposal"):
        extend.vote(conn, session, 2, True)


def test_one_no_ends_the_proposal_and_nobody_is_named(app, conn):
    session, last = table_in_its_last_game(conn, 2)
    extend.propose(conn, session, 1, 1)
    app.outbox.clear()
    extend.vote(conn, session, 2, True)
    assert extend.vote(conn, session, 3, False) == "declined"
    assert max_games(conn, session) == 2
    told = mails(app, "not everybody agreed")
    assert [m["To"] for m in told] == ["a@x.org"]  # only the proposer
    assert not any(name in told[0].get_content() for name in ("User2", "User3", "User4"))
    assert extend.open_proposal(conn, session) is None
    extend.propose(conn, session, 4, 3)  # anybody may try again
    assert extend.open_proposal(conn, session)["rounds"] == 3


def test_an_unanswered_proposal_lapses(app, conn):
    session, last = table_in_its_last_game(conn, 2)
    extend.propose(conn, session, 1, 1)
    conn.execute("UPDATE table_extension SET created_at = datetime('now', '-15 days')")
    assert extend.open_proposal(conn, session) is None
    assert conn.execute("SELECT outcome FROM table_extension").fetchone()[0] == "expired"
    extend.propose(conn, session, 2, 1)


def test_going_on_after_the_table_is_finished(app, conn):
    session, last = table_in_its_last_game(conn, 2)
    finish_game(conn, last, seed=7)
    assert play.table_state(conn, session)["closed"] and play.next_players(conn, last) is None
    extend.propose(conn, session, 3, 1)
    for user in (1, 2):
        extend.vote(conn, session, user, True)
    extend.vote(conn, session, 4, True)
    assert not play.table_state(conn, session)["closed"]
    assert play.next_players(conn, last) is not None
    play.next_game(conn, last, 1)


def test_the_pages_show_the_proposal_and_take_the_answers(app, conn):
    session, last = table_in_its_last_game(conn, 2)
    ann, bob = logged_in(app, 1), logged_in(app, 2)
    page = ann.get(f"/game/{last}/").get_data(as_text=True)
    assert "propose more rounds" in page
    assert "propose more rounds" in ann.get(f"/table/{session}/").get_data(as_text=True)
    response = ann.post(f"/game/{last}/extend", data={"csrf": "t", "rounds": "2"}, follow_redirects=True)
    assert "Your proposal has been sent" in response.get_data(as_text=True)
    assert "Waiting for 3 players" in ann.get(f"/table/{session}/").get_data(as_text=True)
    page = bob.get(f"/game/{last}/").get_data(as_text=True)
    assert "Ann proposes 2 more rounds" in page and ">Agree<" in page and ">Decline<" in page
    assert "propose more rounds" not in page  # (one proposal at a time)
    table = bob.get(f"/table/{session}/").get_data(as_text=True)
    assert "Ann proposes 2 more rounds" in table
    response = bob.post(f"/game/{last}/extend-yes", data={"csrf": "t"}, follow_redirects=True)
    assert "Thank you, you agreed" in response.get_data(as_text=True)
    assert ">Agree<" not in bob.get(f"/game/{last}/").get_data(as_text=True)
    cy = logged_in(app, 3)
    response = cy.post(f"/game/{last}/extend-no", data={"csrf": "t"}, follow_redirects=True)
    assert "the proposal is off" in response.get_data(as_text=True)
    assert "Not everybody agreed" in ann.get(f"/game/{last}/").get_data(as_text=True)
    # somebody who is not at the table can neither see nor answer anything
    outsider = logged_in(app, 5)
    assert outsider.post(f"/game/{last}/extend", data={"csrf": "t", "rounds": "1"}).status_code == 404


def test_the_seat_link_can_answer_too(app, conn):
    session, last = table_in_its_last_game(conn, 2)
    token = {r["user"]: r["token"] for r in conn.execute("SELECT user, token FROM hand WHERE game = ?", (last,))}
    client = app.test_client()
    page = client.get(f"/{token[1]}").get_data(as_text=True)
    csrf_value = page.split('name="csrf" value="')[1].split('"')[0]
    response = client.post(f"/{token[1]}/extend", data={"csrf": csrf_value, "rounds": "1"}, follow_redirects=True)
    assert "Your proposal has been sent" in response.get_data(as_text=True)
    assert extend.open_proposal(conn, session)["rounds"] == 1


def test_a_proposal_changes_the_page_version_so_live_pages_show_it(app, conn):
    from edoko import live

    session, last = table_in_its_last_game(conn, 2)
    before = live.version(conn, last)
    extend.propose(conn, session, 1, 1)
    proposed = live.version(conn, last)
    extend.vote(conn, session, 2, True)
    answered = live.version(conn, last)
    assert len({before, proposed, answered}) == 3
