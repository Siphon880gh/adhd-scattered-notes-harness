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
    if (open || isNoteModalOpen()) {
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "";
    }
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

  var noteModal = qs("[data-note-modal]");
  var noteModalTitle = qs("[data-note-modal-title]", noteModal);
  var noteModalBody = qs("[data-note-modal-body]", noteModal);
  var noteModalLastFocus = null;

  function setNoteModal(open, title, body) {
    if (!noteModal) return;
    if (open) {
      noteModalLastFocus = document.activeElement;
      if (noteModalTitle) noteModalTitle.textContent = title || "";
      if (noteModalBody) noteModalBody.textContent = body || "";
      noteModal.setAttribute("data-full-text", body || "");
      noteModal.hidden = false;
      document.body.style.overflow = "hidden";
      var closeBtn = qs("[data-note-modal-close].note-modal__close", noteModal);
      if (closeBtn) closeBtn.focus();
    } else {
      noteModal.hidden = true;
      if (noteModalTitle) noteModalTitle.textContent = "";
      if (noteModalBody) noteModalBody.textContent = "";
      noteModal.removeAttribute("data-full-text");
      if (!drawer || !drawer.classList.contains("is-open")) {
        document.body.style.overflow = "";
      }
      if (noteModalLastFocus && typeof noteModalLastFocus.focus === "function") {
        noteModalLastFocus.focus();
      }
      noteModalLastFocus = null;
    }
  }

  function isNoteModalOpen() {
    return !!(noteModal && !noteModal.hidden);
  }

  if (noteModal) {
    noteModal.addEventListener("click", function (e) {
      if (e.target.closest("[data-note-modal-close]")) {
        setNoteModal(false);
        return;
      }
      var modalCopyBtn = e.target.closest("[data-note-modal-copy]");
      if (modalCopyBtn) {
        var modalTitle = noteModalTitle ? noteModalTitle.textContent : "";
        var modalBody = noteModal.getAttribute("data-full-text") || "";
        copyNote(modalTitle, modalBody).then(function () {
          flashCopyButton(modalCopyBtn);
        }).catch(function () {
          // clipboard unavailable
        });
      }
    });
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function noteCopyPayload(title, body) {
    var titleText = (title || "").trim();
    var bodyText = body || "";
    var plain = titleText
      ? (bodyText ? titleText + "\n\n" + bodyText : titleText)
      : bodyText;
    var htmlParts = [];
    if (titleText) {
      htmlParts.push("<h3>" + escapeHtml(titleText) + "</h3>");
    }
    if (bodyText) {
      var paragraphs = bodyText.split(/\n{2,}/);
      paragraphs.forEach(function (para) {
        htmlParts.push(
          "<p>" + escapeHtml(para).replace(/\n/g, "<br>") + "</p>"
        );
      });
    }
    return { plain: plain, html: htmlParts.join("") };
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement("textarea");
      ta.value = text;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.opacity = "0";
      document.body.appendChild(ta);
      ta.select();
      try {
        if (!document.execCommand("copy")) {
          reject(new Error("copy failed"));
        } else {
          resolve();
        }
      } catch (err) {
        reject(err);
      } finally {
        document.body.removeChild(ta);
      }
    });
  }

  function copyRich(plain, html) {
    if (
      navigator.clipboard &&
      typeof ClipboardItem !== "undefined" &&
      navigator.clipboard.write
    ) {
      try {
        var item = new ClipboardItem({
          "text/plain": Promise.resolve(
            new Blob([plain], { type: "text/plain" })
          ),
          "text/html": Promise.resolve(new Blob([html], { type: "text/html" })),
        });
        return navigator.clipboard.write([item]).catch(function () {
          return copyRichViaSelection(plain, html);
        });
      } catch (err) {
        // fall through
      }
    }
    return copyRichViaSelection(plain, html);
  }

  function copyRichViaSelection(plain, html) {
    return new Promise(function (resolve, reject) {
      var holder = document.createElement("div");
      holder.contentEditable = "true";
      holder.style.position = "fixed";
      holder.style.left = "-9999px";
      holder.style.whiteSpace = "pre-wrap";
      holder.innerHTML = html || escapeHtml(plain);
      document.body.appendChild(holder);
      var selection = window.getSelection();
      var range = document.createRange();
      range.selectNodeContents(holder);
      selection.removeAllRanges();
      selection.addRange(range);
      try {
        if (document.execCommand("copy")) {
          resolve();
        } else {
          copyText(plain).then(resolve, reject);
        }
      } catch (err) {
        copyText(plain).then(resolve, reject);
      } finally {
        selection.removeAllRanges();
        document.body.removeChild(holder);
      }
    });
  }

  function copyNote(title, body) {
    var payload = noteCopyPayload(title, body);
    return copyRich(payload.plain, payload.html);
  }

  function flashCopyButton(btn) {
    if (!btn) return;
    var label = qs("span", btn) || btn;
    var prev = label.textContent;
    label.textContent = "Copied";
    btn.classList.add("is-copied");
    setTimeout(function () {
      label.textContent = prev;
      btn.classList.remove("is-copied");
    }, 1200);
  }

  function closeSectionInfoTips(except) {
    qsa("[data-section-info]").forEach(function (wrap) {
      if (except && wrap === except) return;
      var toggle = qs("[data-section-info-toggle]", wrap);
      if (toggle) toggle.setAttribute("aria-expanded", "false");
      wrap.classList.remove("is-open");
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      if (isNoteModalOpen()) {
        setNoteModal(false);
        return;
      }
      setDrawer(false);
      return;
    }

    if (isModifierKey(e.key)) {
      setShortcutHints(true);
      return;
    }

    if (
      openBtn &&
      drawer &&
      !e.altKey &&
      !e.metaKey &&
      !e.ctrlKey &&
      !e.shiftKey &&
      e.code === "KeyB" &&
      !isEditableTarget(document.activeElement) &&
      !isEditableTarget(e.target)
    ) {
      e.preventDefault();
      setDrawer(!drawer.classList.contains("is-open"));
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

    var expandedAny = false;
    targets.forEach(function (el) {
      var section = el.closest("[data-section-bucket]");
      if (section && section.classList.contains("is-collapsed")) {
        setCollapsed(section, false);
        expandedAny = true;
      }
    });

    function flashAndScroll() {
      targets.forEach(function (el) {
        el.classList.add("is-flash");
      });
      targets[0].scrollIntoView({ behavior: "smooth", block: "center" });
      highlightTimer = setTimeout(clearOrgHighlight, 2200);
    }

    if (expandedAny) {
      // Wait for section expand animation before scrolling into view
      setTimeout(flashAndScroll, 300);
    } else {
      flashAndScroll();
    }
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

  // --- Phase 2: move buckets, tags, filter ---
  var phase2Root = qs("[data-phase2-root]");
  var organizedDataEl = qs("#phase2-organized-data");
  var BUCKET_LABELS = {
    tasks: "Scattered",
    reference: "Reference",
    articleCandidates: "Articles",
  };
  var BUCKET_KEYS = ["tasks", "reference", "articleCandidates"];
  var activeFilterTag = "";
  var saveTimer = null;
  var organizedState = null;
  var filterLayoutState = [];
  var filterDrag = {
    index: -1,
    didDrag: false,
  };

  function cloneOrganized(data) {
    return {
      tasks: (data.tasks || []).slice(),
      reference: (data.reference || []).slice(),
      articleCandidates: (data.articleCandidates || []).slice(),
    };
  }

  function normalizeTagList(tags) {
    var out = [];
    var seen = {};
    (tags || []).forEach(function (tag) {
      var name = String(tag || "").trim();
      if (!name) return;
      var key = name.toLowerCase();
      if (seen[key]) return;
      seen[key] = true;
      out.push(name);
    });
    return out;
  }

  function findItemLocation(id) {
    for (var i = 0; i < BUCKET_KEYS.length; i++) {
      var bucket = BUCKET_KEYS[i];
      var list = organizedState[bucket] || [];
      for (var j = 0; j < list.length; j++) {
        if (list[j] && list[j].id === id) {
          return { bucket: bucket, index: j, item: list[j] };
        }
      }
    }
    return null;
  }

  function adaptItemForBucket(item, bucket) {
    var next = {
      id: item.id,
      title: item.title || item.id || "Untitled",
      sources: Array.isArray(item.sources) ? item.sources.slice() : [],
      tags: normalizeTagList(item.tags),
    };
    var body = item.body != null ? String(item.body) : "";
    var why = item.why != null ? String(item.why) : "";
    if (bucket === "articleCandidates") {
      next.why = why || body;
    } else {
      next.body = body || why;
    }
    return next;
  }

  function saveOrganized() {
    if (!organizedState) return;
    syncFilterLayout();
    fetch("index.php?action=save-phase2", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        organized: organizedState,
        filterLayout: filterLayoutState,
      }),
    }).catch(function () {
      // local save failure — UI state still updated
    });
  }

  function scheduleSave() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(saveOrganized, 200);
  }

  function collectAllTags() {
    var seen = {};
    var tags = [];
    BUCKET_KEYS.forEach(function (bucket) {
      (organizedState[bucket] || []).forEach(function (item) {
        normalizeTagList(item.tags).forEach(function (tag) {
          var key = tag.toLowerCase();
          if (seen[key]) return;
          seen[key] = true;
          tags.push(tag);
        });
      });
    });
    return tags;
  }

  function syncFilterLayout() {
    var available = collectAllTags();
    var availableMap = {};
    available.forEach(function (tag) {
      availableMap[tag.toLowerCase()] = tag;
    });

    var next = [];
    var used = {};
    (filterLayoutState || []).forEach(function (item) {
      if (!item || typeof item !== "object") return;
      if (item.type === "divider") {
        if (next.length && next[next.length - 1].type === "divider") return;
        next.push({ type: "divider" });
        return;
      }
      if (item.type !== "tag") return;
      var name = String(item.name || "").trim();
      if (!name) return;
      var key = name.toLowerCase();
      if (!availableMap[key] || used[key]) return;
      used[key] = true;
      next.push({ type: "tag", name: availableMap[key] });
    });

    var missing = [];
    available.forEach(function (tag) {
      if (!used[tag.toLowerCase()]) missing.push(tag);
    });
    missing.sort(function (a, b) {
      return a.localeCompare(b, undefined, { sensitivity: "base" });
    });
    missing.forEach(function (tag) {
      next.push({ type: "tag", name: tag });
    });

    filterLayoutState = next;
  }

  function insertFilterDividerAt(index) {
    syncFilterLayout();
    var at = Math.max(0, Math.min(index, filterLayoutState.length));
    if (filterLayoutState[at] && filterLayoutState[at].type === "divider") return;
    if (at > 0 && filterLayoutState[at - 1] && filterLayoutState[at - 1].type === "divider") return;
    filterLayoutState.splice(at, 0, { type: "divider" });
    rebuildFilterBar();
    scheduleSave();
  }

  function removeFilterDividerAt(index) {
    if (!filterLayoutState[index] || filterLayoutState[index].type !== "divider") return;
    filterLayoutState.splice(index, 1);
    rebuildFilterBar();
    scheduleSave();
  }

  function moveFilterItem(fromIndex, toIndex) {
    if (fromIndex < 0 || fromIndex >= filterLayoutState.length) return;
    var item = filterLayoutState[fromIndex];
    if (!item || (item.type !== "tag" && item.type !== "divider")) return;

    var next = filterLayoutState.slice();
    next.splice(fromIndex, 1);
    var insertAt = toIndex;
    if (fromIndex < toIndex) insertAt -= 1;
    insertAt = Math.max(0, Math.min(insertAt, next.length));

    if (item.type === "divider") {
      if (next[insertAt] && next[insertAt].type === "divider") {
        rebuildFilterBar();
        return;
      }
      if (insertAt > 0 && next[insertAt - 1] && next[insertAt - 1].type === "divider") {
        rebuildFilterBar();
        return;
      }
    }

    next.splice(insertAt, 0, item);
    filterLayoutState = next;
    rebuildFilterBar();
    scheduleSave();
  }

  function getFilterDropIndex(bar, x, y) {
    var items = qsa("[data-filter-index]:not(.is-dragging)", bar);
    if (!items.length) return 0;
    for (var i = 0; i < items.length; i++) {
      var box = items[i].getBoundingClientRect();
      var index = parseInt(items[i].getAttribute("data-filter-index") || "0", 10);
      if (y < box.top) {
        return index;
      }
      if (y <= box.bottom) {
        if (x < box.left + box.width / 2) {
          return index;
        }
      }
    }
    return filterLayoutState.length;
  }

  function closeAllFilterMenus(exceptWrap) {
    qsa("[data-filter-menu-wrap]", phase2Root).forEach(function (wrap) {
      if (exceptWrap && wrap === exceptWrap) return;
      var toggle = qs("[data-filter-menu-toggle]", wrap);
      var menu = qs("[data-filter-menu]", wrap);
      if (toggle) toggle.setAttribute("aria-expanded", "false");
      if (menu) menu.hidden = true;
      wrap.classList.remove("is-open");
    });
  }

  function rebuildFilterBar() {
    var bar = qs("[data-tag-filter]", phase2Root);
    if (!bar) return;
    syncFilterLayout();
    bar.innerHTML = "";

    var tags = collectAllTags();
    if (!tags.length) {
      bar.hidden = true;
      activeFilterTag = "";
      filterLayoutState = [];
      return;
    }
    bar.hidden = false;

    if (activeFilterTag && tags.every(function (t) {
      return t.toLowerCase() !== activeFilterTag.toLowerCase();
    })) {
      activeFilterTag = "";
    }

    var label = document.createElement("span");
    label.className = "tag-filter__label";
    label.textContent = "Filter";
    bar.appendChild(label);

    var allBtn = document.createElement("button");
    allBtn.type = "button";
    allBtn.className = "tag-filter__chip";
    allBtn.setAttribute("data-filter-tag", "");
    allBtn.textContent = "All";
    allBtn.classList.toggle("is-active", !activeFilterTag);
    allBtn.setAttribute("aria-pressed", !activeFilterTag ? "true" : "false");
    allBtn.addEventListener("click", function () {
      activeFilterTag = "";
      rebuildFilterBar();
      applyFilter();
    });
    bar.appendChild(allBtn);

    filterLayoutState.forEach(function (item, index) {
      if (item.type === "divider") {
        var divider = document.createElement("button");
        divider.type = "button";
        divider.className = "tag-filter__divider";
        divider.setAttribute("data-filter-divider", "");
        divider.setAttribute("data-filter-index", String(index));
        divider.setAttribute("title", "Drag to reorder · click to remove");
        divider.setAttribute("aria-label", "Divider: drag to reorder, click to remove");
        divider.draggable = true;
        divider.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          if (filterDrag.didDrag) {
            filterDrag.didDrag = false;
            return;
          }
          removeFilterDividerAt(index);
        });
        bar.appendChild(divider);
        return;
      }

      var group = document.createElement("div");
      group.className = "tag-filter__group";
      group.setAttribute("data-filter-index", String(index));
      group.setAttribute("data-filter-tag", item.name);
      group.setAttribute("role", "button");
      group.setAttribute("tabindex", "0");
      group.draggable = true;

      var btn = document.createElement("span");
      btn.className = "tag-filter__chip";
      btn.textContent = item.name;
      var active = activeFilterTag.toLowerCase() === String(item.name).toLowerCase();
      group.classList.toggle("is-active", active);
      group.setAttribute("aria-pressed", active ? "true" : "false");
      group.setAttribute("aria-label", "Filter by " + item.name);

      function activateTagFilter() {
        if (filterDrag.didDrag) {
          filterDrag.didDrag = false;
          return;
        }
        activeFilterTag = item.name;
        rebuildFilterBar();
        applyFilter();
      }

      group.addEventListener("click", function (e) {
        if (e.target.closest("[data-filter-menu-wrap]")) return;
        activateTagFilter();
      });
      group.addEventListener("keydown", function (e) {
        if (e.target.closest("[data-filter-menu-wrap]")) return;
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          activateTagFilter();
        }
      });

      var menuWrap = document.createElement("div");
      menuWrap.className = "tag-filter__menu-wrap";
      menuWrap.setAttribute("data-filter-menu-wrap", "");

      var menuToggle = document.createElement("button");
      menuToggle.type = "button";
      menuToggle.className = "tag-filter__menu-toggle";
      menuToggle.setAttribute("data-filter-menu-toggle", "");
      menuToggle.setAttribute("aria-expanded", "false");
      menuToggle.setAttribute("aria-haspopup", "menu");
      menuToggle.setAttribute("title", "Tag options");
      menuToggle.setAttribute("aria-label", "Options for " + item.name);
      menuToggle.innerHTML =
        '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

      var menu = document.createElement("div");
      menu.className = "tag-filter__menu";
      menu.setAttribute("data-filter-menu", "");
      menu.setAttribute("role", "menu");
      menu.hidden = true;

      function addMenuOption(side, text) {
        var option = document.createElement("button");
        option.type = "button";
        option.className = "tag-filter__menu-option";
        option.setAttribute("data-filter-divider-side", side);
        option.setAttribute("role", "menuitem");
        option.textContent = text;
        option.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          closeAllFilterMenus();
          insertFilterDividerAt(side === "left" ? index : index + 1);
        });
        menu.appendChild(option);
      }

      addMenuOption("left", "Divider to left");
      addMenuOption("right", "Divider to right");

      menuToggle.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        var willOpen = menu.hidden;
        closeAllFilterMenus(willOpen ? menuWrap : null);
        menu.hidden = !willOpen;
        menuToggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
        menuWrap.classList.toggle("is-open", willOpen);
      });

      menuWrap.appendChild(menuToggle);
      menuWrap.appendChild(menu);
      group.appendChild(btn);
      group.appendChild(menuWrap);
      bar.appendChild(group);
    });
  }

  function applyFilter() {
    qsa(".item-card[data-org-id]", phase2Root).forEach(function (card) {
      if (!activeFilterTag) {
        card.hidden = false;
        return;
      }
      var tags = [];
      try {
        tags = JSON.parse(card.getAttribute("data-tags") || "[]");
      } catch (err) {
        tags = [];
      }
      var match = normalizeTagList(tags).some(function (tag) {
        return tag.toLowerCase() === activeFilterTag.toLowerCase();
      });
      card.hidden = !match;
    });
  }

  function syncEmptyState(bucket) {
    var section = qs('[data-section-bucket="' + bucket + '"]', phase2Root);
    if (!section) return;
    var empty = qs('[data-empty-bucket="' + bucket + '"]', section);
    var list = qs('[data-bucket-list="' + bucket + '"]', section);
    var count = list ? qsa(".item-card", list).length : 0;
    if (empty) {
      empty.hidden = count > 0;
    }
  }

  function renderTagChips(card, tags) {
    var list = qs("[data-tag-list]", card);
    if (!list) return;
    list.innerHTML = "";
    normalizeTagList(tags).forEach(function (tag) {
      var chip = document.createElement("span");
      chip.className = "tag-chip";
      chip.setAttribute("data-tag", tag);

      var label = document.createElement("span");
      label.className = "tag-chip__label";
      label.textContent = tag;

      var remove = document.createElement("button");
      remove.type = "button";
      remove.className = "tag-chip__remove";
      remove.setAttribute("data-remove-tag", tag);
      remove.setAttribute("aria-label", "Remove tag " + tag);
      remove.textContent = "×";

      chip.appendChild(label);
      chip.appendChild(remove);
      list.appendChild(chip);
    });
  }

  function closeAllMoveMenus(exceptWrap) {
    qsa("[data-move-wrap]", phase2Root).forEach(function (wrap) {
      if (exceptWrap && wrap === exceptWrap) return;
      var toggle = qs("[data-move-toggle]", wrap);
      var menu = qs("[data-move-menu]", wrap);
      if (toggle) toggle.setAttribute("aria-expanded", "false");
      if (menu) menu.hidden = true;
    });
  }

  function renderMoveMenu(card, bucket) {
    var wrap = qs("[data-move-wrap]", card);
    if (!wrap) return;
    wrap.innerHTML = "";

    var toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "item-card__more-toggle";
    toggle.setAttribute("data-move-toggle", "");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-haspopup", "menu");
    toggle.setAttribute("title", "More actions");
    toggle.setAttribute("aria-label", "More actions");
    toggle.innerHTML =
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="12" cy="19" r="1.75"/></svg>';

    var menu = document.createElement("div");
    menu.className = "item-card__more-menu";
    menu.setAttribute("data-move-menu", "");
    menu.setAttribute("role", "menu");
    menu.hidden = true;

    var label = document.createElement("div");
    label.className = "item-card__more-label";
    label.setAttribute("role", "presentation");
    label.textContent = "Move to";
    menu.appendChild(label);

    BUCKET_KEYS.forEach(function (key) {
      if (key === bucket) return;
      var option = document.createElement("button");
      option.type = "button";
      option.className = "item-card__more-option";
      option.setAttribute("data-move-to", key);
      option.setAttribute("role", "menuitem");
      option.textContent = BUCKET_LABELS[key] || key;
      menu.appendChild(option);
    });

    wrap.appendChild(toggle);
    wrap.appendChild(menu);
  }

  function moveCardDom(card, toBucket) {
    var list = qs('[data-bucket-list="' + toBucket + '"]', phase2Root);
    if (!list) return;
    var fromBucket = card.getAttribute("data-bucket") || "";
    list.appendChild(card);
    card.setAttribute("data-bucket", toBucket);
    renderMoveMenu(card, toBucket);
    syncEmptyState(fromBucket);
    syncEmptyState(toBucket);
  }

  function moveItem(id, toBucket) {
    var loc = findItemLocation(id);
    if (!loc || loc.bucket === toBucket) return;
    var item = adaptItemForBucket(loc.item, toBucket);
    organizedState[loc.bucket].splice(loc.index, 1);
    organizedState[toBucket].push(item);

    var card = qs('[data-org-id="' + id.replace(/"/g, "") + '"]', phase2Root);
    if (card) {
      moveCardDom(card, toBucket);
    }
    scheduleSave();
  }

  function setItemTags(id, tags) {
    var loc = findItemLocation(id);
    if (!loc) return;
    var nextTags = normalizeTagList(tags);
    loc.item.tags = nextTags;
    organizedState[loc.bucket][loc.index] = loc.item;

    var card = qs('[data-org-id="' + id.replace(/"/g, "") + '"]', phase2Root);
    if (card) {
      card.setAttribute("data-tags", JSON.stringify(nextTags));
      renderTagChips(card, nextTags);
    }
    rebuildFilterBar();
    applyFilter();
    scheduleSave();
  }

  function addTagToItem(id, tagName) {
    var loc = findItemLocation(id);
    if (!loc) return;
    var tags = normalizeTagList(loc.item.tags);
    var name = String(tagName || "").trim();
    if (!name) return;
    if (tags.some(function (t) { return t.toLowerCase() === name.toLowerCase(); })) {
      return;
    }
    tags.push(name);
    setItemTags(id, tags);
  }

  function removeTagFromItem(id, tagName) {
    var loc = findItemLocation(id);
    if (!loc) return;
    var next = normalizeTagList(loc.item.tags).filter(function (t) {
      return t.toLowerCase() !== String(tagName || "").toLowerCase();
    });
    setItemTags(id, next);
  }

  var dragState = {
    section: null,
    card: null,
    didReorder: false,
  };

  function sectionCards(section) {
    return qsa(".item-card[data-org-id]", section);
  }

  function setCollapsed(section, collapsed) {
    section.classList.toggle("is-collapsed", collapsed);
    var toggle = qs("[data-collapse-toggle]", section);
    if (toggle) {
      toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
    }
  }

  function syncBucketOrderFromDom(bucket) {
    var list = qs('[data-bucket-list="' + bucket + '"]', phase2Root);
    if (!list || !organizedState) return;
    var byId = {};
    (organizedState[bucket] || []).forEach(function (item) {
      byId[item.id] = item;
    });
    var next = [];
    qsa(".item-card[data-org-id]", list).forEach(function (card) {
      var id = card.getAttribute("data-org-id");
      if (id && byId[id]) {
        next.push(byId[id]);
      }
    });
    organizedState[bucket] = next;
  }

  function clearDropTargets(section) {
    sectionCards(section).forEach(function (card) {
      card.classList.remove("is-drop-target");
    });
  }

  function finishRearrange(section, settleCard) {
    if (!section) return;
    var bucket = section.getAttribute("data-section-bucket") || "";
    var wasActive = section.classList.contains("is-rearranging");
    setRearrangeMode(section, false);
    if (!wasActive) return;

    if (bucket) {
      syncBucketOrderFromDom(bucket);
      scheduleSave();
    }

    if (settleCard) {
      settleCard.classList.remove("is-dragging", "is-drop-target");
      settleCard.classList.add("is-settling");
      settleCard.addEventListener(
        "animationend",
        function () {
          settleCard.classList.remove("is-settling");
        },
        { once: true }
      );
    }
  }

  function setRearrangeMode(section, on) {
    if (!section) return;
    if (!on && section.classList.contains("is-rearranging") === false) {
      return;
    }

    section.classList.toggle("is-rearranging", on);
    var toggle = qs("[data-rearrange-toggle]", section);
    if (toggle) {
      toggle.setAttribute("aria-pressed", on ? "true" : "false");
    }

    sectionCards(section).forEach(function (card) {
      card.draggable = !!on;
      card.classList.toggle("is-draggable", !!on);
      if (!on) {
        card.classList.remove("is-dragging", "is-drop-target");
      }
    });

    if (!on && dragState.section === section) {
      dragState.section = null;
      dragState.card = null;
      dragState.didReorder = false;
    }
  }

  function getDragAfterElement(list, y) {
    var cards = qsa(".item-card[data-org-id]:not(.is-dragging):not([hidden])", list);
    var closest = { offset: Number.NEGATIVE_INFINITY, element: null };
    cards.forEach(function (child) {
      var box = child.getBoundingClientRect();
      var offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        closest = { offset: offset, element: child };
      }
    });
    return closest.element;
  }

  if (phase2Root && organizedDataEl) {
    try {
      organizedState = cloneOrganized(JSON.parse(organizedDataEl.textContent || "{}"));
    } catch (err) {
      organizedState = { tasks: [], reference: [], articleCandidates: [] };
    }

    var filterLayoutDataEl = qs("#phase2-filter-layout-data");
    try {
      filterLayoutState = filterLayoutDataEl
        ? JSON.parse(filterLayoutDataEl.textContent || "[]")
        : [];
      if (!Array.isArray(filterLayoutState)) filterLayoutState = [];
    } catch (err) {
      filterLayoutState = [];
    }

    BUCKET_KEYS.forEach(function (bucket) {
      organizedState[bucket] = (organizedState[bucket] || []).map(function (item) {
        return adaptItemForBucket(item, bucket);
      });
      syncEmptyState(bucket);
    });

    rebuildFilterBar();
    applyFilter();

    phase2Root.addEventListener("click", function (e) {
      var infoToggle = e.target.closest("[data-section-info-toggle]");
      if (infoToggle) {
        var infoWrap = infoToggle.closest("[data-section-info]");
        if (!infoWrap) return;
        var willOpen = !infoWrap.classList.contains("is-open");
        closeSectionInfoTips(willOpen ? infoWrap : null);
        infoToggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
        infoWrap.classList.toggle("is-open", willOpen);
        return;
      }

      var collapseBtn = e.target.closest("[data-collapse-toggle]");
      if (collapseBtn) {
        var collapseSection = collapseBtn.closest("[data-section-bucket]");
        if (!collapseSection) return;
        if (collapseSection.classList.contains("is-rearranging")) {
          finishRearrange(collapseSection, null);
        }
        setCollapsed(collapseSection, !collapseSection.classList.contains("is-collapsed"));
        return;
      }

      var rearrangeBtn = e.target.closest("[data-rearrange-toggle]");
      if (rearrangeBtn) {
        var rearrangeSection = rearrangeBtn.closest("[data-section-bucket]");
        if (!rearrangeSection) return;
        if (rearrangeSection.classList.contains("is-collapsed")) {
          setCollapsed(rearrangeSection, false);
        }
        var turningOn = !rearrangeSection.classList.contains("is-rearranging");
        qsa("[data-section-bucket].is-rearranging", phase2Root).forEach(function (other) {
          if (other !== rearrangeSection) {
            finishRearrange(other, null);
          }
        });
        if (turningOn) {
          setRearrangeMode(rearrangeSection, true);
        } else {
          finishRearrange(rearrangeSection, null);
        }
        return;
      }

      var toggle = e.target.closest("[data-move-toggle]");
      if (toggle) {
        if (toggle.closest(".is-rearranging")) return;
        var wrap = toggle.closest("[data-move-wrap]");
        var menu = wrap ? qs("[data-move-menu]", wrap) : null;
        if (!wrap || !menu) return;
        var willOpen = menu.hidden;
        closeAllMoveMenus(willOpen ? wrap : null);
        menu.hidden = !willOpen;
        toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
        return;
      }

      var moveBtn = e.target.closest("[data-move-to]");
      if (moveBtn) {
        if (moveBtn.closest(".is-rearranging")) return;
        var card = moveBtn.closest(".item-card[data-org-id]");
        if (!card) return;
        closeAllMoveMenus();
        moveItem(card.getAttribute("data-org-id"), moveBtn.getAttribute("data-move-to"));
        return;
      }

      var removeBtn = e.target.closest("[data-remove-tag]");
      if (removeBtn) {
        if (removeBtn.closest(".is-rearranging")) return;
        var tagCard = removeBtn.closest(".item-card[data-org-id]");
        if (!tagCard) return;
        removeTagFromItem(tagCard.getAttribute("data-org-id"), removeBtn.getAttribute("data-remove-tag"));
        return;
      }

      var readMoreBtn = e.target.closest("[data-read-more]");
      if (readMoreBtn) {
        if (readMoreBtn.closest(".is-rearranging")) return;
        var readCard = readMoreBtn.closest(".item-card[data-org-id]");
        if (!readCard) return;
        var readTitle = qs("h3", readCard);
        setNoteModal(
          true,
          readTitle ? readTitle.textContent : "",
          readCard.getAttribute("data-full-text") || ""
        );
        return;
      }

      var copyBtn = e.target.closest("[data-copy-item]");
      if (copyBtn) {
        if (copyBtn.closest(".is-rearranging")) return;
        var copyCard = copyBtn.closest(".item-card[data-org-id]");
        if (!copyCard) return;
        var copyTitleEl = qs("h3", copyCard);
        var copyTitle = copyTitleEl ? copyTitleEl.textContent : "";
        var copyBody = copyCard.getAttribute("data-full-text") || "";
        copyNote(copyTitle, copyBody).then(function () {
          flashCopyButton(copyBtn);
        }).catch(function () {
          // clipboard unavailable
        });
      }
    });

    phase2Root.addEventListener("dragstart", function (e) {
      if (e.target.closest("[data-filter-menu-wrap]")) {
        e.preventDefault();
        return;
      }
      var filterItem = e.target.closest(
        ".tag-filter__group[data-filter-index], .tag-filter__divider[data-filter-index]"
      );
      if (filterItem) {
        var fromIndex = parseInt(filterItem.getAttribute("data-filter-index") || "-1", 10);
        if (isNaN(fromIndex) || fromIndex < 0) {
          e.preventDefault();
          return;
        }
        closeAllFilterMenus();
        filterDrag.index = fromIndex;
        filterDrag.didDrag = false;
        filterItem.classList.add("is-dragging");
        var bar = qs("[data-tag-filter]", phase2Root);
        if (bar) bar.classList.add("is-reordering");
        if (e.dataTransfer) {
          e.dataTransfer.effectAllowed = "move";
          e.dataTransfer.setData(
            "text/plain",
            filterItem.getAttribute("data-filter-tag") || "divider"
          );
        }
        return;
      }

      var card = e.target.closest(".item-card[data-org-id]");
      var section = card ? card.closest("[data-section-bucket].is-rearranging") : null;
      if (!card || !section) {
        e.preventDefault();
        return;
      }
      dragState.section = section;
      dragState.card = card;
      dragState.didReorder = false;
      card.classList.add("is-dragging");
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", card.getAttribute("data-org-id") || "");
      }
    });

    phase2Root.addEventListener("dragover", function (e) {
      if (filterDrag.index >= 0) {
        var filterBar = e.target.closest("[data-tag-filter]");
        if (!filterBar) return;
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = "move";
        filterDrag.didDrag = true;
        var dropIndex = getFilterDropIndex(filterBar, e.clientX, e.clientY);
        qsa(".tag-filter__group[data-filter-index], .tag-filter__divider[data-filter-index]", filterBar).forEach(function (el) {
          var at = parseInt(el.getAttribute("data-filter-index") || "-1", 10);
          el.classList.toggle("is-drop-before", at === dropIndex);
        });
        filterBar.classList.toggle("is-drop-end", dropIndex === filterLayoutState.length);
        return;
      }

      if (!dragState.card || !dragState.section) return;
      var section = e.target.closest("[data-section-bucket]");
      if (!section || section !== dragState.section) return;
      e.preventDefault();
      if (e.dataTransfer) e.dataTransfer.dropEffect = "move";

      var list = qs("[data-bucket-list]", section);
      if (!list) return;
      var after = getDragAfterElement(list, e.clientY);
      clearDropTargets(section);
      if (after == null) {
        list.appendChild(dragState.card);
      } else if (after !== dragState.card) {
        list.insertBefore(dragState.card, after);
        after.classList.add("is-drop-target");
      }
      dragState.didReorder = true;
    });

    phase2Root.addEventListener("drop", function (e) {
      if (filterDrag.index >= 0) {
        var dropBar = e.target.closest("[data-tag-filter]");
        if (!dropBar) return;
        e.preventDefault();
        var toIndex = getFilterDropIndex(dropBar, e.clientX, e.clientY);
        var fromIndex = filterDrag.index;
        filterDrag.index = -1;
        dropBar.classList.remove("is-reordering", "is-drop-end");
        qsa(".is-dragging, .is-drop-before", dropBar).forEach(function (el) {
          el.classList.remove("is-dragging", "is-drop-before");
        });
        if (fromIndex !== toIndex && fromIndex + 1 !== toIndex) {
          moveFilterItem(fromIndex, toIndex);
        } else {
          rebuildFilterBar();
        }
        return;
      }

      if (!dragState.card || !dragState.section) return;
      e.preventDefault();
      var settle = dragState.card;
      var section = dragState.section;
      finishRearrange(section, settle);
    });

    phase2Root.addEventListener("dragend", function () {
      if (filterDrag.index >= 0) {
        var endBar = qs("[data-tag-filter]", phase2Root);
        if (endBar) {
          endBar.classList.remove("is-reordering", "is-drop-end");
          qsa(".is-dragging, .is-drop-before", endBar).forEach(function (el) {
            el.classList.remove("is-dragging", "is-drop-before");
          });
        }
        filterDrag.index = -1;
        setTimeout(function () {
          filterDrag.didDrag = false;
        }, 0);
        return;
      }

      if (!dragState.card || !dragState.section) return;
      var settle = dragState.card;
      var section = dragState.section;
      // drop may already have finished; still safe to settle if still rearranging
      if (section.classList.contains("is-rearranging")) {
        finishRearrange(section, settle);
      } else {
        settle.classList.remove("is-dragging", "is-drop-target");
      }
    });

    document.addEventListener("click", function (e) {
      if (!e.target.closest("[data-move-wrap]")) {
        closeAllMoveMenus();
      }
      if (!e.target.closest("[data-section-info]")) {
        closeSectionInfoTips();
      }
      if (!e.target.closest("[data-filter-menu-wrap]")) {
        closeAllFilterMenus();
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        closeAllMoveMenus();
        closeAllFilterMenus();
        closeSectionInfoTips();
        qsa("[data-section-bucket].is-rearranging", phase2Root).forEach(function (section) {
          finishRearrange(section, null);
        });
      }
    });

    phase2Root.addEventListener("submit", function (e) {
      var form = e.target.closest("[data-tag-form]");
      if (!form) return;
      e.preventDefault();
      var card = form.closest(".item-card[data-org-id]");
      var input = qs("[data-tag-input]", form);
      if (!card || !input) return;
      addTagToItem(card.getAttribute("data-org-id"), input.value);
      input.value = "";
      input.focus();
    });

  }
})();
