# Changelog

What changed on the new e-DoKo site (the Python rewrite), newest first. Every entry
starts with labels in backticks so you can see at a glance what it is about:

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
The same list is shown on the site at `/changes` for now; the plan is to show this file
there instead.

## 2026-11-01

- `account` A start page for newcomers, and sign-up with a passkey: an emailed link, a name, a passkey. Only one mail per address at a time.
- `admin` `account` Admins can set sign-up to open, invitation-only or closed, and invite people by email (admin page or `flask --app edoko edoko invite`).
- `design` The start page explains the site and shows an example table, in a light and a dark version.
- `game play` A finished table shows a summary: points, games won, solos, foxes caught and a game-by-game table.
- `game play` `design` What was said before the game is shown as "Before the game", next to the trick numbers.
- `game play` `design` The remark box is below your own cards, with a hint to write the remark before clicking the card.
- `design` The "nothing has happened for N days" box is above the table.
- `privacy` The privacy page says that a Gravatar picture is stored on our server and can be deleted.
- `data` Umlauts in old comments are repaired; a player without a name is called "Player N".

## 2026-10-31

- `game play` `rules` Playing on the new site: join a game, declare a solo, wedding, poverty or nines, and play your cards, with all the rules of the old site (calls, Schweinchen, dullen).
- `game play` Tables: start a table with house rules and an optional number of games; keep playing with the seats rotating; standings and a winner at the end.
- `mail` Choose normal, fewer or no mails, or a digest at the hours you like; your time zone and language; reminders for games that wait for a week, and cancelling after a month.
- `game play` Remarks with a card: on any card of the trick shown, editable for 15 minutes.
- `privacy` A privacy page that says what is stored.

## 2026-10-22

- `mail` Mail that bounces or draws a complaint is noticed, so nobody is mailed at an address that does not work.

## 2026-10-19

- `game play` Compare a game with how other tables played the same deal: the hands stay in the same places.

## 2026-10-18

- `design` `game play` A new game page: the table with four fixed places, the tricks one by one, speech bubbles for remarks, and an explanation of the game points.
- `account` `design` Profile pictures: generated, uploaded or from Gravatar when you ask for it.
- `account` `admin` Passkeys for logging in, a settings page and an admin page.
