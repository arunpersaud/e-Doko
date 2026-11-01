"""The digest: the mails of the game for people who asked to get them together.

Run hourly (a systemd timer: `flask --app edoko edoko digest`). A user with a
digest of N hours gets theirs when the hour of their own clock is a multiple of
N away from 18:00, as on the PHP site: every 24 hours: at 18:00; every 12: at
18:00 and 06:00; every hour: always.
"""

import logging
import sqlite3
from datetime import UTC, datetime
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from flask_babel import force_locale, gettext as _

from . import mail

log = logging.getLogger(__name__)

FIRST_HOUR = 18
SEPARATOR = "+" * 54


def is_due(user, now: datetime) -> bool:
    try:
        zone = ZoneInfo(user["timezone"])
    except ZoneInfoNotFoundError, ValueError:
        zone = UTC
    hour = now.astimezone(zone).hour
    return (hour - FIRST_HOUR) % user["digest_hours"] == 0


def current_entries(db: sqlite3.Connection, user) -> tuple[list, list[int]]:
    """The queued mails, and the ids of all rows (also the outdated ones)."""
    rows = db.execute("SELECT * FROM mail_queue WHERE user = ? ORDER BY id", (user["id"],)).fetchall()
    keep = []
    for row in rows:
        if row["kind"] == "turn":
            waiting = db.execute(
                """SELECT 1 FROM game JOIN hand ON hand.game = game.id AND hand.seat = game.turn_seat
                   WHERE game.id = ? AND hand.user = ? AND game.status IN ('pre', 'play')""",
                (row["game"], user["id"]),
            ).fetchone()
            if not waiting:  # it is not your turn any more: no use telling you
                continue
        keep.append(row)
    return keep, [row["id"] for row in rows]


def run(db: sqlite3.Connection, now: datetime | None = None, force: bool = False) -> int:
    """Send the digests that are due (all of them if force); returns how many mails went out."""
    now = now or datetime.now(UTC)
    sent = 0
    users = db.execute(
        """SELECT * FROM user WHERE (mail_status = 'ok' OR ?) AND mail_mode != 'none'
             AND disabled_at IS NULL
             AND (digest_hours IS NOT NULL
                  OR EXISTS (SELECT 1 FROM mail_queue WHERE mail_queue.user = user.id))""",
        (mail.demo_mode(),),
    ).fetchall()
    for user in users:
        # (somebody who turned the digest off gets what is left at the next run)
        if user["digest_hours"] and not force and not is_due(user, now):
            continue
        entries, ids = current_entries(db, user)
        if entries:
            with force_locale(user["language"] or "en"):
                body = mail.render_digest(user, [(e["subject"], e["body"]) for e in entries])
                ok = mail.send(user["email"], _("Digest"), body)
            if not ok:
                log.warning("digest for user %s not sent, will try again", user["id"])
                continue
            sent += 1
        if ids:
            db.execute(
                f"DELETE FROM mail_queue WHERE id IN ({','.join('?' * len(ids))})", ids
            )
    return sent
