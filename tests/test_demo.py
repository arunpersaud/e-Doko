import re
from contextlib import closing

import pytest

from edoko import create_app, db, mail, play, rules

RS = rules.Rules("secondwins", "second", "poverty", "1st-own-card")


@pytest.fixture
def demo_app(database):
    with closing(db.connect(database)) as c:
        for i in range(2, 5):
            c.execute("INSERT INTO user (id, name, email) VALUES (?, ?, ?)", (i, f"User{i}", f"u{i}@x.org"))
    return create_app({"TESTING": True, "DATABASE": str(database), "DEMO": 1, "MAIL_BACKEND": "smtp"})


def test_mail_is_kept_not_sent(demo_app):
    with demo_app.app_context():
        assert mail.send("a@x.org", "Hi", "see http://localhost:5000/abc here")
    assert demo_app.outbox == []
    client = demo_app.test_client()
    html = client.get("/demo/mail/1").get_data(as_text=True)
    assert "[DoKo] Hi" in html
    assert '<a href="/abc">http://localhost:5000/abc</a>' in html


def test_names_are_buttons_with_the_mail_and_a_login(demo_app, database):
    with demo_app.app_context(), closing(db.connect(database)) as c:
        game_id = play.create_game(c, [1, 2, 3, 4], RS)
    with demo_app.app_context():
        mail.send("u2@x.org", "Your turn", "<script>alert(1)</script> http://localhost:5000/x")
    client = demo_app.test_client()
    html = client.get(f"/demo/").get_data(as_text=True)
    csrf = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
    assert client.post("/demo/login/1", data={"csrf": csrf}).status_code == 302
    page = client.get(f"/game/{game_id}/").get_data(as_text=True)
    assert page.count('popovertarget="demo-') == 4
    assert "Continue as User2" in page
    assert "&lt;script&gt;alert(1)" in page and "<script>alert" not in page
    assert "[DoKo] Your turn" in page


def test_the_demo_page_is_absent_outside_demo_mode(client):
    assert client.get("/demo/").status_code == 404


def test_demo_refuses_real_mail_credentials(database):
    with pytest.raises(RuntimeError):
        create_app({"TESTING": True, "DATABASE": str(database), "DEMO": 1, "SMTP_PASSWORD": "x"})


def test_a_bounced_address_still_shows_its_mail_in_demo(demo_app, database):
    with closing(db.connect(database)) as c:
        c.execute("UPDATE user SET mail_status = 'bounced' WHERE id = 2")
        user = c.execute("SELECT * FROM user WHERE id = 2").fetchone()
    with demo_app.app_context():
        assert mail.send_template(user, "Hello", "login", link="http://localhost:5000/l", name="x") is not None


def test_demo_over_plain_http_accepts_origin_null_with_the_token(demo_app):
    client = demo_app.test_client()
    html = client.get("/demo/").get_data(as_text=True)
    token = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
    assert client.post("/demo/login/1", data={"csrf": token}, headers={"Origin": "null"}).status_code == 302
    assert client.post("/demo/login/1", data={"csrf": "wrong"}, headers={"Origin": "null"}).status_code == 403


def test_continue_as_returns_to_the_page_and_only_to_this_site(demo_app):
    client = demo_app.test_client()
    for sent, expected in (("/game/1/trick/2", "/game/1/trick/2"), ("//evil.example/", "/"),
                           ("https://evil.example/", "/"), ("", "/")):
        html = client.get("/demo/").get_data(as_text=True)  # (a login renews the token)
        token = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
        response = client.post("/demo/login/2", data={"csrf": token, "next": sent})
        assert response.status_code == 302 and response.headers["Location"].endswith(expected), sent
        assert "evil" not in response.headers["Location"]
