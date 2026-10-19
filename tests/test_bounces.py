import json
from base64 import b64encode
from contextlib import closing
from datetime import UTC, datetime, timedelta

import pytest
from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding, rsa
from cryptography.x509.oid import NameOID

from edoko import create_app, db, sns

TOPIC = "arn:aws:sns:us-east-2:123456789012:edoko-ses"
CERT_URL = "https://sns.us-east-2.amazonaws.com/SimpleNotificationService-test.pem"


def make_certificate(days=30):
    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    name = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, "sns.amazonaws.com")])
    now = datetime.now(UTC)
    cert = (
        x509.CertificateBuilder()
        .subject_name(name)
        .issuer_name(name)
        .public_key(key.public_key())
        .serial_number(x509.random_serial_number())
        .not_valid_before(now - timedelta(days=60))
        .not_valid_after(now + timedelta(days=days))
        .sign(key, hashes.SHA256())
    )
    return key, cert.public_bytes(serialization.Encoding.PEM)


@pytest.fixture(scope="module")
def aws():
    return make_certificate()


@pytest.fixture
def app(database, aws, monkeypatch):
    monkeypatch.setattr(sns, "certificate", lambda url: aws[1])
    return create_app(
        {"TESTING": True, "DATABASE": str(database), "SES_TOPIC_ARN": TOPIC}
    )


def sign(key, message, version="2"):
    """Add the signature of SNS to a message."""
    message = {
        "SignatureVersion": version,
        "SigningCertURL": CERT_URL,
        "TopicArn": TOPIC,
        "Timestamp": "2026-10-19T12:00:00.000Z",
        **message,
    }
    text = "".join(
        f"{name}\n{message[name]}\n"
        for name in sns.SIGNED[message["Type"]]
        if name in message
    )
    algorithm = hashes.SHA256() if version == "2" else hashes.SHA1()
    signature = key.sign(text.encode(), padding.PKCS1v15(), algorithm)
    return {**message, "Signature": b64encode(signature).decode()}


def notification(key, notice, message_id="m1", **extra):
    return sign(
        key,
        {
            "Type": "Notification",
            "MessageId": message_id,
            "Message": json.dumps(notice),
            **extra,
        },
    )


def bounce(address="a@x.org", kind="Permanent", subtype="General", day="2026-10-19"):
    return {
        "notificationType": "Bounce",
        "bounce": {
            "bounceType": kind,
            "bounceSubType": subtype,
            "timestamp": f"{day}T12:00:00.000Z",
            "bouncedRecipients": [
                {"emailAddress": address, "diagnosticCode": "smtp; 550 no such user"}
            ],
        },
    }


def post(client, message):
    return client.post(
        "/ses/notify", data=json.dumps(message), content_type="text/plain"
    )


def status(app, address="a@x.org"):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        return conn.execute(
            "SELECT mail_status FROM user WHERE email = ?", (address,)
        ).fetchone()[0]


def bounces(app):
    with closing(db.connect(app.config["DATABASE"])) as conn:
        return conn.execute("SELECT * FROM mail_bounce ORDER BY id").fetchall()


def test_a_permanent_bounce_stops_the_mail(app, client, aws):
    response = post(client, notification(aws[0], bounce()))
    assert response.status_code == 204
    assert status(app) == "bounced"
    [row] = bounces(app)
    assert (row["kind"], row["subtype"], row["user"]) == ("permanent", "General", 1)
    assert row["occurred_at"] == "2026-10-19 12:00:00"
    assert "no such user" in row["diagnostic"]


def test_the_same_notice_twice_counts_once(app, client, aws):
    message = notification(aws[0], bounce(kind="Transient", subtype="MailboxFull"))
    assert post(client, message).status_code == 204
    assert post(client, message).status_code == 204
    assert len(bounces(app)) == 1


def test_a_complaint_stops_the_mail(app, client, aws):
    complaint = {
        "notificationType": "Complaint",
        "complaint": {
            "timestamp": "2026-10-19T12:00:00.000Z",
            "complaintFeedbackType": "abuse",
            "complainedRecipients": [{"emailAddress": "A@x.org"}],
        },
    }
    assert post(client, notification(aws[0], complaint)).status_code == 204
    assert status(app) == "bounced"
    assert bounces(app)[0]["kind"] == "complaint"


def test_transient_bounces_stop_the_mail_after_five_on_three_days(app, client, aws):
    days = ["2026-10-01", "2026-10-01", "2026-10-02", "2026-10-02", "2026-10-02"]
    for n, day in enumerate(days):
        post(client, notification(aws[0], bounce(kind="Transient", day=day), f"m{n}"))
    assert status(app) == "ok"  # five, but on two days only
    post(client, notification(aws[0], bounce(kind="Transient", day="2026-10-03"), "x"))
    assert status(app) == "bounced"


def test_few_transient_bounces_do_not_stop_the_mail(app, client, aws):
    for n, day in enumerate(["2026-10-01", "2026-10-02", "2026-10-03"]):
        post(client, notification(aws[0], bounce(kind="Transient", day=day), f"m{n}"))
    assert status(app) == "ok"
    assert len(bounces(app)) == 3


def test_a_bounce_of_an_unknown_address_is_kept_and_harmless(app, client, aws):
    post(client, notification(aws[0], bounce("nobody@x.org")))
    assert status(app) == "ok"
    assert bounces(app)[0]["user"] is None


def test_other_notices_are_ignored(app, client, aws):
    notice = {"notificationType": "Delivery", "delivery": {}}
    assert post(client, notification(aws[0], notice)).status_code == 204
    assert post(client, notification(aws[0], "just text", "m2")).status_code == 204
    assert bounces(app) == []


def test_sha1_signatures_of_version_1_are_accepted(app, client, aws):
    message = sign(
        aws[0],
        {"Type": "Notification", "MessageId": "m", "Message": json.dumps(bounce())},
        version="1",
    )
    assert post(client, message).status_code == 204
    assert status(app) == "bounced"


def forged(aws, **change):
    message = notification(aws[0], bounce())
    return {**message, **change}


@pytest.mark.parametrize(
    "change",
    [
        {"Message": json.dumps(bounce("b@x.org"))},  # changed after signing
        {"Signature": "AAAA"},
        {"Signature": 7},
        {"SignatureVersion": "3"},
        {"Type": "Other"},
        {"SigningCertURL": "http://sns.us-east-2.amazonaws.com/x.pem"},
        {"SigningCertURL": "https://evil.example/sns.us-east-2.amazonaws.com.pem"},
        {"SigningCertURL": "https://sns.us-east-2.amazonaws.com@evil.example/x.pem"},
        {"SigningCertURL": "https://sns.us-east-2.amazonaws.com.evil.example/x.pem"},
        {"TopicArn": "arn:aws:sns:us-east-2:999:other"},
    ],
)
def test_forged_messages_are_refused(app, client, aws, change):
    response = post(client, forged(aws, **change))
    assert response.status_code == 403
    assert status(app) == "ok" and bounces(app) == []


def test_a_signature_by_another_key_is_refused(app, client):
    other_key, _ = make_certificate()
    response = post(client, notification(other_key, bounce()))
    assert response.status_code == 403
    assert status(app) == "ok"


def test_an_expired_certificate_is_refused(app, client, monkeypatch):
    key, pem = make_certificate(days=-1)
    monkeypatch.setattr(sns, "certificate", lambda url: pem)
    assert post(client, notification(key, bounce())).status_code == 403


@pytest.mark.parametrize("body", ["", "not json", "[1]", "null", '"x"'])
def test_garbage_is_refused(client, body):
    assert client.post("/ses/notify", data=body).status_code in (400, 403)


def test_the_endpoint_needs_no_session_but_only_exists_when_configured(database):
    plain = create_app({"TESTING": True, "DATABASE": str(database)})
    assert plain.test_client().post("/ses/notify", data="{}").status_code == 404


def test_only_post(client):
    assert client.get("/ses/notify").status_code == 405


def test_the_subscription_is_confirmed(app, client, aws, monkeypatch):
    opened = []
    monkeypatch.setattr(
        sns, "urlopen", lambda url, timeout: opened.append(url) or Fake()
    )
    url = "https://sns.us-east-2.amazonaws.com/?Action=ConfirmSubscription&Token=t"
    message = sign(
        aws[0],
        {
            "Type": "SubscriptionConfirmation",
            "MessageId": "c1",
            "Message": "You have chosen to subscribe",
            "SubscribeURL": url,
            "Token": "t",
        },
    )
    assert post(client, message).status_code == 204
    assert opened == [url]


class Fake:
    def __enter__(self):
        return self

    def __exit__(self, *args):
        return False


def test_a_confirmation_address_outside_aws_is_not_opened(
    app, client, aws, monkeypatch
):
    opened = []
    monkeypatch.setattr(
        sns, "urlopen", lambda url, timeout: opened.append(url) or Fake()
    )
    message = sign(
        aws[0],
        {
            "Type": "SubscriptionConfirmation",
            "MessageId": "c1",
            "Message": "x",
            "SubscribeURL": "https://evil.example/confirm",
            "Token": "t",
        },
    )
    assert post(client, message).status_code == 403
    assert opened == []


def test_no_mail_to_a_user_whose_address_bounces(app, client, aws):
    post(client, notification(aws[0], bounce()))
    assert client_login_mail(app, client) == 0


def client_login_mail(app, client):
    page = client.get("/login").text
    import re

    token = re.search(r'name="csrf" value="([^"]+)"', page)[1]
    client.post("/login/email", data={"email": "a@x.org", "csrf": token})
    return len(app.outbox)


def test_mail_works_again_after_a_reset(app, client, aws):
    post(client, notification(aws[0], bounce()))
    runner = app.test_cli_runner()
    result = runner.invoke(args=["edoko", "mail-reset", "a@x.org"])
    assert result.exit_code == 0, result.output
    assert status(app) == "ok"
    assert bounces(app)[0]["cleared_at"] is not None
    assert client_login_mail(app, client) == 1
    # a bounce after the reset counts again
    post(client, notification(aws[0], bounce(), "m2"))
    assert status(app) == "bounced"


def test_reset_of_an_unknown_address(app):
    result = app.test_cli_runner().invoke(args=["edoko", "mail-reset", "no@x.org"])
    assert result.exit_code != 0


def test_the_admin_page_lists_bounces(app, client, aws):
    post(client, notification(aws[0], bounce()))
    with closing(db.connect(app.config["DATABASE"])) as conn:
        conn.execute("UPDATE user SET is_admin = 1")
    with client.session_transaction() as session:
        session["user_id"] = 1
    page = client.get("/admin").text
    assert "Recent bounces" in page and "permanent (General)" in page
