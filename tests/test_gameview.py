import re

import pytest

from edoko import gameview


@pytest.mark.parametrize(
    "lead, dot",
    [
        ("right", (90.0, 50.0)),
        ("bottom", (50.0, 90.0)),
        ("left", (10.0, 50.0)),
        ("top", (50.0, 10.0)),
    ],
)
def test_the_ring_starts_with_a_dot_at_the_leading_spot(lead, dot):
    ring = gameview.ring(lead)
    assert ring["lead"] == lead and ring["dot"] == dot


def numbers(text):
    return [float(n) for n in re.findall(r"-?\d+\.?\d*", text)]


@pytest.mark.parametrize("lead", ["right", "bottom", "left", "top"])
def test_the_ring_is_an_arc_inside_the_box_that_runs_clockwise(lead):
    ring = gameview.ring(lead)
    x0, y0, rx, ry, rot, large, sweep, x1, y1 = numbers(ring["arc"])
    assert (rx, ry) == (40, 40) and large == 1 and sweep == 1  # clockwise on screen
    # the arc and the head stay inside the 100 x 100 box
    points = numbers(ring["head"]) + [x0, y0, x1, y1]
    assert all(0 <= v <= 100 for v in points)
    # the head has three corners
    assert len(numbers(ring["head"])) == 6


def test_the_ring_leaves_a_gap_before_its_own_start():
    # the end of the arc is not the start: it is a sequence, not a closed circle
    ring = gameview.ring("bottom")
    x1, y1 = numbers(ring["arc"])[-2:]
    gap = ((x1 - ring["dot"][0]) ** 2 + (y1 - ring["dot"][1]) ** 2) ** 0.5
    assert gap > 5
