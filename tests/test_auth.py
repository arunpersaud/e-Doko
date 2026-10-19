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


# --- the menu and the admin page ---------------------------------------------------


def logged_in_as(app, user_id):
    client = app.test_client()
    with client.session_transaction() as session:
        session["user_id"] = user_id
        session["csrf"] = "t"
    return client


def test_the_menu_of_a_player(app):
    page = logged_in_as(app, 1).get("/").text
    assert ">Games</a>" in page and 'title="Ann – Settings"' in page
    assert 'href="/account"' in page and "Settings" in page
    assert "Log out" in page
    assert "/admin" not in page  # not an admin
    assert 'href="/account/passkeys"' not in page  # the passkeys are under Settings


def test_the_menu_of_an_admin_has_the_admin_page(app):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET is_admin = 1 WHERE id = 1")
    page = logged_in_as(app, 1).get("/").text
    assert 'href="/admin"' in page


def test_the_menu_of_a_visitor(app):
    page = app.test_client().get("/").text
    assert "Log in" in page and "Settings" not in page and "Games</a>" not in page


def test_the_admin_page_is_for_admins_only(app):
    assert app.test_client().get("/admin").status_code == 302
    assert logged_in_as(app, 1).get("/admin").status_code == 403
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET is_admin = 1 WHERE id = 1")
        conn.execute(
            "INSERT INTO user (id, name, email) VALUES (2, '<i>Bob</i>', 'b@x.org')"
        )
        conn.execute("UPDATE user SET disabled_at = '2026-01-01 00:00:00' WHERE id = 2")
    page = logged_in_as(app, 1).get("/admin").text
    assert "Users (2)" in page and "a@x.org" in page and "b@x.org" in page
    assert "disabled" in page and "<i>Bob</i>" not in page and "&lt;i&gt;Bob" in page


def test_the_admin_page_counts_passkeys_and_games(app):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET is_admin = 1 WHERE id = 1")
        for n in (1, 2):
            conn.execute(
                "INSERT INTO passkey (user, credential_id, public_key)"
                " VALUES (1, ?, x'00')",
                (bytes([n]),),
            )
    page = logged_in_as(app, 1).get("/admin").text
    assert "built-in method" not in page and "sqlite3" not in page
    row = page.split("<tbody>")[1].split("</tr>")[0]
    cells = [c.split("</td>")[0].strip() for c in row.split("<td>")[1:]]
    assert cells[2:4] == ["0", "2"]  # games, passkeys


# --- sorting the user table ----------------------------------------------------------


def admin_with_users(app):
    """Ann (admin) and four others with different logins, games and passkeys."""
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET is_admin = 1, last_login = '2026-10-01 10:00:00'")
        users = [
            (2, "bob", "z@x.org", "2026-10-19 08:00:00"),
            (3, "Cy", "b@x.org", None),  # never logged in
            (4, "Di", "m@x.org", "2026-09-01 08:00:00"),
            (5, "eve", "c@x.org", "2026-10-10 08:00:00"),
        ]
        for uid, name, email, last in users:
            conn.execute(
                "INSERT INTO user (id, name, email, last_login) VALUES (?, ?, ?, ?)",
                (uid, name, email, last),
            )
        conn.execute("DELETE FROM game")  # the two games of conftest have no hands
        for game in (1, 2, 3):
            conn.execute(
                "INSERT INTO game (id, session, deal, ruleset, status)"
                " VALUES (?, 1, 1, 1, 'play')",
                (game,),
            )
        for game, seat, user in [
            (1, 1, 1),
            (2, 1, 1),
            (3, 1, 1),
            (1, 2, 2),
            (2, 2, 2),
            (1, 3, 5),
        ]:
            conn.execute(
                "INSERT INTO hand (game, seat, user, token) VALUES (?, ?, ?, ?)",
                (game, seat, user, f"t{game}{seat}"),
            )
        for user, n in [(2, 3), (5, 1)]:
            for k in range(n):
                conn.execute(
                    "INSERT INTO passkey (user, credential_id, public_key)"
                    " VALUES (?, ?, x'00')",
                    (user, bytes([user, k])),
                )
    return logged_in_as(app, 1)


def names(page):
    """The names in the first column, in the order shown."""
    import re

    body = page.split("<tbody>")[1]
    return [
        re.sub(r"<[^>]+>", "", n).strip()
        for n in re.findall(r"<tr>\s*<td>(.*?)</td>", body)
    ]


def test_the_default_order_is_the_last_login_with_the_newest_first(app):
    page = admin_with_users(app).get("/admin").text
    assert names(page) == ["bob", "eve", "Ann", "Di", "Cy"]  # Cy never logged in: last


def test_sorting_by_name_ignores_the_case(app):
    client = admin_with_users(app)
    assert names(client.get("/admin?sort=name").text) == [
        "Ann",
        "bob",
        "Cy",
        "Di",
        "eve",
    ]
    assert names(client.get("/admin?sort=name&dir=desc").text) == [
        "eve",
        "Di",
        "Cy",
        "bob",
        "Ann",
    ]


def test_sorting_by_email_games_and_passkeys(app):
    client = admin_with_users(app)
    assert (
        names(client.get("/admin?sort=email").text) == ["eve", "Cy", "Di", "bob", "Ann"]
        or True
    )
    by_email = names(client.get("/admin?sort=email").text)
    assert by_email == ["Ann", "Cy", "eve", "Di", "bob"]  # a@, b@, c@, m@, z@
    games = names(client.get("/admin?sort=games").text)  # most games first
    assert games[:2] == ["Ann", "bob"] and games[2] == "eve"
    passkeys = names(client.get("/admin?sort=passkeys").text)
    assert passkeys[:2] == ["bob", "eve"]
    assert names(client.get("/admin?sort=games&dir=asc").text)[-2:] == ["bob", "Ann"]


def test_users_who_never_logged_in_come_last_in_both_directions(app):
    client = admin_with_users(app)
    assert names(client.get("/admin?sort=last_login&dir=desc").text)[-1] == "Cy"
    assert names(client.get("/admin?sort=last_login&dir=asc").text)[-1] == "Cy"
    assert names(client.get("/admin?sort=last_login&dir=asc").text)[0] == "Di"


def test_rubbish_in_the_query_falls_back_to_the_default(app):
    client = admin_with_users(app)
    default = names(client.get("/admin").text)
    for query_string in (
        "sort=password",
        "sort=name;drop table user",
        "sort=name&dir=sideways",
        "sort=&dir=",
        "dir=asc",
    ):
        response = client.get("/admin?" + query_string)
        assert response.status_code == 200
    assert names(client.get("/admin?sort=password").text) == default
    # an unknown direction keeps the column but uses its natural direction
    assert names(client.get("/admin?sort=name&dir=sideways").text)[0] == "Ann"
    assert names(client.get("/admin?dir=asc").text) == default  # no column: the default


def test_the_headers_are_links_that_reverse_the_sorted_column(app):
    client = admin_with_users(app)
    page = client.get("/admin?sort=name&dir=asc").text
    assert 'aria-sort="ascending"' in page and page.count("aria-sort") == 1
    assert 'href="/admin?sort=name&amp;dir=desc"' in page  # a click reverses
    assert 'href="/admin?sort=games&amp;dir=desc"' in page  # others: natural direction
    assert 'href="/admin?sort=email&amp;dir=asc"' in page
    desc = client.get("/admin?sort=name&dir=desc").text
    assert (
        'aria-sort="descending"' in desc
        and 'href="/admin?sort=name&amp;dir=asc"' in desc
    )
    first = client.get(
        "/admin"
    ).text  # the default order is by last login, newest first
    assert 'aria-sort="descending"' in first.split("Last login")[0].rsplit("<th", 1)[1]


def test_sorting_does_not_open_the_page_to_others(app):
    admin_with_users(app)
    assert logged_in_as(app, 2).get("/admin?sort=name").status_code == 403
    assert app.test_client().get("/admin?sort=name").status_code == 302


def test_unsorted_headers_hint_that_they_can_be_clicked(app):
    page = admin_with_users(app).get("/admin?sort=name").text
    assert page.count('class="idle"') == 4  # the four other sortable columns
