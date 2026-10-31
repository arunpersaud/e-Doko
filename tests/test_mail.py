from edoko import mail


def test_allowlist_skips_other_addresses(app):
    app.config["MAIL_ALLOW"] = "me@x.org, Other@x.org"
    with app.app_context():
        assert mail.send("a@x.org", "hi", "body")  # skipped, but not an error
        assert app.outbox == []
        assert mail.send("other@X.org", "hi", "body")
        assert [m["To"] for m in app.outbox] == ["other@X.org"]


def test_no_allowlist_sends_to_everyone(app):
    with app.app_context():
        mail.send("a@x.org", "hi", "body")
    assert len(app.outbox) == 1
