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

from . import textfix

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
    # an upload and a Gravatar are both kept; one of them (or none: the
    # generated picture) is the one that is shown
    3: """
CREATE TABLE avatar_new (
  user            INTEGER NOT NULL REFERENCES user(id) ON DELETE CASCADE,
  source          TEXT NOT NULL CHECK (source IN ('upload', 'gravatar')),
  image           BLOB NOT NULL,
  content_type    TEXT NOT NULL,
  updated_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  chosen          INTEGER NOT NULL DEFAULT 0 CHECK (chosen IN (0, 1)),
  PRIMARY KEY (user, source)
) STRICT;
INSERT INTO avatar_new (user, source, image, content_type, updated_at, chosen)
  SELECT user, source, image, content_type, updated_at, 1 FROM avatar;
DROP TABLE avatar;
ALTER TABLE avatar_new RENAME TO avatar;
CREATE UNIQUE INDEX avatar_one_chosen ON avatar(user) WHERE chosen = 1;
""",
    # bounces and complaints reported by SES (see bounces.py); user.mail_status
    # says whether mail still goes out
    4: """
CREATE TABLE mail_bounce (
  id              INTEGER PRIMARY KEY,
  notice          TEXT NOT NULL,        -- id of the SNS message (it may come twice)
  address         TEXT NOT NULL COLLATE NOCASE,
  user            INTEGER REFERENCES user(id) ON DELETE SET NULL,
  kind            TEXT NOT NULL
                  CHECK (kind IN
                    ('permanent', 'transient', 'undetermined', 'complaint')),
  subtype         TEXT,
  diagnostic      TEXT,
  occurred_at     TEXT NOT NULL,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cleared_at      TEXT,                 -- the address was fixed: no longer counts
  UNIQUE (notice, address)
) STRICT;
CREATE INDEX mail_bounce_address ON mail_bounce(address);
""",
    # a table can end: after max_games finished games (NULL: no end, the old tables)
    5: """
ALTER TABLE session ADD COLUMN max_games INTEGER CHECK (max_games IS NULL OR max_games > 0);
""",
    # mails of the game for people with a digest: kept here until digest.run sends them
    6: """
CREATE TABLE mail_queue (
  id              INTEGER PRIMARY KEY,
  user            INTEGER NOT NULL REFERENCES user(id) ON DELETE CASCADE,
  kind            TEXT NOT NULL CHECK (kind IN ('turn', 'other')),
  game            INTEGER REFERENCES game(id) ON DELETE CASCADE,
  subject         TEXT NOT NULL,
  body            TEXT NOT NULL,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;
CREATE INDEX mail_queue_user ON mail_queue(user, id);
""",
    # comments and notes from the PHP site with "GrÃ¼ÃŸe" for "Grüße" (textfix.py)
    7: """
UPDATE comment SET body = repair_text(body) WHERE body != repair_text(body);
UPDATE note SET body = repair_text(body) WHERE body != repair_text(body);
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
    db.create_function("repair_text", 1, textfix.repair, deterministic=True)
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
