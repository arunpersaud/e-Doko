import random
import re
from contextlib import closing

import pytest

from edoko import db, play, rules

from test_play import RS, hands, new_game, play_out, stack, start


@pytest.fixture
def conn(app, database):
    with app.app_context(), closing(db.connect(database)) as c:
        c.execute("UPDATE deal SET cards = ? WHERE id = 1", (":".join(map(str, range(1, 49))),))
        for i in range(2, 7):
            c.execute("INSERT INTO user (id, name, email) VALUES (?, ?, ?)", (i, f"User{i}", f"u{i}@x.org"))
        yield c


def users(conn, game_id):
    return [r[0] for r in conn.execute("SELECT user FROM hand WHERE game = ? ORDER BY seat", (game_id,))]


def finish_game(conn, game_id, seed=1, answers=None):
    start(conn, game_id, answers)
    play_out(conn, game_id, random.Random(seed))


def test_next_game_rotates_the_seats(app, conn):
    first = new_game(conn)
    assert play.next_players(conn, first) is None  # not finished
    finish_game(conn, first)
    assert play.next_players(conn, first) == [2, 3, 4, 1]
    second = play.next_game(conn, first, 3)
    assert users(conn, second) == [2, 3, 4, 1]
    sessions = {r[0] for r in conn.execute("SELECT session FROM game WHERE id IN (?, ?)", (first, second))}
    assert len(sessions) == 1  # the same table
    assert play.next_players(conn, first) is None  # only the last game of a table goes on
    with pytest.raises(play.PlayError):
        play.next_game(conn, second, 1)  # a game is still going on


def test_the_same_dealer_after_a_solo_or_a_cancelled_game(app, conn):
    solo = new_game(conn)
    finish_game(conn, solo, answers={2: ("solo", "queen")})
    assert play.next_players(conn, solo) == [1, 2, 3, 4]
    silent = new_game(conn, players=(1, 2, 3, 4))
    stack(conn, silent, {2: [3, 4]})
    finish_game(conn, silent)  # both club queens, no wedding: a silent solo
    assert conn.execute("SELECT solo FROM game WHERE id = ?", (silent,)).fetchone()[0] == "silent"
    assert play.next_players(conn, silent) == [2, 3, 4, 1]
    nines = new_game(conn)
    stack(conn, nines, {1: [25, 26, 33, 34, 41]})
    start(conn, nines, {1: ("nines", None)})
    assert conn.execute("SELECT status FROM game WHERE id = ?", (nines,)).fetchone()[0] == "cancel-nines"
    assert play.next_players(conn, nines) == [1, 2, 3, 4]
    declined = new_game(conn)
    play.decline(conn, hands(conn, declined)[1]["id"])
    assert play.next_players(conn, declined) is None


def test_a_table_ends_after_its_games_and_has_a_winner(app, conn):
    first = play.new_table(conn, 1, [2, 3, 4], RS, max_games=2)
    session = conn.execute("SELECT session FROM game WHERE id = ?", (first,)).fetchone()[0]
    assert conn.execute("SELECT max_games FROM session WHERE id = ?", (session,)).fetchone()[0] == 2
    finish_game(conn, first, seed=1)
    state = play.table_state(conn, session)
    assert (state["played"], state["closed"], state["winners"]) == (1, False, [])
    second = play.next_game(conn, first, 1)
    finish_game(conn, second, seed=2)
    state = play.table_state(conn, session)
    assert (state["played"], state["closed"]) == (2, True)
    assert sum(s["points"] for s in state["standings"]) == 0
    assert state["winners"] and all(
        s["points"] == state["standings"][0]["points"] for s in state["standings"] if s["name"] in state["winners"]
    )
    assert play.next_players(conn, second) is None
    with pytest.raises(play.PlayError, match="finished"):
        play.create_game(conn, users(conn, second), session=session)
    again = play.same_table_again(conn, session, 2)
    assert sorted(users(conn, again)) == [1, 2, 3, 4]
    new_session = conn.execute("SELECT session FROM game WHERE id = ?", (again,)).fetchone()[0]
    assert new_session != session
    assert conn.execute("SELECT max_games FROM session WHERE id = ?", (new_session,)).fetchone()[0] == 2
    with pytest.raises(play.PlayError):
        play.same_table_again(conn, new_session, 1)  # not finished


def test_new_table_checks_its_input(app, conn):
    for others in ([2, 3], [2, 2, 3], [1, 2, 3]):
        with pytest.raises(play.PlayError):
            play.new_table(conn, 1, others, RS)
    with pytest.raises(play.PlayError):
        play.new_table(conn, 1, [2, 3, 4], rules.Rules("secondwins", "x", "cancel", "1st-own-card"))
    with pytest.raises(play.PlayError):
        play.new_table(conn, 1, [2, 3, 4], RS, max_games=0)


def logged_in(app, user_id):
    client = app.test_client()
    with client.session_transaction() as session:
        session["user_id"] = user_id
        session["csrf"] = "t"
    return client


def test_the_new_table_form(app, conn):
    client = logged_in(app, 1)
    page = client.get("/new").get_data(as_text=True)
    assert "Select the players" in page and "User2" in page and ">Ann<" not in page
    data = {"csrf": "t", "player": ["2", "3", "4"], "dullen": "secondwins", "schweinchen": "both",
            "call": "9-cards", "lowtrump": "cancel", "rounds": "3"}
    response = client.post("/new", data=data, follow_redirects=True)
    assert response.status_code == 200
    html = response.get_data(as_text=True)
    assert "Do you want to play this game?" in html
    game = conn.execute("SELECT game.*, ruleset.call, ruleset.schweinchen FROM game JOIN ruleset ON ruleset.id = game.ruleset ORDER BY game.id DESC").fetchone()
    assert (game["call"], game["schweinchen"]) == ("9-cards", "both")
    assert sorted(users(conn, game["id"])) == [1, 2, 3, 4]
    # three rounds are twelve games
    assert conn.execute("SELECT max_games FROM session WHERE id = ?", (game["session"],)).fetchone()[0] == 12
    # a wrong form shows the form again, with the message
    bad = client.post("/new", data={**data, "player": ["2", "2", "3"]})
    assert bad.status_code == 200 and "three other players" in bad.get_data(as_text=True)
    assert client.post("/new", data={**data, "lowtrump": "poverty"}).status_code == 200
    for wrong in ("x", "0", "26"):
        assert client.post("/new", data={**data, "rounds": wrong}).status_code == 200


def test_keep_playing_and_the_table_page_over_http(app, conn):
    first = play.new_table(conn, 1, [2, 3, 4], RS, max_games=1)
    session = conn.execute("SELECT session FROM game WHERE id = ?", (first,)).fetchone()[0]
    finish_game(conn, first)
    client = logged_in(app, 2)
    table = client.get(f"/table/{session}/").get_data(as_text=True)
    assert "1 of 1 games played" in table and "This table is finished." in table
    assert "🏆" in table and "New table with the same players" in table
    # the table is over: no "keep playing"
    assert "Keep playing" not in client.get(f"/game/{first}/").get_data(as_text=True)
    response = client.post(f"/table/{session}/again", data={"csrf": "t"}, follow_redirects=True)
    assert "Do you want to play this game?" in response.get_data(as_text=True)

    open_table = play.new_table(conn, 1, [2, 3, 4], RS)
    finish_game(conn, open_table)
    page = client.get(f"/game/{open_table}/").get_data(as_text=True)
    assert "Keep playing" in page
    response = client.post(f"/game/{open_table}/next", data={"csrf": "t"}, follow_redirects=True)
    assert "Do you want to play this game?" in response.get_data(as_text=True)
    outsider = logged_in(app, 5)
    assert outsider.get(f"/game/{open_table}/").status_code == 404
    before = conn.execute("SELECT count(*) FROM game").fetchone()[0]
    outsider.post(f"/game/{open_table}/next", data={"csrf": "t"})
    assert conn.execute("SELECT count(*) FROM game").fetchone()[0] == before


def test_a_finished_table_shows_a_summary_and_no_keep_playing(app, conn):
    first = play.new_table(conn, 1, [2, 3, 4], RS, max_games=1)
    finish_game(conn, first, seed=2)
    session = conn.execute("SELECT session FROM game WHERE id = ?", (first,)).fetchone()[0]
    summary = play.table_summary(conn, session)
    assert sorted(p["id"] for p in summary["players"]) == [1, 2, 3, 4]
    assert all(p["games"] == 1 for p in summary["players"])
    assert sum(p["points"] for p in summary["players"]) == 0
    assert [r["id"] for r in summary["rounds"]] == [first]
    assert sorted(summary["rounds"][0]["points"]) == [1, 2, 3, 4]
    client = app.test_client()
    with client.session_transaction() as s:
        s["user_id"] = 1
    html = client.get(f"/table/{session}/").get_data(as_text=True)
    assert "Summary" in html and "Game by game" in html
    game_page = client.get(f"/game/{first}/").get_data(as_text=True)
    assert f"/game/{first}/next" not in game_page
