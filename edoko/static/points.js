// The game points: "details" that open with a click or tap (the default of the
// element, so it works with touch and keyboard), and also while the mouse is
// over them. A click while it is open from hovering keeps it open; a click
// outside, or Escape, closes it.
"use strict";

document.querySelectorAll("details.points").forEach((box) => {
  let byHover = false;

  box.addEventListener("pointerenter", (event) => {
    if (event.pointerType === "mouse" && !box.open) {
      box.open = true;
      byHover = true;
    }
  });
  box.addEventListener("pointerleave", (event) => {
    if (event.pointerType === "mouse" && byHover) box.open = false;
    byHover = false;
  });
  box.querySelector("summary").addEventListener("click", (event) => {
    if (byHover) {
      event.preventDefault(); // it is open already: keep it open
      byHover = false;
    }
  });
  document.addEventListener("click", (event) => {
    if (box.open && !box.contains(event.target)) box.open = false;
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && box.open) {
      box.open = false;
      box.querySelector("summary").focus();
    }
  });
});
