#!/usr/bin/env python3
"""A made-up game for the picture on the start page.

    uv run python tools/home_screenshot.py DIR

Writes DIR/edoko.sqlite: four invented players (Anna, Ben, Carla, Dieter), a
random deal that is not from a real game, four tricks played, one player
calling Re and two remarks on the fourth trick. Prints "game=N viewer=ID".

To take the picture: run the app in demo mode on that database (FLASK_DEMO=1
FLASK_DATABASE=DIR/edoko.sqlite ...), log in as the viewer with the demo
page, open /game/N/trick/4 in a window 720 px wide (so the remarks hang off the
players as speech bubbles) and save a screenshot of the element #table with a
transparent background, once with the browser in light and once in dark mode
(edoko/static/home-table-light.png and home-table-dark.png; hide .remark-form
first). The start page picks one with prefers-color-scheme.
"""

import random
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from edoko import create_app, db, play, rules  # noqa: E402

NAMES = ["Anna", "Ben", "Carla", "Dieter"]
RS = rules.Rules("secondwins", "second", "poverty", "1st-own-card")
QUEEN_OF_CLUBS = (3, 4)


def build(folder: Path) -> tuple[int, int]:
    path = folder / "edoko.sqlite"
    db.init_db(path)
    app = create_app({"DATABASE": str(path), "DEMO": 1, "SECRET_KEY": "x", "ORIGIN": "http://127.0.0.1:5058"})
    with app.app_context():
        conn = db.connect(path)
        for i, name in enumerate(NAMES, 1):
            conn.execute(
                "INSERT INTO user (id, name, email) VALUES (?, ?, ?)",
                (i, name, f"{name.lower()}@example.invalid"),
            )
        for seed in range(1, 500):
            random.seed(seed)
            game_id = play.create_game(conn, [1, 2, 3, 4], RS)
            state = play.load(conn, game_id)
            clear = all(not play.sickness_options(state.start[s], RS) for s in range(1, 5))
            trumps = play.trump_count(state.start[1], RS)
            has_queen = any(c in QUEEN_OF_CLUBS for s in (2, 3, 4) for c in state.start[s])
            if clear and 5 <= trumps <= 7 and has_queen:
                break
        else:
            raise SystemExit("no suitable deal found")
            # (the same four players would be handed the same unseen deal again)
            conn.execute("DELETE FROM game WHERE id = ?", (game_id,))
            conn.execute("DELETE FROM session_player")
            conn.execute("DELETE FROM session")
            conn.execute("DELETE FROM deal")
        for seat, h in {r["seat"]: r for r in conn.execute("SELECT * FROM hand WHERE game = ?", (game_id,))}.items():
            play.join(conn, h["id"])
            play.declare(conn, h["id"], "none", None)
        rng = random.Random(7)
        called = False
        while len(play.load(conn, game_id).tricks) < 4 or play.load(conn, game_id).current:
            state = play.load(conn, game_id)
            seat = state.game["turn_seat"]
            card = rng.choice(play.legal_cards(state, seat))
            re_player = any(c in QUEEN_OF_CLUBS for c in state.start[seat])
            call = 120 if re_player and not called and play.possible_calls(state, seat) else None
            called = called or call is not None
            play.play_card(conn, state.hands[seat]["id"], card, call)
        state = play.load(conn, game_id)
        last = conn.execute(
            "SELECT play.id, hand.seat, hand.user FROM play JOIN trick ON trick.id = play.trick"
            " JOIN hand_card ON hand_card.id = play.hand_card JOIN hand ON hand.id = hand_card.hand"
            " WHERE trick.game = ? AND trick.number = 4 ORDER BY play.sequence",
            (game_id,),
        ).fetchall()
        turn = conn.execute("SELECT turn_seat FROM game WHERE id = ?", (game_id,)).fetchone()[0]
        viewer = next(s for s in (1, 2, 3, 4) if s != turn)
        viewer_user = state.hands[viewer]["user"]
        # the remarks come from two others, so the viewer sees no "change" links
        others = [row for row in last if row["user"] != viewer_user]
        play.add_remark(conn, game_id, others[0]["user"], others[0]["id"], "Oh, I was hoping for that one!")
        play.add_remark(conn, game_id, others[2]["user"], others[2]["id"], "Nice trick. Re wins this one.")
        return game_id, viewer_user


if __name__ == "__main__":
    folder = Path(sys.argv[1])
    folder.mkdir(parents=True, exist_ok=True)
    game, viewer = build(folder)
    print(f"game={game} viewer={viewer}")
