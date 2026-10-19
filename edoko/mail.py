"""Sending mail: SMTP (AWS SES) in production, a list in the tests.

FLASK_MAIL_BACKEND  smtp (default) or memory (messages go to app.outbox)
FLASK_SMTP_HOST, FLASK_SMTP_PORT, FLASK_SMTP_USER, FLASK_SMTP_PASSWORD
FLASK_MAIL_FROM     the sender, e.g. noreply@doko.nubati.net
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


def send_template(user, subject, template: str, **context) -> bool:
    """Mail to a user (row with email, language), in their language.

    subject is a lazy string, so it is translated here as well.
    """
    with force_locale(user["language"] or "en"):
        body = render_template(f"mail/{template}.txt", user=user, **context)
        return send(user["email"], str(subject), body)
