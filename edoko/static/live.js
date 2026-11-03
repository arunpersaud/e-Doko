// Live mode on the game page: ask for news every few seconds, but only while the
// page is in front, so that a tab nobody looks at stops counting as "watching".
"use strict";

(function () {
  let last = Date.now();

  function poller() {
    return document.querySelector("[data-live-poller]");
  }

  function poll() {
    const element = poller();
    if (!element || document.visibilityState !== "visible") return;
    last = Date.now();
    htmx.trigger(element, "live-poll");
  }

  setInterval(() => {
    const element = poller();
    if (!element) return;
    const seconds = Number(element.dataset.liveSeconds) || 10;
    if (Date.now() - last >= seconds * 1000) poll();
  }, 1000);

  // back in the tab: look at once
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") poll();
  });

  // an answer to a poll must not wipe what the player is doing right now
  document.addEventListener("htmx:beforeSwap", (event) => {
    const source = event.detail.requestConfig && event.detail.requestConfig.elt;
    if (!source || !source.hasAttribute("data-live-poller")) return;
    const region = document.getElementById("live-region");
    if (!region) return;
    const busy =
      region.querySelector("details[open]:not(.live-help)") ||
      Array.from(region.querySelectorAll("input[type=text], textarea")).some((i) => i.value !== "") ||
      Array.from(region.querySelectorAll("select")).some((s) => s.value !== "");
    if (busy) event.detail.shouldSwap = false; // (the next poll asks again)
  });
})();
