"""e-DoKo, online Doppelkopf (doko.nubati.net).

Configuration comes from environment variables with a FLASK_ prefix:

    FLASK_DATABASE     SQLite file (default: instance/edoko.sqlite)
    FLASK_SECRET_KEY   signs the session cookie
    FLASK_ORIGIN       the site's address, e.g. https://doko.nubati.net

FLASK_SECRET_KEY and FLASK_ORIGIN are required unless the app runs in
debug or testing mode.
"""

import hashlib
import os
from datetime import timedelta
from functools import cache
from pathlib import Path

from flask import Flask, request
from flask_babel import Babel, get_locale

from . import db, migrations, views
from .cli import edoko

LANGUAGES = ["en", "de"]

# Everything is served from this site: no inline scripts or styles, no
# CDNs. data: images are the icons inside pico.min.css.
SECURITY_HEADERS = {
    "Content-Security-Policy": "; ".join(
        [
            "default-src 'none'",
            "script-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'none'",
            "frame-ancestors 'none'",
        ]
    ),
    "X-Content-Type-Options": "nosniff",
    "X-Frame-Options": "DENY",
    # the ?me=<hash> links must never leak
    "Referrer-Policy": "no-referrer",
    "Cross-Origin-Opener-Policy": "same-origin",
    "Permissions-Policy": "camera=(), microphone=(), geolocation=()",
}


def select_locale() -> str | None:
    return request.accept_languages.best_match(LANGUAGES)


def create_app(test_config: dict | None = None) -> Flask:
    app = Flask(__name__)
    app.config["DATABASE"] = os.path.join(app.instance_path, "edoko.sqlite")
    app.config.from_prefixed_env()
    if test_config:
        app.config.update(test_config)

    defaults = {"SECRET_KEY": "insecure-dev-key", "ORIGIN": "http://localhost:5000"}
    for key, value in defaults.items():
        if not app.config.get(key):
            if not (app.debug or app.testing):
                raise RuntimeError(f"FLASK_{key} is not set")
            app.config[key] = value
    https = app.config["ORIGIN"].startswith("https://")
    app.config.update(
        # forms are small; refuse anything bigger
        MAX_CONTENT_LENGTH=64 * 1024,
        SESSION_COOKIE_SECURE=https,
        SESSION_COOKIE_SAMESITE="Lax",
        PERMANENT_SESSION_LIFETIME=timedelta(days=30),
    )

    Babel(app, locale_selector=select_locale)
    app.jinja_env.globals["get_locale"] = get_locale
    db.init_app(app)
    if not app.testing and Path(app.config["DATABASE"]).exists():
        migrations.migrate(Path(app.config["DATABASE"]))

    @cache
    def file_hash(filename: str) -> str | None:
        path = Path(app.static_folder, filename)
        if not path.is_file():
            return None
        return hashlib.sha256(path.read_bytes()).hexdigest()[:12]

    # static URLs carry a hash of the file (?v=...), so a changed file has
    # a new URL and browsers and Cloudflare may keep each version for long
    @app.url_defaults
    def static_version(endpoint, values):
        if endpoint != "static" or "filename" not in values:
            return
        if version := file_hash(values["filename"]):
            values["v"] = version

    @app.after_request
    def set_headers(response):
        response.headers.update(SECURITY_HEADERS)
        if https:
            response.headers["Strict-Transport-Security"] = (
                "max-age=31536000; includeSubDomains"
            )
        if request.endpoint == "static":
            response.cache_control.public = True
            if "v" in request.args:
                response.cache_control.max_age = 365 * 24 * 3600
                response.cache_control.immutable = True
            else:
                response.cache_control.no_cache = True
        return response

    app.register_blueprint(views.site)
    app.cli.add_command(edoko)

    return app
