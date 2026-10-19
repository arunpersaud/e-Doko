import sqlite3
from contextlib import closing

import pytest

from edoko import db, migrations


def v1_database(tmp_path):
    """A database as it was at version 1, before any migration."""
    path = tmp_path / "old.sqlite"
    with closing(sqlite3.connect(path, autocommit=True)) as conn:
        conn.executescript(db.SCHEMA.read_text())
    return path


def test_an_old_database_is_migrated_with_a_backup(tmp_path):
    path = v1_database(tmp_path)
    assert migrations.migrate(path) == list(range(2, migrations.LATEST + 1))
    with closing(sqlite3.connect(path)) as conn:
        assert migrations.version(conn) == migrations.LATEST
        conn.execute("SELECT * FROM avatar")  # added by migration 2
    assert len(list((path.parent / "backups").glob("old-v1-*.sqlite"))) == 1
    assert migrations.migrate(path) == []


def test_a_new_database_is_the_baseline_plus_all_migrations(tmp_path):
    new = tmp_path / "new.sqlite"
    db.init_db(new)
    old = v1_database(tmp_path)
    migrations.migrate(old)

    def tables(path):
        with closing(sqlite3.connect(path)) as conn:
            return sorted(
                (r[0], r[1])
                for r in conn.execute("SELECT name, sql FROM sqlite_master")
                if not r[0].startswith("sqlite_")
            )

    assert tables(new) == tables(old)
    assert not (tmp_path / "backups").exists() or not list(
        (tmp_path / "backups").glob("new-*")
    )


def test_migrate_applies_a_new_migration(tmp_path, monkeypatch):
    path = tmp_path / "x.sqlite"
    db.init_db(path)
    newer = migrations.LATEST + 1
    monkeypatch.setitem(
        migrations.MIGRATIONS,
        newer,
        "CREATE TABLE mail_bounce (id INTEGER PRIMARY KEY);",
    )
    monkeypatch.setattr(migrations, "LATEST", newer)
    assert migrations.migrate(path) == [newer]
    with closing(sqlite3.connect(path)) as conn:
        assert migrations.version(conn) == newer
        conn.execute("SELECT * FROM mail_bounce")


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
        assert migrations.version(conn) == migrations.LATEST
    with pytest.raises(FileExistsError):
        db.init_db(path)


def test_foreign_keys_are_enforced(database):
    with closing(db.connect(database)) as conn, pytest.raises(sqlite3.IntegrityError):
        conn.execute(
            "INSERT INTO hand (game, seat, user, token) VALUES (99, 1, 1, 'x')"
        )
