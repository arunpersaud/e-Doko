"""Command line: `flask --app edoko edoko ...`."""

from pathlib import Path

import click
from flask import current_app
from flask.cli import AppGroup

from . import db, migrations

edoko = AppGroup("edoko", help="e-DoKo database tools.")


@edoko.command("init-db")
def init_db() -> None:
    """Create an empty database at FLASK_DATABASE."""
    path = Path(current_app.config["DATABASE"])
    path.parent.mkdir(parents=True, exist_ok=True)
    db.init_db(path)
    click.echo(f"created {path}")


@edoko.command("migrate")
def migrate() -> None:
    """Apply missing schema migrations (also done at startup)."""
    applied = migrations.migrate(Path(current_app.config["DATABASE"]))
    click.echo(f"applied: {applied}" if applied else "up to date")
