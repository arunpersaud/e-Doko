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
            "INSERT INTO avatar (user, source, image, content_type, chosen)"
            " VALUES (1, 'upload', ?, 'image/webp', 1)",
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


# --- uploads ---------------------------------------------------------------------


def picture(fmt="PNG", size=(300, 200), color="red", mode="RGB", **save):
    from io import BytesIO

    from PIL import Image

    out = BytesIO()
    Image.new(mode, size, color).save(out, format=fmt, **save)
    return out.getvalue()


def upload(client, data, name="me.png", token="t"):
    from io import BytesIO

    return client.post(
        "/account/avatar/upload",
        data={"csrf": token, "picture": (BytesIO(data), name)},
        content_type="multipart/form-data",
    )


def stored(app):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        return conn.execute("SELECT * FROM avatar").fetchall()


def test_process_makes_a_small_square_webp():
    from io import BytesIO

    from PIL import Image

    for fmt in ("PNG", "JPEG", "GIF", "WEBP"):
        out = avatars.process(picture(fmt, (400, 150)))
        image = Image.open(BytesIO(out))
        assert image.format == "WEBP" and image.size == (avatars.SIZE, avatars.SIZE)
        assert len(out) < 20_000


def test_process_drops_the_metadata():
    from io import BytesIO

    from PIL import Image

    exif = Image.Exif()
    exif[0x010E] = "a secret description"  # ImageDescription
    exif[0x0112] = 6  # orientation
    original = picture("JPEG", (200, 100), exif=exif)
    assert b"a secret description" in original
    out = avatars.process(original)
    assert b"a secret description" not in out
    assert not Image.open(BytesIO(out)).getexif()


def test_transparent_parts_become_white():
    from io import BytesIO

    from PIL import Image

    out = avatars.process(picture("PNG", (50, 50), (0, 0, 0, 0), "RGBA"))
    pixel = Image.open(BytesIO(out)).convert("RGB").getpixel((5, 5))
    assert all(v > 250 for v in pixel)


def test_process_refuses_what_is_not_a_usable_picture():
    import pytest

    for junk in (b"", b"hello", b"GIF89a" + b"\0" * 20, picture("PNG")[:40]):
        with pytest.raises(avatars.BadPicture):
            avatars.process(junk)
    # a format we do not accept, even though Pillow could read it
    with pytest.raises(avatars.BadPicture):
        avatars.process(picture("BMP"))


def test_process_refuses_huge_pictures(monkeypatch):
    import pytest

    monkeypatch.setattr(avatars, "MAX_PIXELS", 1000)
    with pytest.raises(avatars.BadPicture):
        avatars.process(picture("PNG", (100, 100)))


def test_upload_stores_the_cleaned_copy_and_serves_it(app):
    client = logged_in(app)
    response = upload(client, picture("JPEG", (640, 480)))
    assert response.status_code == 302
    (row,) = stored(app)
    assert row["source"] == "upload" and row["content_type"] == "image/webp"
    assert row["chosen"] == 1
    assert row["image"][:4] == b"RIFF" and len(row["image"]) < 20_000
    shown = client.get("/avatar/1")
    assert shown.mimetype == "image/webp" and shown.data == row["image"]
    assert "/avatar/1/upload" in client.get("/account").text
    # a new upload replaces the old one
    upload(client, picture("PNG", (100, 100), "blue"))
    assert len(stored(app)) == 1


def test_upload_of_junk_is_refused_with_a_message(app):
    client = logged_in(app)
    upload(client, b"<script>alert(1)</script>", "x.png")
    assert not stored(app)
    assert "This is not a picture we can use." in client.get("/account").text


def test_upload_without_a_file_and_too_big(app):
    client = logged_in(app)
    response = client.post(
        "/account/avatar/upload", data={"csrf": "t"}, content_type="multipart/form-data"
    )
    assert response.status_code == 302 and not stored(app)
    assert "Please choose a picture." in client.get("/account").text
    upload(client, b"x" * (avatars.MAX_UPLOAD + 1))
    assert "too big" in client.get("/account").text and not stored(app)
    # far too much is refused before it is read
    assert upload(client, b"x" * (avatars.MAX_UPLOAD + 200_000)).status_code == 413


def test_upload_needs_login_and_csrf(app):
    assert upload(app.test_client(), picture()).status_code in (302, 403)
    assert upload(logged_in(app), picture(), token="wrong").status_code == 403
    assert not stored(app)


def test_other_forms_are_still_limited_to_64_kb(app):
    client = logged_in(app)
    response = client.post(
        "/account/avatar/reset", data={"csrf": "t", "junk": "x" * 100_000}
    )
    assert response.status_code == 413


def test_choosing_the_generated_picture_keeps_the_copies(app):
    client = logged_in(app)
    upload(client, picture())
    client.post("/account/avatar/choose", data={"csrf": "t", "source": "generated"})
    (row,) = stored(app)  # the upload is still there, but not shown
    assert row["chosen"] == 0
    assert client.get("/avatar/1").mimetype == "image/svg+xml"
    client.post("/account/avatar/choose", data={"csrf": "t", "source": "upload"})
    assert stored(app)[0]["chosen"] == 1
    assert client.get("/avatar/1").mimetype == "image/webp"


def test_the_account_page_needs_a_login(app):
    assert app.test_client().get("/account").status_code == 302


# --- Gravatar ----------------------------------------------------------------------


def gravatar_request(client, token="t"):
    return client.post("/account/avatar/gravatar", data={"csrf": token})


def test_gravatar_url_sends_a_hash_not_the_address():
    import hashlib

    url = avatars.gravatar_url("  Test@Example.org ")
    digest = hashlib.sha256(b"test@example.org").hexdigest()
    assert url == f"https://gravatar.com/avatar/{digest}?s=192&d=404"
    assert "example" not in url and "@" not in url


def test_nothing_is_fetched_unless_the_user_asks(app, monkeypatch):
    def fail(email):
        raise AssertionError("Gravatar was contacted")

    monkeypatch.setattr(avatars, "fetch_gravatar", fail)
    client = logged_in(app)
    for url in ("/", "/account", "/avatar/1", "/game/1/", "/account/passkeys"):
        client.get(url)
    upload(client, picture())  # an upload does not contact it either


def test_the_user_fetches_their_gravatar(app, monkeypatch):
    asked = []
    monkeypatch.setattr(
        avatars, "fetch_gravatar", lambda email: asked.append(email) or picture("JPEG")
    )
    client = logged_in(app)
    assert gravatar_request(client).status_code == 302
    assert asked == ["a@x.org"]  # the account address
    (row,) = stored(app)
    assert row["source"] == "gravatar" and row["content_type"] == "image/webp"
    page = client.get("/account").text
    assert "/avatar/1/gravatar" in page and "Fetch again" in page


def test_fetching_again_gets_a_changed_picture(app, monkeypatch):
    pictures = iter([picture("PNG", color="red"), picture("PNG", color="blue")])
    monkeypatch.setattr(avatars, "fetch_gravatar", lambda email: next(pictures))
    client = logged_in(app)
    gravatar_request(client)
    first = stored(app)[0]["image"]
    gravatar_request(client)
    assert len(stored(app)) == 1 and stored(app)[0]["image"] != first


def test_gravatar_without_a_picture_or_unreachable_keeps_the_old_one(app, monkeypatch):
    client = logged_in(app)
    upload(client, picture())
    kept = stored(app)[0]["image"]

    def none(email):
        raise avatars.NoGravatar

    monkeypatch.setattr(avatars, "fetch_gravatar", none)
    gravatar_request(client)
    assert "Gravatar has no picture for your address." in client.get("/account").text

    def down(email):
        raise avatars.GravatarUnavailable("timeout")

    monkeypatch.setattr(avatars, "fetch_gravatar", down)
    gravatar_request(client)
    assert "try again later" in client.get("/account").text

    monkeypatch.setattr(avatars, "fetch_gravatar", lambda email: b"not a picture")
    gravatar_request(client)
    assert "try again later" in client.get("/account").text
    assert stored(app)[0]["image"] == kept and stored(app)[0]["source"] == "upload"


def test_gravatar_is_rate_limited(app, monkeypatch):
    calls = []
    monkeypatch.setattr(
        avatars, "fetch_gravatar", lambda email: calls.append(1) or picture()
    )
    client = logged_in(app)
    for _ in range(avatars.GRAVATAR_TRIES + 3):
        gravatar_request(client)
    assert len(calls) == avatars.GRAVATAR_TRIES


def test_gravatar_needs_login_and_csrf(app, monkeypatch):
    monkeypatch.setattr(avatars, "fetch_gravatar", lambda email: picture())
    assert gravatar_request(app.test_client()).status_code in (302, 403)
    assert gravatar_request(logged_in(app), token="wrong").status_code == 403
    assert not stored(app)


def test_redirects_only_to_gravatar_over_https():
    import urllib.error
    import urllib.request

    import pytest

    handler = avatars._OnlyGravatar()
    req = urllib.request.Request("https://gravatar.com/avatar/x")

    def follow(url):
        return handler.redirect_request(req, None, 302, "Found", {}, url)

    assert follow("https://secure.gravatar.com/avatar/x").full_url.startswith(
        "https://secure"
    )
    for bad in (
        "https://evil.example/avatar/x",
        "http://gravatar.com/avatar/x",
        "https://gravatar.com.evil.example/x",
        "https://127.0.0.1/x",
        "file:///etc/passwd",
    ):
        with pytest.raises(urllib.error.URLError):
            follow(bad)


# --- several kept pictures ---------------------------------------------------------


def chosen_sources(app):
    return {r["source"]: r["chosen"] for r in stored(app)}


def test_an_upload_and_a_gravatar_are_both_kept_and_you_can_switch(app, monkeypatch):
    monkeypatch.setattr(
        avatars, "fetch_gravatar", lambda email: picture("PNG", color="blue")
    )
    client = logged_in(app)
    upload(client, picture("PNG", color="red"))
    assert chosen_sources(app) == {"upload": 1}
    gravatar_request(client)  # the newest one is shown, the upload is kept
    assert chosen_sources(app) == {"upload": 0, "gravatar": 1}
    client.post("/account/avatar/choose", data={"csrf": "t", "source": "upload"})
    assert chosen_sources(app) == {"upload": 1, "gravatar": 0}
    page = client.get("/account").text
    assert "/avatar/1/upload" in page and "/avatar/1/gravatar" in page
    assert "/avatar/1/generated" in page


def test_choosing_something_that_is_not_kept_changes_nothing(app):
    client = logged_in(app)
    upload(client, picture())
    for source in ("gravatar", "nonsense", ""):
        client.post("/account/avatar/choose", data={"csrf": "t", "source": source})
    assert chosen_sources(app) == {"upload": 1}


def test_deleting_a_copy(app, monkeypatch):
    monkeypatch.setattr(avatars, "fetch_gravatar", lambda email: picture())
    client = logged_in(app)
    upload(client, picture())
    gravatar_request(client)
    client.post("/account/avatar/delete", data={"csrf": "t", "source": "gravatar"})
    assert chosen_sources(app) == {"upload": 0}  # the shown one is gone
    assert client.get("/avatar/1").mimetype == "image/svg+xml"
    client.post("/account/avatar/delete", data={"csrf": "t", "source": "upload"})
    assert not stored(app)


def test_only_one_picture_can_be_shown(app):
    import sqlite3

    import pytest

    client = logged_in(app)
    upload(client, picture())
    with (
        closing(db.connect(app.config["DATABASE"])) as conn,
        pytest.raises(sqlite3.IntegrityError),
    ):
        conn.execute(
            "INSERT INTO avatar (user, source, image, content_type, chosen)"
            " VALUES (1, 'gravatar', x'00', 'image/webp', 1)"
        )


def test_previews_are_only_for_your_own_pictures(app):
    client = logged_in(app)
    upload(client, picture())
    assert client.get("/avatar/1/upload").mimetype == "image/webp"
    assert client.get("/avatar/1/generated").mimetype == "image/svg+xml"
    assert client.get("/avatar/1/gravatar").status_code == 404  # none kept
    assert client.get("/avatar/1/nonsense").status_code == 404
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("INSERT INTO user (id, name, email) VALUES (2, 'Bob', 'b@x.org')")
    assert client.get("/avatar/2/generated").status_code == 404  # somebody else's
    assert app.test_client().get("/avatar/1/upload").status_code == 302


def test_the_account_page_shows_the_three_choices(app):
    page = logged_in(app).get("/account").text
    assert page.count('class="option') == 3
    assert "/avatar/1/generated" in page and "in use" in page
    assert "Fetch my Gravatar" in page


def test_migration_3_keeps_the_picture_that_was_shown(tmp_path):
    import sqlite3

    from edoko import migrations

    path = tmp_path / "v2.sqlite"  # a database as it was at version 2
    with closing(sqlite3.connect(path, autocommit=True)) as conn:
        conn.executescript(db.SCHEMA.read_text())
        conn.executescript(
            migrations.MIGRATIONS[2] + "PRAGMA user_version = 2;"
            "INSERT INTO user (id, name, email) VALUES (1, 'Ann', 'a@x.org');"
            "INSERT INTO avatar (user, source, image, content_type)"
            " VALUES (1, 'gravatar', x'0102', 'image/webp');"
        )
    assert migrations.migrate(path)[-1] == migrations.LATEST
    with closing(sqlite3.connect(path)) as conn:
        assert conn.execute("SELECT source, chosen, image FROM avatar").fetchall() == [
            ("gravatar", 1, b"\x01\x02")
        ]
