"""Convert a database of the PHP site (schema version 5) to the Flask schema.

One-off tool (run it again as often as needed while testing):

    uv run python tools/import_php.py OLD.sqlite NEW.sqlite

OLD is only read; NEW must not exist.

Ids of users, games, hands, tricks, plays and cards are kept. Dropped:
passwords (everybody logs in with a passkey or an emailed link), OpenID,
the Game2/Recovery/digest_email leftovers and rows that point at nothing.
Everything dropped or guessed is counted in the returned report.
"""

import sqlite3
import sys
from collections import Counter
from contextlib import closing
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from edoko import db  # noqa: E402

PREFS = {
    "cardset": "card_set",
    "language": "language",
}


def _bool(value: str | None) -> int:
    return 1 if value == "yes" else 0


def _date(value: str | None) -> str | None:
    return value[:10] if value else None


def import_php(old_path: Path, new_path: Path) -> Counter:
    """Create new_path from the PHP database old_path (not modified)."""
    report: Counter = Counter()
    db.init_db(new_path)
    with (
        closing(db.connect(new_path)) as new,
        closing(sqlite3.connect(f"file:{old_path}?mode=ro", uri=True)) as old,
    ):
        old.row_factory = sqlite3.Row
        new.execute("PRAGMA foreign_keys = OFF")
        new.execute("BEGIN")
        try:
            _copy(old, new, report)
            bad = new.execute("PRAGMA foreign_key_check").fetchall()
            if bad:
                kinds = Counter((r[0], r[2]) for r in bad)
                raise RuntimeError(f"foreign key violations (table, parent): {kinds}")
        except BaseException:
            new.execute("ROLLBACK")
            raise
        new.execute("COMMIT")
    return report


def _copy(old: sqlite3.Connection, new: sqlite3.Connection, report: Counter) -> None:
    def rows(sql: str) -> list[sqlite3.Row]:
        return old.execute(sql).fetchall()

    def insert(table: str, **values) -> None:
        names = ", ".join(f'"{k}"' for k in values)
        marks = ", ".join("?" * len(values))
        new.execute(
            f"INSERT INTO {table} ({names}) VALUES ({marks})", tuple(values.values())
        )
        report[f"{table} imported"] += 1

    # users, with the key/value preferences turned into columns
    prefs: dict[int, dict[str, str]] = {}
    for r in rows("SELECT user_id, pref_key, value FROM User_Prefs"):
        prefs.setdefault(r["user_id"], {})[r["pref_key"]] = r["value"]
    for r in rows("SELECT * FROM User ORDER BY id"):
        p = prefs.get(r["id"], {})
        digest = p.get("digest", "digest-off")
        hours = (
            int(digest[7:-1])
            if digest.startswith("digest-") and digest != "digest-off"
            else None
        )
        insert(
            "user",
            id=r["id"],
            name=r["fullname"],
            email=r["email"],
            timezone=r["timezone"] or "Europe/London",
            language=p.get("language") if p.get("language") in ("en", "de") else None,
            card_set=p.get("cardset", "english"),
            mail_mode="fewer" if p.get("email") == "emailaddict" else "normal",
            digest_hours=hours,
            cc_email=_bool(p.get("ccemail")),
            autosetup=_bool(p.get("autosetup")),
            open_for_games=0 if p.get("open for games") == "no" else 1,
            vacation_start=_date(p.get("vacation start")),
            vacation_stop=_date(p.get("vacation stop")),
            vacation_comment=p.get("vacation comment"),
            last_login=r["last_login"],
            created_at=r["create_date"] or "2008-01-01 00:00:01",
        )
    report["passwords dropped"] = len(rows("SELECT id FROM User WHERE password != ''"))

    # static data
    new.execute("DELETE FROM card")
    for r in rows("SELECT * FROM Card ORDER BY id"):
        insert(
            "card", id=r["id"], suit=r["suite"], rank=r["strength"], points=r["points"]
        )
    for r in rows("SELECT * FROM Rulesets ORDER BY id"):
        insert(
            "ruleset",
            id=r["id"],
            dullen=r["dullen"],
            schweinchen=r["schweinchen"],
            lowtrump=r["lowtrump"],
            call=r["call"],
        )

    # games: decks and sessions become tables of their own
    deals: dict[str, int] = {}
    sessions: dict[int, set[int]] = {}
    hands_of = {}
    for r in rows("SELECT game_id, user_id, position FROM Hand"):
        hands_of.setdefault(r["game_id"], {})[r["user_id"]] = r["position"]
    for g in rows("SELECT * FROM Game ORDER BY id"):
        cards = g["randomnumbers"]
        if cards not in deals:
            deals[cards] = len(deals) + 1
            insert("deal", id=deals[cards], cards=cards)
        sessions.setdefault(g["session"], set()).update(hands_of.get(g["id"], {}))
    for sid in sorted(sessions):
        first = rows(f"SELECT min(create_date) AS d FROM Game WHERE session = {sid}")[
            0
        ]["d"]
        insert("session", id=sid, created_at=first)
        for uid in sorted(sessions[sid]):
            insert("session_player", session=sid, user=uid)
    for g in rows("SELECT * FROM Game ORDER BY id"):
        turn = hands_of.get(g["id"], {}).get(g["player"])
        if g["player"] is not None and turn is None:
            report["game turn unknown"] += 1
        insert(
            "game",
            id=g["id"],
            session=g["session"],
            deal=deals[g["randomnumbers"]],
            ruleset=g["ruleset"],
            status=g["status"],
            type=g["type"],
            solo=g["solo"],
            sickness_state=g["sickness"],
            start_seat=g["startplayer"] or 1,
            turn_seat=turn,
            created_at=g["create_date"],
            updated_at=g["mod_date"],
        )

    for h in rows("SELECT * FROM Hand ORDER BY id"):
        insert(
            "hand",
            id=h["id"],
            game=h["game_id"],
            seat=h["position"],
            user=h["user_id"],
            token=h["hash"],
            status=h["status"],
            party=h["party"],
            sickness=h["sickness"],
            solo=h["solo"],
            point_call=h["point_call"],
        )
    hand_ids = {r["id"] for r in rows("SELECT id FROM Hand")}
    for r in rows("SELECT * FROM Hand_Card ORDER BY id"):
        if r["hand_id"] in hand_ids:
            insert("hand_card", id=r["id"], hand=r["hand_id"], card=r["card_id"])
        else:
            report["hand_card dropped (no hand)"] += 1
    for r in rows("SELECT * FROM Card_Exchange ORDER BY id"):
        if r["orig_hand_id"] in hand_ids and r["new_hand_id"] in hand_ids:
            insert(
                "card_exchange",
                id=r["id"],
                from_hand=r["orig_hand_id"],
                to_hand=r["new_hand_id"],
                card=r["card_id"],
            )
        else:
            report["card_exchange dropped (no hand)"] += 1

    number: Counter = Counter()
    trick_ids = set()
    for t in rows("SELECT * FROM Trick ORDER BY id"):
        number[t["game_id"]] += 1
        trick_ids.add(t["id"])
        insert(
            "trick",
            id=t["id"],
            game=t["game_id"],
            number=number[t["game_id"]],
            winner_seat=t["winner"],
        )
    hand_card_ids = {r["id"] for r in new.execute("SELECT id FROM hand_card")}
    play_ids = set()
    for p in rows("SELECT * FROM Play ORDER BY id"):
        if p["trick_id"] in trick_ids and p["hand_card_id"] in hand_card_ids:
            play_ids.add(p["id"])
            insert(
                "play",
                id=p["id"],
                trick=p["trick_id"],
                sequence=p["sequence"],
                hand_card=p["hand_card_id"],
                created_at=p["create_date"] or p["mod_date"],
            )
        else:
            report["play dropped (no trick or card)"] += 1

    for s in rows("SELECT * FROM Score ORDER BY id"):
        insert(
            "score_item",
            id=s["id"],
            game=s["game_id"],
            party=s["party"],
            kind=s["score"],
            winner=s["winner_id"],
            loser=s["looser_id"],
            created_at=s["create_date"],
        )

    game_of_play = {
        r["id"]: r["game"]
        for r in new.execute(
            "SELECT play.id, trick.game FROM play JOIN trick ON trick.id = play.trick"
        )
    }
    for c in rows("SELECT * FROM Comment ORDER BY id"):
        pid = c["play_id"]
        kind = {-1: "pre", -2: "call"}.get(pid, "move")
        if kind == "move" and pid not in play_ids:
            report["comment dropped (no play)"] += 1
            continue
        insert(
            "comment",
            id=c["id"],
            game=c["game_id"] or game_of_play[pid],
            kind=kind,
            play=pid if kind == "move" else None,
            user=c["user_id"],
            body=c["comment"],
            created_at=c["create_date"] or c["mod_date"],
            edited_at=c["mod_date"] if c["mod_date"] != c["create_date"] else None,
        )
    game_ids = {r["id"] for r in new.execute("SELECT id FROM game")}
    for n in rows("SELECT * FROM Notes ORDER BY id"):
        if n["game_id"] not in game_ids:
            report["note dropped (no game)"] += 1
            continue
        insert(
            "note",
            id=n["id"],
            user=n["user_id"],
            game=n["game_id"],
            body=n["comment"],
            created_at=n["create_date"],
        )
    for r in rows("SELECT * FROM Reminder ORDER BY id"):
        if r["game_id"] not in game_ids:
            report["reminder dropped (no game)"] += 1
            continue
        insert(
            "reminder",
            id=r["id"],
            game=r["game_id"],
            from_user=r["user_id"],
            created_at=r["create_date"],
        )


def main() -> None:
    import argparse

    parser = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    parser.add_argument("old", type=Path, help="database of the PHP site")
    parser.add_argument("new", type=Path, help="database to create")
    args = parser.parse_args()
    for key, count in sorted(import_php(args.old, args.new).items()):
        print(f"{count:8d}  {key}")


if __name__ == "__main__":
    main()
