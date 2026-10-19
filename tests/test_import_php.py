from contextlib import closing

from import_php import import_php

from edoko import db


def test_import(php_database, tmp_path):
    new = tmp_path / "new.sqlite"
    report = import_php(php_database, new)
    with closing(db.connect(new)) as conn:
        q = lambda sql: conn.execute(sql).fetchall()  # noqa: E731
        user = q("SELECT * FROM user WHERE id = 1")[0]
        assert user["mail_mode"] == "fewer"
        assert user["digest_hours"] == 6
        assert user["vacation_start"] == "2013-09-01"
        assert user["language"] == "de"
        assert [tuple(r) for r in q("SELECT id, name FROM user ORDER BY id")][1] == (
            2,
            "Bob",
        )
        # one deal for both games; sessions keep their ids and get their players
        assert q("SELECT count(*) FROM deal")[0][0] == 1
        assert [r[0] for r in q("SELECT session FROM game ORDER BY id")] == [7, 8]
        assert q("SELECT count(*) FROM session_player WHERE session = 7")[0][0] == 4
        # Game.player was a user id, it is a seat now
        assert q("SELECT turn_seat FROM game WHERE id = 1")[0][0] == 3
        assert q("SELECT token FROM hand WHERE id = 5")[0][0] == "h5"
        assert [tuple(r) for r in q("SELECT number, winner_seat FROM trick")] == [
            (1, 2),
            (2, None),
        ]
        # comments: game filled in, special play ids become kinds
        assert [
            tuple(r) for r in q("SELECT id, game, kind FROM comment ORDER BY id")
        ] == [
            (1, 1, "move"),
            (2, 1, "pre"),
        ]
    assert report["passwords dropped"] == 3
    assert report["hand_card dropped (no hand)"] == 1
    assert report["play dropped (no trick or card)"] == 1
    assert report["comment dropped (no play)"] == 1
    assert report["note dropped (no game)"] == 1
    assert report["reminder dropped (no game)"] == 1
