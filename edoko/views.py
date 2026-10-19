import re

from flask import Blueprint, redirect, render_template, request, url_for

from . import db

site = Blueprint("site", __name__)


@site.route("/")
def index():
    games = db.query_one("SELECT count(*) AS n FROM game")["n"]
    return render_template("index.html", games=games)


@site.route("/index.php")
def legacy():
    """Links of the PHP site: index.php?action=game&me=<token> opens the seat."""
    token = request.args.get("me", "")
    if request.args.get("action") == "game" and re.fullmatch(
        r"[0-9a-f]{32}|[A-Za-z0-9_-]{22}", token
    ):
        return redirect(url_for("games.seat", token=token))
    return redirect(url_for("site.index"))
