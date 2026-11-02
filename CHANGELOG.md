# Changelog

All notable changes to the new e-DoKo site (the Python rewrite) are documented in this
file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The versions before 1.0.0 are milestones of the rewrite, while it was tried on a test
instance. 1.0.0 is the first version that replaces the old PHP site. From here on a new
major version means that something players or admins rely on changed incompatibly, a
minor version adds features, and a patch version fixes bugs.

Every entry starts with labels in backticks, so you can see at a glance what it is about:

| Label | What it is about |
| --- | --- |
| `game play` | playing, tables, the game page, remarks |
| `rules` | rules of the game and house rules |
| `design` | how it looks and where things are |
| `mail` | mails, digest, notifications |
| `account` | sign-up, login, passkeys, settings |
| `privacy` | what is stored, and for how long |
| `admin` | things for the admins |
| `data` | the data of the old site |

Wishes and bugs are welcome as [issues on GitHub](https://github.com/arunpersaud/e-Doko/issues).
The site shows a hand-written copy of this list at `/changes` for now; the plan is to show
this file there instead.

## [Unreleased]

### Fixed

- `mail` The texts of the mails (login link, "it is your turn", invitations, reminders, the digest) were missing from the translation file, so they always came in English. They are included now.

### Changed

- `design` German translation of all texts that were still English (about 240 texts: settings, game page, start page, sign-up, privacy page, mails). Please tell us about wrong or clumsy wording.

### Removed

- `data` The code of the old PHP site is no longer in the repository (it stays in the git history, up to the tag `v1.0.0`). `schema-sqlite.sql` and `tools/import_php.py` remain to read its database.

### Added

- `account` `mail` Change your email address in the settings. A link goes to the new address and the address only changes after it was opened while logged in; the old address is told about the request and about the change, with the contact address to write to if it was wrong. Login links mailed to the old address stop working.
- `privacy` The contact address on the privacy page and in these mails is a setting of the server (`FLASK_CONTACT_EMAIL`).

## [1.0.0] - 2026-11-01

The first version that replaces the old PHP site.

### Added

- `account` A start page for newcomers that explains the site, and sign-up with a passkey: an emailed link, a name, a passkey. Only one mail per address at a time, with limits against abuse.
- `admin` `account` Admins can set sign-up to open, invitation-only or closed, and invite people by email (admin page or `flask --app edoko edoko invite`).
- `design` An example table on the start page, in a light and a dark version.
- `game play` A finished table shows a summary: points, games won, solos, foxes caught and a game-by-game table.
- `game play` `design` What was said before the game is shown as "Before the game", next to the trick numbers.
- `design` A page with the changes (`/changes`), linked from the start page and the footer.
- `admin` A demo mode (`FLASK_DEMO=1`) to try the site with nobody getting mail: the mails are shown in the pages, and you can continue as any player.

### Changed

- `game play` `design` The remark box is below your own cards, with a hint to write the remark before clicking the card.
- `design` The "nothing has happened for N days" box is above the table.
- `privacy` The privacy page says that a Gravatar picture is stored on our server and can be deleted, and what sign-up and invitations store.

### Fixed

- `rules` With the rule "Schweinchen after a call", a call made after the second fox no longer counts retroactively; the final scoring now agrees with the game as it was played.
- `data` Umlauts in old comments are repaired, and a player without a name is called "Player N".
- `design` The remark box fits into a narrow player box.

## [0.4.0] - 2026-10-31

### Added

- `game play` `rules` Playing on the new site: join a game, declare a solo, wedding, poverty or nines, and play your cards, with all the rules of the old site (calls, Schweinchen, dullen).
- `game play` Tables: start a table with house rules and an optional number of games; keep playing with the seats rotating; standings and a winner at the end.
- `mail` Choose normal, fewer or no mails, or a digest at the hours you like; your time zone and language; reminders for games that wait for a week, and cancelling after a month.
- `game play` Remarks with a card: on any card of the trick shown, editable for 15 minutes.
- `privacy` A privacy page that says what is stored.

## [0.3.0] - 2026-10-22

### Added

- `mail` Mail that bounces or draws a complaint is noticed, so nobody is mailed at an address that does not work.

## [0.2.0] - 2026-10-19

### Added

- `game play` Compare a game with how other tables played the same deal: the hands stay in the same places.

## [0.1.0] - 2026-10-18

### Added

- `data` The games, players and comments of the old site are converted, and the finished games are replayed with the new rules engine to check them.
- `design` `game play` A new game page: the table with four fixed places, the tricks one by one, speech bubbles for remarks, and an explanation of the game points.
- `account` `design` Profile pictures: generated, uploaded or from Gravatar when you ask for it.
- `account` `admin` Passkeys for logging in, a settings page and an admin page.
