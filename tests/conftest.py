import sqlite3
import sys
from contextlib import closing
from pathlib import Path

import pytest

from edoko import create_app, db

sys.path.insert(0, str(Path(__file__).parent.parent / "tools"))

PHP_SCHEMA = Path(__file__).parent.parent / "schema-sqlite.sql"


@pytest.fixture
def database(tmp_path):
    path = tmp_path / "edoko.sqlite"
    db.init_db(path)
    with closing(db.connect(path)) as conn:
        conn.execute("INSERT INTO user (id, name, email) VALUES (1, 'Ann', 'a@x.org')")
        conn.execute("INSERT INTO deal (id, cards) VALUES (1, '1:2')")
        conn.execute("INSERT INTO session (id) VALUES (1)")
        conn.execute(
            "INSERT INTO ruleset (id, dullen, schweinchen, lowtrump, call) "
            "VALUES (1, 'none', 'none', 'poverty', '1st-own-card')"
        )
        for status in ("gameover", "play"):
            conn.execute(
                "INSERT INTO game (session, deal, ruleset, status) VALUES (1, 1, 1, ?)",
                (status,),
            )
    return path


@pytest.fixture
def app(database: Path):
    return create_app({"TESTING": True, "DATABASE": str(database)})


@pytest.fixture
def client(app):
    return app.test_client()


@pytest.fixture
def php_database(tmp_path):
    """A small database in the PHP site's format (schema version 5)."""
    path = tmp_path / "php.sqlite"
    with closing(sqlite3.connect(path, autocommit=True)) as conn:
        conn.executescript(PHP_SCHEMA.read_text())
        conn.executescript(
            """
INSERT INTO Card (id, suite, strength, points) VALUES (1, 'hearts', 'ten', 10);
INSERT INTO Rulesets (id, dullen, schweinchen, lowtrump, call)
  VALUES (1, 'none', 'none', 'poverty', '1st-own-card');
INSERT INTO User (id, fullname, email, password, timezone) VALUES
  (1, 'Ann', 'a@x.org', '$2y$10$abc', 'Europe/Berlin'),
  (2, 'Bob', 'b@x.org', '', 'Europe/London'),
  (3, 'Cy', 'c@x.org', 'e', 'Europe/London'),
  (4, 'Di', 'd@x.org', 'e', 'Europe/London');
INSERT INTO User_Prefs (user_id, pref_key, value) VALUES
  (1, 'email', 'emailaddict'), (1, 'digest', 'digest-6h'),
  (1, 'vacation start', '2013-09-01 00:00:00'), (1, 'language', 'de');
INSERT INTO Game (id, randomnumbers, type, status, startplayer, player, ruleset,
                  session, create_date, mod_date)
  VALUES (1, '1:2', 'normal', 'play', 1, 3, 1, 7, '2020-01-01 10:00:00', '2020-01-02 10:00:00'),
         (2, '1:2', 'normal', 'pre', 1, NULL, 1, 8, '2020-02-01 10:00:00', '2020-02-01 10:00:00');
INSERT INTO Hand (id, game_id, user_id, hash, status, position) VALUES
  (1, 1, 1, 'h1', 'play', 1), (2, 1, 2, 'h2', 'play', 2),
  (3, 1, 3, 'h3', 'play', 3), (4, 1, 4, 'h4', 'play', 4),
  (5, 2, 1, 'h5', 'start', 1), (6, 2, 2, 'h6', 'start', 2),
  (7, 2, 3, 'h7', 'start', 3), (8, 2, 4, 'h8', 'start', 4);
INSERT INTO Hand_Card (id, hand_id, card_id) VALUES (1, 1, 1), (2, 99, 1);
INSERT INTO Trick (id, game_id, winner) VALUES (1, 1, 2), (2, 1, NULL);
INSERT INTO Play (id, trick_id, hand_card_id, sequence) VALUES
  (1, 1, 1, 1), (2, 77, 1, 2);
INSERT INTO Comment (id, user_id, play_id, game_id, comment) VALUES
  (1, 1, 1, NULL, 'nice'), (2, 2, -1, 1, 'good luck'), (3, 2, 55, 1, 'lost');
INSERT INTO Notes (id, user_id, game_id, comment, create_date)
  VALUES (1, 1, 1, 'mine', '2020-01-01 11:00:00'), (2, 1, 9, 'x', '2020-01-01 11:00:00');
INSERT INTO Reminder (id, user_id, game_id, create_date)
  VALUES (1, 2, 1, '2020-01-03 08:00:00'), (2, 2, 9, '2020-01-03 08:00:00');
"""
        )
    return path
