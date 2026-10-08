-- SQLite schema for e-DoKo (converted from the MySQL schema, version 5)
--
-- Differences to MySQL:
--  * enum -> TEXT with a CHECK constraint
--  * text columns use COLLATE NOCASE like MySQL's *_general_ci collations
--    (except password/hash/randomnumbers, which compare case-sensitively)
--  * "mod_date ... ON UPDATE CURRENT_TIMESTAMP" is emulated by triggers
--  * MySQL's '0000-00-00 00:00:00' became NULL; all timestamps are UTC
--  * AUTO_INCREMENT -> INTEGER PRIMARY KEY AUTOINCREMENT (ids never reused)
--  * Game2, django_migrations, user_openids: unused leftovers, kept as-is

PRAGMA journal_mode = WAL;

CREATE TABLE "Card" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  suite          TEXT COLLATE NOCASE NOT NULL DEFAULT 'diamonds' CHECK (suite IN ('diamonds','hearts','spades','clubs')),
  strength       TEXT COLLATE NOCASE NOT NULL DEFAULT 'nine' CHECK (strength IN ('nine','ten','jack','queen','king','ace')),
  points         INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE "Card_Exchange" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  orig_hand_id   INTEGER NOT NULL DEFAULT 0,
  new_hand_id    INTEGER NOT NULL DEFAULT 0,
  card_id        INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE "Comment" (
  mod_date       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  create_date    TEXT,
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER,
  play_id        INTEGER,
  game_id        INTEGER,
  comment        TEXT COLLATE NOCASE
);

CREATE TABLE "Game" (
  mod_date       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  create_date    TEXT,
  randomnumbers  TEXT,
  type           TEXT COLLATE NOCASE CHECK (type IN ('normal','solo','wedding','poverty','dpoverty')),
  solo           TEXT COLLATE NOCASE CHECK (solo IN ('trumpless','jack','queen','trump','club','spade','heart','silent')),
  sickness       INTEGER,
  startplayer    INTEGER DEFAULT 1,
  player         INTEGER,
  status         TEXT COLLATE NOCASE CHECK (status IN ('pre','play','gameover','cancel-timedout','cancel-nines','cancel-trump','cancel-noplay','cancel-lowtrump')),
  ruleset        INTEGER,
  session        INTEGER,
  id             INTEGER PRIMARY KEY AUTOINCREMENT
);

CREATE TABLE "Game2" (
  mod_date       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  create_date    TEXT,
  randomnumbers  TEXT,
  type           TEXT COLLATE NOCASE CHECK (type IN ('normal','solo','wedding','poverty','dpoverty')),
  solo           TEXT COLLATE NOCASE CHECK (solo IN ('trumpless','jack','queen','trump','club','spade','heart','silent')),
  sickness       INTEGER,
  startplayer    INTEGER DEFAULT 1,
  player         INTEGER,
  status         TEXT COLLATE NOCASE CHECK (status IN ('pre','play','gameover')),
  ruleset        INTEGER,
  session        INTEGER,
  id             INTEGER PRIMARY KEY AUTOINCREMENT
);

CREATE TABLE "Hand" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  game_id        INTEGER NOT NULL DEFAULT 0,
  user_id        INTEGER NOT NULL DEFAULT 0,
  hash           TEXT,
  status         TEXT COLLATE NOCASE DEFAULT 'start' CHECK (status IN ('start','init','check','poverty','play','gameover')),
  position       INTEGER NOT NULL DEFAULT 0,
  party          TEXT COLLATE NOCASE CHECK (party IN ('re','contra')),
  sickness       TEXT COLLATE NOCASE CHECK (sickness IN ('wedding','nines','poverty','solo','lowtrump')),
  solo           TEXT COLLATE NOCASE CHECK (solo IN ('trumpless','jack','queen','trump','club','spade','heart','silent')),
  point_call     TEXT COLLATE NOCASE CHECK (point_call IN ('120','90','60','30','0'))
);

CREATE TABLE "Hand_Card" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  hand_id        INTEGER NOT NULL DEFAULT 0,
  card_id        INTEGER NOT NULL DEFAULT 0,
  played         TEXT COLLATE NOCASE DEFAULT 'false' CHECK (played IN ('true','false'))
);

CREATE TABLE "Notes" (
  mod_date       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  create_date    TEXT,
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER,
  game_id        INTEGER,
  comment        TEXT COLLATE NOCASE
);

CREATE TABLE "Play" (
  mod_date       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  create_date    TEXT,
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  trick_id       INTEGER NOT NULL DEFAULT 0,
  hand_card_id   INTEGER NOT NULL DEFAULT 0,
  sequence       INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE "Recovery" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER NOT NULL DEFAULT 0,
  password       TEXT,
  create_date    TEXT
);

CREATE TABLE "Reminder" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER NOT NULL DEFAULT 0,
  game_id        INTEGER NOT NULL DEFAULT 0,
  create_date    TEXT
);

CREATE TABLE "Rulesets" (
  mod_date       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  create_date    TEXT,
  dullen         TEXT COLLATE NOCASE DEFAULT 'secondwins' CHECK (dullen IN ('none','firstwins','secondwins')),
  schweinchen    TEXT COLLATE NOCASE DEFAULT 'second' CHECK (schweinchen IN ('none','both','second','secondaftercall')),
  lowtrump       TEXT COLLATE NOCASE DEFAULT 'poverty' CHECK (lowtrump IN ('poverty','cancel','none')),
  call           TEXT COLLATE NOCASE DEFAULT '1st-own-card' CHECK (call IN ('1st-own-card','5th-card','9-cards')),
  id             INTEGER PRIMARY KEY AUTOINCREMENT
);

CREATE TABLE "Score" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  create_date    TEXT,
  game_id        INTEGER NOT NULL DEFAULT 0,
  party          TEXT COLLATE NOCASE CHECK (party IN ('re','contra')),
  winner_id      INTEGER,
  looser_id      INTEGER,
  score          TEXT COLLATE NOCASE CHECK (score IN ('120','call120','against120','90','call90','against90','made90','60','call60','against60','made60','30','call30','against30','made30','0','call0','made0','against0','fox','karlchen','dulle','doko','againstqueens'))
);

CREATE TABLE "Trick" (
  mod_date       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  create_date    TEXT,
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  game_id        INTEGER NOT NULL DEFAULT 0,
  winner         INTEGER
);

CREATE TABLE "User" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  fullname       TEXT COLLATE NOCASE,
  email          TEXT COLLATE NOCASE,
  password       TEXT,
  timezone       TEXT COLLATE NOCASE DEFAULT 'Europe/London',
  last_login     TEXT,
  create_date    TEXT
);

CREATE TABLE "User_Game_Prefs" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER NOT NULL DEFAULT 0,
  game_id        INTEGER NOT NULL DEFAULT 0,
  pref_key       TEXT COLLATE NOCASE,
  value          TEXT COLLATE NOCASE
);

CREATE TABLE "User_Prefs" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER NOT NULL DEFAULT 0,
  pref_key       TEXT COLLATE NOCASE,
  value          TEXT COLLATE NOCASE
);

CREATE TABLE "Version" (
  version        INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE "digest_email" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  email          TEXT COLLATE NOCASE,
  create_date    TEXT,
  type           TEXT COLLATE NOCASE NOT NULL DEFAULT 'misc' CHECK (type IN ('misc','your_turn')),
  game_id        INTEGER,
  content        TEXT COLLATE NOCASE
);

CREATE TABLE "django_migrations" (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  app            TEXT COLLATE NOCASE NOT NULL,
  name           TEXT COLLATE NOCASE NOT NULL,
  applied        TEXT NOT NULL
);

CREATE TABLE "user_openids" (
  openid_url     TEXT COLLATE NOCASE NOT NULL PRIMARY KEY,
  user_id        INTEGER NOT NULL
);

CREATE INDEX Card_Exchange_orig_hand_id ON Card_Exchange(orig_hand_id);
CREATE INDEX Card_Exchange_new_hand_id ON Card_Exchange(new_hand_id);
CREATE INDEX Comment_play_id ON Comment(play_id);
CREATE INDEX Game_session ON Game(session);
CREATE INDEX Game_randomnumbers ON Game(randomnumbers);
CREATE INDEX Hand_game_id ON Hand(game_id);
CREATE INDEX Hand_user_id ON Hand(user_id);
CREATE INDEX Hand_Card_hand_id ON Hand_Card(hand_id);
CREATE INDEX Play_trick_id ON Play(trick_id);
CREATE INDEX Score_game_id ON Score(game_id);
CREATE INDEX Trick_game_id ON Trick(game_id);
CREATE INDEX User_Game_Prefs_user_id ON User_Game_Prefs(user_id);
CREATE INDEX User_Prefs_user_id ON User_Prefs(user_id);
CREATE INDEX digest_email_email ON digest_email(email);
CREATE INDEX user_openids_user_id ON user_openids(user_id);

CREATE TRIGGER Comment_mod_date AFTER UPDATE ON "Comment" WHEN NEW.mod_date IS OLD.mod_date
  BEGIN UPDATE "Comment" SET mod_date = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid; END;

CREATE TRIGGER Game_mod_date AFTER UPDATE ON "Game" WHEN NEW.mod_date IS OLD.mod_date
  BEGIN UPDATE "Game" SET mod_date = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid; END;

CREATE TRIGGER Game2_mod_date AFTER UPDATE ON "Game2" WHEN NEW.mod_date IS OLD.mod_date
  BEGIN UPDATE "Game2" SET mod_date = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid; END;

CREATE TRIGGER Notes_mod_date AFTER UPDATE ON "Notes" WHEN NEW.mod_date IS OLD.mod_date
  BEGIN UPDATE "Notes" SET mod_date = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid; END;

CREATE TRIGGER Play_mod_date AFTER UPDATE ON "Play" WHEN NEW.mod_date IS OLD.mod_date
  BEGIN UPDATE "Play" SET mod_date = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid; END;

CREATE TRIGGER Rulesets_mod_date AFTER UPDATE ON "Rulesets" WHEN NEW.mod_date IS OLD.mod_date
  BEGIN UPDATE "Rulesets" SET mod_date = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid; END;

CREATE TRIGGER Trick_mod_date AFTER UPDATE ON "Trick" WHEN NEW.mod_date IS OLD.mod_date
  BEGIN UPDATE "Trick" SET mod_date = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid; END;

