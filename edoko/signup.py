"""Sign-up: an address, a mailed single-use link, then the choice of a name.

No password and no CAPTCHA: the link proves the address, and requests are
limited per client and per address. Somebody who asks for an account with an
address that already has one gets a login link instead, and the answer is the
same either way, so nobody can find out who has an account.
"""

import re
import secrets
import sqlite3
from datetime import UTC, datetime, timedelta

from flask import Blueprint, current_app, render_template, request, session, url_for
from flask_babel import force_locale
from flask_babel import gettext as _
from flask_babel import lazy_gettext as _l

from . import auth, mail
from .db import get_db, query_one

bp = Blueprint("signup", __name__)

LINK_HOURS = 24
LIMIT_ADDRESS = 3  # mails per address and hour
LIMIT_CLIENT = 10  # requests per client and hour
NAME_LENGTH = (2, 40)
ADDRESS = re.compile(r"[^@\s]+@[^@\s]+\.[^@\s]+")


def clean_name(text: str | None) -> str:
    return " ".join((text or "").split())


def name_problem(name: str) -> str | None:
    low, high = NAME_LENGTH
    if not low <= len(name) <= high:
        return _("The name needs %(low)d to %(high)d characters.", low=low, high=high)
    if any(not c.isprintable() for c in name) or "<" in name or ">" in name:
        return _("Please use letters, digits and ordinary punctuation in the name.")
    if query_one("SELECT 1 FROM user WHERE name = ?", (name,)):
        return _("Somebody already uses that name.")
    return None


def valid_token(token: str):
    return query_one(
        "SELECT * FROM signup_token WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?",
        (auth.sha256(token), auth.now()),
    )


@bp.route("/signup")
def form():
    auth.csrf_token()
    return render_template("signup.html")


@bp.post("/signup")
def request_link():
    address = clean_name(request.form.get("email")).lower()
    get_db().execute("DELETE FROM signup_token WHERE expires_at < datetime('now', '-1 day')")
    limited = auth.rate_limited("ip:" + auth.sha256(auth.client_ip()), LIMIT_CLIENT)
    if (
        ADDRESS.fullmatch(address)
        and len(address) <= 254
        and not limited
        and not auth.rate_limited("addr:" + auth.sha256(address), LIMIT_ADDRESS)
    ):
        user = query_one("SELECT * FROM user WHERE email = ? AND disabled_at IS NULL", (address,))
        if user is not None:  # already has an account: the login link is what they need
            token = auth.create_link(user["id"])
            link = current_app.config["ORIGIN"] + url_for("auth.link", token=token)
            mail.send_template(
                user, _l("Your login link"), "login", link=link, hours=auth.LINK_HOURS
            )
        elif query_one("SELECT 1 FROM user WHERE email = ?", (address,)) is None:
            token = secrets.token_urlsafe(32)
            expires = datetime.now(UTC) + timedelta(hours=LINK_HOURS)
            get_db().execute(
                "INSERT INTO signup_token (email, token_hash, expires_at) VALUES (?, ?, ?)",
                (address, auth.sha256(token), expires.strftime("%Y-%m-%d %H:%M:%S")),
            )
            link = current_app.config["ORIGIN"] + url_for("signup.choose", token=token)
            with force_locale(request.accept_languages.best_match(["en", "de"]) or "en"):
                subject = str(_l("Welcome to e-DoKo"))
                body = render_template("mail/signup.txt", link=link, hours=LINK_HOURS)
            mail.send(address, subject, body)
    demo_mails = []
    if current_app.config.get("DEMO"):  # (see demo.py: no mail goes out, show it here)
        from . import demo

        demo_mails = demo.mails_to(address)
    return render_template("signup_sent.html", demo_mails=demo_mails)


@bp.route("/signup/<token>")
def choose(token):
    row = valid_token(token)
    if row is None:
        return render_template("signup_invalid.html"), 404
    auth.csrf_token()
    return render_template("signup_name.html", token=token, name="", problem=None)


@bp.post("/signup/<token>")
def create(token):
    row = valid_token(token)
    if row is None:
        return render_template("signup_invalid.html"), 404
    name = clean_name(request.form.get("name"))
    problem = name_problem(name)
    if problem:
        return render_template("signup_name.html", token=token, name=name, problem=problem), 400
    # the account is made when the passkey has been verified (passkeys.py)
    session["signup"] = {"token": token, "name": name}
    auth.csrf_token()
    return render_template("signup_passkey.html", name=name)


def pending() -> dict | None:
    """The sign-up in this session (name chosen, passkey to come), if its link still works."""
    state = session.get("signup")
    if not state or valid_token(state["token"]) is None:
        return None
    return state


def create_account(state: dict, webauthn_id: bytes, credential: dict, language: str | None) -> int:
    """The user and the first passkey, in one step; raises ValueError if the name is taken."""
    row = valid_token(state["token"])
    if row is None:
        raise ValueError(_("This link does not work any more."))
    db = get_db()
    try:
        db.execute("BEGIN IMMEDIATE")
        db.execute("UPDATE signup_token SET used_at = ? WHERE id = ?", (auth.now(), row["id"]))
        user_id = db.execute(
            "INSERT INTO user (name, email, language, webauthn_id) VALUES (?, ?, ?, ?)",
            (state["name"], row["email"], language, webauthn_id),
        ).lastrowid
        db.execute(
            "INSERT INTO passkey (user, credential_id, public_key, sign_count, name)"
            " VALUES (?, ?, ?, ?, ?)",
            (user_id, *credential),
        )
        db.execute("COMMIT")
    except sqlite3.IntegrityError:
        db.execute("ROLLBACK")
        raise ValueError(_("Somebody already uses that name.")) from None
    return user_id


def init_app(app) -> None:
    app.register_blueprint(bp)
