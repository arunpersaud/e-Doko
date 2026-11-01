"""Repair text that was UTF-8 but got read as Windows-1252/Latin-1 once.

The old PHP data has comments like "GrÃ¼ÃŸe" for "Grüße". The text is only
changed if undoing that mistake gives valid UTF-8 for the whole text.
"""


def repair(text: str | None) -> str | None:
    if not text or text.isascii():
        return text
    try:
        raw = b"".join(
            c.encode("cp1252") if c.encode("cp1252", "ignore") else c.encode("latin-1")
            for c in text
        )
        fixed = raw.decode("utf-8")
    except UnicodeError:
        return text
    return fixed
