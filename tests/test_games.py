from contextlib import closing
from pathlib import Path

import pytest

from edoko import db, games


def add_game(conn, game_id, deal, status, users, session=1, played=None):
    """A game with four hands; a finished one also has cards and 12 tricks.

    played: the number of tricks of a game in progress (None: no cards at all).
    """
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
    tricks = 12 if status == "gameover" else played
    if tricks is None:
        return
    for seat in range(1, 5):
        hand = game_id * 10 + seat
        for n in range(12):
            card = (seat - 1) * 12 + n + 1
            conn.execute(
                "INSERT INTO hand_card (id, hand, card) VALUES (?, ?, ?)",
                (hand * 100 + n, hand, card),
            )
    for number in range(1, tricks + 1):
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
        conn.execute("DELETE FROM game")  # the two games of conftest have no hands
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


def images(page):
    return page.count("/static/cards/english/")


def test_game_in_progress_shows_the_tricks_so_far_and_only_your_cards(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("DELETE FROM game WHERE id = 13")  # replace by one with cards
        add_game(conn, 13, 2, "play", [5, 6, 7, 8], session=2, played=2)
    page = client_for(world, 5).get("/game/13/")
    assert page.status_code == 200
    assert numbers(page.text) == 2
    # the trick on the table (4 cards) and the 10 cards user 5 still holds:
    # no other hand
    assert images(page.text) == 4 + 10
    assert "shown when the game is over" in page.text
    # the same for the others, each sees only their own hand
    assert images(client_for(world, 8).get("/game/13/").text) == 4 + 10
    # nobody else sees anything
    assert status(world, 1, 13) == 404


def test_a_game_without_a_move_shows_the_own_hand_only(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("DELETE FROM game WHERE id = 13")
        add_game(conn, 13, 2, "pre", [5, 6, 7, 8], session=2, played=0)
    page = client_for(world, 6).get("/game/13/").text
    assert images(page) == 12 and "No card has been played yet." in page


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


def numbers(page):
    """The numbered links to the tricks."""
    return page.split('class="trick-numbers"')[1].split("</p>")[0].count("<a ")


def test_page_has_the_four_players_and_a_link_to_every_trick(world):
    page = client_for(world, 1).get("/game/10/").text
    assert numbers(page) == 12
    assert page.count('class="seat ') == 4
    assert 'class="trick"' not in page  # no list of all tricks below the table


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


# --- seat links ---------------------------------------------------------------


def test_seat_link_works_without_login_and_shows_only_that_game(world):
    client = world.test_client()
    response = client.get("/token101")  # not a token: too short
    assert response.status_code == 404
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE hand SET token = ? WHERE id = 101", ("a" * 32,))
    response = client.get("/" + "a" * 32)
    assert response.status_code == 200
    assert "Game 10" in response.text and "Trick 1 / 12" in response.text
    assert response.headers["Cache-Control"] == "private, no-store"
    assert response.headers["X-Robots-Tag"] == "noindex"
    # the other tables of the deal need a login
    assert "/game/11/" not in response.text
    assert "to see how the other tables played this deal" in response.text
    # and the seat link opens nothing else
    assert client.get("/game/11/").status_code == 302


def test_seat_link_of_a_game_in_progress_shows_no_cards(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE hand SET token = ? WHERE id = 131", ("b" * 22,))
    response = world.test_client().get("/" + "b" * 22)
    assert response.status_code == 200
    assert "/static/cards/" not in response.text


def test_seat_link_when_logged_in_as_that_player_lists_other_tables(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE hand SET token = ? WHERE id = 101", ("c" * 32,))
    page = client_for(world, 1).get("/" + "c" * 32).text
    assert "/game/11/" in page
    # logged in as somebody else, the link still only gives the seat view
    page = client_for(world, 5).get("/" + "c" * 32).text
    assert "/game/11/" not in page


def test_unknown_seat_link(world):
    assert world.test_client().get("/" + "d" * 32).status_code == 404
    assert world.test_client().get("/" + "d" * 22).status_code == 404


def test_old_php_links_are_redirected(world):
    client = world.test_client()
    token = "e" * 32
    response = client.get(f"/index.php?action=game&me={token}")
    assert response.headers["Location"] == f"/{token}"
    assert client.get("/index.php?action=game&me=short").headers["Location"] == "/"
    assert client.get("/index.php?action=stats").headers["Location"] == "/"


def test_tokens_are_masked_in_the_access_log():
    from edoko.gunicorn_log import mask

    token = "0123456789abcdef0123456789abcdef"
    assert mask(f"GET /{token} HTTP/1.1") == "GET /<token> HTTP/1.1"
    assert mask(f"/{token}/") == "/<token>/"
    assert mask(f"GET /index.php?action=game&me={token} HTTP/1.1") == (
        "GET /index.php?action=game&me=<token> HTTP/1.1"
    )
    assert mask("/Abc_Def-0123456789xyzZ") == "/<token>"
    assert mask("GET /game/2471/ HTTP/1.1") == "GET /game/2471/ HTTP/1.1"
    assert mask("/static/cards/english/1.png") == "/static/cards/english/1.png"


# --- home page and tables ---------------------------------------------------------


def test_home_lists_turns_and_tables(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE game SET turn_seat = 1 WHERE id = 13")  # user 5
    mine = client_for(world, 5).get("/").text
    assert "My games" in mine
    section = mine.split("Your turn")[1].split("Your tables")[0]
    assert "Game 13" in section and "Waiting for the others" not in section
    other = client_for(world, 6).get("/").text
    waiting = other.split("Waiting for the others")[1].split("Your tables")[0]
    assert "Game 13" in waiting and "Eve's turn" in waiting
    assert "Table 2" in other


def test_home_for_a_new_user_and_anonymous_visitor(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("INSERT INTO user (id, name, email) VALUES (9, 'Ida', 'i@x.org')")
    assert "You have not played yet." in client_for(world, 9).get("/").text
    assert "games so far" in world.test_client().get("/").text


def test_table_page_only_for_its_players(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        for user in (1, 2, 3, 4):
            conn.execute("INSERT INTO session_player VALUES (1, ?)", (user,))
    page = client_for(world, 1).get("/table/1/")
    assert page.status_code == 200
    assert "Game 10" in page.text and "Game 11" not in page.text
    assert client_for(world, 5).get("/table/1/").status_code == 404
    assert world.test_client().get("/table/1/").status_code == 302


# --- command line --------------------------------------------------------------------


def run(app, *args):
    return app.test_cli_runner().invoke(args=["edoko", *args])


def test_make_admin_and_users(app):
    result = run(app, "make-admin", "a@x.org")
    assert result.exit_code == 0 and "admin: a@x.org" in result.output
    assert "admin" in run(app, "users").output
    assert run(app, "make-admin", "a@x.org", "--remove").exit_code == 0
    assert run(app, "make-admin", "nobody@x.org").exit_code != 0


# --- the table: fixed spots, neighbours --------------------------------------------


def spots(page):
    """spot -> name of the player, from the seats of the page."""
    import re

    found = re.findall(
        r'class="seat spot-(\w+)[^"]*">\s*<header>\s*<img[^>]*>\s*<strong>(\w+)', page
    )
    return dict(found)


def test_players_keep_their_spot_when_the_seats_rotate(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        add_game(conn, 16, 4, "gameover", [2, 3, 4, 1])  # same table, rotated
    first = spots(client_for(world, 1).get("/game/10/").text)
    rotated = spots(client_for(world, 1).get("/game/16/").text)
    assert first == rotated
    assert first["bottom"] == "Ann" and first["left"] == "Bob"
    assert first["top"] == "Cy" and first["right"] == "Di"
    # somebody else sees the same table from their own seat
    seen = spots(client_for(world, 3).get("/game/10/").text)
    assert seen == {"bottom": "Cy", "left": "Di", "top": "Ann", "right": "Bob"}


def test_a_viewer_who_did_not_play_sees_the_lowest_player_at_the_bottom(world):
    page = client_for(world, 1).get("/game/11/").text  # table of Eve, Fay, Gus, Hal
    assert spots(page)["bottom"] == "Eve"


def test_the_starting_player_is_marked(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE game SET start_seat = 3 WHERE id = 10")
    page = client_for(world, 1).get("/game/10/").text
    assert page.count('class="start"') == 1
    assert "spot-top" in page.split('class="start"')[0].rsplit("<section", 1)[1]


def test_previous_and_next_game_of_the_table(world):
    page = client_for(world, 1).get("/game/12/").text
    assert 'href="/game/10/" rel="prev"' in page
    assert 'href="/game/14/" rel="next"' in page
    first = client_for(world, 1).get("/game/10/").text
    assert 'rel="prev"' not in first and 'href="/game/12/" rel="next"' in first


def test_neighbours_are_only_games_the_viewer_may_open(world):
    # user 1 looks at table 2's game 11 (same deal): no way on to games 13 or 15
    page = client_for(world, 1).get("/game/11/").text
    assert "/game/13/" not in page and "/game/15/" not in page
    # but a player of that table has them as neighbours
    own = client_for(world, 5).get("/game/11/").text
    assert 'href="/game/13/" rel="next"' in own


def test_seat_links_have_no_neighbour_links(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE hand SET token = ? WHERE id = 121", ("f" * 32,))
    page = world.test_client().get("/" + "f" * 32).text
    assert 'href="/game/' not in page  # no way on to other games


def test_icons_for_solo_wedding_and_calls(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE game SET type = 'solo', solo = 'queen' WHERE id = 10")
        conn.execute("UPDATE hand SET party = 're' WHERE id = 102")
        conn.execute("UPDATE hand SET party = 'contra' WHERE id IN (101, 103, 104)")
        conn.execute(
            "UPDATE hand SET point_call = '90', party = 'contra' WHERE id = 103"
        )
    page = client_for(world, 1).get("/game/10/").text
    assert "icons/queensolo_button.png" in page
    assert "icons/contra_button.png" in page and "icons/90_button.png" in page


def test_a_silent_solo_is_not_announced_while_it_is_played(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("DELETE FROM game WHERE id = 13")
        add_game(conn, 13, 2, "play", [5, 6, 7, 8], session=2, played=1)
        conn.execute("UPDATE game SET type = 'solo', solo = 'silent' WHERE id = 13")
        conn.execute("UPDATE hand SET party = 're' WHERE id = 131")
    assert "solo_button" not in client_for(world, 5).get("/game/13/").text


# --- the trick on the table ---------------------------------------------------------


def table(page):
    """The part of the page that is swapped when clicking through the tricks."""
    return page.split('<div class="felt">')[1].split("</nav>")[0]


def on_table(page):
    return table(page).split('class="trick-nav"')[0]


def test_a_finished_game_starts_with_the_first_trick_and_can_step_through(world):
    client = client_for(world, 1)
    page = client.get("/game/10/").text
    assert "Trick 1 / 12" in page
    assert on_table(page).count("<figure") == 4
    assert 'rel="prev"' not in page.split("trick-nav")[1].split("</nav>")[0]
    assert 'class="trick-label"' in page
    page = client.get("/game/10/trick/5").text
    assert "Trick 5 / 12" in page
    assert 'href="/game/10/trick/4#table" rel="prev"' in page
    assert 'href="/game/10/trick/6#table" rel="next"' in page
    assert 'hx-get="/game/10/trick/6"' in page
    assert "Trick 12 / 12" in client.get("/game/10/trick/12").text
    # numbers outside the game fall back to the default, rubbish is not a page
    assert "Trick 1 / 12" in client.get("/game/10/trick/13").text
    assert client.get("/game/10/trick/abc").status_code == 404
    # links of the earlier version, with a query, go to the clean address
    old = client.get("/game/10/?trick=5")
    assert old.status_code == 302 and old.headers["Location"] == "/game/10/trick/5"


def test_a_game_in_progress_starts_with_the_latest_trick(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("DELETE FROM game WHERE id = 13")
        add_game(conn, 13, 2, "play", [5, 6, 7, 8], session=2, played=3)
    assert "Trick 3 / 3" in client_for(world, 5).get("/game/13/").text
    assert "Trick 2 / 3" in client_for(world, 5).get("/game/13/trick/2").text


def test_the_arrow_ring_starts_at_the_player_who_leads(world):
    # trick 1 of game 10 is led by seat 1: Ann (user 1)
    page = client_for(world, 1).get("/game/10/").text
    assert 'data-lead="bottom"' in page  # Ann is at the bottom
    page = client_for(world, 3).get("/game/10/").text
    assert 'data-lead="top"' in page  # for Cy she is at the top
    page = client_for(world, 2).get("/game/10/").text
    assert 'data-lead="right"' in page  # for Bob at the right


def test_the_selected_trick_shows_in_play_order(world):
    page = client_for(world, 1).get("/game/10/trick/2").text
    seq = on_table(page)
    assert seq.index('<span class="seq">1') < seq.index('<span class="seq">4')


def test_clean_urls_also_for_seat_links(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE hand SET token = ? WHERE id = 101", ("a" * 32,))
    client = world.test_client()
    page = client.get("/" + "a" * 32 + "/trick/3").text
    assert "Trick 3 / 12" in page
    assert f'href="/{"a" * 32}/trick/4#table" rel="next"' in page
    old = client.get("/" + "a" * 32 + "?trick=3")
    assert old.headers["Location"] == "/" + "a" * 32 + "/trick/3"


def test_the_game_info_is_below_the_title(world):
    page = client_for(world, 1).get("/game/10/").text
    head, rest = page.split('<div id="table">')
    assert 'class="game-info"' in head and "gameover" in head.split("game-info")[1]
    assert "gameover" not in rest.split("</section>")[-1].split('class="felt"')[-1]


def hand_images(page, name):
    """The card image numbers of the hand of a player, in the order shown."""
    import re

    section = page.split(f"<strong>{name}</strong>")[1].split("</section>")[0]
    return [int(n) for n in re.findall(r"cards/english/(\d+)\.png", section)]


def test_the_hands_are_sorted_for_the_kind_of_game(world):
    # seat 4 (Di) holds the cards 37 .. 48: spades and hearts. As in the PHP
    # site: trump, then diamonds, clubs, hearts, spades; the best card first.
    page = client_for(world, 1).get("/game/10/").text
    assert hand_images(page, "Di") == [43, 43, 45, 45, 47, 47, 37, 37, 39, 39, 41, 41]
    # seat 1 (Ann) holds 1 .. 12: the queens and the jacks of clubs are trump;
    # with the ruleset of these tests (no dullen) the ten of hearts is a heart
    assert hand_images(page, "Ann") == [3, 3, 5, 5, 7, 7, 9, 9, 11, 11, 1, 1]


def test_hands_follow_the_game_type_and_the_own_hand_too(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE game SET type = 'solo', solo = 'queen' WHERE id = 10")
    page = client_for(world, 1).get("/game/10/").text
    # in a queen solo only the queens are trump; the jacks of clubs rank in the
    # clubs (they come before the hearts), the ten of hearts in the hearts
    assert hand_images(page, "Ann") == [3, 3, 5, 5, 7, 7, 9, 9, 11, 11, 1, 1]
    # a game in progress: the cards still held, sorted too
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("DELETE FROM game WHERE id = 13")
        add_game(conn, 13, 2, "play", [5, 6, 7, 8], session=2, played=0)
    mine = hand_images(client_for(world, 8).get("/game/13/").text, "Hal")
    assert mine == [43, 43, 45, 45, 47, 47, 37, 37, 39, 39, 41, 41]


# --- the winner ---------------------------------------------------------------------


def score_items(world, *items):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        for party, kind in items:
            conn.execute(
                "INSERT INTO score_item (game, party, kind) VALUES (10, ?, ?)",
                (party, kind),
            )


def info(page):
    return page.split('class="game-info"')[1].split("</div>")[0]


def test_the_winner_gets_a_trophy(world):
    score_items(world, ("re", "120"), ("re", "90"), ("contra", "fox"))
    line = info(client_for(world, 1).get("/game/10/").text)
    assert line.count("🏆") == 1
    assert line.index("🏆") < line.index("Re ")  # in front of the winner, Re


def test_the_trophy_goes_to_contra_when_it_won(world):
    score_items(world, ("contra", "againstqueens"), ("contra", "120"), ("re", "fox"))
    line = info(client_for(world, 1).get("/game/10/").text)
    assert line.count("🏆") == 1
    assert line.index("Re ") < line.index("🏆") < line.index("Contra")


def test_no_trophy_without_items_or_with_a_tie(world):
    assert "🏆" not in info(client_for(world, 1).get("/game/10/").text)
    score_items(world, ("re", "fox"), ("contra", "fox"))
    assert "🏆" not in info(client_for(world, 1).get("/game/10/").text)


def test_the_trophy_follows_the_items_not_the_card_points(world):
    # contra has more card points but re called and won: the items decide
    score_items(world, ("re", "120"), ("re", "call120"), ("re", "90"))
    page = client_for(world, 1).get("/game/10/").text
    assert info(page).index("🏆") < info(page).index("Re ")


def test_the_game_points_are_shown_for_the_winning_party(world):
    score_items(
        world,
        ("contra", "againstqueens"),
        ("contra", "120"),
        ("contra", "doko"),
        ("contra", "fox"),
        ("re", "fox"),
    )
    line = info(client_for(world, 1).get("/game/10/").text)
    assert "3 game points for Contra" in line
    assert "triple" not in line
    score_items(world, ("re", "120"), ("re", "90"), ("re", "call90"), ("re", "doko"))
    assert "1 game point for Re" in info(client_for(world, 1).get("/game/10/").text)


def test_the_game_points_in_german_and_for_solos(world):
    score_items(world, ("re", "120"), ("re", "90"))
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("UPDATE game SET type = 'solo', solo = 'jack' WHERE id = 10")
    page = client_for(world, 1).get("/game/10/").text
    assert "2 game points for Re" in info(page) and "the soloist counts triple" in info(
        page
    )
    german = client_for(world, 1).get("/game/10/", headers={"Accept-Language": "de"})
    assert "2 Spielpunkte für Re" in info(german.text)


def test_no_game_points_without_a_winner(world):
    assert "game point" not in info(client_for(world, 1).get("/game/10/").text)
    score_items(world, ("re", "fox"), ("contra", "fox"))
    assert "game point" not in info(client_for(world, 1).get("/game/10/").text)


# --- how the game points are counted -------------------------------------------------


def breakdown(page):
    """The items of the popover on the game points, as text."""
    import re

    box = page.split('<details class="points">')[1].split("</details>")[0]
    return [re.sub(r"<[^>]+>", "", row) for row in re.findall(r"<li>(.*?)</li>", box)]


def test_the_result_list_is_gone_and_the_game_points_explain_themselves(world):
    import re

    score_items(
        world,
        ("contra", "againstqueens"),
        ("contra", "120"),
        ("contra", "doko"),
        ("re", "call90"),
    )
    page = client_for(world, 1).get("/game/10/").text
    assert "<h2>Result</h2>" not in page and 'class="scores"' not in page
    # a click (or tap) opens it: a details element, no script and no hover needed
    assert re.search(r'<details class="points">\s*<summary>', page)
    assert "againstqueens" not in page and "call90" not in page  # words, not codes
    items = breakdown(page)
    assert "+1 Contra: Won against the queens" in items
    assert "+1 Contra: Won" in items
    assert any(i.startswith("+1 Contra: Doppelkopf") for i in items)
    assert "+1 Re: Announced no 90" in items
    assert all(i.startswith("+1 ") for i in items)  # every row says what it is worth


def test_the_wording_of_all_scoring_items(world):
    from edoko.gameview import item_text

    def text(kind, party="re", winner=None, loser=None):
        return item_text(
            {"kind": kind, "party": party, "winner": winner, "loser": loser}
        )

    with world.test_request_context("/"):
        assert text("120") == "Won"
        assert text("90") == "Contra has less than 90 points"
        assert text("30", "contra") == "Re has less than 30 points"
        assert text("0") == "Contra has no trick (schwarz)"
        assert text("call120") == "Announced Re"
        assert text("call120", "contra") == "Announced Contra"
        assert text("call30") == "Announced no 30"
        assert text("call0") == "Announced schwarz"
        # the call of the other party was beaten: it is named after that party
        assert text("against120", "re") == "Beat the announcement of Contra"
        assert text("against90", "contra") == "Beat the announcement of Re: no 90"
        assert text("made60") == "Reached 60 points against the announcement"
        assert text("fox", loser="Bob") == "Caught a fox (Bob)"
        assert text("karlchen", winner="Ann").startswith("Karlchen")
        assert "(Ann)" in text("doko", winner="Ann")
        assert text("againstqueens", "contra") == "Won against the queens"


def test_the_words_are_german_too(world):
    score_items(world, ("contra", "againstqueens"), ("contra", "120"), ("re", "call90"))
    page = client_for(world, 1).get("/game/10/", headers={"Accept-Language": "de"}).text
    items = breakdown(page)
    assert "+1 Kontra: Gegen die Alten gewonnen" in items
    assert "+1 Re: keine 90 angesagt" in items


def test_a_trick_with_one_card_keeps_the_spot_of_its_player(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("DELETE FROM game WHERE id = 13")
        add_game(conn, 13, 2, "play", [5, 6, 7, 8], session=2, played=1)
        conn.execute("DELETE FROM play WHERE id != 13011")  # only seat 1 has played
    page = client_for(world, 6).get("/game/13/").text  # Fay, seat 2, is at the bottom
    figures = on_table(page).count("<figure")
    assert figures == 1
    # seat 1 (Eve) is the player to the right of the bottom player: not in the middle
    assert 'class="play spot-right' in on_table(page)
    assert 'data-lead="right"' in page


def test_the_cross_has_tracks_of_a_fixed_size():
    # a lone card must stay at its spot: the empty places of a trick keep their size
    css = (Path(__file__).parent.parent / "edoko/static/style.css").read_text()
    cross = css.split(".cross {")[1].split("}")[0]
    assert "grid-template-rows: repeat(3, var(--card-h))" in cross
    assert "grid-template-columns: repeat(3, var(--card-w))" in cross


def test_the_bubbles_leave_their_corners_on_narrow_screens():
    # the corner areas do not exist in the one-column grid of a phone: a bubble that
    # kept its grid-area would add columns and squeeze the table
    css = (Path(__file__).parent.parent / "edoko/static/style.css").read_text()
    narrow = css.split("@media (max-width: 42rem) {")[2]
    assert ".table-view > .bubbles" in narrow and "grid-area: auto" in narrow


def test_the_trick_label_has_two_lines_so_the_arrows_do_not_move(world):
    page = client_for(world, 1).get("/game/10/trick/3").text
    label = page.split('class="trick-label"')[1].split("</nav>")[0]
    assert label.count("<span") == 2  # its two lines (the label itself is cut off)
    assert "Trick 3 / 12" in label and "won by" in label


# --- remarks as speech bubbles ---------------------------------------------------


def remark(world, play, user, body):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute(
            "INSERT INTO comment (game, kind, play, user, body)"
            " VALUES (10, 'move', ?, ?, ?)",
            (play, user, body),
        )


def corners(page):
    """corner -> the texts of the bubbles in it."""
    import re

    found = {}
    for corner, inner in re.findall(
        r'<div class="bubbles corner-(\w+)">(.*?)</div>', page, re.S
    ):
        found[corner] = [
            re.sub(r"<[^>]+>", " ", b)
            for b in re.findall(r'<p class="bubble"[^>]*>(.*?)</p>', inner, re.S)
        ]
    return found


def test_a_remark_is_a_bubble_in_the_corner_next_to_its_author(world):
    # Ann (1) is at the bottom, Bob (2) left, Cy (3) top, Di (4) right
    remark(world, 10011, 1, "mine")
    remark(world, 10012, 2, "from the left")
    remark(world, 10013, 3, "from the top")
    remark(world, 10014, 4, "from the right")
    found = corners(client_for(world, 1).get("/game/10/").text)
    assert set(found) == {"se", "sw", "nw", "ne"}  # bottom, left, top, right
    assert "mine" in found["se"][0] and "Ann" in found["se"][0]
    assert "from the left" in found["sw"][0]
    assert "from the top" in found["nw"][0]
    assert "from the right" in found["ne"][0]
    # no list of remarks under the table any more
    assert 'class="trick-comments"' not in client_for(world, 1).get("/game/10/").text


def test_the_corner_follows_the_viewers_spots(world):
    remark(world, 10012, 2, "Bob speaking")  # Bob: left of Ann, top for Di
    assert (
        "Bob speaking" in corners(client_for(world, 1).get("/game/10/").text)["sw"][0]
    )
    assert (
        "Bob speaking" in corners(client_for(world, 4).get("/game/10/").text)["nw"][0]
    )


def test_several_remarks_of_a_player_stack_in_one_corner(world):
    remark(world, 10011, 1, "first")
    remark(world, 10012, 1, "second")
    found = corners(client_for(world, 1).get("/game/10/").text)
    assert (
        len(found["se"]) == 2
        and "first" in found["se"][0]
        and "second" in found["se"][1]
    )


def test_a_bubble_shows_the_card_it_is_about_and_not_a_number(world):
    remark(world, 10013, 1, "about the third card")  # play 10013: seat 3, card 25
    page = client_for(world, 1).get("/game/10/").text
    bubble = page.split('<p class="bubble"')[1].split("</p>")[0]
    assert 'data-seq="3"' in bubble
    assert 'class="card tiny"' in bubble and "cards/english/25.png" in bubble
    assert 'alt="nine diamonds"' in bubble and 'title="nine diamonds"' in bubble
    assert "#3" not in page  # the old number that nobody understood
    # the card on the table has the same number, so hovering can mark it
    assert 'data-seq="3"' in on_table(page)


def test_the_name_is_in_the_bubble_for_screen_readers_and_phones(world):
    remark(world, 10011, 1, "hello")
    page = client_for(world, 1).get("/game/10/").text
    assert '<span class="who">Ann:</span>' in page  # hidden by the style on a desktop
    css = (Path(__file__).parent.parent / "edoko/static/style.css").read_text()
    desktop = css.split("p.bubble .who {")[1].split("}")[0]
    assert "clip-path: inset(50%)" in desktop and "position: absolute" in desktop
    phone = css.split("p.bubble .who {")[2].split("}")[0]  # the media query
    assert "position: static" in phone and "clip-path: none" in phone


def test_only_the_remarks_of_the_shown_trick(world):
    remark(world, 10011, 1, "in trick one")
    remark(world, 10021, 1, "in trick two")
    one = client_for(world, 1).get("/game/10/trick/1").text
    two = client_for(world, 1).get("/game/10/trick/2").text
    assert "in trick one" in one and "in trick two" not in one
    assert "in trick two" in two and "in trick one" not in two


def test_the_text_of_a_bubble_is_escaped(world):
    remark(world, 10011, 1, "<script>alert(1)</script>")
    page = client_for(world, 1).get("/game/10/").text
    assert "<script>alert" not in page and "&lt;script&gt;" in page


def test_a_remark_of_somebody_who_is_not_at_the_table_is_not_lost(world):
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("INSERT INTO user (id, name, email) VALUES (9, 'Ida', 'i@x.org')")
    remark(world, 10011, 9, "from outside")
    page = client_for(world, 1).get("/game/10/").text
    assert "from outside" in page and 'class="trick-comments"' in page
    assert not corners(page)


def test_the_values_decide_the_winner_and_the_game_points(world, monkeypatch):
    # a rule set that gives more for some items: the sums decide, not the rows
    from edoko import rules

    monkeypatch.setattr(
        rules, "item_value", lambda kind, rule_set=None: 2 if kind == "doko" else 1
    )
    # three rows each (a tie by rows), but contra has 2 + 1 + 1 = 4 points against 3
    score_items(
        world,
        ("re", "120"),
        ("re", "90"),
        ("re", "fox"),
        ("contra", "doko"),
        ("contra", "karlchen"),
        ("contra", "againstqueens"),
    )
    page = client_for(world, 1).get("/game/10/").text
    assert "1 game point for Contra" in info(page)
    assert info(page).count("🏆") == 1
    rows = breakdown(page)
    assert any(r.startswith("+2 Contra: Doppelkopf") for r in rows)
    assert sum(r.startswith("+1 ") for r in rows) == 5


# --- announcements are not remarks ----------------------------------------------------


def bubble_of(page):
    return page.split('<p class="bubble')[1].split("</p>")[0]


def test_an_announcement_shows_its_icon_and_not_the_card(world):
    for body, icon, words in [
        ("Re", "re", "Announced Re"),
        ("Contra", "contra", "Announced Contra"),
        ("No 90", "90", "Announced no 90"),
        ("No 60", "60", "Announced no 60"),
        ("No 30", "30", "Announced no 30"),
        ("Zero", "0", "Announced schwarz"),
    ]:
        with closing(db.connect(world.config["DATABASE"])) as conn:
            conn.execute("DELETE FROM comment")
        remark(world, 10013, 1, body)
        bubble = bubble_of(client_for(world, 1).get("/game/10/").text)
        assert f"icons/{icon}_button.png" in bubble
        assert f'alt="{words}"' in bubble
        assert 'class="card tiny"' not in bubble  # not a card that looks like a catch
        assert "announcement" in bubble.split(">")[0]


def test_the_icon_of_an_announcement_still_belongs_to_a_card_for_the_hover(world):
    remark(world, 10013, 1, "Re")
    assert 'data-seq="3"' in bubble_of(client_for(world, 1).get("/game/10/").text)


def test_only_the_words_of_the_site_are_announcements(world):
    # players' own remarks, even "re" or "RE", keep their card and their words
    for body in ("re", "RE", "No 91", "Zero!", "Schweinchen! ", "hello"):
        with closing(db.connect(world.config["DATABASE"])) as conn:
            conn.execute("DELETE FROM comment")
        remark(world, 10013, 1, body)
        bubble = bubble_of(client_for(world, 1).get("/game/10/").text)
        assert (
            'class="card tiny"' in bubble and "announcement" not in bubble.split(">")[0]
        )
        assert body.strip() in bubble


def test_announcements_in_german(world):
    remark(world, 10013, 1, "No 90")
    page = client_for(world, 1).get("/game/10/", headers={"Accept-Language": "de"}).text
    assert 'alt="keine 90 angesagt"' in bubble_of(page)
    with closing(db.connect(world.config["DATABASE"])) as conn:
        conn.execute("DELETE FROM comment")
    remark(world, 10013, 1, "Contra")
    page = client_for(world, 1).get("/game/10/", headers={"Accept-Language": "de"}).text
    assert 'alt="Kontra angesagt"' in bubble_of(page)
