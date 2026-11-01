"""Passkeys (WebAuthn): log in with one, add, rename and remove them.

Passkeys must verify the user (fingerprint, face or device PIN), so a
device alone isn't enough to log in. They are bound to the host name of
ORIGIN. A user can always get back in with an emailed login link, so
removing the last passkey is allowed.
"""

import json
import secrets

import webauthn
from flask import (
    Blueprint,
    abort,
    current_app,
    g,
    jsonify,
    redirect,
    render_template,
    request,
    session,
    url_for,
)
from flask_babel import gettext as _
from webauthn.helpers import bytes_to_base64url
from webauthn.helpers.exceptions import (
    InvalidAuthenticationResponse,
    InvalidRegistrationResponse,
)
from webauthn.helpers.structs import (
    AuthenticatorSelectionCriteria,
    PublicKeyCredentialDescriptor,
    ResidentKeyRequirement,
    UserVerificationRequirement,
)

from . import signup
from .auth import SKIP_COOKIE, log_in, login_required, now, safe_next
from .db import get_db, query, query_one

RP_NAME = "e-DoKo"
SKIP_DAYS = 30

bp = Blueprint("passkeys", __name__)


def rp_id() -> str:
    return current_app.config["RP_ID"]


def origin() -> str:
    return current_app.config["ORIGIN"]


# --- pages -------------------------------------------------------------------


@bp.route("/account/passkeys")
@login_required
def manage():
    rows = query(
        "SELECT id, name, created_at, last_used_at FROM passkey"
        " WHERE user = ? ORDER BY created_at, id",
        (g.user["id"],),
    )
    return render_template("passkeys.html", passkeys=rows)


@bp.route("/account/passkeys/offer")
@login_required
def offer():
    return render_template(
        "passkey_offer.html", next=safe_next(request.args.get("next"))
    )


@bp.post("/account/passkeys/skip")
@login_required
def skip():
    response = redirect(safe_next(request.form.get("next")))
    response.set_cookie(
        SKIP_COOKIE,
        "1",
        max_age=SKIP_DAYS * 24 * 3600,
        httponly=True,
        samesite="Lax",
        secure=current_app.config["ORIGIN"].startswith("https://"),
    )
    return response


@bp.post("/account/passkeys/<int:key>/rename")
@login_required
def rename(key):
    name = " ".join((request.form.get("name") or "").split())[:60]
    changed = (
        get_db()
        .execute(
            "UPDATE passkey SET name = ? WHERE id = ? AND user = ?",
            (name, key, g.user["id"]),
        )
        .rowcount
    )
    if not changed:
        abort(404)
    return redirect(url_for("passkeys.manage"))


@bp.post("/account/passkeys/<int:key>/delete")
@login_required
def delete(key):
    deleted = (
        get_db()
        .execute("DELETE FROM passkey WHERE id = ? AND user = ?", (key, g.user["id"]))
        .rowcount
    )
    if not deleted:
        abort(404)
    return redirect(url_for("passkeys.manage"))


# --- registration ------------------------------------------------------------


@bp.post("/passkey/register/options")
def register_options():
    data = request.get_json(silent=True) or {}
    signing_up = g.user is None and signup.pending()
    if g.user is None and not signing_up:
        return jsonify(error=_("Please log in first.")), 401
    if signing_up:  # a new account: made when the passkey is verified
        webauthn_id, user_name, existing = secrets.token_bytes(32), signing_up["name"], []
    else:
        webauthn_id, user_name = g.user["webauthn_id"], g.user["name"]
        existing = query("SELECT credential_id FROM passkey WHERE user = ?", (g.user["id"],))
    options = webauthn.generate_registration_options(
        rp_id=rp_id(),
        rp_name=RP_NAME,
        user_id=webauthn_id,
        user_name=user_name,
        authenticator_selection=AuthenticatorSelectionCriteria(
            resident_key=ResidentKeyRequirement.REQUIRED,
            user_verification=UserVerificationRequirement.REQUIRED,
        ),
        exclude_credentials=[
            PublicKeyCredentialDescriptor(id=c["credential_id"]) for c in existing
        ],
    )
    session["registration"] = {
        "challenge": bytes_to_base64url(options.challenge),
        "user": None if signing_up else g.user["id"],
        "webauthn_id": bytes_to_base64url(webauthn_id) if signing_up else None,
        "name": " ".join((data.get("name") or "").split())[:60],
    }
    return current_app.response_class(
        webauthn.options_to_json(options), mimetype="application/json"
    )


@bp.post("/passkey/register/verify")
def register_verify():
    state = session.pop("registration", None)
    signing_up = state is not None and state["user"] is None and signup.pending()
    if state is None or not (
        signing_up or (g.user is not None and g.user["id"] == state["user"])
    ):
        return jsonify(error=_("Please start again.")), 400
    try:
        verified = webauthn.verify_registration_response(
            credential=request.get_data(as_text=True),
            expected_challenge=webauthn.base64url_to_bytes(state["challenge"]),
            expected_rp_id=rp_id(),
            expected_origin=origin(),
            require_user_verification=True,
        )
    except InvalidRegistrationResponse:
        return jsonify(error=_("The passkey could not be verified.")), 400
    credential = (
        verified.credential_id,
        verified.credential_public_key,
        verified.sign_count,
        state["name"],
    )
    if signing_up:
        try:
            user_id = signup.create_account(
                signing_up,
                webauthn.base64url_to_bytes(state["webauthn_id"]),
                credential,
                request.accept_languages.best_match(["en", "de"]),
            )
        except ValueError as problem:
            return jsonify(error=str(problem)), 400
        session.pop("signup", None)
        log_in(user_id)
        return jsonify(ok=True, next=url_for("site.index"))
    get_db().execute(
        "INSERT INTO passkey (user, credential_id, public_key, sign_count, name)"
        " VALUES (?, ?, ?, ?, ?)",
        (state["user"], *credential),
    )
    return jsonify(ok=True, next=url_for("passkeys.manage"))


# --- login -------------------------------------------------------------------


@bp.post("/passkey/login/options")
def login_options():
    data = request.get_json(silent=True) or {}
    # discoverable credentials: the browser offers the passkeys it has
    options = webauthn.generate_authentication_options(
        rp_id=rp_id(), user_verification=UserVerificationRequirement.REQUIRED
    )
    session["authentication"] = {
        "challenge": bytes_to_base64url(options.challenge),
        "next": safe_next(data.get("next")),
    }
    return current_app.response_class(
        webauthn.options_to_json(options), mimetype="application/json"
    )


@bp.post("/passkey/login/verify")
def login_verify():
    state = session.pop("authentication", None)
    data = request.get_json(silent=True) or {}
    if state is None or "rawId" not in data:
        return jsonify(error=_("Please start again.")), 400
    try:
        credential_id = webauthn.base64url_to_bytes(data["rawId"])
    except ValueError:
        return jsonify(error=_("Unknown passkey.")), 400
    row = query_one(
        "SELECT passkey.*, user.disabled_at FROM passkey"
        " JOIN user ON user.id = passkey.user WHERE passkey.credential_id = ?",
        (credential_id,),
    )
    if row is None or row["disabled_at"]:
        return jsonify(error=_("This passkey is not registered here.")), 400
    try:
        verified = webauthn.verify_authentication_response(
            credential=json.dumps(data),
            expected_challenge=webauthn.base64url_to_bytes(state["challenge"]),
            expected_rp_id=rp_id(),
            expected_origin=origin(),
            credential_public_key=row["public_key"],
            credential_current_sign_count=row["sign_count"],
            require_user_verification=True,
        )
    except InvalidAuthenticationResponse:
        return jsonify(error=_("The passkey could not be verified.")), 400
    get_db().execute(
        "UPDATE passkey SET sign_count = ?, last_used_at = ? WHERE id = ?",
        (verified.new_sign_count, now(), row["id"]),
    )
    log_in(row["user"])
    return jsonify(ok=True, next=state["next"])


def init_app(app) -> None:
    app.register_blueprint(bp)
