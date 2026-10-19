"""Replay the finished games of the PHP site with the rules engine.

Needs a converted copy of the real database (it holds user data, so it is
not in the repository):

    EDOKO_GOLDEN_DB=/path/to/edoko.sqlite uv run pytest tests/test_golden.py

The engine implements the rules as they are meant. A few old games were
decided differently by older versions of the PHP site; they are listed
here so that every other difference makes the test fail.
"""

import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).parent.parent / "tools"))

DB = os.environ.get("EDOKO_GOLDEN_DB")
pytestmark = pytest.mark.skipif(not DB, reason="EDOKO_GOLDEN_DB is not set")

# Old PHP versions: no second-ten-of-hearts rule in wedding games (before the
# bugfix of 2013), Schweinchen in some poverty games of 2007/08, and the
# moment the second fox counted depended on the page request.
LEGACY_WINNERS = {
    75, 82, 108, 150, 243, 370, 402, 417, 593,
    2204, 2234, 2253, 2272, 2286, 2328,
}  # fmt: skip
# The same games, plus the first games (2006-2008) which were scored before
# Karlchen and doppelkopf existed, and one score without a kind (game 2).
LEGACY_ITEMS = {
    2, 51, 57, 59, 64, 67, 72, 75, 78, 79, 81, 82, 107, 108, 150, 206, 243,
    417, 593, 2204, 2234, 2272, 2286,
}  # fmt: skip


@pytest.fixture(scope="module")
def replayed():
    import replay_check

    return replay_check.check(Path(DB))


def test_every_finished_game_was_replayed(replayed):
    ok, bad = replayed
    assert ok["games"] >= 2282
    assert "incomplete tricks" not in bad and "no parties to score with" not in bad


def test_trick_winners(replayed):
    _, bad = replayed
    assert set(bad["trick winners"]) == LEGACY_WINNERS


def test_parties(replayed):
    _, bad = replayed
    assert "parties differ from the stored ones" not in bad


def test_scoring_items(replayed):
    _, bad = replayed
    assert set(bad["scoring items"]) == LEGACY_ITEMS
