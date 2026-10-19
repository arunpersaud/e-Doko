"""Pictures of the players.

Everybody gets a generated picture (an identicon, drawn locally from the
user id and the secret key, so it says nothing about the person). A player
may replace it with a picture of their own: an upload, or the Gravatar of
their address, fetched by our server only when they ask for it. Whatever
the source, the picture is stored here and served from this site, so no
browser ever contacts a third party.
"""

import hashlib
import hmac
import io
import urllib.error
import urllib.parse
import urllib.request

from flask import Blueprint, Response, abort, current_app, g, request
from PIL import Image, ImageOps, UnidentifiedImageError

from .auth import login_required
from .db import get_db, query_one

bp = Blueprint("avatars", __name__)

CELLS = 5
SIZE = 192  # pixels, a square
MAX_UPLOAD = 2 * 1024 * 1024  # bytes
MAX_PIXELS = 24_000_000  # refuse bigger pictures: they could eat all memory
FORMATS = ["PNG", "JPEG", "WEBP", "GIF"]
Image.MAX_IMAGE_PIXELS = MAX_PIXELS


class BadPicture(ValueError):
    """The data is not a picture we are willing to use."""


class NoGravatar(Exception):
    """Gravatar has no picture for this address."""


class GravatarUnavailable(Exception):
    """Gravatar could not be reached or answered with something odd."""


GRAVATAR = "https://gravatar.com/avatar/"
GRAVATAR_HOSTS = ("gravatar.com", "secure.gravatar.com", "www.gravatar.com")
GRAVATAR_TIMEOUT = 10  # seconds
GRAVATAR_TRIES = 10  # per user and hour


def gravatar_url(email: str) -> str:
    """The address of the Gravatar for an email address (we send a SHA-256 hash).

    d=404: an answer of 404 if there is none (not a picture of their own).
    """
    digest = hashlib.sha256(email.strip().lower().encode()).hexdigest()
    return f"{GRAVATAR}{digest}?{urllib.parse.urlencode({'s': SIZE, 'd': '404'})}"


class _OnlyGravatar(urllib.request.HTTPRedirectHandler):
    """Follow redirects, but only to https pages of Gravatar itself."""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        target = urllib.parse.urlsplit(newurl)
        if target.scheme != "https" or target.hostname not in GRAVATAR_HOSTS:
            raise urllib.error.URLError(f"redirect to {newurl!r} refused")
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def fetch_gravatar(email: str) -> bytes:
    """Download the Gravatar of an address; only called when the user asks.

    Raises NoGravatar or GravatarUnavailable.
    """
    request = urllib.request.Request(
        gravatar_url(email), headers={"User-Agent": "e-DoKo (doko.nubati.net)"}
    )
    opener = urllib.request.build_opener(_OnlyGravatar)
    try:
        with opener.open(request, timeout=GRAVATAR_TIMEOUT) as response:
            data = response.read(MAX_UPLOAD + 1)
    except urllib.error.HTTPError as error:
        if error.code == 404:
            raise NoGravatar from error
        raise GravatarUnavailable(f"Gravatar answered {error.code}") from error
    except (urllib.error.URLError, OSError) as error:
        raise GravatarUnavailable(str(error)) from error
    if len(data) > MAX_UPLOAD:
        raise GravatarUnavailable("the picture is too big")
    return data


def identicon(user_id: int, secret: str) -> str:
    """A symmetric 5x5 pattern in a colour of its own, as SVG (no scripts or styles)."""
    digest = hmac.new(
        secret.encode(), f"avatar:{user_id}".encode(), hashlib.sha256
    ).digest()
    hue = digest[0] * 360 // 256
    ink = f"hsl({hue} 55% 45%)"
    paper = f"hsl({hue} 35% 92%)"
    rects = []
    for row in range(CELLS):
        for col in range((CELLS + 1) // 2):
            if digest[1 + row * 3 + col] & 1:
                for x in {col, CELLS - 1 - col}:
                    rects.append(f'<rect x="{x}" y="{row}" width="1" height="1"/>')
    return (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 5 5" width="160" '
        f'height="160"><rect width="5" height="5" fill="{paper}"/>'
        f'<g fill="{ink}">{"".join(rects)}</g></svg>'
    )


def process(data: bytes) -> bytes:
    """Make a small, clean square WebP of an uploaded picture.

    Only PNG, JPEG, WebP and (the first frame of) GIF are accepted, and the
    picture is decoded and drawn again, so nothing of the original file
    (metadata, extra data, a script in a polyglot) is kept.
    """
    try:
        with Image.open(io.BytesIO(data), formats=FORMATS) as image:
            if image.width * image.height > MAX_PIXELS:
                raise BadPicture("This picture is too big (too many pixels).")
            image.load()
            image = ImageOps.exif_transpose(image)
            rgba = image.convert("RGBA")
    except (UnidentifiedImageError, OSError, Image.DecompressionBombError) as error:
        raise BadPicture("This is not a picture we can use.") from error
    flat = Image.new("RGB", rgba.size, "white")  # transparent parts become white
    flat.paste(rgba, mask=rgba.getchannel("A"))
    square = ImageOps.fit(flat, (SIZE, SIZE), Image.Resampling.LANCZOS)
    out = io.BytesIO()
    square.save(out, format="WEBP", quality=85, method=4)
    return out.getvalue()


SOURCES = ("generated", "upload", "gravatar")


def store(user_id: int, source: str, image: bytes) -> None:
    """Keep a picture and make it the one that is shown."""
    db = get_db()
    db.execute("UPDATE avatar SET chosen = 0 WHERE user = ?", (user_id,))
    db.execute(
        """INSERT INTO avatar (user, source, image, content_type, updated_at, chosen)
           VALUES (?, ?, ?, 'image/webp', datetime('now'), 1)
           ON CONFLICT (user, source) DO UPDATE SET image = excluded.image,
             content_type = excluded.content_type, updated_at = excluded.updated_at,
             chosen = 1""",
        (user_id, source, image),
    )


def choose(user_id: int, source: str) -> bool:
    """Show another of the kept pictures, or the generated one (False: none kept)."""
    db = get_db()
    if source != "generated" and not query_one(
        "SELECT 1 FROM avatar WHERE user = ? AND source = ?", (user_id, source)
    ):
        return False
    db.execute("UPDATE avatar SET chosen = 0 WHERE user = ?", (user_id,))
    if source != "generated":
        db.execute(
            "UPDATE avatar SET chosen = 1 WHERE user = ? AND source = ?",
            (user_id, source),
        )
    return True


def delete(user_id: int, source: str) -> None:
    """Throw away a kept picture (if it was shown, the generated one is back)."""
    get_db().execute(
        "DELETE FROM avatar WHERE user = ? AND source = ?", (user_id, source)
    )


def kept(user_id: int) -> dict:
    """The kept pictures of a user: source -> row (updated_at, chosen)."""
    rows = get_db().execute(
        "SELECT source, updated_at, chosen FROM avatar WHERE user = ?", (user_id,)
    )
    return {row["source"]: row for row in rows}


def picture(user_id: int, source: str | None = None) -> tuple[bytes, str] | None:
    """A kept picture of a user: the shown one, or the one of a given source."""
    if source is None:
        where, params = "chosen = 1", (user_id,)
    else:
        where, params = "source = ?", (user_id, source)
    row = query_one(
        f"SELECT image, content_type FROM avatar WHERE user = ? AND {where}", params
    )
    return (row["image"], row["content_type"]) if row else None


def respond(body: bytes, content_type: str) -> Response:
    response = Response(body, mimetype=content_type)
    # for members only; they check with the ETag, so a new picture shows at once
    response.headers["Cache-Control"] = "private, no-cache"
    response.set_etag(hashlib.sha256(body).hexdigest()[:20])
    return response.make_conditional(request)


def generated(user_id: int) -> tuple[bytes, str]:
    svg = identicon(user_id, current_app.config["SECRET_KEY"])
    return svg.encode(), "image/svg+xml"


@bp.route("/avatar/<int:user_id>")
@login_required
def avatar(user_id):
    if query_one("SELECT 1 FROM user WHERE id = ?", (user_id,)) is None:
        abort(404)
    return respond(*(picture(user_id) or generated(user_id)))


@bp.route("/avatar/<int:user_id>/<source>")
@login_required
def preview(user_id, source):
    """One of your own kept pictures (or the generated one), to choose between."""
    if user_id != g.user["id"] or source not in SOURCES:
        abort(404)
    found = generated(user_id) if source == "generated" else picture(user_id, source)
    if found is None:
        abort(404)
    return respond(*found)


def init_app(app) -> None:
    app.register_blueprint(bp)
