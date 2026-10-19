"""Replay the finished games of a converted database with edoko.rules and
compare with what the PHP site stored (trick winners, parties, scoring items).

    uv run python tools/replay_check.py DB.sqlite [--show N]

Prints how many games match and lists the mismatches by kind. Only reads.
"""

import argparse
import sqlite3
import sys
from collections import Counter, defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from edoko import rules  # noqa: E402


def load(db: sqlite3.Connection, game_id: int):
    g = db.execute(
        "SELECT game.*, ruleset.dullen, ruleset.schweinchen, ruleset.lowtrump,"
        " ruleset.call FROM game JOIN ruleset ON ruleset.id = game.ruleset"
        " WHERE game.id = ?",
        (game_id,),
    ).fetchone()
    hands = {
        r["seat"]: r
        for r in db.execute("SELECT * FROM hand WHERE game = ?", (game_id,))
    }
    seat_of_hand = {h["id"]: s for s, h in hands.items()}
    cards = defaultdict(list)
    for r in db.execute(
        "SELECT hand, card FROM hand_card WHERE hand IN"
        " (SELECT id FROM hand WHERE game = ?)",
        (game_id,),
    ):
        cards[seat_of_hand[r["hand"]]].append(r["card"])
    tricks, stored_winners = [], []
    for t in db.execute(
        "SELECT id, winner_seat FROM trick WHERE game = ? ORDER BY number", (game_id,)
    ):
        plays = [
            (seat_of_hand[r["hand"]], r["card"])
            for r in db.execute(
                "SELECT hand_card.hand, hand_card.card FROM play"
                " JOIN hand_card ON hand_card.id = play.hand_card"
                " WHERE play.trick = ? ORDER BY play.sequence",
                (t["id"],),
            )
        ]
        tricks.append(plays)
        stored_winners.append(t["winner_seat"])
    items = [
        (r["party"], r["kind"])
        for r in db.execute(
            "SELECT party, kind FROM score_item WHERE game = ?", (game_id,)
        )
    ]
    exchange = [
        (seat_of_hand[r["from_hand"]], seat_of_hand[r["to_hand"]])
        for r in db.execute(
            "SELECT from_hand, to_hand FROM card_exchange WHERE from_hand IN"
            " (SELECT id FROM hand WHERE game = ?)",
            (game_id,),
        )
    ]
    return g, hands, cards, tricks, stored_winners, items, exchange


def derive_parties(g, hands, cards, winners, exchange):
    """The parties as the rules give them (None where the data is not enough)."""
    mode = g["type"]
    if mode == "normal":
        return {s: "re" if 3 in c or 4 in c else "contra" for s, c in cards.items()}
    if mode == "solo" and g["solo"] == "silent":  # played like a normal game
        return {s: "re" if 3 in c or 4 in c else "contra" for s, c in cards.items()}
    if mode == "solo":
        return {s: "re" if s == g["start_seat"] else "contra" for s in hands}
    if mode == "wedding":
        wed = next((s for s, h in hands.items() if h["sickness"] == "wedding"), None)
        if wed is None:
            return None
        partner = None
        for w in winners[:3]:
            if w != wed:
                partner = w
                break
        return {s: "re" if s in (wed, partner) else "contra" for s in hands}
    if mode == "poverty" and exchange:
        re = {exchange[0][0], exchange[0][1]}
        return {s: "re" if s in re else "contra" for s in hands}
    return None


def check(db_path: Path):
    """Replay all finished games; returns (counts of what matched, mismatches).

    The mismatches are a dict kind -> list of game ids, and "_diff" -> a list
    of (game id, extra items of the engine, extra items of PHP).
    """
    db = sqlite3.connect(f"file:{db_path}?mode=ro", uri=True)
    db.row_factory = sqlite3.Row
    ok = Counter()
    bad: dict[str, list] = defaultdict(list)
    ids = [
        r[0]
        for r in db.execute("SELECT id FROM game WHERE status = 'gameover' ORDER BY id")
    ]
    ok["games"] = len(ids)
    for gid in ids:
        g, hands, cards, tricks, stored_winners, items, exchange = load(db, gid)
        if len(tricks) != 12 or any(len(t) != 4 for t in tricks):
            bad["incomplete tricks"].append(gid)
            continue
        rs = rules.Rules(g["dullen"], g["schweinchen"], g["lowtrump"], g["call"])
        mode = g["type"]
        game_type = g["solo"] if mode == "solo" else mode

        # the foxes: holder at the start; the call is approximated by the final calls
        foxes = rules.foxes_for(cards, game_type, rs)
        parties_db = {s: h["party"] for s, h in hands.items()}
        calls = {
            s: (int(h["point_call"]) if h["point_call"] is not None else None)
            for s, h in hands.items()
        }
        if foxes.holder is not None and rs.schweinchen == "secondaftercall":
            partner_calls = [
                calls[s]
                for s in hands
                if parties_db.get(s) == parties_db.get(foxes.holder)
            ]
            foxes.called = any(c is not None for c in partner_calls)

        # 1. trick winners
        winners = []
        f2 = rules.Foxes(foxes.holder, called=foxes.called)
        for plays in tricks:
            for _s, c in plays:
                f2.played(c)
            winners.append(rules.trick_winner(plays, game_type, rs, f2))
        if winners == stored_winners:
            ok["winners"] += 1
        else:
            bad["trick winners"].append(gid)

        # 2. parties
        derived = derive_parties(g, hands, cards, stored_winners, exchange)
        if derived is None:
            ok["parties skipped"] += 1
        elif derived == parties_db:
            ok["parties"] += 1
        else:
            bad["parties differ from the stored ones"].append(gid)
        parties = parties_db if all(parties_db.values()) else derived
        if not parties:
            bad["no parties to score with"].append(gid)
            continue

        # 3. scoring items
        result = rules.replay(
            tricks,
            mode=mode,
            solo=g["solo"],
            rules=rs,
            parties=parties,
            calls=calls,
            foxes=rules.Foxes(foxes.holder, called=foxes.called),
        )
        mine, theirs = Counter(i.key() for i in result.items), Counter(items)
        if mine == theirs:
            ok["items"] += 1
        else:
            bad["scoring items"].append(gid)
            bad["_diff"].append((gid, dict(mine - theirs), dict(theirs - mine)))

    return ok, bad


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("db", type=Path)
    ap.add_argument("--show", type=int, default=5, help="examples per mismatch kind")
    args = ap.parse_args()
    ok, bad = check(args.db)
    print(f"{ok.pop('games')} finished games")
    for key, n in sorted(ok.items()):
        print(f"  ok       {n:5d}  {key}")
    for key, gids in sorted(bad.items()):
        if key != "_diff":
            print(f"  MISMATCH {len(gids):5d}  {key}: {gids[: args.show]}")
    for gid, extra, missing in bad.get("_diff", [])[: args.show]:
        print(f"    game {gid}: engine has extra {extra}, PHP had extra {missing}")


if __name__ == "__main__":
    main()
