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
  var highlightTimer = null;
  var popoverTimer = null;
  var lastBlameClick = null;
  var prevBlameClick = null;

  function setDrawer(open) {
    if (!drawer || !backdrop) return;
    drawer.classList.toggle("is-open", open);
    backdrop.classList.toggle("is-open", open);
    drawer.setAttribute("aria-hidden", open ? "false" : "true");
    document.body.style.overflow = open ? "hidden" : "";
  }

  function isEditableTarget(el) {
    if (!el || el === document.body || el === document.documentElement) {
      return false;
    }
    var tag = (el.tagName || "").toLowerCase();
    return tag === "input" || tag === "textarea";
  }

  function setShortcutHints(on) {
    if (!openBtn) return;
    if (on && isEditableTarget(document.activeElement)) {
      on = false;
    }
    document.body.classList.toggle("is-showing-shortcuts", on);
  }

  function isModifierKey(key) {
    return key === "Alt" || key === "Control" || key === "Meta" || key === "Shift";
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
    if (e.key === "Escape") {
      setDrawer(false);
      return;
    }

    if (isModifierKey(e.key)) {
      setShortcutHints(true);
      return;
    }

    if (
      openBtn &&
      !e.altKey &&
      !e.metaKey &&
      !e.ctrlKey &&
      !e.shiftKey &&
      e.code === "KeyB" &&
      !isEditableTarget(document.activeElement) &&
      !isEditableTarget(e.target)
    ) {
      e.preventDefault();
      setDrawer(true);
    }
  });

  document.addEventListener("keyup", function (e) {
    if (isModifierKey(e.key) && !e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
      setShortcutHints(false);
    }
  });

  window.addEventListener("blur", function () {
    setShortcutHints(false);
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
      if (drawer && drawer.classList.contains("is-showing-dropped")) {
        scrollFirstDroppedIntoView();
      }
    });
  });

  var highlightDroppedBtn = qs("[data-highlight-dropped]");

  function scrollFirstDroppedIntoView() {
    var panel = qs("[data-blame-panel]:not([hidden])");
    if (!panel) return;
    var first = qs("[data-blame-line][data-dropped='1']", panel);
    if (first) {
      first.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
  }

  function setDroppedHighlight(on) {
    if (!drawer) return;
    drawer.classList.toggle("is-showing-dropped", on);
    if (highlightDroppedBtn) {
      highlightDroppedBtn.classList.toggle("is-active", on);
      highlightDroppedBtn.setAttribute("aria-pressed", on ? "true" : "false");
    }
    if (on) scrollFirstDroppedIntoView();
  }

  if (highlightDroppedBtn) {
    highlightDroppedBtn.addEventListener("click", function () {
      setDroppedHighlight(!drawer.classList.contains("is-showing-dropped"));
    });
  }

  function clearOrgHighlight() {
    qsa(".item-card.is-flash").forEach(function (el) {
      el.classList.remove("is-flash");
    });
    if (highlightTimer) {
      clearTimeout(highlightTimer);
      highlightTimer = null;
    }
  }

  function highlightOrgIds(ids) {
    clearOrgHighlight();
    var targets = [];
    ids.forEach(function (id) {
      var el = qs('[data-org-id="' + id.replace(/"/g, "") + '"]');
      if (el) targets.push(el);
    });
    if (!targets.length) return false;

    targets.forEach(function (el) {
      el.classList.add("is-flash");
    });
    targets[0].scrollIntoView({ behavior: "smooth", block: "center" });
    highlightTimer = setTimeout(clearOrgHighlight, 2200);
    return true;
  }

  function clearAllDroppedFeedback() {
    qsa("[data-blame-line].has-tip, [data-blame-line].is-shaking").forEach(function (el) {
      var tip = qs(".blame-line__tip", el);
      if (tip) tip.remove();
      el.classList.remove("is-shaking", "has-tip");
    });
    if (popoverTimer) {
      clearTimeout(popoverTimer);
      popoverTimer = null;
    }
  }

  function showDroppedFeedback(line) {
    clearAllDroppedFeedback();
    line.classList.add("is-shaking", "has-tip");

    var tip = document.createElement("div");
    tip.className = "blame-line__tip";
    tip.setAttribute("role", "status");
    tip.textContent = "Line dropped";
    line.appendChild(tip);

    requestAnimationFrame(function () {
      tip.classList.add("is-visible");
    });

    popoverTimer = setTimeout(function () {
      clearAllDroppedFeedback();
    }, 1600);

    line.addEventListener(
      "animationend",
      function () {
        line.classList.remove("is-shaking");
      },
      { once: true }
    );
  }

  function markBlameClick(line) {
    if (lastBlameClick === line) {
      return;
    }

    if (prevBlameClick) {
      prevBlameClick.classList.remove("is-click-prev");
    }
    if (lastBlameClick) {
      lastBlameClick.classList.remove("is-click-last");
      lastBlameClick.classList.add("is-click-prev");
      prevBlameClick = lastBlameClick;
    }

    line.classList.remove("is-click-prev");
    line.classList.add("is-click-last");
    lastBlameClick = line;
  }

  function onBlameLineActivate(line) {
    var dropped = line.getAttribute("data-dropped") === "1";
    if (dropped) {
      showDroppedFeedback(line);
      return;
    }

    markBlameClick(line);

    var raw = line.getAttribute("data-to") || "";
    var ids = raw.split(",").map(function (s) {
      return s.trim();
    }).filter(Boolean);
    var hasTarget = ids.some(function (id) {
      return !!qs('[data-org-id="' + id.replace(/"/g, "") + '"]');
    });
    if (!hasTarget) {
      showDroppedFeedback(line);
      return;
    }

    setDrawer(false);
    // Wait for drawer close so scroll targets aren't under the panel
    setTimeout(function () {
      highlightOrgIds(ids);
    }, 240);
  }

  qsa("[data-blame-line]").forEach(function (line) {
    line.addEventListener("click", function () {
      onBlameLineActivate(line);
    });
    line.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        onBlameLineActivate(line);
      }
    });
  });
})();
