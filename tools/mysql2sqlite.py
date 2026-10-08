#!/usr/bin/env python3
"""Convert an e-DoKo mysqldump into a fresh SQLite database.

usage: mysql2sqlite.py DUMP.sql OUT.sqlite

The dump must be created with --skip-extended-insert (one INSERT per row).
OUT.sqlite must not exist yet; the schema comes from ../schema-sqlite.sql.

Data fixes on the way:
 * '0000-00-00 00:00:00' -> NULL (timestamps in the dump are UTC)
 * the old DB_quote_smart() escaped _ and % with a backslash, and MySQL kept
   the backslash ("America/New\\_York"); those backslashes are removed
 * invalid enum values, which MySQL stored as '', become NULL
 * the AUTO_INCREMENT counters are copied, so new ids continue where MySQL
   stopped
"""

import re
import sqlite3
import sys
from pathlib import Path

SCHEMA = Path(__file__).resolve().parent.parent / "schema-sqlite.sql"
INSERT = re.compile(r"^INSERT INTO `(\w+)` VALUES \((.*)\);$")
# [^;] keeps the match inside one CREATE TABLE statement
AUTO_INCREMENT = re.compile(r"^CREATE TABLE `(\w+)`[^;]*?\) ENGINE=\w+ AUTO_INCREMENT=(\d+)",
                            re.M)
ESCAPES = {"0": "\0", "n": "\n", "r": "\r", "t": "\t", "Z": "\x1a",
           "b": "\b", "\\": "\\", "'": "'", '"': '"'}
ZERO_DATE = "0000-00-00 00:00:00"
QUOTE_SMART = re.compile(r"\\([_%])")


def parse_values(s):
    """Parse the inside of a MySQL VALUES (...) tuple."""
    values, i, n = [], 0, len(s)
    while i < n:
        if s[i] == "'":
            i += 1
            buf = []
            while s[i] != "'" or s[i + 1:i + 2] == "'":
                if s[i] == "\\":
                    buf.append(ESCAPES.get(s[i + 1], "\\" + s[i + 1]))
                    i += 2
                elif s[i] == "'":  # '' inside a string
                    buf.append("'")
                    i += 2
                else:
                    buf.append(s[i])
                    i += 1
            i += 1
            value = "".join(buf)
            values.append(None if value == ZERO_DATE else value)
        else:
            j = s.find(",", i)
            j = n if j == -1 else j
            token = s[i:j].strip()
            if token == "NULL":
                values.append(None)
            elif re.fullmatch(r"-?\d+", token):
                values.append(int(token))
            elif re.fullmatch(r"-?\d+\.\d*(e[-+]?\d+)?", token, re.I):
                values.append(float(token))
            else:
                raise ValueError(f"unsupported literal {token!r}")
            i = j
        if i < n:
            assert s[i] == ",", f"expected ',' at {i}: {s[i:i+20]!r}"
            i += 1
    return values


def main(dump, out):
    if Path(out).exists():
        sys.exit(f"{out} exists; refusing to overwrite")
    db = sqlite3.connect(out)
    db.executescript(SCHEMA.read_text())
    counts, fixed = {}, {}
    with open(dump, encoding="utf-8") as f:
        text = f.read()
    for line in text.splitlines():
        m = INSERT.match(line)
        if not m:
            continue
        table, raw = m.groups()
        values = parse_values(raw)
        for k, v in enumerate(values):
            if isinstance(v, str) and QUOTE_SMART.search(v):
                values[k] = QUOTE_SMART.sub(r"\1", v)
                fixed[table] = fixed.get(table, 0) + 1
        marks = ",".join("?" * len(values))
        sql = f'INSERT INTO "{table}" VALUES ({marks})'
        try:
            db.execute(sql, values)
        except sqlite3.IntegrityError as e:
            if "CHECK" not in str(e) or "" not in values:
                raise
            db.execute(sql, [None if v == "" else v for v in values])
            print(f"{table} id {values[0]}: invalid enum '' -> NULL", file=sys.stderr)
        counts[table] = counts.get(table, 0) + 1
    # unlike MySQL, sqlite_sequence only knows max(id) of the copied rows
    for table, next_id in AUTO_INCREMENT.findall(text):
        # sqlite_sequence has no unique key: update, or insert for empty tables
        if not db.execute("UPDATE sqlite_sequence SET seq = ? WHERE name = ?",
                          (int(next_id) - 1, table)).rowcount:
            db.execute("INSERT INTO sqlite_sequence (name, seq) VALUES (?, ?)",
                       (table, int(next_id) - 1))
    db.commit()
    for table, count in sorted(counts.items()):
        extra = f"  ({fixed[table]} values: removed \\_ / \\% backslash)" if table in fixed else ""
        print(f"{table:16} {count:7}{extra}")
    db.close()


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(*sys.argv[1:])
