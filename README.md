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

Configuration: `FLASK_DATABASE`, `FLASK_SECRET_KEY`, `FLASK_ORIGIN`
(see `edoko/__init__.py`).

## This branch

Branch `flask` is the rewrite; `main` is still the PHP site (running at
doko.nubati.net until the cutover). The PHP files stay in the tree as
reference while pages are ported and are removed at the cutover, after
which `main` takes over this branch (fast-forward).
