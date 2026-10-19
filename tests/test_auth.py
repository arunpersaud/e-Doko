import re
from contextlib import closing

import pytest

from edoko import auth, db


def csrf(client):
    """Start a session and return its CSRF token."""
    page = client.get("/login").text
    return re.search(r'name="csrf" value="([^"]+)"', page)[1]


def request_link(client, app, address="a@x.org", **headers):
    token = csrf(client)
    response = client.post(
        "/login/email", data={"email": address, "csrf": token}, headers=headers
    )
    return response, token


def link_from(app):
    body = app.outbox[-1].get_content()
    return re.search(r"http://localhost:5000(/login/link/\S+)", body)[1]


def test_login_page(client):
    assert "Send me a login link" in client.get("/login").text


def test_link_login_flow(app, client):
    response, token = request_link(client, app)
    assert "If that address belongs to an account" in response.text
    assert len(app.outbox) == 1
    assert app.outbox[0]["To"] == "a@x.org"
    assert app.outbox[0]["Subject"] == "[DoKo] Your login link"
    path = link_from(app)

    # opening the link (a mail scanner might) does not use it up
    page = client.get(path)
    assert page.status_code == 200 and "Hello Ann" in page.text
    assert client.get(path).status_code == 200

    response = client.post(path, data={"csrf": token})
    assert response.status_code == 302
    assert "Ann" in client.get("/").text

    # single use
    other = app.test_client()
    assert other.get(path).status_code == 404


def test_unknown_address_gets_same_answer_and_no_mail(app, client):
    known, _ = request_link(client, app)
    unknown, _ = request_link(client, app, "nobody@x.org")
    assert known.text == unknown.text
    assert len(app.outbox) == 1


def test_address_case_and_blanks_do_not_matter(app, client):
    request_link(client, app, "  A@X.org ")
    assert len(app.outbox) == 1


def test_rate_limit_per_address(app, client):
    for _ in range(auth.LIMIT_ADDRESS + 2):
        request_link(client, app)
    assert len(app.outbox) == auth.LIMIT_ADDRESS


def test_rate_limit_per_client(app, client):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        for i in range(auth.LIMIT_CLIENT + 2):
            conn.execute(
                "INSERT INTO user (name, email) VALUES (?, ?)", (f"U{i}", f"u{i}@x.org")
            )
    for i in range(auth.LIMIT_CLIENT + 2):
        request_link(client, app, f"u{i}@x.org")
    assert len(app.outbox) == auth.LIMIT_CLIENT


def test_expired_link(app, client):
    request_link(client, app)
    path = link_from(app)
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE login_token SET expires_at = '2000-01-01 00:00:00'")
    assert client.get(path).status_code == 404


def test_disabled_user_gets_no_link_and_is_logged_out(app, client):
    request_link(client, app)
    path = link_from(app)
    _, token = request_link(client, app)
    client.post(path, data={"csrf": token})
    assert "Ann" in client.get("/").text
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET disabled_at = '2026-01-01 00:00:00'")
    assert "Ann" not in client.get("/").text
    count = len(app.outbox)
    request_link(client, app)
    assert len(app.outbox) == count


def test_link_in_german(app, client):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET language = 'de'")
    request_link(client, app)
    assert app.outbox[0]["Subject"] == "[DoKo] Dein Anmelde-Link"
    assert "Hallo Ann," in app.outbox[0].get_content()


@pytest.mark.parametrize(
    "target, expected",
    [
        ("/game/3/", "/game/3/"),
        ("//evil.org", "/"),
        ("https://evil.org", "/"),
        ("", "/"),
    ],
)
def test_next_is_only_a_path_on_this_site(app, client, target, expected):
    client.set_cookie("passkey_skip", "1")  # no passkey offer in between
    request_link(client, app)
    path = link_from(app)
    token = csrf(client)
    response = client.post(path, data={"csrf": token, "next": target})
    assert response.headers["Location"] == expected


def test_post_checks_csrf_and_origin(app, client):
    token = csrf(client)
    assert client.post("/logout").status_code == 403
    assert client.post("/logout", data={"csrf": "wrong"}).status_code == 403
    response = client.post(
        "/logout", data={"csrf": token}, headers={"Origin": "https://evil.org"}
    )
    assert response.status_code == 403
    response = client.post(
        "/logout", data={"csrf": token}, headers={"Origin": "http://localhost:5000"}
    )
    assert response.status_code == 302


def test_logout(app, client):
    _, token = request_link(client, app)
    client.post(link_from(app), data={"csrf": token})
    token = re.search(r'name="csrf" value="([^"]+)"', client.get("/").text)[1]
    client.post("/logout", data={"csrf": token})
    assert "Log in" in client.get("/").text


def test_login_required_redirects(app):
    from flask import g

    @app.get("/private")
    @auth.login_required
    def private():
        return g.user["name"]

    client = app.test_client()
    response = client.get("/private")
    assert response.headers["Location"] == "/login?next=/private"


@pytest.mark.parametrize(
    "headers, status",
    [
        # Firefox/Chrome with Referrer-Policy: no-referrer
        ({"Origin": "null", "Sec-Fetch-Site": "same-origin"}, 302),
        ({"Origin": "null"}, 403),
        ({"Origin": "null", "Sec-Fetch-Site": "cross-site"}, 403),
        ({"Sec-Fetch-Site": "cross-site"}, 403),
        ({"Sec-Fetch-Site": "same-site"}, 403),
        ({"Sec-Fetch-Site": "same-origin", "Origin": "http://localhost:5000"}, 302),
        ({"Sec-Fetch-Site": "same-origin", "Origin": "https://evil.org"}, 403),
    ],
)
def test_post_origin_variants(client, headers, status):
    token = csrf(client)
    response = client.post("/logout", data={"csrf": token}, headers=headers)
    assert response.status_code == status
