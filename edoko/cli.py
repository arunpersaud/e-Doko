"""Command line: `flask --app edoko edoko ...`."""

from pathlib import Path

import click
from flask import current_app
from flask.cli import AppGroup

from . import bounces, db, migrations

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


@edoko.command("users")
def users() -> None:
    """List the users: id, admin, passkeys, last login, name, email."""
    rows = db.connect(current_app.config["DATABASE"]).execute(
        """SELECT user.id, user.name, user.email, user.is_admin, user.disabled_at,
                  user.last_login,
                  (SELECT count(*) FROM passkey WHERE passkey.user = user.id) AS keys
           FROM user ORDER BY user.id"""
    )
    for u in rows:
        flags = ("admin" if u["is_admin"] else "").ljust(5) + (
            " disabled" if u["disabled_at"] else ""
        )
        click.echo(
            f"{u['id']:4d}  {flags:14s} {u['keys']} passkeys  "
            f"{(u['last_login'] or '-')[:10]:10s}  {u['name']} <{u['email']}>"
        )


@edoko.command("mail-reset")
@click.argument("email")
def mail_reset(email: str) -> None:
    """Send mail to this user again after bounces (the address works now)."""
    user = db.query_one("SELECT id FROM user WHERE email = ?", (email,))
    if user is None:
        raise click.ClickException(f"no user with the address {email}")
    bounces.reset(user["id"])
    click.echo(f"mail to {email} is on again")


@edoko.command("make-admin")
@click.argument("email")
@click.option("--remove", is_flag=True, help="take the admin rights away")
def make_admin(email: str, remove: bool) -> None:
    """Make the user with this email address an admin (several are fine)."""
    conn = db.connect(current_app.config["DATABASE"])
    changed = conn.execute(
        "UPDATE user SET is_admin = ? WHERE email = ?", (0 if remove else 1, email)
    ).rowcount
    if not changed:
        raise click.ClickException(f"no user with the address {email}")
    click.echo(("removed admin rights of " if remove else "admin: ") + email)
