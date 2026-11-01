def test_index(client):
    response = client.get("/")
    assert response.status_code == 200
    assert b"2 games so far" in response.data


def test_german(client):
    response = client.get("/", headers={"Accept-Language": "de"})
    assert '<html lang="de">' in response.text


def test_security_headers(client):
    headers = client.get("/").headers
    assert headers["Referrer-Policy"] == "no-referrer"
    assert "default-src 'none'" in headers["Content-Security-Policy"]
    assert "script-src 'self'" in headers["Content-Security-Policy"]


def test_static_is_versioned(client):
    html = client.get("/").text
    assert "pico.min.css?v=" in html
    response = client.get("/static/pico.min.css?v=x")
    assert "immutable" in response.headers["Cache-Control"]


def test_the_privacy_page_is_public_and_linked_from_every_page(client):
    page = client.get("/privacy")
    assert page.status_code == 200
    text = page.get_data(as_text=True)
    for word in ("Cloudflare", "Amazon", "Gravatar", "passkey", "cookie"):
        assert word in text
    assert 'href="/privacy"' in client.get("/").get_data(as_text=True)


def test_start_page_changes_page_and_footer_link(client):
    home = client.get("/").text
    assert "home-table-light.png" in home and "home-table-dark.png" in home and "open an issue on GitHub" in home
    assert 'href="/changes"' in home and "digest" in home
    changes = client.get("/changes")
    assert changes.status_code == 200 and "2026-10-31" in changes.text
    assert 'href="/changes"' in client.get("/privacy").text  # in the footer of every page
