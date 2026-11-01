from contextlib import closing

from edoko import db, migrations
from edoko.textfix import repair


def test_repair():
    assert repair("Liebe GrÃ¼ÃŸe, Birgit") == "Liebe Grüße, Birgit"
    assert repair("Liebe Grüße") == "Liebe Grüße"  # fine already
    assert repair("plain") == "plain"
    assert repair("Ã¼ and then ü") == "Ã¼ and then ü"  # not valid when undone: left alone
    assert repair("(â•¯Â°â–¡Â°ï¼‰â•¯ï¸µ â”»â”\x81â”»") == "(╯°□°）╯︵ ┻━┻"
    assert repair(None) is None


def test_migration_repairs_comments(tmp_path):
    path = tmp_path / "e.sqlite"
    db.init_db(path)
    with closing(db.connect(path)) as c:
        c.execute("PRAGMA foreign_keys = OFF")
        c.execute("PRAGMA user_version = 6")
        c.execute("INSERT INTO user (id, name, email) VALUES (1, 'A', 'a@x.org')")
        c.execute("INSERT INTO deal (id, cards) VALUES (1, '1')")
        c.execute("INSERT INTO session (id) VALUES (1)")
        c.execute("INSERT INTO ruleset (id, dullen, schweinchen, lowtrump, call) VALUES (1, 'none', 'none', 'poverty', '1st-own-card')")
        c.execute("INSERT INTO game (id, session, deal, ruleset, status) VALUES (1, 1, 1, 1, 'gameover')")
        c.execute("INSERT INTO comment (game, kind, user, body) VALUES (1, 'pre', 1, 'zurÃ¼ck')")
        assert migrations.apply(c, 6) == [7]
        assert c.execute("SELECT body FROM comment").fetchone()[0] == "zurück"
