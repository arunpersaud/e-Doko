import sys
import xml.etree.ElementTree as ET
from contextlib import closing
from pathlib import Path

from edoko import avatars, db

sys.path.insert(0, str(Path(__file__).parent))


def logged_in(app, user_id=1):
    client = app.test_client()
    with client.session_transaction() as session:
        session["user_id"] = user_id
        session["csrf"] = "t"
    return client


def test_identicon_is_valid_svg_and_deterministic():
    svg = avatars.identicon(1, "secret")
    assert svg == avatars.identicon(1, "secret")
    root = ET.fromstring(svg)
    assert root.tag.endswith("svg")
    assert "script" not in svg and "href" not in svg and "style" not in svg


def test_identicons_differ_between_users_and_secrets():
    pictures = {avatars.identicon(i, "secret") for i in range(1, 30)}
    assert len(pictures) > 25
    assert avatars.identicon(1, "other") != avatars.identicon(1, "secret")


def test_identicon_is_symmetric():
    root = ET.fromstring(avatars.identicon(7, "secret"))
    ns = "{http://www.w3.org/2000/svg}"
    cells = {
        (int(r.get("x")), int(r.get("y"))) for r in root.iter(f"{ns}rect") if r.get("x")
    }
    assert cells == {(4 - x, y) for x, y in cells}


def test_avatar_route(app):
    response = logged_in(app).get("/avatar/1")
    assert response.status_code == 200
    assert response.mimetype == "image/svg+xml"
    assert response.headers["Cache-Control"] == "private, no-cache"
    # revalidated with the ETag, not downloaded again
    again = logged_in(app).get(
        "/avatar/1", headers={"If-None-Match": response.headers["ETag"]}
    )
    assert again.status_code == 304


def test_avatar_needs_a_login_and_a_user(app):
    assert app.test_client().get("/avatar/1").status_code == 302
    assert logged_in(app).get("/avatar/999").status_code == 404


def test_a_stored_picture_is_served_instead(app):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute(
            "INSERT INTO avatar (user, source, image, content_type)"
            " VALUES (1, 'upload', ?, 'image/webp')",
            (b"RIFFxxxxWEBP",),
        )
    response = logged_in(app).get("/avatar/1")
    assert response.mimetype == "image/webp" and response.data == b"RIFFxxxxWEBP"
    assert logged_in(app).get("/avatar/2").status_code == 404  # no such user


def test_the_game_page_shows_the_avatars(app):
    from test_games import add_game

    with closing(db.connect(app.config["DATABASE"])) as conn:
        for uid in (2, 3, 4):
            conn.execute(
                "INSERT INTO user (id, name, email) VALUES (?, ?, ?)",
                (uid, f"U{uid}", f"u{uid}@x.org"),
            )
        conn.execute("DELETE FROM game")
        add_game(conn, 10, 1, "gameover", [1, 2, 3, 4])
    page = logged_in(app).get("/game/10/").text
    assert page.count('src="/avatar/') == 4
