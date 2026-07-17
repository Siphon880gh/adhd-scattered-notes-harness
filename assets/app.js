(function () {
  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  var openBtn = qs("[data-drawer-open]");
  var closeBtn = qs("[data-drawer-close]");
  var backdrop = qs("[data-drawer-backdrop]");
  var drawer = qs("[data-drawer]");

  function setDrawer(open) {
    if (!drawer || !backdrop) return;
    drawer.classList.toggle("is-open", open);
    backdrop.classList.toggle("is-open", open);
    drawer.setAttribute("aria-hidden", open ? "false" : "true");
    document.body.style.overflow = open ? "hidden" : "";
  }

  if (openBtn) {
    openBtn.addEventListener("click", function () {
      setDrawer(true);
    });
  }
  if (closeBtn) {
    closeBtn.addEventListener("click", function () {
      setDrawer(false);
    });
  }
  if (backdrop) {
    backdrop.addEventListener("click", function () {
      setDrawer(false);
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") setDrawer(false);
  });

  var fileButtons = qsa("[data-blame-file]");
  var panels = qsa("[data-blame-panel]");

  fileButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var name = btn.getAttribute("data-blame-file");
      fileButtons.forEach(function (b) {
        b.classList.toggle("is-active", b === btn);
      });
      panels.forEach(function (panel) {
        var match = panel.getAttribute("data-blame-panel") === name;
        panel.hidden = !match;
      });
    });
  });
})();
