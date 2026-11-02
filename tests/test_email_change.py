import re
from contextlib import closing

from edoko import db


def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html)[1]


def log_in(client):
    with client.session_transaction() as s:
        s["user_id"] = 1
    return client


def user_email(app):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        return conn.execute("SELECT email FROM user WHERE id = 1").fetchone()[0]


def ask(client, address):
    token = csrf(client.get("/account").text)
    return client.post("/account/email", data={"csrf": token, "email": address})


def link_in(message):
    return re.search(r"http://\S+/account/email/\S+", message.get_content())[0]


def test_change_needs_the_link_and_tells_the_old_address(app, client):
    app.config["CONTACT_EMAIL"] = "admin@doko.example"
    log_in(client)
    ask(client, "New@X.org")
    assert user_email(app) == "a@x.org"  # nothing yet
    to_new = [m for m in app.outbox if m["To"] == "new@x.org"]
    to_old = [m for m in app.outbox if m["To"] == "a@x.org"]
    assert len(to_new) == 1 and len(to_old) == 1
    assert "admin@doko.example" in to_old[0].get_content()
    assert "new@x.org" in to_old[0].get_content()
    link = link_in(to_new[0])
    page = client.get(link[len("http://localhost:5000"):])
    assert "new@x.org" in page.text
    client.post(link[len("http://localhost:5000"):], data={"csrf": csrf(page.text)})
    assert user_email(app) == "new@x.org"
    assert app.outbox[-1]["To"] == "a@x.org"  # and told afterwards
    # a link works once
    assert client.get(link[len("http://localhost:5000"):]).status_code == 404


def test_the_link_needs_the_account_owner(app, client):
    log_in(client)
    ask(client, "new@x.org")
    path = link_in([m for m in app.outbox if m["To"] == "new@x.org"][0])[len("http://localhost:5000"):]
    other = app.test_client()
    assert other.get(path).status_code in (302, 401, 403)
    assert user_email(app) == "a@x.org"


def test_taken_address_sends_nothing_but_looks_the_same(app, client):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("INSERT INTO user (id, name, email) VALUES (2, 'Bob', 'b@x.org')")
    log_in(client)
    response = ask(client, "b@x.org")
    assert response.status_code == 302 and app.outbox == []


def test_bad_and_same_address_and_rate_limit(app, client):
    log_in(client)
    ask(client, "nonsense")
    ask(client, "a@x.org")
    assert app.outbox == []
    for n in range(5):
        ask(client, f"n{n}@x.org")
    assert len([m for m in app.outbox if m["To"].startswith("n")]) == 3


def test_login_links_to_the_old_address_die(app, client):
    log_in(client)
    ask(client, "new@x.org")
    path = link_in([m for m in app.outbox if m["To"] == "new@x.org"][0])[len("http://localhost:5000"):]
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute(
            "INSERT INTO login_token (user, token_hash, expires_at) VALUES (1, 'x', '2999-01-01 00:00:00')"
        )
    page = client.get(path)
    client.post(path, data={"csrf": csrf(page.text)})
    with closing(db.connect(app.config["DATABASE"])) as conn:
        assert conn.execute("SELECT count(*) FROM login_token").fetchone()[0] == 0


def test_the_notice_comes_in_the_users_language(app, client):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET language = 'de' WHERE id = 1")
    log_in(client)
    ask(client, "new@x.org")
    old = [m for m in app.outbox if m["To"] == "a@x.org"][0]
    assert "jemand hat darum gebeten" in old.get_content()
    assert "Hello" not in old.get_content()
