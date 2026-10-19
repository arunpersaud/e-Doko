from contextlib import closing
from pathlib import Path

import pytest

from edoko import create_app, db


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
