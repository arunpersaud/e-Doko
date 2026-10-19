import sqlite3
from contextlib import closing

import pytest

from edoko import db, migrations


def test_migrate_applies_and_backs_up(database, monkeypatch):
    monkeypatch.setitem(
        migrations.MIGRATIONS, 2, "CREATE TABLE mail_bounce (id INTEGER PRIMARY KEY);"
    )
    monkeypatch.setattr(migrations, "LATEST", 2)
    assert migrations.migrate(database) == [2]
    with closing(sqlite3.connect(database)) as conn:
        assert migrations.version(conn) == 2
        conn.execute("SELECT * FROM mail_bounce")
    assert len(list((database.parent / "backups").glob("*-v1-*.sqlite"))) == 1
    assert migrations.migrate(database) == []


def test_migrate_refuses_unversioned_database(tmp_path):
    path = tmp_path / "php.sqlite"
    sqlite3.connect(path).close()
    with pytest.raises(RuntimeError):
        migrations.migrate(path)


def test_init_db(tmp_path):
    path = tmp_path / "new.sqlite"
    db.init_db(path)
    with closing(sqlite3.connect(path)) as conn:
        assert conn.execute("SELECT count(*) FROM card").fetchone()[0] == 48
        assert migrations.version(conn) == 1
    with pytest.raises(FileExistsError):
        db.init_db(path)


def test_foreign_keys_are_enforced(database):
    with closing(db.connect(database)) as conn, pytest.raises(sqlite3.IntegrityError):
        conn.execute(
            "INSERT INTO hand (game, seat, user, token) VALUES (99, 1, 1, 'x')"
        )
