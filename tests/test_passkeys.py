import re
from contextlib import closing

import pytest
from fake_authenticator import FakeAuthenticator

from edoko import db


def post_json(client, url, data, token):
    return client.post(url, json=data, headers={"X-CSRF-Token": token})


def page_token(client, url="/account/passkeys"):
    return re.search(r'name="csrf-token" content="([^"]+)"', client.get(url).text)[1]


@pytest.fixture
def logged_in(app, client):
    """The client, logged in as Ann (id 1), and the page token."""
    with client.session_transaction() as session:
        session["user_id"] = 1
        session["csrf"] = "test-token"
    return client


def register(client, authenticator, name=""):
    token = page_token(client)
    response = post_json(client, "/passkey/register/options", {"name": name}, token)
    assert response.status_code == 200
    return post_json(
        client,
        "/passkey/register/verify",
        authenticator.register(response.json),
        token,
    )


def test_register_and_login(app, client, logged_in):
    authenticator = FakeAuthenticator()
    response = register(client, authenticator, "my phone")
    assert response.json["ok"]
    assert "my phone" in client.get("/account/passkeys").text

    # log in from a fresh browser
    other = app.test_client()
    token = re.search(r'name="csrf-token" content="([^"]+)"', other.get("/login").text)[
        1
    ]
    options = post_json(other, "/passkey/login/options", {"next": "/game/3/"}, token)
    handle = None
    response = post_json(
        other, "/passkey/login/verify", authenticator.login(options.json, handle), token
    )
    assert response.status_code == 200, response.json
    assert response.json == {"ok": True, "next": "/game/3/"}
    assert "Ann" in other.get("/").text
    with closing(db.connect(app.config["DATABASE"])) as conn:
        row = conn.execute("SELECT sign_count, last_used_at FROM passkey").fetchone()
        assert row["sign_count"] == 1 and row["last_used_at"]


def test_login_with_replayed_counter_is_refused(app, client, logged_in):
    authenticator = FakeAuthenticator()
    register(client, authenticator)
    other = app.test_client()
    token = re.search(r'name="csrf-token" content="([^"]+)"', other.get("/login").text)[
        1
    ]
    options = post_json(other, "/passkey/login/options", {}, token)
    assertion = authenticator.login(options.json)
    assert (
        post_json(other, "/passkey/login/verify", assertion, token).status_code == 200
    )

    again = app.test_client()
    token = re.search(r'name="csrf-token" content="([^"]+)"', again.get("/login").text)[
        1
    ]
    options = post_json(again, "/passkey/login/options", {}, token)
    authenticator.counter = 0  # a cloned key that starts counting again
    response = post_json(
        again, "/passkey/login/verify", authenticator.login(options.json), token
    )
    assert response.status_code == 400


def test_login_with_wrong_origin_is_refused(app, client, logged_in):
    register(client, FakeAuthenticator())
    evil = FakeAuthenticator(origin="https://evil.org")
    evil.credential_id = b"x"
    other = app.test_client()
    token = re.search(r'name="csrf-token" content="([^"]+)"', other.get("/login").text)[
        1
    ]
    options = post_json(other, "/passkey/login/options", {}, token)
    response = post_json(
        other, "/passkey/login/verify", evil.login(options.json), token
    )
    assert response.status_code == 400


def test_unknown_passkey(app, client):
    token = re.search(
        r'name="csrf-token" content="([^"]+)"', client.get("/login").text
    )[1]
    options = post_json(client, "/passkey/login/options", {}, token)
    response = post_json(
        client, "/passkey/login/verify", FakeAuthenticator().login(options.json), token
    )
    assert response.status_code == 400


def test_disabled_user_cannot_use_passkey(app, client, logged_in):
    authenticator = FakeAuthenticator()
    register(client, authenticator)
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET disabled_at = '2026-01-01 00:00:00'")
    other = app.test_client()
    token = re.search(r'name="csrf-token" content="([^"]+)"', other.get("/login").text)[
        1
    ]
    options = post_json(other, "/passkey/login/options", {}, token)
    response = post_json(
        other, "/passkey/login/verify", authenticator.login(options.json), token
    )
    assert response.status_code == 400


def test_register_needs_login(client):
    token = re.search(
        r'name="csrf-token" content="([^"]+)"', client.get("/login").text
    )[1]
    response = post_json(client, "/passkey/register/options", {}, token)
    assert response.status_code == 401


def test_rename_and_remove(app, client, logged_in):
    register(client, FakeAuthenticator(), "old")
    token = page_token(client)
    with closing(db.connect(app.config["DATABASE"])) as conn:
        key = conn.execute("SELECT id FROM passkey").fetchone()[0]
    client.post(
        f"/account/passkeys/{key}/rename", data={"name": " new  name ", "csrf": token}
    )
    assert "new name" in client.get("/account/passkeys").text
    client.post(f"/account/passkeys/{key}/delete", data={"csrf": token})
    assert "You have no passkey yet" in client.get("/account/passkeys").text


def test_cannot_touch_somebody_elses_passkey(app, client, logged_in):
    register(client, FakeAuthenticator())
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("INSERT INTO user (id, name, email) VALUES (2, 'Bob', 'b@x.org')")
        key = conn.execute("SELECT id FROM passkey").fetchone()[0]
    with client.session_transaction() as session:
        session["user_id"] = 2
    token = page_token(client)
    assert (
        client.post(f"/account/passkeys/{key}/delete", data={"csrf": token}).status_code
        == 404
    )


def test_offer_after_link_login_and_skip(app, client):
    token = re.search(r'name="csrf" value="([^"]+)"', client.get("/login").text)[1]
    client.post("/login/email", data={"email": "a@x.org", "csrf": token})
    path = re.search(
        r"http://localhost:5000(/login/link/\S+)", app.outbox[-1].get_content()
    )[1]
    response = client.post(path, data={"csrf": token, "next": "/game/3/"})
    assert response.headers["Location"] == "/account/passkeys/offer?next=/game/3/"
    page = client.get(response.headers["Location"]).text
    assert "Skip for now" in page
    skip = client.post(
        "/account/passkeys/skip",
        data={
            "csrf": re.search(r'name="csrf-token" content="([^"]+)"', page)[1],
            "next": "/game/3/",
        },
    )
    assert skip.headers["Location"] == "/game/3/"
    assert "passkey_skip=1" in skip.headers["Set-Cookie"]
    assert "Max-Age=2592000" in skip.headers["Set-Cookie"]


def test_no_offer_when_user_has_a_passkey(app, client, logged_in):
    register(client, FakeAuthenticator())
    fresh = app.test_client()
    token = re.search(r'name="csrf" value="([^"]+)"', fresh.get("/login").text)[1]
    fresh.post("/login/email", data={"email": "a@x.org", "csrf": token})
    path = re.search(
        r"http://localhost:5000(/login/link/\S+)", app.outbox[-1].get_content()
    )[1]
    response = fresh.post(path, data={"csrf": token})
    assert response.headers["Location"] == "/"


def test_anonymous_visitors_get_no_cookie(client):
    response = client.get("/")
    assert "Set-Cookie" not in response.headers
