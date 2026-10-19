"""Bounces and complaints reported by Amazon SES.

SES notifies an SNS topic; SNS posts each notice to /ses/notify (signed, see
sns.py). Policy: a permanent bounce or a complaint stops all mail to the
user at once (mail_status 'bounced', SES would suppress the address anyway);
transient bounces (full mailbox, ...) are counted, and mail stops after 5 of
them on at least 3 different days. The user is not told by mail, of course:
the admin page lists them, and so does the user's own page later.

FLASK_SES_TOPIC_ARN  the one SNS topic that is believed; without it the
                     endpoint does not exist
"""

import json
import logging
from datetime import UTC, datetime

from flask import Blueprint, abort, current_app, request

from . import sns
from .db import get_db, query, query_one

log = logging.getLogger(__name__)

bp = Blueprint("ses", __name__)

TRANSIENT_COUNT = 5
TRANSIENT_DAYS = 3
KINDS = {"Permanent": "permanent", "Transient": "transient"}


def utc(stamp: object) -> str:
    """'2026-10-19T12:00:00.000Z' -> '2026-10-19 12:00:00' (now if unreadable)."""
    try:
        moment = datetime.fromisoformat(str(stamp)).astimezone(UTC)
    except ValueError:
        moment = datetime.now(UTC)
    return moment.strftime("%Y-%m-%d %H:%M:%S")


def should_stop(address: str, kind: str) -> bool:
    if kind in ("permanent", "complaint"):
        return True
    rows = query(
        """SELECT substr(occurred_at, 1, 10) AS day FROM mail_bounce
           WHERE address = ? AND kind IN ('transient', 'undetermined')
             AND cleared_at IS NULL""",
        (address,),
    )
    return (
        len(rows) >= TRANSIENT_COUNT and len({r["day"] for r in rows}) >= TRANSIENT_DAYS
    )


def record(address, kind, subtype, diagnostic, occurred_at, notice_id) -> None:
    """Store one bounce and stop the mail to the user if the policy says so."""
    user = query_one("SELECT id FROM user WHERE email = ?", (address,))
    db = get_db()
    new = db.execute(
        """INSERT OR IGNORE INTO mail_bounce
             (notice, address, user, kind, subtype, diagnostic, occurred_at)
           VALUES (?, ?, ?, ?, ?, ?, ?)""",
        (
            notice_id,
            address,
            user["id"] if user else None,
            kind,
            subtype,
            (diagnostic or "")[:500] or None,
            occurred_at,
        ),
    ).rowcount
    if new and user and should_stop(address, kind):
        db.execute(
            "UPDATE user SET mail_status = 'bounced' WHERE id = ?", (user["id"],)
        )
        log.warning("mail to user %d stopped: %s bounce", user["id"], kind)


def reset(user_id: int) -> None:
    """Mail to the user works again (the address was fixed, or the user asked):
    the bounces so far no longer count."""
    db = get_db()
    db.execute(
        """UPDATE mail_bounce SET cleared_at = CURRENT_TIMESTAMP
           WHERE cleared_at IS NULL
             AND address = (SELECT email FROM user WHERE id = ?)""",
        (user_id,),
    )
    db.execute("UPDATE user SET mail_status = 'ok' WHERE id = ?", (user_id,))


def handle(notice: dict, notice_id: str) -> None:
    kind = notice.get("notificationType") or notice.get("eventType")
    if kind == "Bounce" and isinstance(notice.get("bounce"), dict):
        bounce = notice["bounce"]
        what = KINDS.get(bounce.get("bounceType"), "undetermined")
        people = bounce.get("bouncedRecipients", [])
        for person in people:
            record(
                str(person.get("emailAddress", "")).strip(),
                what,
                bounce.get("bounceSubType"),
                person.get("diagnosticCode"),
                utc(bounce.get("timestamp")),
                notice_id,
            )
    elif kind == "Complaint" and isinstance(notice.get("complaint"), dict):
        complaint = notice["complaint"]
        for person in complaint.get("complainedRecipients", []):
            record(
                str(person.get("emailAddress", "")).strip(),
                "complaint",
                complaint.get("complaintFeedbackType"),
                None,
                utc(complaint.get("timestamp")),
                notice_id,
            )
    # deliveries and the like are not wanted


@bp.post("/ses/notify")
def notify():
    topic = current_app.config.get("SES_TOPIC_ARN")
    if not topic:
        abort(404)
    try:
        message = json.loads(request.get_data(as_text=True))
    except ValueError:
        abort(400)
    if not isinstance(message, dict) or message.get("TopicArn") != topic:
        abort(403)
    try:
        sns.verify(message)
        if message["Type"] == "SubscriptionConfirmation":
            sns.confirm(message.get("SubscribeURL"))
    except sns.Invalid as error:
        log.warning("refused an SNS message: %s", error)
        abort(403)
    if message["Type"] == "Notification":
        try:
            notice = json.loads(message["Message"])
        except ValueError:
            notice = None
        if isinstance(notice, dict):
            handle(notice, message["MessageId"])
    return "", 204


def init_app(app) -> None:
    app.register_blueprint(bp)
