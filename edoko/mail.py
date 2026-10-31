"""Sending mail: SMTP (AWS SES) in production, a list in the tests.

FLASK_MAIL_BACKEND  smtp (default) or memory (messages go to app.outbox)
FLASK_SMTP_HOST (required, the SES SMTP endpoint of your region),
FLASK_SMTP_PORT (587), FLASK_SMTP_USER, FLASK_SMTP_PASSWORD
FLASK_MAIL_FROM     the sender address (required), a verified SES identity
FLASK_MAIL_ALLOW    optional, comma separated addresses: mail goes only to these,
                    everything else is logged and skipped (test instance with a
                    copy of the real user data)
"""

import logging
import smtplib
from email.message import EmailMessage
from email.utils import formatdate, make_msgid

from flask import current_app, render_template
from flask_babel import force_locale

log = logging.getLogger(__name__)

SUBJECT_PREFIX = "[DoKo] "


def send(to: str, subject: str, body: str) -> bool:
    """Send one plain text mail; False if it could not be sent."""
    config = current_app.config
    allow = {a.strip().lower() for a in (config.get("MAIL_ALLOW") or "").split(",") if a.strip()}
    if allow and to.lower() not in allow:
        log.info("mail to %s skipped (not in FLASK_MAIL_ALLOW): %s", to, subject)
        return True
    message = EmailMessage()
    message["From"] = config["MAIL_FROM"]
    message["To"] = to
    message["Subject"] = SUBJECT_PREFIX + subject
    message["Date"] = formatdate(localtime=False)
    message["Message-ID"] = make_msgid(domain=config["MAIL_FROM"].split("@")[-1])
    message.set_content(body)
    if config["MAIL_BACKEND"] == "memory":
        current_app.outbox.append(message)
        return True
    try:
        with smtplib.SMTP(config["SMTP_HOST"], config["SMTP_PORT"], timeout=20) as smtp:
            smtp.starttls()
            smtp.login(config["SMTP_USER"], config["SMTP_PASSWORD"])
            smtp.send_message(message)
    except OSError, smtplib.SMTPException:
        log.exception("could not send mail")
        return False
    return True


def render(user, subject, template: str, **context) -> tuple[str, str]:
    """The subject and the text of a mail to a user, in their language."""
    with force_locale(user["language"] or "en"):
        body = render_template(f"mail/{template}.txt", user=user, **context)
        return str(subject), body


def send_template(user, subject, template: str, **context) -> bool:
    """Mail to a user (row with email, language), in their language.

    subject is a lazy string, so it is translated here as well.
    """
    if user["mail_status"] != "ok":
        log.info("no mail to user %s: %s", user["id"], user["mail_status"])
        return False
    subject, body = render(user, subject, template, **context)
    return send(user["email"], subject, body)


def deliver(db, user, subject, template: str, *, kind="other", game=None, **context) -> bool:
    """A mail about a game: at once, or kept for the user's digest.

    kind 'turn' is dropped from the digest if it is no longer the user's turn then.
    """
    if not user["digest_hours"]:
        return send_template(user, subject, template, **context)
    if user["mail_status"] != "ok":
        return False
    subject, body = render(user, subject, template, **context)
    db.execute(
        "INSERT INTO mail_queue (user, kind, game, subject, body) VALUES (?, ?, ?, ?, ?)",
        (user["id"], kind, game, subject, body),
    )
    return True


def render_digest(user, entries: list[tuple[str, str]]) -> str:
    from .digest import SEPARATOR

    return render_template("mail/digest.txt", user=user, entries=entries, separator=SEPARATOR)
