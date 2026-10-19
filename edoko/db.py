"""Access to the SQLite database.

schema.sql is version 1 (PRAGMA user_version) and migrations.py has the
changes since; a new database gets both and an older one is brought up to
date when the app starts. A database from the PHP
site is converted with importer.py.
"""

import sqlite3
from pathlib import Path

from flask import Flask, current_app, g

SCHEMA = Path(__file__).with_name("schema.sql")


def connect(path: str | Path) -> sqlite3.Connection:
    db = sqlite3.connect(path, autocommit=True, timeout=10)
    db.row_factory = sqlite3.Row
    db.execute("PRAGMA foreign_keys = ON")
    return db


def get_db() -> sqlite3.Connection:
    """The connection for the current request."""
    if "db" not in g:
        g.db = connect(current_app.config["DATABASE"])
    return g.db


def close_db(exc: BaseException | None = None) -> None:
    db = g.pop("db", None)
    if db is not None:
        db.close()


def query(sql: str, params: tuple | dict = ()) -> list[sqlite3.Row]:
    return get_db().execute(sql, params).fetchall()


def query_one(sql: str, params: tuple | dict = ()) -> sqlite3.Row | None:
    return get_db().execute(sql, params).fetchone()


def init_db(path: Path) -> None:
    """Create an empty database: the baseline schema and all migrations."""
    from . import migrations

    if path.exists():
        raise FileExistsError(path)
    db = connect(path)
    try:
        db.execute("PRAGMA journal_mode = WAL")
        db.executescript(SCHEMA.read_text())
        migrations.apply(db, 1)
    finally:
        db.close()


def init_app(app: Flask) -> None:
    app.teardown_appcontext(close_db)
