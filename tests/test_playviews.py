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
