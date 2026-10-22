# e-DoKo (Flask rewrite)

Online Doppelkopf, the successor of the PHP site
([e-Doko](https://github.com/arunpersaud/e-Doko)). Same SQLite schema
(version 5) and the same URLs, so running games carry over.
Plan and status: `~/gitop/ROADMAP.md`, section B, e-Doko.

## Development

    uv sync
    uv run pytest
    uv run ruff check .
    # empty database, or converted from the PHP site's database (one-off tool)
    export FLASK_DATABASE=instance/edoko.sqlite
    uv run flask --app edoko edoko init-db
    uv run python tools/import_php.py PHP.sqlite instance/new.sqlite
    uv run flask --app edoko run --debug

Translations (en msgids, de in `edoko/translations/de`):

    uv run pybabel extract -F babel.cfg -o messages.pot .
    uv run pybabel update -i messages.pot -d edoko/translations
    uv run pybabel compile -d edoko/translations

## Configuration

All settings are environment variables with the prefix `FLASK_` (see
`edoko/__init__.py`); nothing is read from a file in the repository.

| variable | |
| --- | --- |
| `FLASK_DATABASE` | SQLite file |
| `FLASK_SECRET_KEY` | signs the session cookie (secret) |
| `FLASK_ORIGIN` | the site's address, e.g. `https://doko.example.org` |
| `FLASK_MAIL_FROM` | sender address, a verified SES identity |
| `FLASK_SMTP_HOST` | SES SMTP endpoint of your region, e.g. `email-smtp.us-east-2.amazonaws.com` |
| `FLASK_SMTP_USER`, `FLASK_SMTP_PASSWORD` | SES SMTP credentials (secret) |
| `FLASK_SES_TOPIC_ARN` | SNS topic for bounces, see below |

`FLASK_SECRET_KEY`, `FLASK_ORIGIN`, `FLASK_MAIL_FROM` and `FLASK_SMTP_HOST`
are required unless the app runs in debug or testing mode. Keep the secrets
out of the repository, e.g. as container secrets or an encrypted env file.

## Mail and bounces (Amazon SES)

Mail goes out through SES over SMTP. Set up once, in one AWS region:

1. SES: verify the sending domain (DKIM) and leave the sandbox.
2. IAM: a user for SMTP with `ses:SendRawEmail`, restricted to the sender
   address; create its SMTP credentials (`FLASK_SMTP_USER`/`_PASSWORD`).

Bounces and complaints are reported by SES to an SNS topic, which posts them
to the app (`POST <origin>/ses/notify`, `edoko/bounces.py`). No AWS
credentials are needed on the server. The app checks the SNS signature and
only believes the one topic in `FLASK_SES_TOPIC_ARN`; the endpoint does not
exist (404) until that is set.

1. SNS: create a **Standard** topic (FIFO cannot be used by SES or HTTPS) in
   the same region as SES. Defaults are fine.
2. SES > Identities > your domain > Notifications > Feedback notifications:
   set Bounce and Complaint to the topic. Leave Delivery empty, original
   headers off. If SES cannot publish, allow `ses.amazonaws.com` to
   `sns:Publish` in the topic's access policy.
3. Set `FLASK_SES_TOPIC_ARN` to the topic's ARN and restart the app.
4. SNS > the topic > Create subscription: protocol HTTPS, endpoint
   `<origin>/ses/notify`, no raw message delivery. The app confirms it
   itself; the status turns to Confirmed. If it stays Pending, a CDN or bot
   protection in front of the site is blocking AWS: allow the path.
5. Test: send a mail to `bounce@simulator.amazonses.com`; it shows up as a
   permanent bounce in the table `mail_bounce` and on the admin page.

A permanent bounce or a complaint stops all mail to that user at once;
transient bounces stop it after 5 on at least 3 different days. After
changing the site's address a new subscription (step 4) is needed.
`flask --app edoko edoko mail-reset ADDRESS` turns the mail on again.

## This branch

Branch `flask` is the rewrite; `main` is still the PHP site (running at
doko.nubati.net until the cutover). The PHP files stay in the tree as
reference while pages are ported and are removed at the cutover, after
which `main` takes over this branch (fast-forward).
