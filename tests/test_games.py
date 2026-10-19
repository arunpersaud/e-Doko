from contextlib import closing

import pytest

from edoko import db, games


def add_game(conn, game_id, deal, status, users, session=1):
    """A game with four hands; a finished one also has cards and 12 tricks."""
    conn.execute(
        "INSERT OR IGNORE INTO deal (id, cards) VALUES (?, ?)", (deal, str(deal))
    )
    conn.execute(
        "INSERT INTO game (id, session, deal, ruleset, status, type)"
        " VALUES (?, ?, ?, 1, ?, 'normal')",
        (game_id, session, deal, status),
    )
    for seat, user in enumerate(users, 1):
        conn.execute(
            "INSERT INTO hand (id, game, seat, user, token, party)"
            " VALUES (?, ?, ?, ?, ?, 're')",
            (game_id * 10 + seat, game_id, seat, user, f"token{game_id}{seat}"),
        )
    if status != "gameover":
        return
    for seat in range(1, 5):
        hand = game_id * 10 + seat
        for n in range(12):
            card = (seat - 1) * 12 + n + 1
            conn.execute(
                "INSERT INTO hand_card (id, hand, card) VALUES (?, ?, ?)",
                (hand * 100 + n, hand, card),
            )
    for number in range(1, 13):
        trick = game_id * 100 + number
        conn.execute(
            "INSERT INTO trick (id, game, number, winner_seat) VALUES (?, ?, ?, 1)",
            (trick, game_id, number),
        )
        for seq in range(1, 5):
            hand = game_id * 10 + seq
            conn.execute(
                "INSERT INTO play (id, trick, sequence, hand_card) VALUES (?, ?, ?, ?)",
                (trick * 10 + seq, trick, seq, hand * 100 + number - 1),
            )


@pytest.fixture
def world(app, database):
    """Users 1-8 (Ann ... Hal); games on three deals.

    game 10, deal 1: finished, users 1-4        game 11, deal 1: finished, 5-8
    game 12, deal 2: finished, users 1-4        game 13, deal 2: in progress, 5-8
    game 14, deal 3: cancelled, users 1-4       game 15, deal 3: finished, 5-8
    """
    with closing(db.connect(database)) as conn:
        for uid, name in enumerate(["Bob", "Cy", "Di", "Eve", "Fay", "Gus", "Hal"], 2):
            conn.execute(
                "INSERT INTO user (id, name, email) VALUES (?, ?, ?)",
                (uid, name, f"{name.lower()}@x.org"),
            )
        conn.execute("INSERT INTO session (id) VALUES (2)")
        add_game(conn, 10, 1, "gameover", [1, 2, 3, 4])
        add_game(conn, 11, 1, "gameover", [5, 6, 7, 8], session=2)
        add_game(conn, 12, 2, "gameover", [1, 2, 3, 4])
        add_game(conn, 13, 2, "play", [5, 6, 7, 8], session=2)
        add_game(conn, 14, 3, "cancel-nines", [1, 2, 3, 4])
        add_game(conn, 15, 3, "gameover", [5, 6, 7, 8], session=2)
    return app


def client_for(app, user_id):
    client = app.test_client()
    with client.session_transaction() as session:
        session["user_id"] = user_id
        session["csrf"] = "t"
    return client


def status(app, user_id, game_id):
    return client_for(app, user_id).get(f"/game/{game_id}/").status_code


def test_player_sees_own_finished_game(world):
    response = client_for(world, 1).get("/game/10/")
    assert response.status_code == 200
    assert "Game 10" in response.text
    assert "/static/cards/english/" in response.text
    assert response.headers["Cache-Control"] == "private, no-store"


def test_same_deal_other_table_is_visible_after_finishing(world):
    # user 1 finished deal 1 at table 1, so may look at table 2's game 11
    assert status(world, 1, 11) == 200
    assert status(world, 5, 10) == 200


def test_outsider_sees_nothing(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("INSERT INTO user (id, name, email) VALUES (9, 'Ida', 'i@x.org')")
    for game_id in (10, 11, 12, 13, 14, 15):
        assert status(world, 9, game_id) == 404


def test_unfinished_own_deal_does_not_unlock_others(world):
    # user 5 plays deal 2 (game 13, not over): must not see game 12
    assert status(world, 5, 12) == 404
    # but users 1-4 finished it and see nothing of game 13's hands either
    page = client_for(world, 1).get("/game/13/")
    assert page.status_code == 404


def test_players_of_unfinished_game_get_a_status_page_only(world):
    response = client_for(world, 5).get("/game/13/")
    assert response.status_code == 200
    assert "/static/cards/" not in response.text
    assert "Tricks" not in response.text
    assert "shown when it is over" in response.text


def test_cancelled_game_does_not_count_as_finished(world):
    # user 1 has only a cancelled game on deal 3: game 15 stays hidden
    assert status(world, 1, 15) == 404
    # users 5-8 finished it, but game 14 was cancelled: never shown
    assert status(world, 5, 14) == 404


def test_cancelled_game_shows_status_to_players_without_details(world):
    response = client_for(world, 1).get("/game/14/")
    assert response.status_code == 200
    assert "/static/cards/" not in response.text


def test_other_tables_list_only_finished_games(world):
    page = client_for(world, 1).get("/game/12/").text
    assert "Other tables" not in page  # game 13 is still being played
    page = client_for(world, 1).get("/game/10/").text
    assert "Other tables that played this deal" in page
    assert "/game/11/" in page
    assert "Eve, Fay, Gus, Hal" in page


def test_unknown_game_and_login_required(world):
    assert status(world, 1, 999) == 404
    response = world.test_client().get("/game/10/")
    assert response.status_code == 302 and "/login" in response.headers["Location"]


def test_page_lists_tricks_and_hands(world):
    page = client_for(world, 1).get("/game/10/").text
    assert page.count('class="trick"') == 12
    assert "Trick 12" in page
    assert page.count("<h3>") == 4


def test_comments_are_shown_unless_deleted(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute(
            "INSERT INTO comment (game, kind, play, user, body)"
            " VALUES (10, 'move', 10011, 2, 'nice one')"
        )
        conn.execute(
            "INSERT INTO comment (game, kind, play, user, body, deleted_at)"
            " VALUES (10, 'move', 10011, 2, 'gone', '2026-01-01 00:00:00')"
        )
    page = client_for(world, 1).get("/game/10/").text
    assert "nice one" in page and "gone" not in page


def test_image_number():
    assert [games.image_number(i) for i in (1, 2, 3, 4, 47, 48)] == [1, 1, 3, 3, 47, 47]


def test_markup_in_names_and_comments_is_escaped(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET name = '<b>Bob</b>' WHERE id = 2")
        conn.execute(
            "INSERT INTO comment (game, kind, play, user, body)"
            " VALUES (10, 'move', 10011, 2, '<script>alert(1)</script>')"
        )
    page = client_for(world, 1).get("/game/10/").text
    assert "<script>alert" not in page and "<b>Bob" not in page
    assert "&lt;script&gt;" in page
