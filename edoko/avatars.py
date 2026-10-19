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

from flask import Blueprint, Response, abort, current_app, request
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


def store(user_id: int, source: str, image: bytes) -> None:
    get_db().execute(
        """INSERT INTO avatar (user, source, image, content_type, updated_at)
           VALUES (?, ?, ?, 'image/webp', datetime('now'))
           ON CONFLICT (user) DO UPDATE SET source = excluded.source,
             image = excluded.image, content_type = excluded.content_type,
             updated_at = excluded.updated_at""",
        (user_id, source, image),
    )


def remove(user_id: int) -> None:
    get_db().execute("DELETE FROM avatar WHERE user = ?", (user_id,))


def source_of(user_id: int):
    """Where the picture of a user comes from: a row (source, updated_at) or None."""
    return query_one("SELECT source, updated_at FROM avatar WHERE user = ?", (user_id,))


def picture(user_id: int) -> tuple[bytes, str] | None:
    """The stored picture of a user: (image, content type), or None."""
    row = query_one("SELECT image, content_type FROM avatar WHERE user = ?", (user_id,))
    return (row["image"], row["content_type"]) if row else None


@bp.route("/avatar/<int:user_id>")
@login_required
def avatar(user_id):
    if query_one("SELECT 1 FROM user WHERE id = ?", (user_id,)) is None:
        abort(404)
    stored = picture(user_id)
    if stored:
        body, content_type = stored
    else:
        body = identicon(user_id, current_app.config["SECRET_KEY"]).encode()
        content_type = "image/svg+xml"
    response = Response(body, mimetype=content_type)
    # for members only; they check with the ETag, so a new picture shows at once
    response.headers["Cache-Control"] = "private, no-cache"
    response.set_etag(hashlib.sha256(body).hexdigest()[:20])
    return response.make_conditional(request)


def init_app(app) -> None:
    app.register_blueprint(bp)
