import re
from contextlib import closing

import pytest
from fake_authenticator import FakeAuthenticator

from edoko import db


def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html)[1]


def meta_token(html):
    return re.search(r'name="csrf-token" content="([^"]+)"', html)[1]


def ask(client, address):
    token = csrf(client.get("/signup").text)
    return client.post("/signup", data={"csrf": token, "email": address})


def link_in(app):
    return re.search(r"http://\S+/signup/\S+", app.outbox[-1].get_content())[0]


def test_the_start_page_explains_and_sets_no_cookie(client):
    response = client.get("/")
    assert "Doppelkopf" in response.text and 'href="/signup"' in response.text
    assert "Set-Cookie" not in response.headers


def test_a_new_address_gets_a_sign_up_link(app, client):
    response = ask(client, "New@Example.org")
    assert response.status_code == 200 and "Check your mail" in response.text
    assert [m["To"] for m in app.outbox] == ["new@example.org"]
    path = "/" + link_in(app).split("/", 3)[3]
    assert "Choose your name" in client.get(path).text


def test_an_existing_address_gets_a_login_link_and_the_same_answer(app, client):
    new = ask(client, "fresh@example.org").text
    app.outbox.clear()
    old = ask(app.test_client(), "a@x.org").text
    assert old == new  # (nobody can tell who has an account)
    assert "login" in app.outbox[-1].get_content().lower()
    assert "/signup/" not in app.outbox[-1].get_content()


def test_requests_are_limited(app, client):
    for _ in range(3):
        ask(client, "many@example.org")
    assert len(app.outbox) == 3
    ask(client, "many@example.org")
    assert len(app.outbox) == 3  # the fourth gets the page but no mail


def test_nonsense_addresses_send_nothing(app, client):
    ask(client, "not an address")
    assert app.outbox == []


def test_the_whole_sign_up_with_a_passkey(app, client):
    ask(client, "new@example.org")
    path = "/" + link_in(app).split("/", 3)[3]
    html = client.get(path).text
    refused = client.post(path, data={"csrf": csrf(html), "name": "Ann"})  # taken
    assert refused.status_code == 400 and "already uses that name" in refused.text
    page = client.post(path, data={"csrf": csrf(html), "name": "  Nina   Neu "})
    assert page.status_code == 200 and "Nina Neu" in page.text
    with closing(db.connect(app.config["DATABASE"])) as conn:  # nothing yet
        assert conn.execute("SELECT count(*) FROM user WHERE email = 'new@example.org'").fetchone()[0] == 0
    token = meta_token(page.text)
    authenticator = FakeAuthenticator()
    options = client.post("/passkey/register/options", json={"name": "phone"}, headers={"X-CSRF-Token": token})
    assert options.status_code == 200
    done = client.post("/passkey/register/verify", json=authenticator.register(options.json), headers={"X-CSRF-Token": token})
    assert done.status_code == 200 and done.json == {"ok": True, "next": "/"}
    with closing(db.connect(app.config["DATABASE"])) as conn:
        user = conn.execute("SELECT * FROM user WHERE email = 'new@example.org'").fetchone()
        assert user["name"] == "Nina Neu"
        assert conn.execute("SELECT count(*) FROM passkey WHERE user = ?", (user["id"],)).fetchone()[0] == 1
    assert "Nina Neu" in client.get("/account").text  # logged in
    assert "does not work any more" in client.get(path).text  # the link is used up


def test_no_account_without_a_sign_up_link(client):
    with client.session_transaction() as session:
        session["csrf"] = "t"
    assert client.post("/passkey/register/options", json={}, headers={"X-CSRF-Token": "t"}).status_code == 401
