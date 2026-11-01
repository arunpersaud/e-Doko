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
    # (the address limit is a second line of defence: it counts mails, one per expired link)
    for _ in range(3):
        ask(client, "many@example.org")
        with closing(db.connect(app.config["DATABASE"])) as conn:
            conn.execute("UPDATE signup_token SET expires_at = '2000-01-01 00:00:00'")
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


# --- protection, the setting and invitations ---------------------------------


def as_admin(client):
    with client.session_transaction() as session:
        session["user_id"] = 1
        session["csrf"] = "t"
    return client


@pytest.fixture
def admin(app):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET is_admin = 1 WHERE id = 1")
    return as_admin(app.test_client())


def post_admin(client, url, **data):
    return client.post(url, data={"csrf": "t", **data}, follow_redirects=True)


def test_too_many_requests_from_one_client_get_a_message_with_minutes(app, client):
    for n in range(10):
        assert ask(client, f"p{n}@example.org").status_code == 200
    response = ask(client, "one-more@example.org")
    assert response.status_code == 429 and re.search(r"about \d+ minutes", response.text)


def test_the_global_cap_protects_the_mail_reputation(app, client, monkeypatch):
    from edoko import signup

    monkeypatch.setattr(signup, "CAP_HOUR", 2)
    for n in range(2):
        ask(app.test_client(), f"c{n}@example.org")
    app.outbox.clear()
    response = ask(app.test_client(), "late@example.org")
    assert response.status_code == 503 and "Many people" in response.text
    assert app.outbox == []


def test_no_sign_up_mail_to_an_address_that_bounced(app, client):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute(
            "INSERT INTO mail_bounce (notice, address, kind, occurred_at) VALUES ('n', 'gone@example.org', 'permanent', '2026-01-01')"
        )
    assert ask(client, "gone@example.org").status_code == 200
    assert app.outbox == []


def test_the_hidden_field_catches_bots(app, client):
    token = csrf(client.get("/signup").text)
    response = client.post("/signup", data={"csrf": token, "email": "bot@example.org", "website": "http://spam"})
    assert response.status_code == 200 and app.outbox == []


def test_the_setting_decides_who_can_sign_up(app, client, admin):
    post_admin(admin, "/admin/signup", mode="invite")
    assert client.get("/signup").status_code == 403
    assert "by invitation" in client.get("/").text and 'href="/signup"' not in client.get("/").text
    # an open request link stops working when the setting turns away from open
    post_admin(admin, "/admin/signup", mode="open")
    ask(client, "early@example.org")
    path = "/" + link_in(app).split("/", 3)[3]
    assert client.get(path).status_code == 200
    post_admin(admin, "/admin/signup", mode="invite")
    assert client.get(path).status_code == 404
    post_admin(admin, "/admin/signup", mode="nonsense")
    assert "by invitation" in client.get("/").text  # unchanged


def test_an_admin_invites_by_email(app, client, admin):
    post_admin(admin, "/admin/signup", mode="invite")
    page = post_admin(admin, "/admin/invite", email="Friend@Example.org").text
    assert "Invitation sent to friend@example.org" in page and "friend@example.org" in page
    mail = app.outbox[-1]
    assert mail["To"] == "friend@example.org" and "invited" in mail.get_content()
    path = "/" + re.search(r"http://\S+/signup/\S+", mail.get_content())[0].split("/", 3)[3]
    assert client.get(path).status_code == 200  # works although sign-up is by invitation
    post_admin(admin, "/admin/signup", mode="closed")
    assert client.get(path).status_code == 404  # closed means closed
    assert "has an account already" in post_admin(admin, "/admin/invite", email="a@x.org").text
    assert client.post("/admin/invite", data={"csrf": "x", "email": "z@example.org"}).status_code == 403  # not logged in


def test_cli_invite_and_mode(app):
    runner = app.test_cli_runner()
    assert runner.invoke(args=["edoko", "signup-mode", "invite"]).output.strip() == "invite"
    assert runner.invoke(args=["edoko", "signup-mode"]).output.strip() == "invite"
    result = runner.invoke(args=["edoko", "invite", "cli@example.org"])
    assert result.exit_code == 0 and "/signup/" in result.output and app.outbox == []
    result = runner.invoke(args=["edoko", "invite", "--mail", "cli2@example.org"])
    assert [m["To"] for m in app.outbox] == ["cli2@example.org"]
    assert runner.invoke(args=["edoko", "invite", "a@x.org"]).exit_code != 0


def test_only_one_sign_up_mail_while_its_link_works(app, client):
    for _ in range(3):
        assert "Check your mail" in ask(app.test_client(), "bot@example.org").text
    assert [m["To"] for m in app.outbox] == ["bot@example.org"]
    # once the link has expired, the address may ask again
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE signup_token SET expires_at = '2000-01-01 00:00:00'")
    ask(app.test_client(), "bot@example.org")
    assert len(app.outbox) == 2


def test_an_existing_address_gets_one_login_mail_an_hour_from_the_form(app):
    for _ in range(3):
        ask(app.test_client(), "a@x.org")
    assert len(app.outbox) == 1
