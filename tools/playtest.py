#!/usr/bin/env python3
"""Play one e-DoKo game: every player submits the first allowed choice of
the game form until Game.status is gameover (or nothing changes).

usage: playtest.py DB.sqlite GAME_ID   (talks to http://127.0.0.1:8082)
Only for test copies: it plays real moves and triggers mails."""
import sqlite3, sys, urllib.parse, urllib.request
from html.parser import HTMLParser

BASE = "http://127.0.0.1:8082/index.php"
DB, GAME = sys.argv[1], int(sys.argv[2])

class Forms(HTMLParser):
    def __init__(self):
        super().__init__(); self.forms = []; self.cur = None; self.sel = None
    def handle_starttag(self, tag, a):
        a = dict(a)
        if tag == "form":
            self.cur = {"action": a.get("action", ""), "fields": {}, "radios": {}}; self.forms.append(self.cur)
        elif self.cur is None: return
        elif tag == "input":
            t, n = a.get("type", "text"), a.get("name")
            if not n or "disabled" in a: return
            if t == "radio": self.cur["radios"].setdefault(n, []).append(a.get("value"))
            elif t in ("hidden",): self.cur["fields"][n] = a.get("value", "")
            elif t == "checkbox": pass
        elif tag == "select": self.sel = a.get("name")
        elif tag == "option" and self.sel and self.sel not in self.cur["fields"]:
            self.cur["fields"][self.sel] = a.get("value", "")
    def handle_endtag(self, tag):
        if tag == "form": self.cur = None
        if tag == "select": self.sel = None

def get(url, data=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    return urllib.request.urlopen(urllib.request.Request(url, body)).read().decode("utf-8", "replace")

db = sqlite3.connect(DB)
hashes = [h for (h,) in db.execute("SELECT hash FROM Hand WHERE game_id=? ORDER BY position", (GAME,))]
state = lambda: db.execute("SELECT status,(SELECT COUNT(*) FROM Play JOIN Trick ON Trick.id=Play.trick_id WHERE Trick.game_id=?) FROM Game WHERE id=?", (GAME, GAME)).fetchone()
stale = 0
for rnd in range(400):
    before = state()
    if before[0] != "pre" and before[0] != "play": break
    for h in hashes:
        p = Forms(); p.feed(get(f"{BASE}?action=game&me={h}"))
        for f in p.forms:
            if not f["radios"] and set(f["fields"]) <= {"me"}: continue
            data = dict(f["fields"]); data.setdefault("me", h)
            for n, vals in f["radios"].items(): data[n] = vals[0]
            url = urllib.parse.urljoin(BASE, f["action"].replace("&amp;", "&"))
            get(url, data)
            break
    after = state()
    stale = stale + 1 if after == before else 0
    if stale >= 3: print("no progress", after); break
print("final:", state(), "rounds:", rnd)
