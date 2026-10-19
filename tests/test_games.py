from contextlib import closing

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
    return page.split('class="game-info"')[1].split("</p>")[0]


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
