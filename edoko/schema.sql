-- e-DoKo schema, version 1 (the Flask rewrite).
--
-- IDs of users, games, hands, tricks, plays and cards are the ones of the
-- PHP site (version 5 of its schema); edoko/importer.py converts a PHP
-- database. All timestamps are UTC, "YYYY-MM-DD HH:MM:SS".
-- Later changes go into migrations.py.

CREATE TABLE user (
  id              INTEGER PRIMARY KEY,
  name            TEXT NOT NULL UNIQUE COLLATE NOCASE,
  email           TEXT NOT NULL UNIQUE COLLATE NOCASE,
  timezone        TEXT NOT NULL DEFAULT 'Europe/London',
  language        TEXT CHECK (language IN ('en', 'de')),  -- NULL: browser
  card_set        TEXT NOT NULL DEFAULT 'english',
  -- 'fewer' = the old "less emails" option
  mail_mode       TEXT NOT NULL DEFAULT 'normal'
                  CHECK (mail_mode IN ('normal', 'fewer', 'none')),
  -- set by the SES bounce handling: no mail while 'bounced'
  mail_status     TEXT NOT NULL DEFAULT 'ok' CHECK (mail_status IN ('ok', 'bounced')),
  digest_hours    INTEGER CHECK (digest_hours > 0),       -- NULL: digest off
  cc_email        INTEGER NOT NULL DEFAULT 0 CHECK (cc_email IN (0, 1)),
  autosetup       INTEGER NOT NULL DEFAULT 0 CHECK (autosetup IN (0, 1)),
  open_for_games  INTEGER NOT NULL DEFAULT 1 CHECK (open_for_games IN (0, 1)),
  vacation_start  TEXT,                                   -- dates
  vacation_stop   TEXT,
  vacation_comment TEXT,
  is_admin        INTEGER NOT NULL DEFAULT 0 CHECK (is_admin IN (0, 1)),
  disabled_at     TEXT,                                   -- NULL: may log in
  -- the passkeys' user handle: random, not the id or the name
  webauthn_id     BLOB NOT NULL DEFAULT (randomblob(32)),
  last_login      TEXT,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;

-- login: passkeys, and single-use links by email (only the hash is stored)
CREATE TABLE passkey (
  id              INTEGER PRIMARY KEY,
  user            INTEGER NOT NULL REFERENCES user(id) ON DELETE CASCADE,
  credential_id   BLOB NOT NULL UNIQUE,
  public_key      BLOB NOT NULL,
  sign_count      INTEGER NOT NULL DEFAULT 0,
  name            TEXT NOT NULL DEFAULT '',
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at    TEXT
) STRICT;
CREATE INDEX passkey_user ON passkey(user);

CREATE TABLE login_token (
  id              INTEGER PRIMARY KEY,
  user            INTEGER NOT NULL REFERENCES user(id) ON DELETE CASCADE,
  token_hash      TEXT NOT NULL UNIQUE,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      TEXT NOT NULL,
  used_at         TEXT
) STRICT;

-- recent attempts, to limit requests for login links; the key is a hash
-- (of an address or an IP), rows older than a day are deleted
CREATE TABLE rate_limit (
  key             TEXT NOT NULL,
  at              TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;
CREATE INDEX rate_limit_key ON rate_limit(key, at);

CREATE TABLE card (
  id              INTEGER PRIMARY KEY,
  suit            TEXT NOT NULL CHECK (suit IN ('diamonds', 'hearts', 'spades', 'clubs')),
  rank            TEXT NOT NULL CHECK (rank IN ('nine', 'ten', 'jack', 'queen', 'king', 'ace')),
  points          INTEGER NOT NULL
) STRICT;

CREATE TABLE ruleset (
  id              INTEGER PRIMARY KEY,
  dullen          TEXT NOT NULL CHECK (dullen IN ('none', 'firstwins', 'secondwins')),
  schweinchen     TEXT NOT NULL
                  CHECK (schweinchen IN ('none', 'both', 'second', 'secondaftercall')),
  lowtrump        TEXT NOT NULL CHECK (lowtrump IN ('poverty', 'cancel', 'none')),
  call            TEXT NOT NULL CHECK (call IN ('1st-own-card', '5th-card', '9-cards')),
  UNIQUE (dullen, schweinchen, lowtrump, call)
) STRICT;

-- a shuffled deck: the 48 card ids in order, "10:11:2:..."; several games
-- (at different tables) can play the same deal
CREATE TABLE deal (
  id              INTEGER PRIMARY KEY,
  cards           TEXT NOT NULL UNIQUE
) STRICT;

-- a group of four players playing a series of games (seating rotates)
CREATE TABLE session (
  id              INTEGER PRIMARY KEY,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;

CREATE TABLE session_player (
  session         INTEGER NOT NULL REFERENCES session(id) ON DELETE CASCADE,
  user            INTEGER NOT NULL REFERENCES user(id),
  PRIMARY KEY (session, user)
) STRICT, WITHOUT ROWID;

CREATE TABLE game (
  id              INTEGER PRIMARY KEY,
  session         INTEGER NOT NULL REFERENCES session(id),
  deal            INTEGER NOT NULL REFERENCES deal(id),
  ruleset         INTEGER NOT NULL REFERENCES ruleset(id),
  status          TEXT NOT NULL CHECK (status IN
                  ('pre', 'play', 'gameover', 'cancel-timedout', 'cancel-nines',
                   'cancel-trump', 'cancel-noplay', 'cancel-lowtrump')),
  type            TEXT CHECK (type IN
                  ('normal', 'solo', 'wedding', 'poverty', 'dpoverty')),
  solo            TEXT CHECK (solo IN
                  ('trumpless', 'jack', 'queen', 'trump', 'club', 'spade', 'heart',
                   'silent')),
  -- bookkeeping of the declaration phase (-1 ... 30 in the old data)
  sickness_state  INTEGER,
  start_seat      INTEGER NOT NULL DEFAULT 1 CHECK (start_seat BETWEEN 1 AND 4),
  turn_seat       INTEGER CHECK (turn_seat BETWEEN 1 AND 4),   -- whose turn
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;
CREATE INDEX game_session ON game(session);
CREATE INDEX game_deal ON game(deal);
CREATE INDEX game_status ON game(status);

CREATE TABLE hand (
  id              INTEGER PRIMARY KEY,
  game            INTEGER NOT NULL REFERENCES game(id) ON DELETE CASCADE,
  seat            INTEGER NOT NULL CHECK (seat BETWEEN 1 AND 4),
  user            INTEGER NOT NULL REFERENCES user(id),
  -- secret of this seat: whoever knows it may play it (links in the mails)
  token           TEXT NOT NULL UNIQUE,
  status          TEXT NOT NULL DEFAULT 'start'
                  CHECK (status IN ('start', 'init', 'check', 'poverty', 'play',
                                    'gameover')),
  party           TEXT CHECK (party IN ('re', 'contra')),
  sickness        TEXT CHECK (sickness IN
                  ('wedding', 'nines', 'poverty', 'solo', 'lowtrump')),
  solo            TEXT CHECK (solo IN
                  ('trumpless', 'jack', 'queen', 'trump', 'club', 'spade', 'heart',
                   'silent')),
  point_call      TEXT CHECK (point_call IN ('120', '90', '60', '30', '0')),
  -- final game points of this player; filled in by the rules engine
  points          INTEGER,
  UNIQUE (game, seat),
  UNIQUE (game, user)
) STRICT;
CREATE INDEX hand_user ON hand(user);

CREATE TABLE hand_card (
  id              INTEGER PRIMARY KEY,
  hand            INTEGER NOT NULL REFERENCES hand(id) ON DELETE CASCADE,
  card            INTEGER NOT NULL REFERENCES card(id),
  UNIQUE (hand, card)
) STRICT;

-- cards swapped in a poverty
CREATE TABLE card_exchange (
  id              INTEGER PRIMARY KEY,
  from_hand       INTEGER NOT NULL REFERENCES hand(id) ON DELETE CASCADE,
  to_hand         INTEGER NOT NULL REFERENCES hand(id) ON DELETE CASCADE,
  card            INTEGER NOT NULL REFERENCES card(id)
) STRICT;
CREATE INDEX card_exchange_from ON card_exchange(from_hand);
CREATE INDEX card_exchange_to ON card_exchange(to_hand);

CREATE TABLE trick (
  id              INTEGER PRIMARY KEY,
  game            INTEGER NOT NULL REFERENCES game(id) ON DELETE CASCADE,
  number          INTEGER NOT NULL CHECK (number BETWEEN 1 AND 12),
  winner_seat     INTEGER CHECK (winner_seat BETWEEN 1 AND 4),
  UNIQUE (game, number)
) STRICT;

-- a card played into a trick; "has this card been played" = it has a row here
CREATE TABLE play (
  id              INTEGER PRIMARY KEY,
  trick           INTEGER NOT NULL REFERENCES trick(id) ON DELETE CASCADE,
  sequence        INTEGER NOT NULL CHECK (sequence BETWEEN 1 AND 4),
  hand_card       INTEGER NOT NULL UNIQUE REFERENCES hand_card(id) ON DELETE CASCADE,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (trick, sequence)
) STRICT;

-- scoring items of a game (won 120, fox caught, ...)
CREATE TABLE score_item (
  id              INTEGER PRIMARY KEY,
  game            INTEGER NOT NULL REFERENCES game(id) ON DELETE CASCADE,
  party           TEXT NOT NULL CHECK (party IN ('re', 'contra')),
  kind            TEXT CHECK (kind IN
                  ('120', 'call120', 'against120', '90', 'call90', 'against90',
                   'made90', '60', 'call60', 'against60', 'made60', '30', 'call30',
                   'against30', 'made30', '0', 'call0', 'made0', 'against0', 'fox',
                   'karlchen', 'dulle', 'doko', 'againstqueens')),
  winner          INTEGER REFERENCES user(id),
  loser           INTEGER REFERENCES user(id),
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;
CREATE INDEX score_item_game ON score_item(game);

-- public comments: on a move, or before the game ('pre': remarks while the
-- game starts, 'call': remarks on a call)
CREATE TABLE comment (
  id              INTEGER PRIMARY KEY,
  game            INTEGER NOT NULL REFERENCES game(id) ON DELETE CASCADE,
  kind            TEXT NOT NULL DEFAULT 'move' CHECK (kind IN ('move', 'pre', 'call')),
  play            INTEGER REFERENCES play(id) ON DELETE CASCADE,
  user            INTEGER NOT NULL REFERENCES user(id),
  parent          INTEGER REFERENCES comment(id),
  body            TEXT NOT NULL,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at       TEXT,
  deleted_at      TEXT,
  CHECK ((kind = 'move') = (play IS NOT NULL))
) STRICT;
CREATE INDEX comment_game ON comment(game);
CREATE INDEX comment_play ON comment(play);

-- private notes of a player on a game
CREATE TABLE note (
  id              INTEGER PRIMARY KEY,
  user            INTEGER NOT NULL REFERENCES user(id),
  game            INTEGER NOT NULL REFERENCES game(id) ON DELETE CASCADE,
  body            TEXT NOT NULL,
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;
CREATE INDEX note_user_game ON note(user, game);

-- "please make your move" mails: at most one per game within the cooldown
-- (to_user is NULL for the old rows)
CREATE TABLE reminder (
  id              INTEGER PRIMARY KEY,
  game            INTEGER NOT NULL REFERENCES game(id) ON DELETE CASCADE,
  from_user       INTEGER NOT NULL REFERENCES user(id),
  to_user         INTEGER REFERENCES user(id),
  created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
) STRICT;
CREATE INDEX reminder_game ON reminder(game, created_at);

INSERT INTO card (id, suit, rank, points) VALUES
  (1, 'hearts', 'ten', 10),
  (2, 'hearts', 'ten', 10),
  (3, 'clubs', 'queen', 3),
  (4, 'clubs', 'queen', 3),
  (5, 'spades', 'queen', 3),
  (6, 'spades', 'queen', 3),
  (7, 'hearts', 'queen', 3),
  (8, 'hearts', 'queen', 3),
  (9, 'diamonds', 'queen', 3),
  (10, 'diamonds', 'queen', 3),
  (11, 'clubs', 'jack', 2),
  (12, 'clubs', 'jack', 2),
  (13, 'spades', 'jack', 2),
  (14, 'spades', 'jack', 2),
  (15, 'hearts', 'jack', 2),
  (16, 'hearts', 'jack', 2),
  (17, 'diamonds', 'jack', 2),
  (18, 'diamonds', 'jack', 2),
  (19, 'diamonds', 'ace', 11),
  (20, 'diamonds', 'ace', 11),
  (21, 'diamonds', 'ten', 10),
  (22, 'diamonds', 'ten', 10),
  (23, 'diamonds', 'king', 4),
  (24, 'diamonds', 'king', 4),
  (25, 'diamonds', 'nine', 0),
  (26, 'diamonds', 'nine', 0),
  (27, 'clubs', 'ace', 11),
  (28, 'clubs', 'ace', 11),
  (29, 'clubs', 'ten', 10),
  (30, 'clubs', 'ten', 10),
  (31, 'clubs', 'king', 4),
  (32, 'clubs', 'king', 4),
  (33, 'clubs', 'nine', 0),
  (34, 'clubs', 'nine', 0),
  (35, 'spades', 'ace', 11),
  (36, 'spades', 'ace', 11),
  (37, 'spades', 'ten', 10),
  (38, 'spades', 'ten', 10),
  (39, 'spades', 'king', 4),
  (40, 'spades', 'king', 4),
  (41, 'spades', 'nine', 0),
  (42, 'spades', 'nine', 0),
  (43, 'hearts', 'ace', 11),
  (44, 'hearts', 'ace', 11),
  (45, 'hearts', 'king', 4),
  (46, 'hearts', 'king', 4),
  (47, 'hearts', 'nine', 0),
  (48, 'hearts', 'nine', 0);

PRAGMA user_version = 1;
