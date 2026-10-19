from flask import Blueprint, render_template

from . import db

site = Blueprint("site", __name__)


@site.route("/")
def index():
    games = db.query_one("SELECT count(*) AS n FROM game")["n"]
    return render_template("index.html", games=games)
