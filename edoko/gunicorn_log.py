"""Access log without seat tokens.

A seat link (/<token>, or the old index.php?action=game&me=<token>) is a
secret, so it must not end up in a log file. Use it with
--logger-class edoko.gunicorn_log.Logger.
"""

import re

from gunicorn.glogging import Logger as BaseLogger

TOKEN = r"(?:[0-9a-f]{32}|[A-Za-z0-9_-]{22})"
PATH = re.compile(rf"(?<=/){TOKEN}(?=[/? ]|$)")
QUERY = re.compile(rf"(?<=[?&]me=){TOKEN}")


def mask(text: str) -> str:
    return QUERY.sub("<token>", PATH.sub("<token>", text))


class Logger(BaseLogger):
    def atoms(self, resp, req, environ, request_time):
        atoms = super().atoms(resp, req, environ, request_time)
        for key in ("r", "U", "q", "RU"):
            if key in atoms:
                atoms[key] = mask(atoms[key])
        return atoms
