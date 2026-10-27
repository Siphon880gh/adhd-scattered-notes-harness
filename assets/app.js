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
    var label = qs("span", btn);
    var prev = label ? label.textContent : null;
    var prevTitle = btn.getAttribute("title");
    if (label) label.textContent = "Copied";
    if (!label && prevTitle) btn.setAttribute("title", "Copied");
    btn.classList.add("is-copied");
    setTimeout(function () {
      if (label) label.textContent = prev;
      if (!label && prevTitle) btn.setAttribute("title", prevTitle);
      btn.classList.remove("is-copied");
    }, 1200);
  }

  function copySectionVisibleNotes(section) {
    if (!section) return Promise.resolve(false);
    var list = qs("[data-bucket-list]", section);
    if (!list) return Promise.resolve(false);
    var cards = qsa(".item-card[data-org-id]:not([hidden])", list);
    if (!cards.length) return Promise.resolve(false);
    var plainParts = [];
    var htmlParts = [];
    cards.forEach(function (card) {
      var titleEl = qs("h3", card);
      var title = titleEl ? titleEl.textContent : "";
      var body = card.getAttribute("data-full-text") || "";
      var payload = noteCopyPayload(title, body);
      if (payload.plain) plainParts.push(payload.plain);
      if (payload.html) htmlParts.push(payload.html);
    });
    if (!plainParts.length) return Promise.resolve(false);
    return copyRich(plainParts.join("\n\n\n"), htmlParts.join("<p><br></p><p><br></p>")).then(function () {
      return true;
    });
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
  var reviewLocked = phase2Root
    ? String(phase2Root.getAttribute("data-phase") || "") === "3"
    : false;
  var snapshotReadonly = phase2Root
    ? phase2Root.getAttribute("data-snapshot-readonly") === "1"
    : false;
  var snapshotId = phase2Root
    ? String(phase2Root.getAttribute("data-snapshot-id") || "").trim()
    : "";
  var isSnapshotView = snapshotId !== "";
  var BUCKET_LABELS = {
    tasks: "Scattered",
    reference: "Reference",
    articleCandidates: "Articles",
  };
  var BUCKET_KEYS = ["tasks", "reference", "articleCandidates"];
  var activeFilterTag = "";
  var activeKindFilter = ""; // "" | "task" | "not-task"
  var saveTimer = null;
  var suggestionsSaveTimer = null;
  var organizedState = null;
  var filterLayoutState = [];
  var notebookOrderState = {};
  var PANEL_SUGGEST_KEYS = ["tasks", "reference", "articleCandidates"];
  var suggestionsState = {
    panel: { tasks: "", reference: "", articleCandidates: "" },
    items: {},
  };
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
      checked: !!item.checked,
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

  function setItemChecked(id, checked) {
    if (reviewLocked) return;
    var loc = findItemLocation(id);
    if (!loc) return;
    loc.item.checked = !!checked;
    organizedState[loc.bucket][loc.index] = loc.item;

    var card = qs('[data-org-id="' + id.replace(/"/g, "") + '"]', phase2Root);
    if (card) {
      card.classList.toggle("is-checked", !!checked);
      card.setAttribute("data-checked", checked ? "1" : "0");
      var btn = qs("[data-check-item]", card);
      if (btn) {
        btn.setAttribute("aria-pressed", checked ? "true" : "false");
        var label = checked ? "Unmark as reviewed" : "Mark as reviewed";
        btn.setAttribute("title", label);
        btn.setAttribute("aria-label", label);
      }
    }
    scheduleSave();
  }

  function saveOrganized() {
    if (!organizedState) return;
    syncFilterLayout();
    var body = JSON.stringify({
      organized: organizedState,
      filterLayout: filterLayoutState,
      notebookOrder: notebookOrderState,
      id: isSnapshotView ? snapshotId : undefined,
    });
    var url = isSnapshotView
      ? "index.php?action=update-snapshot"
      : "index.php?action=save-phase2";
    fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: body,
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.data || !result.data.ok) {
          throw new Error(
            (result.data && result.data.error) || "Save failed"
          );
        }
        showAppToast(isSnapshotView ? "Saved to snapshot" : "Saved", {
          saved: true,
        });
      })
      .catch(function (err) {
        showAppToast(err && err.message ? err.message : "Save failed");
      });
  }

  function scheduleSave() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(saveOrganized, 200);
  }

  function normalizeSuggestionsState(data) {
    var next = {
      panel: { tasks: "", reference: "", articleCandidates: "" },
      items: {},
    };
    if (!data || typeof data !== "object") return next;
    if (data.panel && typeof data.panel === "object") {
      PANEL_SUGGEST_KEYS.forEach(function (key) {
        next.panel[key] = String(data.panel[key] || "").trim();
      });
    }
    if (data.items && typeof data.items === "object") {
      Object.keys(data.items).forEach(function (id) {
        var text = String(data.items[id] || "").trim();
        if (!id || !text) return;
        next.items[id] = text;
      });
    }
    return next;
  }

  function saveSuggestions() {
    if (reviewLocked) return;
    fetch("index.php?action=save-suggestions", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(suggestionsState),
    }).catch(function () {
      // local save failure — UI state still updated
    });
  }

  function scheduleSuggestionsSave() {
    if (reviewLocked) return;
    if (suggestionsSaveTimer) clearTimeout(suggestionsSaveTimer);
    suggestionsSaveTimer = setTimeout(saveSuggestions, 300);
  }

  function syncItemSuggestionUi(card, text) {
    if (!card) return;
    var has = !!(text && String(text).trim());
    card.classList.toggle("has-suggestion", has);
  }

  function syncPanelSuggestionUi(section, text) {
    if (!section) return;
    var has = !!(text && String(text).trim());
    section.classList.toggle("has-panel-suggestion", has);
  }

  function setItemSuggestion(id, text) {
    if (!id) return;
    var trimmed = String(text || "").trim();
    if (trimmed) {
      suggestionsState.items[id] = trimmed;
    } else {
      delete suggestionsState.items[id];
    }
    var card = qs('[data-org-id="' + id.replace(/"/g, "") + '"]', phase2Root);
    syncItemSuggestionUi(card, trimmed);
    scheduleSuggestionsSave();
  }

  function setPanelSuggestion(panel, text) {
    if (PANEL_SUGGEST_KEYS.indexOf(panel) === -1) return;
    var trimmed = String(text || "").trim();
    suggestionsState.panel[panel] = trimmed;
    var section = qs(
      '[data-section-bucket="' + String(panel).replace(/"/g, "") + '"]',
      phase2Root
    );
    syncPanelSuggestionUi(section, trimmed);
    scheduleSuggestionsSave();
  }

  function closeAllSuggestPanels(except) {
    qsa("[data-suggest-panel]", phase2Root).forEach(function (panel) {
      if (except && panel === except) return;
      panel.hidden = true;
      var wrap = panel.closest("[data-item-suggest]");
      var toggle = wrap ? qs("[data-suggest-toggle]", wrap) : null;
      if (toggle) toggle.setAttribute("aria-expanded", "false");
    });
    qsa("[data-panel-suggest-panel]", phase2Root).forEach(function (panel) {
      if (except && panel === except) return;
      panel.hidden = true;
      var section = panel.closest("[data-section-bucket]");
      var toggle = section ? qs("[data-panel-suggest-toggle]", section) : null;
      if (toggle) toggle.setAttribute("aria-expanded", "false");
    });
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

  // --- Phase 3 Notebook mode ---
  var notebookView = qs("[data-notebook-view]", phase2Root);
  var panelsView = qs("[data-panels-view]", phase2Root);
  var notebookFoldersEl = qs("[data-notebook-folders]", phase2Root);
  var notebookEmptyEl = qs("[data-notebook-empty]", phase2Root);
  var notebookWorkspaceEl = qs("[data-notebook-workspace]", phase2Root);
  var notebookNotesEl = qs("[data-notebook-notes]", phase2Root);
  var notebookTitleEl = qs("[data-notebook-title]", phase2Root);
  var notebookCrumbEl = qs("[data-notebook-crumb]", phase2Root);
  var notebookRearrangeBtn = qs("[data-notebook-rearrange]", phase2Root);
  var notebookCopyBtn = qs("[data-notebook-copy]", phase2Root);
  var notebookToolbarActions = qs("[data-notebook-toolbar-actions]", phase2Root);

  function setNotebookActionExpanded(activeBtn) {
    [notebookRearrangeBtn, notebookCopyBtn].forEach(function (btn) {
      if (!btn) return;
      btn.classList.toggle("is-expanded", btn === activeBtn);
    });
  }

  function clearNotebookActionExpandedIfIdle() {
    if (!notebookToolbarActions) {
      setNotebookActionExpanded(null);
      return;
    }
    if (notebookToolbarActions.contains(document.activeElement)) {
      setNotebookActionExpanded(document.activeElement.closest(
        "[data-notebook-rearrange], [data-notebook-copy]"
      ));
      return;
    }
    setNotebookActionExpanded(null);
  }

  if (notebookToolbarActions) {
    [notebookRearrangeBtn, notebookCopyBtn].forEach(function (btn) {
      if (!btn) return;
      btn.addEventListener("pointerenter", function () {
        setNotebookActionExpanded(btn);
      });
      btn.addEventListener("focusin", function () {
        setNotebookActionExpanded(btn);
      });
    });
    notebookToolbarActions.addEventListener("pointerleave", function () {
      clearNotebookActionExpandedIfIdle();
    });
    notebookToolbarActions.addEventListener("focusout", function () {
      setTimeout(clearNotebookActionExpandedIfIdle, 0);
    });
  }
  var viewMode = "panels";
  var activeNotebookTag = "";
  var activeNotebookFolderLabel = "";
  var notebookCollapsedIds = {};
  var notebookDrag = {
    note: null,
    didReorder: false,
  };
  var notebookTagDrag = {
    tag: "",
    fromGroup: -1,
    didDrag: false,
  };

  function notebookFolderLabel(heading, untitledIndex) {
    var name = String(heading || "").trim();
    if (name) return { text: name, untitled: false };
    return { text: "Untitled #" + untitledIndex, untitled: true };
  }

  function isTaskTitle(title) {
    return String(title || "").trim().indexOf("Task: ") === 0;
  }

  function itemMatchesKind(item) {
    if (!activeKindFilter) return true;
    var isTask = isTaskTitle(item && item.title);
    if (activeKindFilter === "task") return isTask;
    if (activeKindFilter === "not-task") return !isTask;
    return true;
  }

  function cardMatchesKind(card) {
    if (!activeKindFilter) return true;
    var titleEl = qs("h3", card);
    return itemMatchesKind({ title: titleEl ? titleEl.textContent : "" });
  }

  function syncKindFilterUi() {
    qsa("[data-kind-filter-btn]", phase2Root).forEach(function (btn) {
      var value = btn.getAttribute("data-kind-filter-btn") || "";
      var on = value === activeKindFilter;
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });
  }

  function setKindFilter(value) {
    var next = value === "task" || value === "not-task" ? value : "";
    if (activeKindFilter === next) return;
    activeKindFilter = next;
    syncKindFilterUi();
    applyFilter();
    if (viewMode === "notebook") {
      syncNotebookNavAvailability();
      if (activeNotebookTag) {
        if (
          activeKindFilter &&
          itemsForNotebookTag(activeNotebookTag).length === 0
        ) {
          clearNotebookSelection();
        } else {
          openNotebook(activeNotebookTag, activeNotebookFolderLabel);
        }
      }
    }
  }

  function clearNotebookSelection() {
    activeNotebookTag = "";
    activeNotebookFolderLabel = "";
    setNotebookRearrangeMode(false);
    if (notebookFoldersEl) {
      qsa(".notebook-nav", notebookFoldersEl).forEach(function (btn) {
        btn.classList.remove("is-active");
      });
    }
    if (notebookEmptyEl) notebookEmptyEl.hidden = false;
    if (notebookWorkspaceEl) notebookWorkspaceEl.hidden = true;
    if (notebookNotesEl) notebookNotesEl.innerHTML = "";
  }

  function notebookEmptyUnderKindFilter(tagName) {
    return !!activeKindFilter && itemsForNotebookTag(tagName).length === 0;
  }

  function syncNotebookNavAvailability() {
    if (!notebookFoldersEl) return;
    qsa(".notebook-nav[data-notebook-tag]", notebookFoldersEl).forEach(function (btn) {
      var tag = btn.getAttribute("data-notebook-tag") || "";
      var disabled = notebookEmptyUnderKindFilter(tag);
      btn.classList.toggle("is-disabled", disabled);
      btn.setAttribute("aria-disabled", disabled ? "true" : "false");
      if (disabled) {
        btn.setAttribute(
          "title",
          activeKindFilter === "task"
            ? "No task notes in this notebook"
            : "No non-task notes in this notebook"
        );
      } else if (btn.draggable) {
        btn.setAttribute("title", "Drag to another folder to regroup");
      } else {
        btn.removeAttribute("title");
      }
    });
  }

  var appToastTimer = null;

  function showAppToast(message, opts) {
    var toast = qs("[data-app-toast]");
    if (!toast) {
      toast = document.createElement("div");
      toast.className = "app-toast";
      toast.setAttribute("data-app-toast", "");
      toast.setAttribute("role", "status");
      toast.setAttribute("aria-live", "polite");
      document.body.appendChild(toast);
    }
    var isSaved = !!(opts && opts.saved);
    toast.textContent = message;
    toast.hidden = false;
    toast.classList.toggle("is-saved", isSaved);
    toast.classList.add("is-visible");
    if (appToastTimer) clearTimeout(appToastTimer);
    appToastTimer = setTimeout(function () {
      toast.classList.remove("is-visible");
      appToastTimer = setTimeout(function () {
        toast.hidden = true;
        toast.classList.remove("is-saved");
      }, 220);
    }, isSaved ? 1600 : 2800);
  }

  function showNotebookKindEmptyToast(tagName) {
    var label = tagName ? '"' + tagName + '"' : "this notebook";
    if (activeKindFilter === "task") {
      showAppToast("There's no task notes in " + label + ".");
      return;
    }
    if (activeKindFilter === "not-task") {
      showAppToast("There's no non-task notes in " + label + ".");
      return;
    }
    showAppToast("There's no notes in " + label + ".");
  }

  function countItemsForTag(tagName) {
    var key = String(tagName || "").toLowerCase();
    if (!key || !organizedState) return 0;
    var n = 0;
    BUCKET_KEYS.forEach(function (bucket) {
      (organizedState[bucket] || []).forEach(function (item) {
        var match = normalizeTagList(item.tags).some(function (tag) {
          return tag.toLowerCase() === key;
        });
        if (match) n += 1;
      });
    });
    return n;
  }

  function itemsForNotebookTag(tagName) {
    var key = String(tagName || "").toLowerCase();
    var rows = [];
    if (!key || !organizedState) return rows;
    BUCKET_KEYS.forEach(function (bucket) {
      (organizedState[bucket] || []).forEach(function (item) {
        var match = normalizeTagList(item.tags).some(function (tag) {
          return tag.toLowerCase() === key;
        });
        if (!match) return;
        if (!itemMatchesKind(item)) return;
        rows.push({
          bucket: bucket,
          item: item,
        });
      });
    });

    var order = notebookOrderState[tagName] || [];
    if (!order.length) {
      var foldKey = Object.keys(notebookOrderState).find(function (k) {
        return String(k).toLowerCase() === key;
      });
      if (foldKey) order = notebookOrderState[foldKey] || [];
    }
    if (!order.length) return rows;

    var byId = {};
    rows.forEach(function (row) {
      byId[row.item.id] = row;
    });
    var sorted = [];
    var used = {};
    order.forEach(function (id) {
      if (!byId[id] || used[id]) return;
      used[id] = true;
      sorted.push(byId[id]);
    });
    rows.forEach(function (row) {
      if (used[row.item.id]) return;
      sorted.push(row);
    });
    return sorted;
  }

  function itemBodyText(item) {
    if (!item) return "";
    if (item.body != null && String(item.body) !== "") return String(item.body);
    if (item.why != null) return String(item.why);
    return "";
  }

  function setNotebookRearrangeMode(on) {
    if (!notebookView || !notebookNotesEl) return;
    notebookView.classList.toggle("is-rearranging", !!on);
    if (notebookRearrangeBtn) {
      notebookRearrangeBtn.setAttribute("aria-pressed", on ? "true" : "false");
    }
    qsa(".notebook-note[data-org-id]", notebookNotesEl).forEach(function (note) {
      note.draggable = !!on;
      note.classList.toggle("is-draggable", !!on);
      if (!on) {
        note.classList.remove("is-dragging", "is-drop-target");
      }
    });
    if (!on) {
      notebookDrag.note = null;
      notebookDrag.didReorder = false;
    }
  }

  function finishNotebookRearrange(settleNote) {
    if (!notebookView || !notebookView.classList.contains("is-rearranging")) {
      setNotebookRearrangeMode(false);
      return;
    }
    if (activeNotebookTag && notebookNotesEl) {
      var ids = [];
      qsa(".notebook-note[data-org-id]", notebookNotesEl).forEach(function (note) {
        var id = note.getAttribute("data-org-id");
        if (id) ids.push(id);
      });
      notebookOrderState[activeNotebookTag] = ids;
      scheduleSave();
    }
    setNotebookRearrangeMode(false);
    if (settleNote) {
      settleNote.classList.remove("is-dragging", "is-drop-target");
      settleNote.classList.add("is-settling");
      settleNote.addEventListener(
        "animationend",
        function () {
          settleNote.classList.remove("is-settling");
        },
        { once: true }
      );
    }
  }

  function getNotebookDragAfterElement(y) {
    var notes = qsa(
      ".notebook-note[data-org-id]:not(.is-dragging)",
      notebookNotesEl
    );
    var closest = { offset: Number.NEGATIVE_INFINITY, element: null };
    notes.forEach(function (child) {
      var box = child.getBoundingClientRect();
      var offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        closest = { offset: offset, element: child };
      }
    });
    return closest.element;
  }

  function clearNotebookDropTargets() {
    if (!notebookNotesEl) return;
    qsa(".notebook-note", notebookNotesEl).forEach(function (note) {
      note.classList.remove("is-drop-target");
    });
  }

  function setNotebookNoteCollapsed(note, collapsed) {
    if (!note) return;
    var id = note.getAttribute("data-org-id") || "";
    note.classList.toggle("is-collapsed", collapsed);
    var toggle = qs("[data-notebook-note-collapse]", note);
    if (toggle) {
      toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
    }
    if (id) {
      if (collapsed) notebookCollapsedIds[id] = true;
      else delete notebookCollapsedIds[id];
    }
  }

  function renderNotebookNote(row) {
    var item = row.item;
    var bucket = row.bucket;
    var id = item.id || "";
    var title = item.title || id || "Untitled";
    var body = itemBodyText(item);
    var sources = Array.isArray(item.sources) ? item.sources : [];
    var collapsed = !!(id && notebookCollapsedIds[id]);

    var note = document.createElement("article");
    note.className = "notebook-note" + (collapsed ? " is-collapsed" : "");
    note.setAttribute("data-org-id", id);
    note.setAttribute("data-bucket", bucket);
    note.setAttribute("data-full-text", body);

    var header = document.createElement("div");
    header.className = "notebook-note__header";

    var collapseBtn = document.createElement("button");
    collapseBtn.type = "button";
    collapseBtn.className = "notebook-note__collapse";
    collapseBtn.setAttribute("data-notebook-note-collapse", "");
    collapseBtn.setAttribute("aria-expanded", collapsed ? "false" : "true");
    collapseBtn.setAttribute("title", collapsed ? "Expand note" : "Collapse note");
    collapseBtn.setAttribute("aria-label", collapsed ? "Expand note" : "Collapse note");
    collapseBtn.innerHTML = '<span class="notebook-note__chevron" aria-hidden="true"></span>';

    var heading = document.createElement("h3");
    heading.className = "notebook-note__title";
    heading.textContent = title;

    var copyBtn = document.createElement("button");
    copyBtn.type = "button";
    copyBtn.className = "notebook-note__copy";
    copyBtn.setAttribute("data-notebook-note-copy", "");
    copyBtn.setAttribute("title", "Copy note");
    copyBtn.setAttribute("aria-label", "Copy note");
    copyBtn.innerHTML =
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>Copy</span>';

    header.appendChild(collapseBtn);
    header.appendChild(heading);
    header.appendChild(copyBtn);

    var bodyEl = document.createElement("div");
    bodyEl.className = "notebook-note__body";
    bodyEl.textContent = body || "";

    var meta = document.createElement("div");
    meta.className = "notebook-note__meta";
    var bits = [BUCKET_LABELS[bucket] || bucket];
    if (sources.length) bits.push(sources.join(", "));
    meta.textContent = bits.join(" · ");

    note.appendChild(header);
    note.appendChild(bodyEl);
    note.appendChild(meta);
    return note;
  }

  function openNotebook(tagName, folderLabel) {
    if (!notebookView || !notebookNotesEl) return;
    activeNotebookTag = tagName;
    activeNotebookFolderLabel = folderLabel || "";
    setNotebookRearrangeMode(false);

    if (notebookEmptyEl) notebookEmptyEl.hidden = true;
    if (notebookWorkspaceEl) notebookWorkspaceEl.hidden = false;
    if (notebookTitleEl) notebookTitleEl.textContent = tagName;
    if (notebookCrumbEl) {
      notebookCrumbEl.textContent = activeNotebookFolderLabel
        ? activeNotebookFolderLabel + " /"
        : "";
    }

    qsa(".notebook-nav", notebookFoldersEl).forEach(function (btn) {
      var active =
        String(btn.getAttribute("data-notebook-tag") || "").toLowerCase() ===
        String(tagName).toLowerCase();
      btn.classList.toggle("is-active", active);
    });

    notebookNotesEl.innerHTML = "";
    var rows = itemsForNotebookTag(tagName);
    if (!rows.length) {
      var empty = document.createElement("p");
      empty.className = "notebook__notes-empty";
      if (activeKindFilter === "task") {
        empty.textContent = "No task notes in this notebook.";
      } else if (activeKindFilter === "not-task") {
        empty.textContent = "No non-task notes in this notebook.";
      } else {
        empty.textContent = "No notes tagged with this notebook yet.";
      }
      notebookNotesEl.appendChild(empty);
      return;
    }
    rows.forEach(function (row) {
      notebookNotesEl.appendChild(renderNotebookNote(row));
    });
  }

  function rebuildNotebookSidebar() {
    if (!notebookFoldersEl || !reviewLocked) return;
    syncFilterLayout();
    var available = collectAllTags();
    var availableMap = {};
    available.forEach(function (tag) {
      availableMap[tag.toLowerCase()] = tag;
    });
    var parsed = parseFilterGroups(filterLayoutState, availableMap);
    var groups = parsed.groups.slice();
    var used = parsed.used;
    var missing = [];
    available.forEach(function (tag) {
      if (!used[tag.toLowerCase()]) missing.push(tag);
    });
    if (missing.length) {
      if (!groups.length) {
        groups.push({ heading: "", tags: missing.slice() });
      } else {
        groups[groups.length - 1].tags = groups[groups.length - 1].tags.concat(missing);
      }
    }

    var prevActive = activeNotebookTag;
    var openFolders = {};
    qsa(".notebook-folder", notebookFoldersEl).forEach(function (folder, idx) {
      if (!folder.classList.contains("is-collapsed")) {
        openFolders[idx] = true;
      }
    });
    var hadFolders = qsa(".notebook-folder", notebookFoldersEl).length > 0;

    notebookFoldersEl.innerHTML = "";
    var untitledN = 0;
    groups.forEach(function (group, gIndex) {
      if (!group.tags || !group.tags.length) return;
      var named = String(group.heading || "").trim();
      if (!named) untitledN += 1;
      var labelInfo = notebookFolderLabel(named, untitledN);

      var folder = document.createElement("div");
      folder.className = "notebook-folder";
      folder.setAttribute("data-notebook-folder", String(gIndex));
      folder.setAttribute("data-notebook-folder-heading", named);
      if (hadFolders && !openFolders[gIndex]) {
        folder.classList.add("is-collapsed");
      }

      var head = document.createElement("div");
      head.className = "notebook-folder__head";
      head.setAttribute("data-notebook-folder-toggle", "");
      head.setAttribute(
        "title",
        folder.classList.contains("is-collapsed") ? "Expand folder" : "Collapse folder"
      );

      var toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "notebook-folder__toggle";
      toggle.setAttribute("data-notebook-folder-toggle", "");
      toggle.setAttribute("tabindex", "-1");
      toggle.setAttribute(
        "aria-expanded",
        folder.classList.contains("is-collapsed") ? "false" : "true"
      );
      toggle.setAttribute(
        "aria-label",
        (folder.classList.contains("is-collapsed") ? "Expand " : "Collapse ") + labelInfo.text
      );
      toggle.innerHTML =
        '<span class="notebook-folder__chevron" aria-hidden="true">▾</span>';

      var nameEl = document.createElement("span");
      nameEl.className = "notebook-folder__name";
      nameEl.setAttribute("data-notebook-folder-rename", "");
      nameEl.setAttribute("role", "button");
      nameEl.setAttribute("tabindex", "0");
      nameEl.setAttribute(
        "title",
        named ? "Click to rename" : "Click to name this folder"
      );
      nameEl.setAttribute(
        "aria-label",
        named ? "Rename folder " + named : "Name folder " + labelInfo.text
      );
      nameEl.textContent = labelInfo.text;

      head.appendChild(toggle);
      head.appendChild(nameEl);

      var list = document.createElement("ul");
      list.className = "notebook-folder__list";

      group.tags.forEach(function (tag) {
        var li = document.createElement("li");
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "notebook-nav";
        btn.setAttribute("data-notebook-tag", tag);
        btn.setAttribute("data-notebook-folder-label", labelInfo.text);
        btn.setAttribute("data-notebook-group", String(gIndex));
        btn.draggable = true;
        btn.setAttribute("title", "Drag to another folder to regroup");
        btn.innerHTML =
          '<svg class="notebook-nav__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>' +
          '<span class="notebook-nav__label"></span>' +
          '<span class="notebook-nav__count"></span>';
        qs(".notebook-nav__label", btn).textContent = tag;
        qs(".notebook-nav__count", btn).textContent = String(countItemsForTag(tag));
        if (notebookEmptyUnderKindFilter(tag)) {
          btn.classList.add("is-disabled");
          btn.setAttribute("aria-disabled", "true");
          btn.setAttribute(
            "title",
            activeKindFilter === "task"
              ? "No task notes in this notebook"
              : "No non-task notes in this notebook"
          );
        } else {
          btn.setAttribute("aria-disabled", "false");
        }
        if (
          prevActive &&
          String(prevActive).toLowerCase() === String(tag).toLowerCase()
        ) {
          btn.classList.add("is-active");
        }
        li.appendChild(btn);
        list.appendChild(li);
      });

      folder.appendChild(head);
      folder.appendChild(list);
      notebookFoldersEl.appendChild(folder);
    });

    if (!groups.length) {
      var empty = document.createElement("p");
      empty.className = "muted";
      empty.style.padding = "0.5rem 0.65rem";
      empty.textContent = "No tags yet — add tags in Panels view.";
      notebookFoldersEl.appendChild(empty);
    }

    if (prevActive && viewMode === "notebook") {
      var stillExists = false;
      var activeBtn = null;
      qsa(".notebook-nav", notebookFoldersEl).forEach(function (btn) {
        if (
          String(btn.getAttribute("data-notebook-tag") || "").toLowerCase() ===
          String(prevActive).toLowerCase()
        ) {
          stillExists = true;
          activeBtn = btn;
        }
      });
      if (
        stillExists &&
        !(activeBtn && activeBtn.classList.contains("is-disabled"))
      ) {
        openNotebook(
          prevActive,
          activeBtn
            ? activeBtn.getAttribute("data-notebook-folder-label") || ""
            : activeNotebookFolderLabel
        );
      } else {
        clearNotebookSelection();
      }
    }
  }

  function setViewMode(mode) {
    if (!reviewLocked || !notebookView || !panelsView) return;
    viewMode = mode === "notebook" ? "notebook" : "panels";
    var isNotebook = viewMode === "notebook";
    phase2Root.classList.toggle("is-notebook-mode", isNotebook);
    panelsView.hidden = isNotebook;
    notebookView.hidden = !isNotebook;

    qsa("[data-view-mode-btn]", phase2Root).forEach(function (btn) {
      var on = btn.getAttribute("data-view-mode-btn") === viewMode;
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-selected", on ? "true" : "false");
    });

    if (isNotebook) {
      qsa("[data-section-bucket].is-rearranging", phase2Root).forEach(function (section) {
        finishRearrange(section, null);
      });
      rebuildNotebookSidebar();
      if (activeNotebookTag) {
        openNotebook(activeNotebookTag, activeNotebookFolderLabel);
      }
    } else {
      setNotebookRearrangeMode(false);
      applyFilter();
    }
  }

  function copyNotebookAll() {
    if (!notebookNotesEl || !activeNotebookTag) return Promise.resolve();
    var partsPlain = [];
    var partsHtml = [];
    qsa(".notebook-note[data-org-id]", notebookNotesEl).forEach(function (note) {
      var titleEl = qs(".notebook-note__title", note);
      var title = titleEl ? titleEl.textContent : "";
      var body = note.getAttribute("data-full-text") || "";
      var payload = noteCopyPayload(title, body);
      if (payload.plain) partsPlain.push(payload.plain);
      if (payload.html) partsHtml.push(payload.html);
    });
    if (!partsPlain.length) return Promise.resolve();
    return copyRich(partsPlain.join("\n\n---\n\n"), partsHtml.join("<hr>"));
  }

  function mergeFilterGroupHeadings(left, right) {
    var a = String(left || "").trim();
    var b = String(right || "").trim();
    if (!a) return b;
    if (!b) return a;
    if (a.toLowerCase() === b.toLowerCase()) return a;
    return a + " + " + b;
  }

  function parseFilterGroups(layout, availableMap) {
    var groups = [];
    var heading = "";
    var tags = [];
    var used = {};

    function flush() {
      if (!tags.length) {
        heading = "";
        return;
      }
      groups.push({ heading: heading, tags: tags.slice() });
      heading = "";
      tags = [];
    }

    (layout || []).forEach(function (item) {
      if (!item || typeof item !== "object") return;
      if (item.type === "heading") {
        var headingName = String(item.name || "").trim();
        if (!headingName) return;
        if (!tags.length) {
          heading = headingName;
        } else if (!heading) {
          heading = headingName;
        }
        return;
      }
      if (item.type === "divider") {
        if (tags.length) flush();
        var after = String(item.heading || "").trim();
        if (after) heading = after;
        return;
      }
      if (item.type !== "tag") return;
      var name = String(item.name || "").trim();
      if (!name) return;
      var key = name.toLowerCase();
      if (availableMap) {
        if (!availableMap[key] || used[key]) return;
        used[key] = true;
        tags.push(availableMap[key]);
        return;
      }
      if (used[key]) return;
      used[key] = true;
      tags.push(name);
    });
    flush();

    return { groups: groups, used: used };
  }

  function layoutFromFilterGroups(groups) {
    var next = [];
    (groups || []).forEach(function (group) {
      if (!group || !group.tags || !group.tags.length) return;
      if (next.length) next.push({ type: "divider" });
      var heading = String(group.heading || "").trim();
      if (heading) next.push({ type: "heading", name: heading });
      group.tags.forEach(function (tag) {
        next.push({ type: "tag", name: tag });
      });
    });
    return next;
  }

  function syncFilterLayout() {
    var available = collectAllTags();
    var availableMap = {};
    available.forEach(function (tag) {
      availableMap[tag.toLowerCase()] = tag;
    });

    var parsed = parseFilterGroups(filterLayoutState, availableMap);
    var groups = parsed.groups;
    var used = parsed.used;

    var missing = [];
    available.forEach(function (tag) {
      if (!used[tag.toLowerCase()]) missing.push(tag);
    });
    missing.sort(function (a, b) {
      return a.localeCompare(b, undefined, { sensitivity: "base" });
    });
    if (missing.length) {
      if (!groups.length) {
        groups.push({ heading: "", tags: missing.slice() });
      } else {
        groups[groups.length - 1].tags = groups[groups.length - 1].tags.concat(missing);
      }
    }

    filterLayoutState = layoutFromFilterGroups(groups);
  }

  function insertFilterDividerAt(index) {
    syncFilterLayout();
    var at = Math.max(0, Math.min(index, filterLayoutState.length));
    if (filterLayoutState[at] && filterLayoutState[at].type === "divider") return;
    if (at > 0 && filterLayoutState[at - 1] && filterLayoutState[at - 1].type === "divider") return;

    var leftTags = 0;
    for (var i = at - 1; i >= 0; i--) {
      if (filterLayoutState[i].type === "divider") break;
      if (filterLayoutState[i].type === "tag") leftTags += 1;
    }
    var rightTags = 0;
    for (var j = at; j < filterLayoutState.length; j++) {
      if (filterLayoutState[j].type === "divider") break;
      if (filterLayoutState[j].type === "tag") rightTags += 1;
    }
    if (!leftTags || !rightTags) return;

    filterLayoutState.splice(at, 0, { type: "divider" });
    rebuildFilterBar();
    scheduleSave();
  }

  function removeFilterDividerAt(index) {
    syncFilterLayout();
    if (!filterLayoutState[index] || filterLayoutState[index].type !== "divider") return;

    var parsed = parseFilterGroups(filterLayoutState, null);
    var groups = parsed.groups;
    var boundary = -1;
    var cursor = 0;
    for (var g = 0; g < groups.length - 1; g++) {
      var group = groups[g];
      if (group.heading) cursor += 1;
      cursor += group.tags.length;
      if (cursor === index) {
        boundary = g;
        break;
      }
      cursor += 1; // divider
    }
    if (boundary < 0) {
      filterLayoutState.splice(index, 1);
      rebuildFilterBar();
      scheduleSave();
      return;
    }

    var left = groups[boundary];
    var right = groups[boundary + 1];
    groups.splice(boundary, 2, {
      heading: mergeFilterGroupHeadings(left.heading, right.heading),
      tags: left.tags.concat(right.tags),
    });
    filterLayoutState = layoutFromFilterGroups(groups);
    rebuildFilterBar();
    scheduleSave();
  }

  function setFilterGroupHeadingAt(groupIndex, nextHeading) {
    syncFilterLayout();
    var parsed = parseFilterGroups(filterLayoutState, null);
    var groups = parsed.groups;
    if (groupIndex < 0 || groupIndex >= groups.length) return;
    groups[groupIndex].heading = String(nextHeading || "").trim();
    filterLayoutState = layoutFromFilterGroups(groups);
    rebuildFilterBar();
    scheduleSave();
  }

  function editFilterGroupHeading(groupIndex, currentHeading) {
    var current = String(currentHeading || "").trim();
    var message = current
      ? "Rename group heading (leave empty to remove):"
      : "Name this filter group:";
    var next = window.prompt(message, current);
    if (next === null) return;
    setFilterGroupHeadingAt(groupIndex, next);
  }

  var notebookFolderRename = {
    folder: null,
    input: null,
    committed: false,
  };

  function notebookFolderDisplayLabel(folderEl) {
    var named = String(folderEl.getAttribute("data-notebook-folder-heading") || "").trim();
    if (named) return { text: named, untitled: false };
    var untitledIndex = 0;
    var found = { text: "Untitled #1", untitled: true };
    qsa(".notebook-folder", notebookFoldersEl).forEach(function (el) {
      if (String(el.getAttribute("data-notebook-folder-heading") || "").trim()) return;
      untitledIndex += 1;
      if (el === folderEl) found = notebookFolderLabel("", untitledIndex);
    });
    return found;
  }

  function restoreNotebookFolderNameEl(folderEl, replaceEl) {
    if (!folderEl || !replaceEl) return;
    var labelInfo = notebookFolderDisplayLabel(folderEl);
    var named = !labelInfo.untitled;
    var nameEl = document.createElement("span");
    nameEl.className = "notebook-folder__name";
    nameEl.setAttribute("data-notebook-folder-rename", "");
    nameEl.setAttribute("role", "button");
    nameEl.setAttribute("tabindex", "0");
    nameEl.setAttribute(
      "title",
      named ? "Click to rename" : "Click to name this folder"
    );
    nameEl.setAttribute(
      "aria-label",
      named ? "Rename folder " + labelInfo.text : "Name folder " + labelInfo.text
    );
    nameEl.textContent = labelInfo.text;
    replaceEl.replaceWith(nameEl);
    folderEl.classList.remove("is-renaming");
  }

  function commitNotebookFolderRename() {
    if (notebookFolderRename.committed) return;
    var folder = notebookFolderRename.folder;
    var input = notebookFolderRename.input;
    if (!folder || !input) return;
    notebookFolderRename.committed = true;
    var groupIndex = parseInt(folder.getAttribute("data-notebook-folder") || "-1", 10);
    var next = String(input.value || "").trim();
    var prev = String(folder.getAttribute("data-notebook-folder-heading") || "").trim();
    notebookFolderRename.folder = null;
    notebookFolderRename.input = null;
    if (isNaN(groupIndex) || groupIndex < 0 || next === prev) {
      restoreNotebookFolderNameEl(folder, input);
      notebookFolderRename.committed = false;
      return;
    }
    setFilterGroupHeadingAt(groupIndex, next);
    notebookFolderRename.committed = false;
  }

  function abortNotebookFolderRename() {
    var folder = notebookFolderRename.folder;
    var input = notebookFolderRename.input;
    if (!folder || !input) return;
    notebookFolderRename.committed = true;
    notebookFolderRename.folder = null;
    notebookFolderRename.input = null;
    restoreNotebookFolderNameEl(folder, input);
    notebookFolderRename.committed = false;
  }

  function beginNotebookFolderRename(folderEl) {
    if (!folderEl || folderEl.classList.contains("is-renaming")) return;
    if (notebookFolderRename.folder && notebookFolderRename.folder !== folderEl) {
      commitNotebookFolderRename();
    }
    var nameEl = qs("[data-notebook-folder-rename]", folderEl);
    if (!nameEl) return;
    var current = String(folderEl.getAttribute("data-notebook-folder-heading") || "").trim();
    var input = document.createElement("input");
    input.type = "text";
    input.className = "notebook-folder__input";
    input.setAttribute("data-notebook-folder-input", "");
    input.value = current;
    input.setAttribute("aria-label", "Folder name");
    input.maxLength = 80;
    input.placeholder = "Folder name";
    folderEl.classList.add("is-renaming");
    nameEl.replaceWith(input);
    notebookFolderRename.folder = folderEl;
    notebookFolderRename.input = input;
    notebookFolderRename.committed = false;
    input.focus();
    input.select();

    input.addEventListener("keydown", function (ev) {
      if (ev.key === "Enter") {
        ev.preventDefault();
        ev.stopPropagation();
        commitNotebookFolderRename();
        return;
      }
      if (ev.key === "Escape") {
        ev.preventDefault();
        ev.stopPropagation();
        abortNotebookFolderRename();
      }
    });

    input.addEventListener("blur", function () {
      if (notebookFolderRename.input === input) {
        commitNotebookFolderRename();
      }
    });

    input.addEventListener("click", function (ev) {
      ev.stopPropagation();
    });
    input.addEventListener("mousedown", function (ev) {
      ev.stopPropagation();
    });
  }

  function toggleNotebookFolder(folder) {
    if (!folder || folder.classList.contains("is-renaming")) return;
    var willCollapse = !folder.classList.contains("is-collapsed");
    folder.classList.toggle("is-collapsed", willCollapse);
    var head = qs(".notebook-folder__head", folder);
    var toggleBtn = qs(".notebook-folder__toggle", folder);
    if (head) {
      head.setAttribute(
        "title",
        willCollapse ? "Expand folder" : "Collapse folder"
      );
    }
    if (toggleBtn) {
      toggleBtn.setAttribute("aria-expanded", willCollapse ? "false" : "true");
    }
  }

  function clearNotebookTagDropIndicators() {
    if (!notebookFoldersEl) return;
    qsa(".notebook-folder.is-drop-target, .notebook-nav.is-drop-before, .notebook-nav.is-drop-after", notebookFoldersEl).forEach(function (el) {
      el.classList.remove("is-drop-target", "is-drop-before", "is-drop-after");
    });
  }

  function getNotebookTagInsertIndex(folderEl, clientY) {
    var list = qs(".notebook-folder__list", folderEl);
    if (!list) return 0;
    var items = qsa(".notebook-nav[data-notebook-tag]:not(.is-dragging)", list);
    for (var i = 0; i < items.length; i++) {
      var box = items[i].getBoundingClientRect();
      if (clientY < box.top + box.height / 2) return i;
    }
    return items.length;
  }

  function resolveNotebookTagDrop(clientY, targetEl) {
    if (!targetEl || !notebookFoldersEl) return null;
    var folder = targetEl.closest(".notebook-folder");
    if (!folder || !notebookFoldersEl.contains(folder)) return null;
    var groupIndex = parseInt(folder.getAttribute("data-notebook-folder") || "-1", 10);
    if (isNaN(groupIndex) || groupIndex < 0) return null;

    var onHead = !!targetEl.closest(".notebook-folder__head");
    var insertAt;
    if (onHead || folder.classList.contains("is-collapsed")) {
      var list = qs(".notebook-folder__list", folder);
      insertAt = list
        ? qsa(".notebook-nav[data-notebook-tag]:not(.is-dragging)", list).length
        : 0;
    } else {
      insertAt = getNotebookTagInsertIndex(folder, clientY);
    }
    return { groupIndex: groupIndex, insertAt: insertAt, folder: folder };
  }

  function moveNotebookTag(tagName, toGroupIndex, insertAt) {
    var tagKey = String(tagName || "").trim().toLowerCase();
    if (!tagKey) return false;

    syncFilterLayout();
    var parsed = parseFilterGroups(filterLayoutState, null);
    var groups = parsed.groups.map(function (group) {
      return {
        heading: group.heading,
        tags: group.tags.slice(),
      };
    });

    var fromGroup = -1;
    var fromIndex = -1;
    var tagCanonical = tagName;
    for (var g = 0; g < groups.length; g++) {
      for (var t = 0; t < groups[g].tags.length; t++) {
        if (String(groups[g].tags[t]).toLowerCase() === tagKey) {
          fromGroup = g;
          fromIndex = t;
          tagCanonical = groups[g].tags[t];
          break;
        }
      }
      if (fromGroup >= 0) break;
    }
    if (fromGroup < 0) return false;
    if (toGroupIndex < 0 || toGroupIndex >= groups.length) return false;

    var insert = Math.max(0, Math.min(insertAt, groups[toGroupIndex].tags.length));
    if (fromGroup === toGroupIndex) {
      if (insert === fromIndex || insert === fromIndex + 1) {
        return false;
      }
      groups[fromGroup].tags.splice(fromIndex, 1);
      if (insert > fromIndex) insert -= 1;
      groups[toGroupIndex].tags.splice(insert, 0, tagCanonical);
    } else {
      groups[fromGroup].tags.splice(fromIndex, 1);
      insert = Math.max(0, Math.min(insert, groups[toGroupIndex].tags.length));
      groups[toGroupIndex].tags.splice(insert, 0, tagCanonical);
      if (!groups[fromGroup].tags.length) {
        groups.splice(fromGroup, 1);
      }
    }

    filterLayoutState = layoutFromFilterGroups(groups);
    rebuildFilterBar();
    scheduleSave();
    return true;
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

    // Keep headings anchored to the start of their tag group.
    if (item.type === "tag" && next[insertAt] && next[insertAt].type === "heading") {
      insertAt += 1;
    }
    if (item.type === "divider") {
      if (next[insertAt] && next[insertAt].type === "divider") {
        rebuildFilterBar();
        return;
      }
      if (insertAt > 0 && next[insertAt - 1] && next[insertAt - 1].type === "divider") {
        rebuildFilterBar();
        return;
      }
      if (next[insertAt] && next[insertAt].type === "heading") {
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

    var cluster = null;
    var clusterBody = null;
    var groupIndex = -1;
    var currentClusterHeading = "";

    function startCluster(headingName, gIndex) {
      groupIndex = gIndex;
      currentClusterHeading = String(headingName || "").trim();
      cluster = document.createElement("div");
      cluster.className = "tag-filter__cluster";
      cluster.setAttribute("data-filter-group", String(gIndex));

      var headingRow = document.createElement("div");
      headingRow.className = "tag-filter__heading-row";

      var headingBtn = document.createElement("button");
      headingBtn.type = "button";
      headingBtn.className = "tag-filter__heading";
      headingBtn.setAttribute("data-filter-heading", "");
      headingBtn.setAttribute("data-filter-group", String(gIndex));
      if (currentClusterHeading) {
        headingBtn.textContent = currentClusterHeading;
        headingBtn.classList.add("is-named");
        headingBtn.setAttribute("title", "Rename or clear group heading");
        headingBtn.setAttribute(
          "aria-label",
          "Group heading: " + currentClusterHeading + ". Click to rename or remove."
        );
      } else {
        headingBtn.classList.add("is-placeholder");
        headingBtn.setAttribute("title", "Name this filter group");
        headingBtn.setAttribute("aria-label", "Name this filter group");
        headingBtn.innerHTML =
          '<svg class="tag-filter__heading-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>' +
          '<span class="tag-filter__heading-label">Name group</span>';
        headingBtn.addEventListener("focus", function () {
          headingBtn.classList.add("is-active");
        });
        headingBtn.addEventListener("blur", function () {
          headingBtn.classList.remove("is-active");
        });
      }
      var headingValue = currentClusterHeading;
      headingBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        editFilterGroupHeading(gIndex, headingValue);
      });
      if (!currentClusterHeading) {
        headingRow.addEventListener("click", function (e) {
          if (e.target.closest(".tag-filter__heading")) return;
          e.preventDefault();
          e.stopPropagation();
          headingBtn.click();
        });
      }
      headingRow.appendChild(headingBtn);

      if (currentClusterHeading) {
        var clearBtn = document.createElement("button");
        clearBtn.type = "button";
        clearBtn.className = "tag-filter__heading-clear";
        clearBtn.setAttribute("title", "Remove group heading");
        clearBtn.setAttribute("aria-label", "Remove group heading");
        clearBtn.textContent = "×";
        clearBtn.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          setFilterGroupHeadingAt(gIndex, "");
        });
        headingRow.appendChild(clearBtn);
      }

      cluster.appendChild(headingRow);

      clusterBody = document.createElement("div");
      clusterBody.className = "tag-filter__cluster-tags";
      cluster.appendChild(clusterBody);
      bar.appendChild(cluster);
    }

    filterLayoutState.forEach(function (item, index) {
      if (item.type === "heading") {
        startCluster(item.name, groupIndex + 1);
        return;
      }

      if (item.type === "divider") {
        cluster = null;
        clusterBody = null;
        currentClusterHeading = "";
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

      if (item.type !== "tag") return;

      if (!clusterBody) {
        startCluster("", groupIndex + 1);
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

      group.appendChild(btn);
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

      function addDividerArrowOption(side, label, svgPath) {
        var option = document.createElement("button");
        option.type = "button";
        option.className = "tag-filter__menu-option tag-filter__menu-option--arrow";
        option.setAttribute("data-filter-divider-side", side);
        option.setAttribute("role", "menuitem");
        option.setAttribute("aria-label", label);
        option.innerHTML =
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="' +
          svgPath +
          '"/></svg><span>' +
          label +
          "</span>";
        option.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          closeAllFilterMenus();
          insertFilterDividerAt(side === "left" ? index : index + 1);
        });
        menu.appendChild(option);
      }

      addDividerArrowOption("left", "Add divider left", "M19 12H5M11 6l-6 6 6 6");
      addDividerArrowOption("right", "Add divider right", "M5 12h14M13 6l6 6-6 6");

      var separator = document.createElement("div");
      separator.className = "tag-filter__menu-separator";
      separator.setAttribute("role", "separator");
      menu.appendChild(separator);

      var headingOption = document.createElement("button");
      headingOption.type = "button";
      headingOption.className = "tag-filter__menu-option";
      headingOption.setAttribute("role", "menuitem");
      var menuHeading = currentClusterHeading;
      var thisGroupIndex = groupIndex;
      headingOption.textContent = menuHeading ? "Rename group heading" : "Name group";
      headingOption.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        closeAllFilterMenus();
        editFilterGroupHeading(thisGroupIndex, menuHeading);
      });
      menu.appendChild(headingOption);

      if (menuHeading) {
        var removeHeadingOption = document.createElement("button");
        removeHeadingOption.type = "button";
        removeHeadingOption.className = "tag-filter__menu-option";
        removeHeadingOption.setAttribute("role", "menuitem");
        removeHeadingOption.textContent = "Remove group heading";
        removeHeadingOption.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          closeAllFilterMenus();
          setFilterGroupHeadingAt(thisGroupIndex, "");
        });
        menu.appendChild(removeHeadingOption);
      }

      var separator2 = document.createElement("div");
      separator2.className = "tag-filter__menu-separator";
      separator2.setAttribute("role", "separator");
      menu.appendChild(separator2);

      var deleteOption = document.createElement("button");
      deleteOption.type = "button";
      deleteOption.className = "tag-filter__menu-option tag-filter__menu-option--danger";
      deleteOption.setAttribute("role", "menuitem");
      deleteOption.textContent = "Delete tag";
      deleteOption.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        closeAllFilterMenus();
        var tagLabel = item.name;
        if (!window.confirm('Delete tag "' + tagLabel + '" from all cards?')) {
          return;
        }
        deleteTagFromAllCards(tagLabel);
      });
      menu.appendChild(deleteOption);

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
      group.appendChild(menuWrap);
      clusterBody.appendChild(group);
    });

    if (viewMode === "notebook") {
      rebuildNotebookSidebar();
    }
  }

  function applyFilter() {
    qsa(".item-card[data-org-id]", phase2Root).forEach(function (card) {
      var tagOk = true;
      if (activeFilterTag) {
        var tags = [];
        try {
          tags = JSON.parse(card.getAttribute("data-tags") || "[]");
        } catch (err) {
          tags = [];
        }
        tagOk = normalizeTagList(tags).some(function (tag) {
          return tag.toLowerCase() === activeFilterTag.toLowerCase();
        });
      }
      card.hidden = !(tagOk && cardMatchesKind(card));
    });
  }

  function syncBucketCounts() {
    if (!organizedState) return;
    BUCKET_KEYS.forEach(function (bucket) {
      var el = qs('[data-bucket-count="' + bucket + '"]');
      if (!el) return;
      el.textContent = String((organizedState[bucket] || []).length);
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
    syncBucketCounts();
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

  function deleteTagFromAllCards(tagName) {
    var name = String(tagName || "").trim();
    if (!name || !organizedState) return;
    var key = name.toLowerCase();
    var changed = false;

    BUCKET_KEYS.forEach(function (bucket) {
      (organizedState[bucket] || []).forEach(function (item, index) {
        var tags = normalizeTagList(item.tags);
        var next = tags.filter(function (t) {
          return t.toLowerCase() !== key;
        });
        if (next.length === tags.length) return;
        item.tags = next;
        organizedState[bucket][index] = item;
        changed = true;

        var card = qs('[data-org-id="' + String(item.id).replace(/"/g, "") + '"]', phase2Root);
        if (card) {
          card.setAttribute("data-tags", JSON.stringify(next));
          renderTagChips(card, next);
        }
      });
    });

    if (!changed) return;

    if (activeFilterTag && activeFilterTag.toLowerCase() === key) {
      activeFilterTag = "";
    }
    rebuildFilterBar();
    applyFilter();
    scheduleSave();
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

    var notebookOrderDataEl = qs("#phase2-notebook-order-data");
    try {
      notebookOrderState = notebookOrderDataEl
        ? JSON.parse(notebookOrderDataEl.textContent || "{}")
        : {};
      if (!notebookOrderState || typeof notebookOrderState !== "object" || Array.isArray(notebookOrderState)) {
        notebookOrderState = {};
      }
    } catch (err) {
      notebookOrderState = {};
    }

    var suggestionsDataEl = qs("#phase2-suggestions-data");
    try {
      suggestionsState = normalizeSuggestionsState(
        suggestionsDataEl ? JSON.parse(suggestionsDataEl.textContent || "{}") : null
      );
    } catch (err) {
      suggestionsState = normalizeSuggestionsState(null);
    }

    BUCKET_KEYS.forEach(function (bucket) {
      organizedState[bucket] = (organizedState[bucket] || []).map(function (item) {
        return adaptItemForBucket(item, bucket);
      });
      syncEmptyState(bucket);
    });

    rebuildFilterBar();
    applyFilter();

    if (reviewLocked && notebookView) {
      setViewMode("panels");
    }

    phase2Root.addEventListener("click", function (e) {
      var viewModeBtn = e.target.closest("[data-view-mode-btn]");
      if (viewModeBtn) {
        setViewMode(viewModeBtn.getAttribute("data-view-mode-btn") || "panels");
        return;
      }

      var kindFilterBtn = e.target.closest("[data-kind-filter-btn]");
      if (kindFilterBtn) {
        setKindFilter(kindFilterBtn.getAttribute("data-kind-filter-btn") || "");
        return;
      }

      var folderRename = e.target.closest("[data-notebook-folder-rename]");
      if (folderRename) {
        e.preventDefault();
        beginNotebookFolderRename(folderRename.closest(".notebook-folder"));
        return;
      }

      if (e.target.closest("[data-notebook-folder-input]")) {
        return;
      }

      var folderToggleHit = e.target.closest("[data-notebook-folder-toggle]");
      if (folderToggleHit) {
        toggleNotebookFolder(folderToggleHit.closest(".notebook-folder"));
        return;
      }

      var notebookNav = e.target.closest(".notebook-nav[data-notebook-tag]");
      if (notebookNav) {
        if (notebookTagDrag.didDrag) {
          notebookTagDrag.didDrag = false;
          return;
        }
        var navTag = notebookNav.getAttribute("data-notebook-tag") || "";
        if (
          notebookNav.classList.contains("is-disabled") ||
          notebookNav.getAttribute("aria-disabled") === "true"
        ) {
          showNotebookKindEmptyToast(navTag);
          return;
        }
        openNotebook(
          navTag,
          notebookNav.getAttribute("data-notebook-folder-label") || ""
        );
        return;
      }

      var noteCollapse = e.target.closest("[data-notebook-note-collapse]");
      if (noteCollapse) {
        var noteEl = noteCollapse.closest(".notebook-note");
        if (!noteEl) return;
        if (notebookView && notebookView.classList.contains("is-rearranging")) {
          finishNotebookRearrange(null);
        }
        setNotebookNoteCollapsed(noteEl, !noteEl.classList.contains("is-collapsed"));
        var expanded = !noteEl.classList.contains("is-collapsed");
        noteCollapse.setAttribute("title", expanded ? "Collapse note" : "Expand note");
        noteCollapse.setAttribute("aria-label", expanded ? "Collapse note" : "Expand note");
        return;
      }

      var noteCopy = e.target.closest("[data-notebook-note-copy]");
      if (noteCopy) {
        var copyNoteEl = noteCopy.closest(".notebook-note");
        if (!copyNoteEl) return;
        var noteTitleEl = qs(".notebook-note__title", copyNoteEl);
        copyNote(
          noteTitleEl ? noteTitleEl.textContent : "",
          copyNoteEl.getAttribute("data-full-text") || ""
        ).then(function () {
          flashCopyButton(noteCopy);
        }).catch(function () {});
        return;
      }

      if (e.target.closest("[data-notebook-rearrange]")) {
        if (!activeNotebookTag) return;
        var turningOn = !(notebookView && notebookView.classList.contains("is-rearranging"));
        if (turningOn) {
          setNotebookRearrangeMode(true);
        } else {
          finishNotebookRearrange(null);
        }
        return;
      }

      if (e.target.closest("[data-notebook-copy]")) {
        copyNotebookAll().then(function () {
          flashCopyButton(notebookCopyBtn);
        }).catch(function () {});
        return;
      }

      var itemSuggestToggle = e.target.closest("[data-suggest-toggle]");
      if (itemSuggestToggle) {
        if (reviewLocked) return;
        if (itemSuggestToggle.closest(".is-rearranging")) return;
        var itemSuggestWrap = itemSuggestToggle.closest("[data-item-suggest]");
        var itemSuggestPanel = itemSuggestWrap
          ? qs("[data-suggest-panel]", itemSuggestWrap)
          : null;
        if (!itemSuggestPanel) return;
        var itemWillOpen = itemSuggestPanel.hidden;
        closeAllSuggestPanels(itemWillOpen ? itemSuggestPanel : null);
        itemSuggestPanel.hidden = !itemWillOpen;
        itemSuggestToggle.setAttribute("aria-expanded", itemWillOpen ? "true" : "false");
        if (itemWillOpen) {
          var itemInput = qs("[data-suggest-input]", itemSuggestPanel);
          if (itemInput) itemInput.focus();
        }
        return;
      }

      var panelSuggestToggle = e.target.closest("[data-panel-suggest-toggle]");
      if (panelSuggestToggle) {
        if (reviewLocked) return;
        var panelSection = panelSuggestToggle.closest("[data-section-bucket]");
        var panelSuggestPanel = panelSection
          ? qs("[data-panel-suggest-panel]", panelSection)
          : null;
        if (!panelSuggestPanel) return;
        var panelWillOpen = panelSuggestPanel.hidden;
        closeAllSuggestPanels(panelWillOpen ? panelSuggestPanel : null);
        panelSuggestPanel.hidden = !panelWillOpen;
        panelSuggestToggle.setAttribute("aria-expanded", panelWillOpen ? "true" : "false");
        if (panelWillOpen) {
          if (panelSection.classList.contains("is-collapsed")) {
            setCollapsed(panelSection, false);
          }
          var panelInput = qs("[data-panel-suggest-input]", panelSuggestPanel);
          if (panelInput) panelInput.focus();
        }
        return;
      }

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

      var copySectionBtn = e.target.closest("[data-copy-section]");
      if (copySectionBtn) {
        var copySection = copySectionBtn.closest("[data-section-bucket]");
        if (!copySection) return;
        copySectionVisibleNotes(copySection).then(function (didCopy) {
          if (didCopy) flashCopyButton(copySectionBtn);
        }).catch(function () {
          // clipboard unavailable
        });
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

      var checkBtn = e.target.closest("[data-check-item]");
      var titleEl = e.target.closest(".item-card__title-row > h3");
      if (checkBtn || titleEl) {
        if (reviewLocked) return;
        if ((checkBtn || titleEl).closest(".is-rearranging")) return;
        var checkCard = (checkBtn || titleEl).closest(".item-card[data-org-id]");
        if (!checkCard) return;
        var checkToggle = qs("[data-check-item]", checkCard);
        var nextChecked = checkToggle
          ? checkToggle.getAttribute("aria-pressed") !== "true"
          : checkCard.getAttribute("data-checked") !== "1";
        setItemChecked(checkCard.getAttribute("data-org-id"), nextChecked);
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

      var notebookNavDrag = e.target.closest(".notebook-nav[data-notebook-tag]");
      if (notebookNavDrag) {
        var dragTag = notebookNavDrag.getAttribute("data-notebook-tag") || "";
        var dragGroup = parseInt(
          notebookNavDrag.getAttribute("data-notebook-group") || "-1",
          10
        );
        if (!dragTag) {
          e.preventDefault();
          return;
        }
        notebookTagDrag.tag = dragTag;
        notebookTagDrag.fromGroup = isNaN(dragGroup) ? -1 : dragGroup;
        notebookTagDrag.didDrag = false;
        notebookNavDrag.classList.add("is-dragging");
        if (notebookFoldersEl) {
          notebookFoldersEl.classList.add("is-reordering-notebooks");
        }
        if (e.dataTransfer) {
          e.dataTransfer.effectAllowed = "move";
          e.dataTransfer.setData("text/plain", dragTag);
        }
        return;
      }

      var notebookNote = e.target.closest(".notebook-note[data-org-id]");
      if (notebookNote) {
        if (!notebookView || !notebookView.classList.contains("is-rearranging")) {
          e.preventDefault();
          return;
        }
        notebookDrag.note = notebookNote;
        notebookDrag.didReorder = false;
        notebookNote.classList.add("is-dragging");
        if (e.dataTransfer) {
          e.dataTransfer.effectAllowed = "move";
          e.dataTransfer.setData(
            "text/plain",
            notebookNote.getAttribute("data-org-id") || ""
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

      if (notebookTagDrag.tag) {
        var dropInfo = resolveNotebookTagDrop(e.clientY, e.target);
        if (!dropInfo) return;
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = "move";
        notebookTagDrag.didDrag = true;
        if (dropInfo.folder.classList.contains("is-collapsed")) {
          dropInfo.folder.classList.remove("is-collapsed");
          var expandToggle = qs(".notebook-folder__toggle", dropInfo.folder);
          if (expandToggle) expandToggle.setAttribute("aria-expanded", "true");
        }
        clearNotebookTagDropIndicators();
        dropInfo.folder.classList.add("is-drop-target");
        var dropList = qs(".notebook-folder__list", dropInfo.folder);
        if (dropList) {
          var dropNavs = qsa(
            ".notebook-nav[data-notebook-tag]:not(.is-dragging)",
            dropList
          );
          if (dropInfo.insertAt < dropNavs.length) {
            dropNavs[dropInfo.insertAt].classList.add("is-drop-before");
          } else if (dropNavs.length) {
            dropNavs[dropNavs.length - 1].classList.add("is-drop-after");
          }
        }
        return;
      }

      if (notebookDrag.note && notebookNotesEl) {
        if (!e.target.closest("[data-notebook-notes]")) return;
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = "move";
        var afterNote = getNotebookDragAfterElement(e.clientY);
        clearNotebookDropTargets();
        if (afterNote == null) {
          notebookNotesEl.appendChild(notebookDrag.note);
        } else if (afterNote !== notebookDrag.note) {
          notebookNotesEl.insertBefore(notebookDrag.note, afterNote);
          afterNote.classList.add("is-drop-target");
        }
        notebookDrag.didReorder = true;
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

      if (notebookTagDrag.tag) {
        var tagDrop = resolveNotebookTagDrop(e.clientY, e.target);
        var movedTag = notebookTagDrag.tag;
        notebookTagDrag.tag = "";
        notebookTagDrag.fromGroup = -1;
        clearNotebookTagDropIndicators();
        if (notebookFoldersEl) {
          notebookFoldersEl.classList.remove("is-reordering-notebooks");
        }
        qsa(".notebook-nav.is-dragging", notebookFoldersEl || phase2Root).forEach(function (el) {
          el.classList.remove("is-dragging");
        });
        if (!tagDrop) return;
        e.preventDefault();
        moveNotebookTag(movedTag, tagDrop.groupIndex, tagDrop.insertAt);
        return;
      }

      if (notebookDrag.note) {
        e.preventDefault();
        var settleNotebookNote = notebookDrag.note;
        finishNotebookRearrange(settleNotebookNote);
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

      if (notebookTagDrag.tag || notebookFoldersEl && notebookFoldersEl.classList.contains("is-reordering-notebooks")) {
        clearNotebookTagDropIndicators();
        if (notebookFoldersEl) {
          notebookFoldersEl.classList.remove("is-reordering-notebooks");
        }
        qsa(".notebook-nav.is-dragging", notebookFoldersEl || phase2Root).forEach(function (el) {
          el.classList.remove("is-dragging");
        });
        notebookTagDrag.tag = "";
        notebookTagDrag.fromGroup = -1;
        setTimeout(function () {
          notebookTagDrag.didDrag = false;
        }, 0);
        return;
      }

      if (notebookDrag.note) {
        var settleNb = notebookDrag.note;
        if (notebookView && notebookView.classList.contains("is-rearranging")) {
          finishNotebookRearrange(settleNb);
        } else {
          settleNb.classList.remove("is-dragging", "is-drop-target");
          notebookDrag.note = null;
        }
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
      if (
        !e.target.closest("[data-item-suggest]") &&
        !e.target.closest("[data-panel-suggest]") &&
        !e.target.closest("[data-panel-suggest-panel]")
      ) {
        closeAllSuggestPanels();
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        if (notebookFolderRename.input) {
          abortNotebookFolderRename();
          return;
        }
        closeAllMoveMenus();
        closeAllFilterMenus();
        closeSectionInfoTips();
        closeAllSuggestPanels();
        qsa("[data-section-bucket].is-rearranging", phase2Root).forEach(function (section) {
          finishRearrange(section, null);
        });
        if (notebookView && notebookView.classList.contains("is-rearranging")) {
          finishNotebookRearrange(null);
        }
      }
    });

    phase2Root.addEventListener("keydown", function (e) {
      var renameTarget = e.target.closest("[data-notebook-folder-rename]");
      if (!renameTarget) return;
      if (e.key !== "Enter" && e.key !== " ") return;
      e.preventDefault();
      beginNotebookFolderRename(renameTarget.closest(".notebook-folder"));
    });

    phase2Root.addEventListener("input", function (e) {
      var itemInput = e.target.closest("[data-suggest-input]");
      if (itemInput) {
        var itemCard = itemInput.closest(".item-card[data-org-id]");
        if (!itemCard) return;
        setItemSuggestion(itemCard.getAttribute("data-org-id"), itemInput.value);
        return;
      }
      var panelInput = e.target.closest("[data-panel-suggest-input]");
      if (panelInput) {
        setPanelSuggestion(panelInput.getAttribute("data-panel-suggest-input"), panelInput.value);
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

  function jumpToBucketSection(bucket) {
    var section = qs('[data-section-bucket="' + bucket.replace(/"/g, "") + '"]');
    if (!section) return;
    var wasCollapsed = section.classList.contains("is-collapsed");
    if (wasCollapsed) {
      section.classList.remove("is-collapsed");
      var toggle = qs("[data-collapse-toggle]", section);
      if (toggle) {
        toggle.setAttribute("aria-expanded", "true");
      }
    }
    function scroll() {
      section.scrollIntoView({ behavior: "smooth", block: "start" });
    }
    if (wasCollapsed) {
      setTimeout(scroll, 280);
    } else {
      scroll();
    }
  }

  var bucketCountsBar = qs("[data-bucket-counts]");
  if (bucketCountsBar) {
    function closeCountMenus(except) {
      qsa("[data-count-menu].is-open", bucketCountsBar).forEach(function (menu) {
        if (except && menu === except) return;
        menu.classList.remove("is-open");
        var toggle = qs("[data-count-menu-toggle]", menu);
        if (toggle) toggle.setAttribute("aria-expanded", "false");
      });
    }

    function scrollToJumpTarget(id) {
      var target = document.getElementById(id);
      if (!target) return false;
      var header = qs(".app-header");
      var counts = qs("[data-bucket-counts]");
      var offset = (header ? header.offsetHeight : 0) + (counts ? counts.offsetHeight : 0) + 10;
      var top = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
      target.classList.remove("is-jump-flash");
      void target.offsetWidth;
      target.classList.add("is-jump-flash");
      return true;
    }

    bucketCountsBar.addEventListener("click", function (e) {
      var toggle = e.target.closest("[data-count-menu-toggle]");
      if (toggle && bucketCountsBar.contains(toggle)) {
        var menu = toggle.closest("[data-count-menu]");
        var willOpen = menu && !menu.classList.contains("is-open");
        closeCountMenus(willOpen ? menu : null);
        if (menu && willOpen) {
          menu.classList.add("is-open");
          toggle.setAttribute("aria-expanded", "true");
        }
        return;
      }

      var jump = e.target.closest("[data-count-jump]");
      if (jump && bucketCountsBar.contains(jump)) {
        var id = jump.getAttribute("data-count-jump") || "";
        if (id && scrollToJumpTarget(id)) {
          e.preventDefault();
          closeCountMenus();
          jump.blur();
        }
        return;
      }

      var btn = e.target.closest("[data-jump-bucket]");
      if (!btn || !bucketCountsBar.contains(btn)) return;
      var bucket = btn.getAttribute("data-jump-bucket");
      if (!bucket) return;
      jumpToBucketSection(bucket);
    });

    document.addEventListener("click", function (e) {
      if (!e.target.closest("[data-count-menu]")) closeCountMenus();
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeCountMenus();
    });
  }

  // Phase 1 blocked-URL paste panels + Suggest notes
  (function initPhase1Panels() {
    var root = qs("[data-phase1-root]");
    if (!root) return;

    var phase1SuggestTimer = null;
    var phase1SuggestState = { shorthands: {}, enrichments: {} };

    function normalizePhase1SuggestState(data) {
      var next = { shorthands: {}, enrichments: {} };
      if (!data || typeof data !== "object") return next;
      if (data.shorthands && typeof data.shorthands === "object") {
        Object.keys(data.shorthands).forEach(function (token) {
          var text = String(data.shorthands[token] || "").trim();
          if (!token || !text) return;
          next.shorthands[token] = text;
        });
      }
      if (data.enrichments && typeof data.enrichments === "object") {
        Object.keys(data.enrichments).forEach(function (id) {
          var text = String(data.enrichments[id] || "").trim();
          if (!id || !text) return;
          next.enrichments[id] = text;
        });
      }
      return next;
    }

    try {
      var dataEl = qs("#phase1-suggestions-data");
      phase1SuggestState = normalizePhase1SuggestState(
        dataEl ? JSON.parse(dataEl.textContent || "{}") : null
      );
    } catch (err) {
      phase1SuggestState = normalizePhase1SuggestState(null);
    }

    function findShorthandTokenKey(token) {
      var fold = String(token || "").trim().toLowerCase();
      if (!fold) return "";
      var keys = Object.keys(phase1SuggestState.shorthands);
      for (var i = 0; i < keys.length; i++) {
        if (String(keys[i]).toLowerCase() === fold) return keys[i];
      }
      return String(token || "").trim();
    }

    function shorthandInputsForToken(token) {
      var fold = String(token || "").trim().toLowerCase();
      return qsa('[data-phase1-suggest-input][data-phase1-suggest-kind="shorthand"]', root).filter(
        function (el) {
          return String(el.getAttribute("data-phase1-suggest-key") || "").toLowerCase() === fold;
        }
      );
    }

    function syncSuggestInputValue(input, text) {
      if (!input || input === document.activeElement) return;
      if (input.value !== text) input.value = text;
    }

    function syncShorthandUi(token, text) {
      var has = !!String(text || "").trim();
      shorthandInputsForToken(token).forEach(function (input) {
        syncSuggestInputValue(input, text);
        var wrap = input.closest("[data-phase1-shorthand-wrap]");
        if (wrap) wrap.classList.toggle("has-phase1-suggestion", has);
      });
    }

    function syncEnrichmentUi(key, text) {
      var has = !!String(text || "").trim();
      qsa(
        '[data-phase1-suggest-input][data-phase1-suggest-kind="enrichment"][data-phase1-suggest-key="' +
          String(key).replace(/"/g, "") +
          '"]',
        root
      ).forEach(function (input) {
        syncSuggestInputValue(input, text);
      });
      qsa('[data-phase1-enrichment-wrap="' + String(key).replace(/"/g, "") + '"]', root).forEach(
        function (wrap) {
          wrap.classList.toggle("has-phase1-suggestion", has);
        }
      );
    }

    function savePhase1Suggestions() {
      fetch("index.php?action=save-phase1-suggestions", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(phase1SuggestState),
      }).catch(function () {
        // local save failure — UI state still updated
      });
    }

    function schedulePhase1SuggestSave() {
      if (phase1SuggestTimer) clearTimeout(phase1SuggestTimer);
      phase1SuggestTimer = setTimeout(savePhase1Suggestions, 300);
    }

    function setShorthandSuggestion(token, text) {
      var key = String(token || "").trim();
      if (!key) return;
      var trimmed = String(text || "").trim();
      var storedKey = findShorthandTokenKey(key);
      Object.keys(phase1SuggestState.shorthands).forEach(function (existing) {
        if (String(existing).toLowerCase() === key.toLowerCase()) {
          delete phase1SuggestState.shorthands[existing];
        }
      });
      if (trimmed) {
        phase1SuggestState.shorthands[storedKey || key] = trimmed;
      }
      syncShorthandUi(key, String(text || ""));
      schedulePhase1SuggestSave();
    }

    function setEnrichmentSuggestion(id, text) {
      if (!id) return;
      var trimmed = String(text || "").trim();
      if (trimmed) {
        phase1SuggestState.enrichments[id] = trimmed;
      } else {
        delete phase1SuggestState.enrichments[id];
      }
      syncEnrichmentUi(id, String(text || ""));
      schedulePhase1SuggestSave();
    }

    function closePhase1SuggestPanels(except) {
      qsa("[data-phase1-suggest-panel]", root).forEach(function (panel) {
        if (except && panel === except) return;
        panel.hidden = true;
        var wrap = panel.closest("[data-phase1-suggest]");
        var toggle = wrap ? qs("[data-phase1-suggest-toggle]", wrap) : null;
        if (toggle) toggle.setAttribute("aria-expanded", "false");
      });
    }

    function syncBlockUi(block, hasPaste) {
      block.classList.toggle("has-paste", !!hasPaste);
      var status = qs("[data-url-status]", block);
      if (status) {
        status.textContent = hasPaste ? "Saved — return to chat" : "Needs paste";
      }
    }

    function saveUrlPaste(block) {
      var file = block.getAttribute("data-url-file") || "";
      var line = parseInt(block.getAttribute("data-url-line") || "0", 10);
      var noteEl = qs("[data-url-note]", block);
      var pasteEl = qs("[data-url-paste]", block);
      var saveBtn = qs("[data-url-save]", block);
      var savedEl = qs("[data-url-saved]", block);
      var userNote = noteEl ? noteEl.value : "";
      var userPaste = pasteEl ? pasteEl.value : "";
      if (saveBtn) saveBtn.disabled = true;

      fetch("index.php?action=save-url-paste", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          file: file,
          line: line,
          userPaste: userPaste,
          userNote: userNote,
        }),
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.data || !result.data.ok) {
            throw new Error(
              (result.data && result.data.error) || "Save failed"
            );
          }
          var hasPaste =
            String(userPaste || "").trim() !== "" ||
            String(userNote || "").trim() !== "";
          syncBlockUi(block, hasPaste);
          if (savedEl) {
            savedEl.hidden = false;
            setTimeout(function () {
              savedEl.hidden = true;
            }, 1800);
          }
        })
        .catch(function () {
          if (savedEl) {
            savedEl.hidden = false;
            savedEl.textContent = "Save failed — try again";
            setTimeout(function () {
              savedEl.hidden = true;
              savedEl.textContent = "Saved to phase1.json";
            }, 2200);
          }
        })
        .finally(function () {
          if (saveBtn) saveBtn.disabled = false;
        });
    }

    root.addEventListener("click", function (e) {
      if (handlePhase1RegionClick(e)) return;

      var suggestToggle = e.target.closest("[data-phase1-suggest-toggle]");
      if (suggestToggle && root.contains(suggestToggle)) {
        var suggestWrap = suggestToggle.closest("[data-phase1-suggest]");
        var suggestPanel = suggestWrap
          ? qs("[data-phase1-suggest-panel]", suggestWrap)
          : null;
        if (!suggestPanel) return;
        var willOpen = suggestPanel.hidden;
        closePhase1SuggestPanels(willOpen ? suggestPanel : null);
        suggestPanel.hidden = !willOpen;
        suggestToggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
        if (willOpen) {
          var input = qs("[data-phase1-suggest-input]", suggestPanel);
          if (input) input.focus();
        }
        return;
      }

      var btn = e.target.closest("[data-url-save]");
      if (!btn || !root.contains(btn)) return;
      var block = btn.closest("[data-url-block]");
      if (!block) return;
      saveUrlPaste(block);
    });

    root.addEventListener("input", function (e) {
      var input = e.target.closest("[data-phase1-suggest-input]");
      if (!input || !root.contains(input)) return;
      var kind = input.getAttribute("data-phase1-suggest-kind") || "";
      var key = input.getAttribute("data-phase1-suggest-key") || "";
      if (kind === "shorthand") {
        setShorthandSuggestion(key, input.value);
      } else if (kind === "enrichment") {
        setEnrichmentSuggestion(key, input.value);
      }
    });

    document.addEventListener("click", function (e) {
      if (!e.target.closest("[data-phase1-suggest]")) {
        closePhase1SuggestPanels();
      }
    });

    initPhase1Minimap();
    initPhase1Regions();
  })();

  function phase1Hue(id, previous) {
    var hues = [199, 152, 42, 270, 330, 12, 88, 230];
    var hue = hues[(id - 1) % hues.length];
    if (previous !== null && hue === previous) {
      hue = hues[id % hues.length];
    }
    return hue;
  }

  function phase1PreviewFrom(row) {
    if (!row) return "";
    var texts = qsa(".line__text", row);
    var text = "";
    for (var i = 0; i < texts.length; i++) {
      var line = texts[i].closest(".line");
      if (line && line.classList.contains("line--added")) continue;
      text = texts[i].textContent || "";
      break;
    }
    text = text.replace(/\s+/g, " ").trim();
    if (text.length > 84) {
      text = text.slice(0, 83).replace(/\s+$/, "") + "…";
    }
    return text;
  }

  function phase1ThoughtHeader() {
    var header = document.createElement("header");
    header.className = "thought__head";
    var index = document.createElement("span");
    index.className = "thought__index";
    var range = document.createElement("span");
    range.className = "thought__range";
    var preview = document.createElement("span");
    preview.className = "thought__preview";
    var actions = document.createElement("div");
    actions.className = "thought__actions";
    function action(label, attr, value) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "thought__action";
      button.textContent = label;
      button.setAttribute(attr, value);
      return button;
    }
    actions.appendChild(action("Split", "data-thought-split", ""));
    actions.appendChild(action("Merge up", "data-thought-merge", "prev"));
    actions.appendChild(action("Merge down", "data-thought-merge", "next"));
    header.appendChild(index);
    header.appendChild(range);
    header.appendChild(preview);
    header.appendChild(actions);
    return header;
  }

  function phase1Sections(doc) {
    return qsa("section.thought", doc || document);
  }

  function phase1NewThought() {
    var section = document.createElement("section");
    section.className = "thought";
    section.setAttribute("data-thought", "0");
    section.appendChild(phase1ThoughtHeader());
    return section;
  }

  function phase1Summary(count) {
    var el = qs("[data-thought-summary]");
    if (!el) return;
    if (count === 0) {
      el.textContent = "No note text in this file yet. The map is this whole file.";
      return;
    }
    var mode = el.getAttribute("data-thought-mode") || "dashes";
    var noun = count === 1 ? "note" : "notes";
    var how = mode === "dashes"
      ? "split on a line of dashes (---)"
      : "split on blank lines";
    var text = count + " distinct " + noun + ", " + how + ".";
    if (el.getAttribute("data-thought-edited") === "1") {
      text += " Your boundary edits are saved.";
    }
    text += " The map is this whole file.";
    el.textContent = text;
  }

  function paintPhase1Thoughts() {
    var doc = qs("[data-phase1-lines]");
    if (!doc) return [];
    var sections = phase1Sections(doc);
    var previous = null;
    sections.forEach(function (section, i) {
      var id = i + 1;
      var hue = phase1Hue(id, previous);
      previous = hue;
      section.style.setProperty("--thought-hue", String(hue));
      section.setAttribute("data-thought", String(id));
      section.id = "thought-" + id;
      var rows = qsa(".thought-row", section);
      var start = rows.length ? rows[0].getAttribute("data-source-line") || "" : "";
      var end = rows.length ? rows[rows.length - 1].getAttribute("data-source-line") || "" : "";
      var indexEl = qs(".thought__index", section);
      if (indexEl) indexEl.textContent = "Note " + id;
      var rangeEl = qs(".thought__range", section);
      if (rangeEl) {
        rangeEl.textContent = start === end ? "line " + start : "lines " + start + "–" + end;
      }
      var previewEl = qs(".thought__preview", section);
      if (previewEl) previewEl.textContent = phase1PreviewFrom(rows[0]);
      var splitBtn = qs("[data-thought-split]", section);
      if (splitBtn) {
        splitBtn.disabled = rows.length < 2;
        splitBtn.textContent = "Split";
        splitBtn.setAttribute("aria-pressed", "false");
      }
      var up = qs('[data-thought-merge="prev"]', section);
      var down = qs('[data-thought-merge="next"]', section);
      if (up) up.disabled = i === 0;
      if (down) down.disabled = i === sections.length - 1;
      section.classList.remove("is-splitting");
    });
    phase1Summary(sections.length);
    document.dispatchEvent(new CustomEvent("phase1-regions-changed"));
    return sections;
  }

  function clearPhase1SplitMode(except) {
    var doc = qs("[data-phase1-lines]");
    if (!doc) return;
    qsa(".thought.is-splitting", doc).forEach(function (section) {
      if (except && section === except) return;
      section.classList.remove("is-splitting");
      var button = qs("[data-thought-split]", section);
      if (!button) return;
      button.textContent = "Split";
      button.setAttribute("aria-pressed", "false");
    });
  }

  var phase1RegionSaveTimer = 0;

  function savePhase1Regions() {
    var doc = qs("[data-phase1-lines]");
    if (!doc) return;
    var file = doc.getAttribute("data-phase1-file") || "";
    var starts = phase1Sections(doc).map(function (section) {
      var row = qs(".thought-row", section);
      return row ? parseInt(row.getAttribute("data-source-line") || "0", 10) : 0;
    }).filter(function (n) { return n > 0; });
    fetch("index.php?action=save-phase1-regions", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ file: file, starts: starts }),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        var summary = qs("[data-thought-summary]");
        if (!summary) return;
        if (!data || !data.ok) {
          summary.setAttribute("data-thought-edited", "0");
          phase1Summary(phase1Sections(doc).length);
          summary.textContent = summary.textContent.replace(
            " The map is this whole file.",
            " Could not save boundary edits. The map is this whole file."
          );
          return;
        }
        summary.setAttribute("data-thought-edited", data.edited ? "1" : "0");
        phase1Summary(phase1Sections(doc).length);
      })
      .catch(function () {
        var summary = qs("[data-thought-summary]");
        if (!summary || !doc) return;
        phase1Summary(phase1Sections(doc).length);
        summary.textContent = summary.textContent.replace(
          " The map is this whole file.",
          " Could not save boundary edits. The map is this whole file."
        );
      });
  }

  function commitPhase1Regions() {
    paintPhase1Thoughts();
    clearTimeout(phase1RegionSaveTimer);
    phase1RegionSaveTimer = setTimeout(savePhase1Regions, 120);
  }

  function handlePhase1RegionClick(e) {
    var doc = qs("[data-phase1-lines]");
    if (!doc || !doc.contains(e.target)) return false;

    var splitAt = e.target.closest("[data-thought-split-at]");
    if (splitAt && doc.contains(splitAt)) {
      e.preventDefault();
      var row = splitAt.closest(".thought-row");
      var section = row ? row.closest("[data-thought]") : null;
      if (!row || !section || !section.classList.contains("is-splitting")) return true;
      var rows = qsa(".thought-row", section);
      if (rows.indexOf(row) <= 0) return true;
      var created = phase1NewThought();
      section.after(created);
      for (var i = rows.indexOf(row); i < rows.length; i++) {
        created.appendChild(rows[i]);
      }
      commitPhase1Regions();
      return true;
    }

    var splitBtn = e.target.closest("[data-thought-split]");
    if (splitBtn && doc.contains(splitBtn)) {
      e.preventDefault();
      if (splitBtn.disabled) return true;
      var splitSection = splitBtn.closest("[data-thought]");
      if (!splitSection) return true;
      var opening = !splitSection.classList.contains("is-splitting");
      clearPhase1SplitMode(null);
      if (opening) {
        splitSection.classList.add("is-splitting");
        splitBtn.textContent = "Cancel";
        splitBtn.setAttribute("aria-pressed", "true");
      }
      return true;
    }

    var mergeBtn = e.target.closest("[data-thought-merge]");
    if (mergeBtn && doc.contains(mergeBtn)) {
      e.preventDefault();
      if (mergeBtn.disabled) return true;
      var mergeSection = mergeBtn.closest("[data-thought]");
      if (!mergeSection) return true;
      var dir = mergeBtn.getAttribute("data-thought-merge") || "";
      var sections = phase1Sections(doc);
      var index = sections.indexOf(mergeSection);
      var other = dir === "prev" ? sections[index - 1] : sections[index + 1];
      if (!other) return true;
      var target = dir === "prev" ? other : mergeSection;
      var source = dir === "prev" ? mergeSection : other;
      qsa(".thought-row", source).forEach(function (sourceRow) {
        target.appendChild(sourceRow);
      });
      source.remove();
      commitPhase1Regions();
      return true;
    }

    return false;
  }

  function initPhase1Regions() {
    var doc = qs("[data-phase1-lines]");
    if (!doc) return;
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (!qs(".thought.is-splitting", doc)) return;
      clearPhase1SplitMode(null);
    });
    paintPhase1Thoughts();
  }

  function initPhase1Minimap() {
    var map = qs("[data-phase1-minimap]");
    var track = map ? qs("[data-minimap-track]", map) : null;
    var viewport = map ? qs("[data-minimap-viewport]", map) : null;
    var doc = qs("[data-phase1-lines]");
    if (!map || !track || !viewport || !doc) return;

    var notes = phase1Sections(doc);
    var buttons = qsa("[data-minimap-note]", map);
    var dragging = false;
    var dragOffset = 0;

    function syncMinimapNotes() {
      notes = phase1Sections(doc);
      qsa("[data-minimap-note]", map).forEach(function (btn) {
        btn.remove();
      });
      notes.forEach(function (section) {
        var id = section.getAttribute("data-thought") || "";
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "minimap__note";
        btn.setAttribute("data-minimap-note", id);
        var hue = section.style.getPropertyValue("--thought-hue").trim();
        if (hue) btn.style.setProperty("--thought-hue", hue);
        if (qs(".line--uncertain", section)) btn.classList.add("has-uncertain");
        if (qs(".line--added", section)) btn.classList.add("has-added");
        if (qs(".url-block", section)) btn.classList.add("has-blocked");
        var indexText = qs(".thought__index", section);
        var rangeText = qs(".thought__range", section);
        var previewText = qs(".thought__preview", section);
        var tip = [indexText, rangeText, previewText].map(function (el) {
          return el ? (el.textContent || "").trim() : "";
        }).filter(Boolean).join(" · ");
        btn.title = tip;
        btn.setAttribute("aria-label", tip || ("Note " + id));
        var label = document.createElement("span");
        label.className = "minimap__note-label";
        label.textContent = id;
        btn.appendChild(label);
        track.insertBefore(btn, viewport);
      });
      buttons = qsa("[data-minimap-note]", map);
      var count = qs(".minimap__title span", map);
      if (count) count.textContent = String(notes.length);
    }

    function chromeOffset() {
      var header = qs(".app-header");
      var counts = qs("[data-bucket-counts]");
      return (header ? header.offsetHeight : 0) + (counts ? counts.offsetHeight : 0) + 10;
    }

    function layoutBlocks() {
      var docH = doc.offsetHeight;
      var trackH = track.clientHeight;
      var docRect = doc.getBoundingClientRect();
      if (docH <= 0 || trackH <= 0) return;
      buttons.forEach(function (btn) {
        var id = btn.getAttribute("data-minimap-note") || "";
        var section = qs('[data-thought="' + id.replace(/"/g, "") + '"]', doc);
        if (!section) return;
        var rect = section.getBoundingClientRect();
        var px = Math.max(2, (rect.height / docH) * trackH);
        btn.style.position = "absolute";
        btn.style.left = "0";
        btn.style.right = "0";
        btn.style.flex = "none";
        btn.style.top = ((rect.top - docRect.top) / docH) * trackH + "px";
        btn.style.height = px + "px";
        btn.classList.toggle("is-labeled", px >= 12);
      });
    }

    function placeViewport() {
      var trackH = track.clientHeight;
      var docH = doc.offsetHeight;
      if (trackH <= 0 || docH <= 0) return;
      var size = Math.min(1, window.innerHeight / docH);
      var start = (window.scrollY - contentTop()) / docH;
      if (start < 0) start = 0;
      if (start > 1 - size) start = 1 - size;
      viewport.hidden = false;
      viewport.style.height = Math.max(14, size * trackH) + "px";
      viewport.style.top = start * trackH + "px";
    }

    function contentTop() {
      return doc.getBoundingClientRect().top + window.scrollY;
    }

    function markCurrent() {
      var focusY = chromeOffset() + 8;
      var current = "";
      notes.forEach(function (el) {
        if (current) return;
        var rect = el.getBoundingClientRect();
        if (rect.top <= focusY && rect.bottom > focusY) {
          current = el.getAttribute("data-thought") || "";
        }
      });
      if (!current && notes.length) {
        current = notes[0].getBoundingClientRect().top > focusY
          ? notes[0].getAttribute("data-thought") || ""
          : notes[notes.length - 1].getAttribute("data-thought") || "";
      }
      notes.forEach(function (el) {
        el.classList.toggle("is-current", (el.getAttribute("data-thought") || "") === current);
      });
      buttons.forEach(function (el) {
        var on = (el.getAttribute("data-minimap-note") || "") === current;
        el.classList.toggle("is-current", on);
        if (on) el.setAttribute("aria-current", "true");
        else el.removeAttribute("aria-current");
      });
    }

    function refresh() {
      layoutBlocks();
      placeViewport();
      markCurrent();
    }

    track.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-minimap-note]");
      if (!btn || !track.contains(btn)) return;
      var id = btn.getAttribute("data-minimap-note") || "";
      var section = qs('[data-thought="' + id.replace(/"/g, "") + '"]', doc);
      if (!section) return;
      var top = section.getBoundingClientRect().top + window.scrollY - chromeOffset();
      window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
    });

    viewport.addEventListener("mousedown", function (e) {
      dragging = true;
      dragOffset = e.clientY - viewport.getBoundingClientRect().top;
      viewport.classList.add("is-dragging");
      e.preventDefault();
    });

    document.addEventListener("mousemove", function (e) {
      if (!dragging) return;
      var rect = track.getBoundingClientRect();
      var y = e.clientY - rect.top - dragOffset;
      var ratio = rect.height > 0 ? y / rect.height : 0;
      var target = contentTop() + ratio * doc.offsetHeight;
      window.scrollTo(0, Math.max(0, target));
    });

    document.addEventListener("mouseup", function () {
      if (!dragging) return;
      dragging = false;
      viewport.classList.remove("is-dragging");
    });

    doc.addEventListener("click", function () {
      requestAnimationFrame(refresh);
    });
    window.addEventListener("scroll", function () {
      placeViewport();
      markCurrent();
    }, { passive: true });
    document.addEventListener("phase1-regions-changed", function () {
      syncMinimapNotes();
      refresh();
    });
    window.addEventListener("resize", refresh);
    refresh();
  }

  // --- Project switcher ---
  (function initProjects() {
    var menu = qs("[data-projects-menu]");
    var toggle = qs("[data-projects-toggle]");
    var panel = qs("[data-projects-panel]");

    function setProjectsOpen(open) {
      if (!toggle || !panel) return;
      panel.hidden = !open;
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    }

    if (toggle && panel) {
      toggle.addEventListener("click", function (e) {
        e.stopPropagation();
        var willOpen = panel.hidden;
        if (willOpen) {
          var snapPanel = qs("[data-snapshots-panel]");
          var snapToggle = qs("[data-snapshots-toggle]");
          if (snapPanel) snapPanel.hidden = true;
          if (snapToggle) snapToggle.setAttribute("aria-expanded", "false");
        }
        setProjectsOpen(willOpen);
      });
    }

    document.addEventListener("click", function (e) {
      var button = e.target.closest("[data-switch-project]");
      if (button) {
        e.preventDefault();
        var name = button.getAttribute("data-switch-project") || "";
        var current = menu ? menu.getAttribute("data-current-project") || "" : "";
        var onSnapshot = /[?&]snapshot=/.test(window.location.search);
        if (name !== "" && name === current && !onSnapshot) {
          setProjectsOpen(false);
          return;
        }
        button.disabled = true;
        fetch("index.php?action=switch-project", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ folder: name }),
        })
          .then(function (res) {
            return res.json().then(function (data) {
              if (!res.ok || !data || !data.ok) {
                throw new Error((data && data.error) || "Could not switch project");
              }
              window.location.href = "index.php";
            });
          })
          .catch(function (err) {
            button.disabled = false;
            showAppToast(err && err.message ? err.message : "Could not switch project");
          });
        return;
      }
      if (!menu || !e.target.closest("[data-projects-menu]")) {
        setProjectsOpen(false);
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") setProjectsOpen(false);
    });
  })();

  // --- Phase 3 snapshots ---
  (function initSnapshots() {
    var menu = qs("[data-snapshots-menu]");
    var toggle = qs("[data-snapshots-toggle]");
    var panel = qs("[data-snapshots-panel]");
    var manageBtn = qs("[data-snapshots-manage]");
    var manageLabel = qs("[data-snapshots-manage-label]");
    var saveBtn = qs("[data-save-snapshot]");
    var dialog = qs("[data-snapshot-dialog]");
    var dialogForm = qs("[data-snapshot-dialog-form]");
    var dialogTitle = qs("[data-snapshot-dialog-title]");
    var dialogMessage = qs("[data-snapshot-dialog-message]");
    var dialogNameField = qs("[data-snapshot-dialog-name-field]");
    var dialogName = qs("[data-snapshot-dialog-name]");
    var dialogSubmit = qs("[data-snapshot-dialog-submit]");
    var dialogAction = null;

    function setSnapshotsOpen(open) {
      if (!toggle || !panel) return;
      panel.hidden = !open;
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      if (!open && manageBtn) {
        panel.classList.remove("is-managing");
        manageBtn.setAttribute("aria-pressed", "false");
        if (manageLabel) manageLabel.textContent = "Manage";
      }
    }

    if (toggle && panel) {
      toggle.addEventListener("click", function (e) {
        e.stopPropagation();
        var willOpen = panel.hidden;
        if (willOpen) {
          var projectPanel = qs("[data-projects-panel]");
          var projectToggle = qs("[data-projects-toggle]");
          if (projectPanel) projectPanel.hidden = true;
          if (projectToggle) projectToggle.setAttribute("aria-expanded", "false");
        }
        setSnapshotsOpen(willOpen);
      });
      document.addEventListener("click", function (e) {
        if (!menu || !e.target.closest("[data-snapshots-menu]")) {
          setSnapshotsOpen(false);
        }
      });
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") setSnapshotsOpen(false);
      });
    }

    if (manageBtn && panel) {
      manageBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        var managing = panel.classList.toggle("is-managing");
        manageBtn.setAttribute("aria-pressed", managing ? "true" : "false");
        if (manageLabel) manageLabel.textContent = managing ? "Done" : "Manage";
      });
    }

    function updateSnapshot(url, payload) {
      return fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      }).then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data || !data.ok) {
            throw new Error((data && data.error) || "Snapshot update failed");
          }
          return data;
        });
      });
    }

    function closeSnapshotDialog() {
      if (!dialog) return;
      dialog.hidden = true;
      dialogAction = null;
    }

    function openSnapshotDialog(action, id, name) {
      if (!dialog || !dialogForm || !dialogTitle || !dialogMessage || !dialogSubmit) return;
      dialogAction = { action: action, id: id, name: name };
      var isDelete = action === "delete";
      dialogTitle.textContent = isDelete ? "Delete snapshot?" : "Rename snapshot";
      dialogMessage.textContent = isDelete
        ? 'Delete "' + name + '"? This cannot be undone.'
        : "Choose a new name for this snapshot.";
      dialogNameField.hidden = isDelete;
      dialogName.required = !isDelete;
      dialogName.value = isDelete ? "" : name;
      dialogSubmit.textContent = isDelete ? "Delete" : "Rename";
      dialogSubmit.classList.toggle("app-header__btn--danger", isDelete);
      dialog.hidden = false;
      if (!isDelete) {
        dialogName.focus();
        dialogName.select();
      } else {
        dialogSubmit.focus();
      }
    }

    if (dialog) {
      qsa("[data-snapshot-dialog-cancel]", dialog).forEach(function (btn) {
        btn.addEventListener("click", closeSnapshotDialog);
      });
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && !dialog.hidden) closeSnapshotDialog();
      });
      dialogForm.addEventListener("submit", function (e) {
        e.preventDefault();
        if (!dialogAction) return;
        var isDelete = dialogAction.action === "delete";
        var nextName = String(dialogName.value || "").trim();
        if (!isDelete && !nextName) {
          showAppToast("Enter a snapshot name.");
          dialogName.focus();
          return;
        }
        dialogSubmit.disabled = true;
        updateSnapshot(
          "index.php?action=" + (isDelete ? "delete-snapshot" : "rename-snapshot"),
          isDelete ? { id: dialogAction.id } : { id: dialogAction.id, name: nextName }
        )
          .then(function () {
            showAppToast(isDelete ? "Snapshot deleted" : "Snapshot renamed", { saved: true });
            var wasCurrentSnapshot = new URL(window.location.href).searchParams.get("snapshot") === dialogAction.id;
            window.setTimeout(function () {
              window.location.href = wasCurrentSnapshot && isDelete ? "index.php" : window.location.href;
            }, 500);
          })
          .catch(function (err) {
            showAppToast(err && err.message ? err.message : (isDelete ? "Delete failed" : "Rename failed"));
          })
          .then(function () { dialogSubmit.disabled = false; });
      });
    }

    if (panel) {
      panel.addEventListener("click", function (e) {
        var renameBtn = e.target.closest("[data-rename-snapshot]");
        var deleteBtn = e.target.closest("[data-delete-snapshot]");
        var btn = renameBtn || deleteBtn;
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var id = String(btn.getAttribute("data-snapshot-id") || "");
        var name = String(btn.getAttribute("data-snapshot-name") || "Snapshot");
        if (!id) return;
        openSnapshotDialog(renameBtn ? "rename" : "delete", id, name);
      });
    }

    if (saveBtn) {
      saveBtn.addEventListener("click", function () {
        var defaultName = "";
        var meta = qs(".app-header__meta");
        if (meta) {
          var m = meta.textContent.match(/inputs\/([^\s·]+)/);
          if (m) defaultName = m[1];
        }
        var name = window.prompt("Save snapshot as (name required)", defaultName || "");
        if (name === null) return;
        name = String(name || "").trim();
        if (!name) {
          showAppToast("Enter a snapshot name.");
          return;
        }
        saveBtn.disabled = true;
        fetch("index.php?action=save-snapshot", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ name: name }),
        })
          .then(function (res) {
            return res.json().then(function (data) {
              return { ok: res.ok, data: data };
            });
          })
          .then(function (result) {
            if (!result.ok || !result.data || !result.data.ok) {
              throw new Error(
                (result.data && result.data.error) || "Save failed"
              );
            }
            var snap = result.data.snapshot || {};
            showAppToast(
              "Saved " + (snap.path || "snapshot") + " — open anytime via Snapshots",
              { saved: true }
            );
            window.setTimeout(function () {
              window.location.reload();
            }, 700);
          })
          .catch(function (err) {
            showAppToast(err && err.message ? err.message : "Save failed");
          })
          .then(function () {
            saveBtn.disabled = false;
          });
      });
    }
  })();
})();
