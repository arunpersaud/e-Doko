"""Demo mode (FLASK_DEMO=1): try the site with nobody getting mail.

Meant for a throwaway instance with a throwaway copy of the database. Mail is not sent but stored in the table demo_mail; every
player's name becomes a button that shows the mail this person would have
got, with working links, and lets you continue as that person. Never run it
with the real database or with mail credentials: the app refuses to start.
"""

import re
from itertools import count

from flask import Blueprint, abort, current_app, redirect, render_template, request, url_for
from markupsafe import Markup, escape

from . import auth, digest
from .db import get_db, query as query_all

bp = Blueprint("demo", __name__, url_prefix="/demo")

SCHEMA = """
CREATE TABLE IF NOT EXISTS demo_mail (
    id      INTEGER PRIMARY KEY,
    to_addr TEXT NOT NULL,
    subject TEXT NOT NULL,
    body    TEXT NOT NULL,
    created TEXT NOT NULL DEFAULT (datetime('now'))
)
"""

URL = re.compile(r"https?://[^\s<>]+")


def enabled() -> bool:
    return bool(current_app.config.get("DEMO"))


def store(to: str, subject: str, body: str) -> None:
    """Keep a mail instead of sending it."""
    get_db().execute(
        "INSERT INTO demo_mail (to_addr, subject, body) VALUES (?, ?, ?)", (to, subject, body)
    )


def mails_for(user_id: int, limit: int = 8) -> list:
    return query_all(
        """SELECT demo_mail.* FROM demo_mail JOIN user ON lower(user.email) = lower(demo_mail.to_addr)
           WHERE user.id = ? ORDER BY demo_mail.id DESC LIMIT ?""",
        (user_id, limit),
    )


def mails_to(address: str, limit: int = 3) -> list:
    """The latest mails to an address (it may belong to nobody yet: a sign-up)."""
    return query_all(
        "SELECT * FROM demo_mail WHERE lower(to_addr) = lower(?) ORDER BY id DESC LIMIT ?",
        (address, limit),
    )


def linkify(text: str) -> Markup:
    """A mail text as HTML: the links to this site work (relative), others stay text."""
    origin = current_app.config["ORIGIN"].rstrip("/")
    out, pos = [], 0
    for m in URL.finditer(text):
        out.append(escape(text[pos : m.start()]))
        url = m.group(0)
        if url.startswith(origin):
            out.append(Markup('<a href="{}">{}</a>').format(url[len(origin) :] or "/", url))
        else:
            out.append(escape(url))
        pos = m.end()
    out.append(escape(text[pos:]))
    return Markup("").join(out)


@bp.route("/")
def index():
    users = query_all(
        """SELECT user.id, user.name, user.email,
                  (SELECT count(*) FROM demo_mail WHERE lower(to_addr) = lower(user.email)) AS mails
           FROM user WHERE disabled_at IS NULL ORDER BY mails DESC, lower(name)"""
    )
    return render_template("demo.html", users=users)


@bp.route("/mail/<int:user_id>")
def inbox(user_id):
    user = get_db().execute("SELECT id, name FROM user WHERE id = ?", (user_id,)).fetchone()
    if user is None:
        abort(404)
    return render_template("demo_mail.html", person=user, mails=mails_for(user_id, 100))


@bp.route("/login/<int:user_id>", methods=["POST"])
def login(user_id):
    if get_db().execute("SELECT 1 FROM user WHERE id = ?", (user_id,)).fetchone() is None:
        abort(404)
    auth.log_in(user_id)
    # back to the page the button was on (a path on this site only)
    target = request.form.get("next", "")
    if not target.startswith("/") or target.startswith("//") or "\\" in target:
        target = url_for("site.index")
    return redirect(target)


@bp.route("/digest", methods=["POST"])
def run_digest():
    """Send the digests now, whatever the hour."""
    digest.run(get_db(), force=True)
    return redirect(url_for("demo.index"))


def init_app(app) -> None:
    if not app.config.get("DEMO"):
        return
    for key in ("SMTP_PASSWORD", "SES_TOPIC_ARN"):
        if app.config.get(key):
            raise RuntimeError(f"FLASK_DEMO is on, but FLASK_{key} is set: refusing to start")
    app.config["MAIL_BACKEND"] = "demo"
    ids = count(1)
    app.jinja_env.globals.update(
        demo=True, demo_mails=mails_for, demo_id=lambda: next(ids), demo_linkify=linkify
    )
    app.register_blueprint(bp)
    with app.app_context():
        get_db().executescript(SCHEMA)
