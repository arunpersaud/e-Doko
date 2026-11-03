"""Live mode: a player who watches a game gets no "your turn" mail for it.

A "Live" button on the game page switches it on, and it is off again whenever the
page is loaded anew. While it is on, the page asks every POLL_SECONDS whether
anything changed, and each such request is a heartbeat: hand.live_until is
HEARTBEAT_SECONDS ahead. A "your turn" mail for a live player is not sent but held
(turn_hold), and goes out

 - when the heartbeat stops (the tab was closed or hidden: nobody is watching),
 - when GRACE_MINUTES have passed and it is still their turn (an open tab is not
   attention), or
 - when live mode is switched off.

A move makes the hold obsolete: it is dropped. Live mode also ends by itself,
CAP_MINUTES after the last thing the player did (any move or comment renews it).
The holds are released by the pages' own requests and by a timer
(`flask --app edoko edoko release-holds`, every minute).
"""

import hashlib
import logging
import sqlite3
import time

log = logging.getLogger(__name__)

POLL_SECONDS = 10
HEARTBEAT_SECONDS = 35
CAP_MINUTES = 30
GRACE_MINUTES = 5


def clock() -> int:
    return int(time.time())


def is_live(hand, now: int | None = None) -> bool:
    """Is this hand (row with live_until and live_expires) being watched now?"""
    now = clock() if now is None else now
    return bool(
        hand["live_until"] and hand["live_until"] > now
        and hand["live_expires"] and hand["live_expires"] > now
    )


def start(db: sqlite3.Connection, hand_id: int) -> None:
    now = clock()
    db.execute(
        "UPDATE hand SET live_until = ?, live_expires = ? WHERE id = ?",
        (now + HEARTBEAT_SECONDS, now + CAP_MINUTES * 60, hand_id),
    )


def beat(db: sqlite3.Connection, hand_id: int) -> bool:
    """A poll from a live page; False if live mode has run out by now."""
    now = clock()
    done = db.execute(
        "UPDATE hand SET live_until = ? WHERE id = ? AND live_expires > ?",
        (now + HEARTBEAT_SECONDS, hand_id, now),
    )
    return done.rowcount == 1


def stop(db: sqlite3.Connection, hand_id: int) -> None:
    db.execute("UPDATE hand SET live_until = NULL, live_expires = NULL WHERE id = ?", (hand_id,))
    hand = db.execute("SELECT game FROM hand WHERE id = ?", (hand_id,)).fetchone()
    if hand:
        release(db, hand["game"])


def touch(db: sqlite3.Connection, hand_id: int) -> None:
    """The player did something: live mode runs for another CAP_MINUTES."""
    now = clock()
    db.execute(
        "UPDATE hand SET live_expires = ? WHERE id = ? AND live_expires > ?",
        (now + CAP_MINUTES * 60, hand_id, now),
    )


def holds_mail(db: sqlite3.Connection, hand) -> bool:
    """Is the turn mail of this hand to be held? Only if it would be sent at all."""
    if not is_live(hand):
        return False
    user = db.execute("SELECT * FROM user WHERE id = ?", (hand["user"],)).fetchone()
    return (
        user["mail_mode"] == "normal" and user["mail_status"] == "ok"
        and not user["digest_hours"]
    )


def hold(db: sqlite3.Connection, hand, first: bool) -> None:
    db.execute(
        "INSERT OR REPLACE INTO turn_hold (game, hand, since, first) VALUES (?, ?, ?, ?)",
        (hand["game"], hand["id"], clock(), int(bool(first))),
    )


def clear_holds(db: sqlite3.Connection, game_id: int) -> None:
    """The turn moved on: whatever was held is out of date."""
    db.execute("DELETE FROM turn_hold WHERE game = ?", (game_id,))


def release(db: sqlite3.Connection, game_id: int | None = None, now: int | None = None) -> int:
    """Send the held turn mails that are due (of one game, or of all); returns how many."""
    from . import play  # (play imports this module for the holds)

    now = clock() if now is None else now
    query = (
        "SELECT turn_hold.id AS hold_id, turn_hold.since, turn_hold.first, hand.*,"
        " game.status AS game_status, game.turn_seat FROM turn_hold"
        " JOIN hand ON hand.id = turn_hold.hand JOIN game ON game.id = turn_hold.game"
    )
    rows = db.execute(
        query + (" WHERE turn_hold.game = ?" if game_id is not None else ""),
        (game_id,) if game_id is not None else (),
    ).fetchall()
    sent = 0
    for row in rows:
        waiting = row["game_status"] in play.ACTIVE and row["turn_seat"] == row["seat"]
        due = not is_live(row, now) or row["since"] + GRACE_MINUTES * 60 <= now
        if waiting and not due:
            continue
        # (whoever deletes the row sends: two workers do not send it twice)
        if db.execute("DELETE FROM turn_hold WHERE id = ?", (row["hold_id"],)).rowcount and waiting:
            play.send_turn_mail(db, row, bool(row["first"]))
            sent += 1
    return sent


def seats_live(db: sqlite3.Connection, game_id: int) -> dict[int, bool]:
    """seat -> is that player watching live right now"""
    now = clock()
    return {
        r["seat"]: is_live(r, now)
        for r in db.execute(
            "SELECT seat, live_until, live_expires FROM hand WHERE game = ?", (game_id,)
        )
    }


def version(db: sqlite3.Connection, game_id: int) -> str:
    """Changes whenever the page of this game would look different (cheap to compute)."""
    game = db.execute(
        "SELECT status, turn_seat, updated_at, type, solo FROM game WHERE id = ?", (game_id,)
    ).fetchone()
    if game is None:
        return "gone"
    plays = db.execute(
        "SELECT count(*), coalesce(max(play.id), 0) FROM play"
        " JOIN trick ON trick.id = play.trick WHERE trick.game = ?",
        (game_id,),
    ).fetchone()
    comments = db.execute(
        "SELECT count(*), coalesce(max(id), 0), coalesce(max(edited_at), ''),"
        " coalesce(max(deleted_at), '') FROM comment WHERE game = ?",
        (game_id,),
    ).fetchone()
    exchanged = db.execute(
        "SELECT count(*) FROM card_exchange JOIN hand ON hand.id = card_exchange.to_hand"
        " WHERE hand.game = ?",
        (game_id,),
    ).fetchone()[0]
    reminders = db.execute("SELECT count(*) FROM reminder WHERE game = ?", (game_id,)).fetchone()[0]
    table = db.execute(  # (a proposal of more rounds: new, answered, closed; the length of the table)
        "SELECT session.max_games, count(table_extension.id), coalesce(max(table_extension.id), 0),"
        " count(table_extension.closed_at),"
        " (SELECT count(*) FROM table_extension_vote JOIN table_extension e ON e.id = extension"
        "  WHERE e.session = session.id)"
        " FROM game JOIN session ON session.id = game.session"
        " LEFT JOIN table_extension ON table_extension.session = session.id WHERE game.id = ?",
        (game_id,),
    ).fetchone()
    hands = [
        f"{h['seat']}{h['status']}{h['party']}{h['sickness']}{h['point_call']}{int(live)}"
        for h, live in (
            (h, is_live(h)) for h in db.execute("SELECT * FROM hand WHERE game = ? ORDER BY seat", (game_id,))
        )
    ]
    parts = [tuple(game), tuple(plays), tuple(comments), exchanged, reminders, tuple(table), hands]
    return hashlib.sha1(repr(parts).encode()).hexdigest()[:12]
