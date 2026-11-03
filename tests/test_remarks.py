import pytest

from edoko import play
from test_play import RS, hands, new_game, start
from test_playviews import Seat, conn, tokens  # noqa: F401 (conn is a fixture)


def started(conn):
    game_id = new_game(conn)
    start(conn, game_id)
    return game_id, hands(conn, game_id)


def first_play(conn, game_id, hs, remark=None):
    state = play.load(conn, game_id)
    play.play_card(conn, hs[1]["id"], play.legal_cards(state, 1)[0], remark=remark)
    return conn.execute("SELECT play.id FROM play JOIN trick ON trick.id = play.trick WHERE trick.game = ?", (game_id,)).fetchone()[0]


def bodies(conn, game_id):
    return [r[0] for r in conn.execute("SELECT body FROM comment WHERE game = ? AND deleted_at IS NULL ORDER BY id", (game_id,))]


def test_a_remark_with_a_card(app, conn):
    game_id, hs = started(conn)
    first_play(conn, game_id, hs, remark="  a   fine\nopening  ")
    assert bodies(conn, game_id) == ["a fine opening"]
    row = conn.execute("SELECT kind, play, user FROM comment").fetchone()
    assert (row["kind"], row["user"]) == ("move", 1) and row["play"] is not None


def test_a_remark_that_is_too_long_stops_the_move(app, conn):
    game_id, hs = started(conn)
    state = play.load(conn, game_id)
    with pytest.raises(play.PlayError, match="at most 500"):
        play.play_card(conn, hs[1]["id"], play.legal_cards(state, 1)[0], remark="x" * 501)
    assert conn.execute("SELECT count(*) FROM play").fetchone()[0] == 0


def test_players_remark_on_any_card_of_their_game(app, conn):
    game_id, hs = started(conn)
    play_id = first_play(conn, game_id, hs)
    play.add_remark(conn, game_id, 3, play_id, "  well played ")  # seat 3 (user 3) comments on seat 1's card
    assert bodies(conn, game_id) == ["well played"]
    with pytest.raises(play.PlayError, match="write something"):
        play.add_remark(conn, game_id, 3, play_id, "   ")
    with pytest.raises(play.PlayError, match="no such card"):
        play.add_remark(conn, game_id, 3, 9999, "x")
    with pytest.raises(play.PlayError, match="not a player"):
        play.add_remark(conn, game_id, 5, play_id, "x")  # nobody at this table


def test_you_can_edit_your_remark_for_a_while_and_delete_it_always(app, conn):
    game_id, hs = started(conn)
    play_id = first_play(conn, game_id, hs)
    play.add_remark(conn, game_id, 2, play_id, "first")
    comment = conn.execute("SELECT id FROM comment").fetchone()[0]
    play.edit_remark(conn, comment, 2, "second")
    row = conn.execute("SELECT body, edited_at FROM comment WHERE id = ?", (comment,)).fetchone()
    assert row["body"] == "second" and row["edited_at"] is not None
    for other in (1, 3):
        with pytest.raises(play.PlayError, match="your own"):
            play.edit_remark(conn, comment, other, "mine now")
        with pytest.raises(play.PlayError, match="your own"):
            play.delete_remark(conn, comment, other)
    conn.execute("UPDATE comment SET created_at = datetime('now', '-20 minutes') WHERE id = ?", (comment,))
    with pytest.raises(play.PlayError, match="too late"):
        play.edit_remark(conn, comment, 2, "third")
    play.delete_remark(conn, comment, 2)
    assert bodies(conn, game_id) == []
    assert conn.execute("SELECT deleted_at FROM comment WHERE id = ?", (comment,)).fetchone()[0] is not None  # kept
    with pytest.raises(play.PlayError):
        play.delete_remark(conn, comment, 2)  # already gone


def test_remarks_over_http_are_escaped_and_can_be_changed(app, conn):
    game_id, hs = started(conn)
    first_play(conn, game_id, hs)
    seats = {seat: Seat(app, token) for seat, token in tokens(conn, game_id).items()}
    html = seats[3].page()  # (not on turn: the box below the table)
    assert 'class="remark-form"' in html and "Send" in html
    # whose turn it is has the box with the card below the hand instead
    assert 'class="remark-form"' not in seats[2].page()
    play_id = conn.execute("SELECT id FROM play").fetchone()[0]
    page = seats[2].post("remark", play=play_id, body="<script>alert(1)</script> hi").get_data(as_text=True)
    assert "<script>alert(1)</script>" not in page and "&lt;script&gt;alert(1)&lt;/script&gt; hi" in page
    assert "bubble-edit" in page  # the author can change it ...
    assert "bubble-edit" not in seats[3].page()  # ... the others cannot
    comment = conn.execute("SELECT id FROM comment").fetchone()[0]
    page = seats[2].post("editremark", remark=comment, body="better").get_data(as_text=True)
    assert "better" in page and "(edited)" in page
    page = seats[2].post("deleteremark", remark=comment).get_data(as_text=True)
    assert "better" not in page
    # somebody else's remark: refused with a message
    seats[2].post("remark", play=play_id, body="mine")
    comment = conn.execute("SELECT id FROM comment WHERE deleted_at IS NULL").fetchone()[0]
    assert "your own remarks" in seats[3].post("deleteremark", remark=comment).get_data(as_text=True)


def test_the_remark_box_with_the_card(app, conn):
    game_id, hs = started(conn)
    seats = {seat: Seat(app, token) for seat, token in tokens(conn, game_id).items()}
    html = seats[1].page()
    assert 'name="remark"' in html
    import re
    card = re.findall(r'name="card" value="(\d+)"', html)[0]
    page = seats[1].post("play", card=card, remark="here we go").get_data(as_text=True)
    assert "here we go" in page
