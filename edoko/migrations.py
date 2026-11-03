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

# The PHP site never stored the points of a hand, it added up the scoring items.
# Game points = re items - contra items (one point each); re gets them (times 3 in a
# solo), contra minus (as rules.player_points does for a game played here).
POINTS_FROM_ITEMS = """
UPDATE hand SET points = (
  SELECT CASE WHEN hand.party = 're' THEN 1 ELSE -1 END
         * (CASE WHEN hand.party = 're' AND game.type = 'solo' THEN 3 ELSE 1 END)
         * (SELECT coalesce(sum(CASE score_item.party WHEN 're' THEN 1 ELSE -1 END), 0)
              FROM score_item WHERE score_item.game = hand.game)
    FROM game WHERE game.id = hand.game)
WHERE points IS NULL AND party IS NOT NULL
  AND game IN (SELECT id FROM game WHERE status = 'gameover');
"""

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
    # a user without a name (an old account that never filled it in) is
    # invisible at the tables
    8: """
UPDATE user SET name = 'Player ' || id WHERE trim(name) = '';
""",
    # sign-up: a mailed single-use link proves the address, there the name is chosen
    9: """
CREATE TABLE signup_token (
  id              INTEGER PRIMARY KEY,
  email           TEXT NOT NULL COLLATE NOCASE,
  token_hash      TEXT NOT NULL UNIQUE,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      TEXT NOT NULL,
  used_at         TEXT,
  invited         INTEGER NOT NULL DEFAULT 0 CHECK (invited IN (0, 1))  -- by an admin
) STRICT;

-- settings an admin changes on the site (signup: open, invite, closed)
CREATE TABLE setting (
  key             TEXT PRIMARY KEY,
  value           TEXT NOT NULL
) STRICT;
""",
    # a new address is only taken over after a link mailed to it was used
    10: """
CREATE TABLE email_change (
  id              INTEGER PRIMARY KEY,
  user            INTEGER NOT NULL REFERENCES user(id) ON DELETE CASCADE,
  new_email       TEXT NOT NULL COLLATE NOCASE,
  token_hash      TEXT NOT NULL UNIQUE,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      TEXT NOT NULL,
  used_at         TEXT
) STRICT;
""",
    # the finished games of the PHP site have no points per player: the standings
    # and the table summary were all zeros
    11: POINTS_FROM_ITEMS,
    # live mode (live.py): who is watching a game right now (unix seconds), and the
    # "your turn" mails held back for them
    12: """
ALTER TABLE hand ADD COLUMN live_until INTEGER;
ALTER TABLE hand ADD COLUMN live_expires INTEGER;
CREATE TABLE turn_hold (
  id              INTEGER PRIMARY KEY,
  game            INTEGER NOT NULL REFERENCES game(id) ON DELETE CASCADE,
  hand            INTEGER NOT NULL UNIQUE REFERENCES hand(id) ON DELETE CASCADE,
  since           INTEGER NOT NULL,
  first           INTEGER NOT NULL DEFAULT 0 CHECK (first IN (0, 1))
) STRICT;
""",
    # going on at a table (extend.py): a proposal of more rounds and the answers
    13: """
CREATE TABLE table_extension (
  id              INTEGER PRIMARY KEY,
  session         INTEGER NOT NULL REFERENCES session(id) ON DELETE CASCADE,
  proposer        INTEGER NOT NULL REFERENCES user(id),
  rounds          INTEGER NOT NULL CHECK (rounds > 0),
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at       TEXT,
  outcome         TEXT CHECK (outcome IN ('accepted', 'declined', 'expired'))
) STRICT;
-- one proposal at a time
CREATE UNIQUE INDEX table_extension_open ON table_extension(session) WHERE closed_at IS NULL;
CREATE TABLE table_extension_vote (
  extension       INTEGER NOT NULL REFERENCES table_extension(id) ON DELETE CASCADE,
  user            INTEGER NOT NULL REFERENCES user(id),
  agree           INTEGER NOT NULL CHECK (agree IN (0, 1)),
  PRIMARY KEY (extension, user)
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
