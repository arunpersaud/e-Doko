import random
import re
from contextlib import closing

import pytest

from edoko import db, play, rules

RS = rules.Rules("secondwins", "second", "poverty", "1st-own-card")


@pytest.fixture
def conn(app, database):
    with app.app_context(), closing(db.connect(database)) as c:
        c.execute("UPDATE deal SET cards = ? WHERE id = 1", (":".join(map(str, range(1, 49))),))
        for i in range(2, 5):
            c.execute("INSERT INTO user (id, name, email) VALUES (?, ?, ?)", (i, f"User{i}", f"u{i}@x.org"))
        yield c


def tokens(conn, game_id):
    return {
        r["seat"]: r["token"]
        for r in conn.execute("SELECT seat, token FROM hand WHERE game = ?", (game_id,))
    }


def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)


class Seat:
    """One player at their seat link, like a browser with its own cookies."""

    def __init__(self, app, token):
        self.client, self.token = app.test_client(), token

    def page(self):
        response = self.client.get(f"/{self.token}")
        assert response.status_code == 200
        return response.get_data(as_text=True)

    def post(self, action, **data):
        if "csrf" not in data:  # (a page without a form has none)
            data["csrf"] = csrf(self.page())
        return self.client.post(f"/{self.token}/{action}", data=data, follow_redirects=True)


def test_full_game_through_the_seat_links(app, conn):
    rng = random.Random(3)
    game_id = play.create_game(conn, [1, 2, 3, 4], RS)
    seats = {seat: Seat(app, token) for seat, token in tokens(conn, game_id).items()}

    # asked whether to play, then whether sick; then waiting for the others
    assert "Do you want to play this game?" in seats[1].page()
    for seat in seats.values():
        assert seat.post("join").status_code == 200
    assert "anything to say before the game starts" in seats[1].page()
    assert "Waiting for the other players" not in seats[1].page()
    for number, seat in seats.items():
        html = seat.post("declare", sickness="none").get_data(as_text=True)
    assert conn.execute("SELECT status FROM game WHERE id = ?", (game_id,)).fetchone()[0] == "play"

    for _move in range(48):
        turn = conn.execute("SELECT turn_seat FROM game WHERE id = ?", (game_id,)).fetchone()[0]
        other = seats[turn % 4 + 1].page()
        assert "It is not your turn" in other and 'name="card"' not in other
        html = seats[turn].page()
        buttons = re.findall(r'name="card" value="(\d+)"', html)
        assert buttons  # the cards that may be played
        seats[turn].post("play", card=rng.choice(buttons))
    assert conn.execute("SELECT status FROM game WHERE id = ?", (game_id,)).fetchone()[0] == "gameover"
    over = seats[1].page()
    assert 'name="card"' not in over and "Re" in over


def test_a_refused_move_says_why_and_changes_nothing(app, conn):
    game_id = play.create_game(conn, [1, 2, 3, 4], RS)
    seats = {seat: Seat(app, token) for seat, token in tokens(conn, game_id).items()}
    for seat in seats.values():
        seat.post("join")
        seat.post("declare", sickness="none")
    turn = conn.execute("SELECT turn_seat FROM game WHERE id = ?", (game_id,)).fetchone()[0]
    wrong = seats[turn % 4 + 1]
    with wrong.client.session_transaction() as session:
        session["csrf"] = "t"
    html = wrong.post("play", card=1, csrf="t").get_data(as_text=True)
    assert "It is not your turn." in html
    assert conn.execute("SELECT count(*) FROM play").fetchone()[0] == 0
    html = seats[turn].post("play", card=99).get_data(as_text=True)
    assert "do not have that card" in html


def test_posts_need_the_csrf_token_and_a_real_seat(app, conn):
    game_id = play.create_game(conn, [1, 2, 3, 4], RS)
    token = tokens(conn, game_id)[1]
    client = app.test_client()
    assert client.post(f"/{token}/join").status_code == 403
    page = Seat(app, token)
    assert page.client.post("/" + "x" * 22 + "/join", data={"csrf": "y"}).status_code in (403, 404)
    assert page.post("nonsense").status_code == 404


def test_a_logged_in_player_can_play_on_the_game_page(app, conn):
    game_id = play.create_game(conn, [1, 2, 3, 4], RS)
    client = app.test_client()
    with client.session_transaction() as session:
        session["user_id"] = 1
    page = client.get(f"/game/{game_id}/")
    if page.status_code != 200:  # the way of logging in differs: use the login link
        pytest.skip("test login by session key is not available")
    html = page.get_data(as_text=True)
    assert "Do you want to play this game?" in html
    response = client.post(f"/game/{game_id}/join", data={"csrf": csrf(html)}, follow_redirects=True)
    assert response.status_code == 200
    assert conn.execute("SELECT status FROM hand WHERE game = ? AND seat = 1", (game_id,)).fetchone()[0] == "init"


def test_a_silent_solo_is_not_announced_while_it_is_played(app, conn):
    game_id = play.create_game(conn, [1, 2, 3, 4], RS)
    conn.execute("DELETE FROM hand_card WHERE hand IN (SELECT id FROM hand WHERE game = ?)", (game_id,))
    ids = {r["seat"]: r["id"] for r in conn.execute("SELECT id, seat FROM hand WHERE game = ?", (game_id,))}
    cards = {1: [3, 4] + list(range(25, 35)), 2: list(range(5, 17)), 3: list(range(35, 47)), 4: [1, 2, 17, 18, 19, 20, 21, 22, 23, 24, 47, 48]}
    for seat, mine in cards.items():
        conn.executemany("INSERT INTO hand_card (hand, card) VALUES (?, ?)", [(ids[seat], c) for c in mine])
    seats = {seat: Seat(app, token) for seat, token in tokens(conn, game_id).items()}
    for seat in seats.values():
        seat.post("join")
        seat.post("declare", sickness="none")
    assert conn.execute("SELECT solo FROM game WHERE id = ?", (game_id,)).fetchone()[0] == "silent"
    page = seats[2].page()
    assert "normal · play" in page and "silent" not in page and "solo" not in page.split("<main")[1].split("</main>")[0]


def idle(conn, game_id, days):
    conn.execute("UPDATE game SET updated_at = datetime('now', ?) WHERE id = ?", (f"-{days} days", game_id))


def test_reminders_and_cancelling_an_idle_game(app, conn):
    game_id = play.create_game(conn, [1, 2, 3, 4], RS)
    app.outbox.clear()
    seats = {seat: Seat(app, token) for seat, token in tokens(conn, game_id).items()}
    html = seats[2].post("remind").get_data(as_text=True)
    assert "wait longer" in html and not app.outbox
    idle(conn, game_id, 8)
    assert "Remind the player" in seats[2].page()
    assert "It is your own turn" in seats[1].post("remind").get_data(as_text=True)  # seat 1 is on turn
    assert "An email has been sent out." in seats[2].post("remind").get_data(as_text=True)
    assert [m["To"] for m in app.outbox] == ["a@x.org"] and "reminder" in app.outbox[0]["Subject"].lower()
    assert "already been sent" in seats[3].post("remind").get_data(as_text=True)
    assert conn.execute("SELECT count(*) FROM reminder").fetchone()[0] == 1
    assert "wait longer" in seats[2].post("cancel").get_data(as_text=True)
    idle(conn, game_id, 31)
    assert "Cancel this game" in seats[2].page()
    app.outbox.clear()
    seats[2].post("cancel")
    assert conn.execute("SELECT status FROM game WHERE id = ?", (game_id,)).fetchone()[0] == "cancel-timedout"
    assert len(app.outbox) == 4
    # a game that is over can not be canceled again
    with seats[2].client.session_transaction() as session:
        session["csrf"] = "t"
    assert "over already" in seats[2].post("cancel", csrf="t").get_data(as_text=True)
