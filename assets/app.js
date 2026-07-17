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
    fetch("index.php?action=save-phase2", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ organized: organizedState }),
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
    tags.sort(function (a, b) {
      return a.localeCompare(b, undefined, { sensitivity: "base" });
    });
    return tags;
  }

  function rebuildFilterBar() {
    var bar = qs("[data-tag-filter]", phase2Root);
    if (!bar) return;
    var tags = collectAllTags();
    bar.innerHTML = "";
    if (!tags.length) {
      bar.hidden = true;
      activeFilterTag = "";
      return;
    }
    bar.hidden = false;

    var label = document.createElement("span");
    label.className = "tag-filter__label";
    label.textContent = "Filter";
    bar.appendChild(label);

    function addChip(value, text) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "tag-filter__chip";
      btn.setAttribute("data-filter-tag", value);
      btn.textContent = text;
      var active = value === activeFilterTag;
      btn.classList.toggle("is-active", active);
      btn.setAttribute("aria-pressed", active ? "true" : "false");
      btn.addEventListener("click", function () {
        activeFilterTag = value;
        rebuildFilterBar();
        applyFilter();
      });
      bar.appendChild(btn);
    }

    if (activeFilterTag && tags.every(function (t) {
      return t.toLowerCase() !== activeFilterTag.toLowerCase();
    })) {
      activeFilterTag = "";
    }

    addChip("", "All");
    tags.forEach(function (tag) {
      addChip(tag, tag);
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
    toggle.className = "item-card__move-toggle";
    toggle.setAttribute("data-move-toggle", "");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-haspopup", "listbox");
    toggle.innerHTML =
      "<span>Move</span>" +
      '<svg class="item-card__move-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

    var menu = document.createElement("div");
    menu.className = "item-card__move-menu";
    menu.setAttribute("data-move-menu", "");
    menu.setAttribute("role", "listbox");
    menu.hidden = true;

    BUCKET_KEYS.forEach(function (key) {
      if (key === bucket) return;
      var option = document.createElement("button");
      option.type = "button";
      option.className = "item-card__move-option";
      option.setAttribute("data-move-to", key);
      option.setAttribute("role", "option");
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

    BUCKET_KEYS.forEach(function (bucket) {
      organizedState[bucket] = (organizedState[bucket] || []).map(function (item) {
        return adaptItemForBucket(item, bucket);
      });
      syncEmptyState(bucket);
    });

    phase2Root.addEventListener("click", function (e) {
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
      }
    });

    phase2Root.addEventListener("dragstart", function (e) {
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
      if (!dragState.card || !dragState.section) return;
      e.preventDefault();
      var settle = dragState.card;
      var section = dragState.section;
      finishRearrange(section, settle);
    });

    phase2Root.addEventListener("dragend", function () {
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
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        closeAllMoveMenus();
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

    qsa("[data-filter-tag]", phase2Root).forEach(function (btn) {
      btn.addEventListener("click", function () {
        activeFilterTag = btn.getAttribute("data-filter-tag") || "";
        rebuildFilterBar();
        applyFilter();
      });
    });
  }
})();
