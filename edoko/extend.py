"""Going on at a table: the players propose more rounds and all have to agree.

A table with an end (session.max_games, always whole rounds of GAMES_PER_ROUND
games, see play.COUNTED) is finished when they are played. During its last game,
or once it is finished, any player may propose N more rounds. The other three
answer; if all agree, max_games grows by N rounds and the table goes on, and if
one declines the proposal is off (nobody is told who). One proposal at a time; an
unanswered one lapses after EXPIRE_DAYS.
"""

import sqlite3

from flask_babel import gettext as _
from flask_babel import lazy_gettext as _l

from . import play

MAX_ROUNDS = 25
EXPIRE_DAYS = 14
SHOW_OUTCOME_DAYS = 3  # how long the table page remembers that nobody agreed


def table_players(db: sqlite3.Connection, session_id: int) -> list[int]:
    return [
        r[0]
        for r in db.execute(
            "SELECT user FROM session_player WHERE session = ? ORDER BY user", (session_id,)
        )
    ]


def last_game(db: sqlite3.Connection, session_id: int) -> int | None:
    return db.execute("SELECT max(id) FROM game WHERE session = ?", (session_id,)).fetchone()[0]


def open_proposal(db: sqlite3.Connection, session_id: int):
    """The proposal waiting for answers (None if there is none)."""
    db.execute(
        "UPDATE table_extension SET closed_at = CURRENT_TIMESTAMP, outcome = 'expired'"
        " WHERE session = ? AND closed_at IS NULL AND created_at < datetime('now', ?)",
        (session_id, f"-{EXPIRE_DAYS} days"),
    )
    return db.execute(
        "SELECT table_extension.*, user.name AS proposer_name FROM table_extension"
        " JOIN user ON user.id = table_extension.proposer"
        " WHERE session = ? AND closed_at IS NULL",
        (session_id,),
    ).fetchone()


def in_last_game(state: dict) -> bool:
    """The table has an end and its last game is being played (or is over)."""
    return state["max_games"] is not None and state["played"] >= state["max_games"] - 1


def status(db: sqlite3.Connection, session_id: int, user_id: int) -> dict | None:
    """What the pages show about going on at this table (None: nothing to show)."""
    state = play.table_state(db, session_id)
    if state["max_games"] is None or user_id not in table_players(db, session_id):
        return None
    proposal = open_proposal(db, session_id)
    info: dict = {
        "can_propose": proposal is None and in_last_game(state),
        "default_rounds": max(1, min(MAX_ROUNDS, state["max_games"] // play.GAMES_PER_ROUND)),
        "max_rounds": MAX_ROUNDS,
        "proposal": None,
        "nobody_agreed": False,
    }
    if proposal is not None:
        votes = {
            r["user"]: r["agree"]
            for r in db.execute(
                "SELECT user, agree FROM table_extension_vote WHERE extension = ?",
                (proposal["id"],),
            )
        }
        info["proposal"] = {
            "rounds": proposal["rounds"],
            "proposer": proposal["proposer_name"],
            "i_proposed": proposal["proposer"] == user_id,
            "i_answered": user_id in votes,
            "waiting": len(table_players(db, session_id)) - len(votes),
        }
    else:
        last = db.execute(
            "SELECT outcome FROM table_extension WHERE session = ? AND closed_at IS NOT NULL"
            " AND closed_at > datetime('now', ?) ORDER BY id DESC LIMIT 1",
            (session_id, f"-{SHOW_OUTCOME_DAYS} days"),
        ).fetchone()
        info["nobody_agreed"] = bool(last and last["outcome"] in ("declined", "expired"))
    return info


def _mail(db, session_id: int, user_id: int, subject, template: str, **context) -> None:
    """A mail about the table, with the link to the user's seat in its last game."""
    game_id = last_game(db, session_id)
    hand = db.execute(
        "SELECT token FROM hand WHERE game = ? AND user = ?", (game_id, user_id)
    ).fetchone()
    if hand is None:
        return
    play._mail(
        db, user_id, subject, template, game_id=game_id, session=session_id,
        link=play._link(hand["token"]), **context,
    )


def propose(db: sqlite3.Connection, session_id: int, user_id: int, rounds: int) -> None:
    if not 1 <= rounds <= MAX_ROUNDS:
        raise play.PlayError(_("Please choose between 1 and %(n)d rounds.", n=MAX_ROUNDS))
    with play.transaction(db):
        players = table_players(db, session_id)
        if user_id not in players:
            raise play.PlayError(_("Only the players of the table can do that."))
        state = play.table_state(db, session_id)
        if state["max_games"] is None:
            raise play.PlayError(_("This table has no end, so there is nothing to go on with."))
        if open_proposal(db, session_id) is not None:
            raise play.PlayError(_("There is a proposal already: please answer it first."))
        if not in_last_game(state):
            raise play.PlayError(_("You can propose more rounds during the last game of the table."))
        proposal = db.execute(
            "INSERT INTO table_extension (session, proposer, rounds) VALUES (?, ?, ?)",
            (session_id, user_id, rounds),
        ).lastrowid
        db.execute(
            "INSERT INTO table_extension_vote (extension, user, agree) VALUES (?, ?, 1)",
            (proposal, user_id),
        )
        name = db.execute("SELECT name FROM user WHERE id = ?", (user_id,)).fetchone()[0]
    for other in players:
        if other != user_id:
            _mail(
                db, session_id, other, _l("Table %(n)d: %(name)s proposes more rounds", n=session_id, name=name),
                "extension_proposed", proposer=name, rounds=rounds,
            )


def vote(db: sqlite3.Connection, session_id: int, user_id: int, agree: bool) -> str:
    """Answer the open proposal; returns what it came to: waiting, accepted or declined."""
    with play.transaction(db):
        players = table_players(db, session_id)
        if user_id not in players:
            raise play.PlayError(_("Only the players of the table can do that."))
        proposal = open_proposal(db, session_id)
        if proposal is None:
            raise play.PlayError(_("There is no proposal to answer."))
        if db.execute(
            "SELECT 1 FROM table_extension_vote WHERE extension = ? AND user = ?",
            (proposal["id"], user_id),
        ).fetchone():
            raise play.PlayError(_("You have answered this proposal already."))
        db.execute(
            "INSERT INTO table_extension_vote (extension, user, agree) VALUES (?, ?, ?)",
            (proposal["id"], user_id, int(agree)),
        )
        agreed = db.execute(
            "SELECT count(*) FROM table_extension_vote WHERE extension = ? AND agree = 1",
            (proposal["id"],),
        ).fetchone()[0]
        outcome = "waiting"
        if not agree:
            outcome = "declined"
        elif agreed == len(players):
            outcome = "accepted"
            db.execute(
                "UPDATE session SET max_games = max_games + ? WHERE id = ?",
                (proposal["rounds"] * play.GAMES_PER_ROUND, session_id),
            )
        if outcome != "waiting":
            db.execute(
                "UPDATE table_extension SET closed_at = CURRENT_TIMESTAMP, outcome = ? WHERE id = ?",
                (outcome, proposal["id"]),
            )
    if outcome == "declined":  # only the proposer hears of it, and not who said no
        _mail(
            db, session_id, proposal["proposer"],
            _l("Table %(n)d: not everybody agreed", n=session_id),
            "extension_declined", rounds=proposal["rounds"],
        )
    elif outcome == "accepted":
        for player in players:
            _mail(
                db, session_id, player,
                _l("Table %(n)d goes on", n=session_id), "extension_accepted",
                rounds=proposal["rounds"],
            )
    return outcome
