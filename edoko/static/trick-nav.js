// Left and right arrow keys step through the tricks on the game page.
"use strict";

document.addEventListener("keydown", (event) => {
  if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
  const tag = (event.target.tagName || "").toLowerCase();
  if (tag === "input" || tag === "textarea" || tag === "select") return;
  const rel = { ArrowLeft: "prev", ArrowRight: "next" }[event.key];
  if (!rel) return;
  const link = document.querySelector(`nav.trick-nav a[rel="${rel}"]`);
  if (link) {
    event.preventDefault();
    link.click();
  }
});
