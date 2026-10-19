"""Schema migrations.

The baseline is version 1 (schema.sql, frozen). Each entry below brings the
database from version N-1 to N (PRAGMA user_version). A new database is
the baseline plus all migrations (db.init_db); an existing one is brought
up to date by migrate(), which runs when the app starts, takes a backup
first and holds a lock, so two gunicorn workers don't both migrate.
"""

import fcntl
import sqlite3
from contextlib import closing
from datetime import UTC, datetime
from pathlib import Path

MIGRATIONS: dict[int, str] = {
    # profile pictures: uploaded or fetched from Gravatar (else generated)
    2: """
CREATE TABLE avatar (
  user            INTEGER PRIMARY KEY REFERENCES user(id) ON DELETE CASCADE,
  source          TEXT NOT NULL CHECK (source IN ('upload', 'gravatar')),
  image           BLOB NOT NULL,
  content_type    TEXT NOT NULL,
  updated_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;
""",
}

LATEST = max(MIGRATIONS, default=1)


def version(db: sqlite3.Connection) -> int:
    return db.execute("PRAGMA user_version").fetchone()[0]


def backup(db: sqlite3.Connection, path: Path, label: str) -> Path:
    """Consistent copy of the database into backups/ next to it."""
    folder = path.parent / "backups"
    folder.mkdir(mode=0o700, exist_ok=True)
    stamp = datetime.now(UTC).strftime("%Y%m%d-%H%M%S")
    target = folder / f"{path.stem}-{label}-{stamp}.sqlite"
    with closing(sqlite3.connect(target)) as copy:
        db.backup(copy)
    return target


def apply(db: sqlite3.Connection, current: int) -> list[int]:
    """Run the migrations after version current on an open connection."""
    applied = []
    for target in range(current + 1, LATEST + 1):
        db.executescript(
            "BEGIN;\n"
            + MIGRATIONS[target]
            + f"\nPRAGMA user_version = {target};\nCOMMIT;"
        )
        applied.append(target)
    return applied


def migrate(path: Path) -> list[int]:
    """Apply missing migrations; returns the versions applied."""
    applied = []
    with open(path.with_name(path.name + ".lock"), "w") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        with closing(sqlite3.connect(path, autocommit=True)) as db:
            current = version(db)
            if current == 0:
                raise RuntimeError(f"{path} is not an e-DoKo database (version 0)")
            if current >= LATEST:
                return applied
            backup(db, path, f"v{current}")
            applied = apply(db, current)
    return applied
