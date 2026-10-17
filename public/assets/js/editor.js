/**
 * PGM – edytor gry.
 *
 * Stan gry trzymany jest w obiekcie `state` (jedno źródło prawdy).
 * Lista punktów, markery i linia trasy są z niego odrysowywane po każdej zmianie.
 * Zapis wysyła CAŁĄ grę jednym żądaniem (POST dla nowej, PUT dla istniejącej);
 * serwer zapisuje ją w transakcji i odsyła aktualny stan z identyfikatorami.
 *
 * Sekcje pliku:
 *   1. Dane startowe i stan
 *   2. Narzędzia
 *   3. Zakładki
 *   4. Informacje podstawowe
 *   5. Mapa
 *   6. Lista punktów (wybór, kolejność, przeciąganie)
 *   7. Szczegóły punktu i zadanie
 *   8. Zapis i obsługa błędów
 *   9. Ochrona przed utratą zmian
 */
(function () {
  "use strict";

  var dataEl = document.getElementById("editor-data");
  if (!dataEl || typeof L === "undefined") return;

  /* ================================================================
     1. Dane startowe i stan
     ================================================================ */
  var boot = JSON.parse(dataEl.textContent);
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');

  var TASK_LABELS = {
    single_choice: "Jednokrotny wybór",
    multiple_choice: "Wielokrotny wybór",
    open_answer: "Odpowiedź otwarta",
    photo: "Zdjęcie",
    video: "Film",
    info: "Informacja"
  };
  var STATUS_LABELS = { draft: "Szkic", published: "Opublikowana", hidden: "Ukryta", archived: "Zarchiwizowana" };
  var CHOICE = ["single_choice", "multiple_choice"];
  var MAX_POINTS = 200;
  var MAX_OPTIONS = 10;

  var state = {
    game: null,        // dane podstawowe gry
    waypoints: [],     // [{key, id, name, description, player_hint, latitude, longitude, task}]
    selectedKey: null,
    dirty: false,
    saving: false,
    errors: []         // błędy z ostatniej próby zapisu
  };

  var keySeq = 0;
  function newKey() { keySeq += 1; return "p" + keySeq; }

  function defaultGame() {
    return {
      id: null, status: "draft", lock_version: null, title: "", short_description: "", description: "",
      estimated_duration_minutes: 60, difficulty: "medium", completion_points: 5, categories: [],
      custom_category: "", available_from: "", available_until: ""
    };
  }

  function defaultTask() {
    return {
      task_type: "single_choice", title: "", description: "", hint: "", max_points: 1,
      expected_answer: "", evaluation_instructions: "", media_requirement: "", media_max_seconds: null,
      unlock_mode: "on_arrival", unlock_radius_m: 30,
      options: [{ text: "", is_correct: true }, { text: "", is_correct: false }]
    };
  }

  /** Wczytuje grę odesłaną przez serwer do stanu edytora. */
  function loadGame(g) {
    if (!g) {
      state.game = defaultGame();
      state.waypoints = [];
      return;
    }
    state.game = {
      id: g.id, status: g.status, lock_version: g.lock_version, title: g.title || "",
      short_description: g.short_description || "", description: g.description || "",
      estimated_duration_minutes: g.estimated_duration_minutes, difficulty: g.difficulty,
      completion_points: g.completion_points, categories: (g.categories || []).slice(),
      custom_category: g.custom_category || "", available_from: g.available_from || "",
      available_until: g.available_until || ""
    };
    state.waypoints = (g.waypoints || []).map(function (w) {
      return {
        key: newKey(), id: w.id, name: w.name, description: w.description || "",
        player_hint: w.player_hint || "", latitude: w.latitude, longitude: w.longitude,
        task: w.task ? JSON.parse(JSON.stringify(w.task)) : null
      };
    });
  }

  /* ================================================================
     2. Narzędzia
     ================================================================ */
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function round7(n) { return Math.round(n * 1e7) / 1e7; }

  function findIndex(key) {
    for (var i = 0; i < state.waypoints.length; i++) if (state.waypoints[i].key === key) return i;
    return -1;
  }
  function selected() {
    var i = findIndex(state.selectedKey);
    return i === -1 ? null : state.waypoints[i];
  }

  /** Odległość w metrach między dwoma punktami (wzór haversine). */
  function distance(a, b) {
    var R = 6371000, rad = Math.PI / 180;
    var dLat = (b.latitude - a.latitude) * rad, dLng = (b.longitude - a.longitude) * rad;
    var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(a.latitude * rad) * Math.cos(b.latitude * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.sqrt(h));
  }

  function formatDistance(m) {
    if (m < 1000) return Math.round(m) + " m";
    return (m / 1000).toFixed(m < 10000 ? 2 : 1).replace(".", ",") + " km";
  }

  function pluralPoints(n) {
    if (n === 1) return "punkt";
    var l = n % 10, t = n % 100;
    return l >= 2 && l <= 4 && (t < 12 || t > 14) ? "punkty" : "punktów";
  }

  function announce(text) {
    var live = document.getElementById("editor-live");
    live.textContent = "";
    window.setTimeout(function () { live.textContent = text; }, 30);
  }

  var uid = 0;
  /** Nadaje unikalne id polom w szablonie i łączy je z etykietami (dostępność). */
  function linkLabels(root) {
    $all("label[data-for]", root).forEach(function (label) {
      var name = label.getAttribute("data-for");
      var field = root.querySelector('[data-wp="' + name + '"], [data-task="' + name + '"]');
      if (!field) return;
      uid += 1;
      field.id = field.id || "fld-" + name + "-" + uid;
      label.setAttribute("for", field.id);
    });
  }

  /* ================================================================
     3. Zakładki
     ================================================================ */
  var tabs = $all(".editor-tab[data-tab]");
  var mapReady = false;

  function showTab(name, focusTab) {
    tabs.forEach(function (tab) {
      var active = tab.getAttribute("data-tab") === name;
      tab.setAttribute("aria-selected", String(active));
      tab.tabIndex = active ? 0 : -1;
      tab.classList.toggle("is-active", active);
      document.getElementById(tab.getAttribute("aria-controls")).hidden = !active;
      if (active && focusTab) tab.focus();
    });
    if (name === "route") {
      if (!mapReady) initMap();
      window.setTimeout(function () { map.invalidateSize(); }, 0);
    }
  }

  tabs.forEach(function (tab, i) {
    tab.addEventListener("click", function () { showTab(tab.getAttribute("data-tab")); });
    tab.addEventListener("keydown", function (e) {
      if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
      var next = tabs[(i + (e.key === "ArrowRight" ? 1 : tabs.length - 1)) % tabs.length];
      showTab(next.getAttribute("data-tab"), true);
    });
  });
  $all("[data-goto-tab]").forEach(function (btn) {
    btn.addEventListener("click", function () { showTab(btn.getAttribute("data-goto-tab")); window.scrollTo(0, 0); });
  });

  /* ================================================================
     4. Informacje podstawowe
     ================================================================ */
  var heading = document.getElementById("editor-heading");
  var customField = document.getElementById("custom-category-field");

  function fillInfoForm() {
    $all("[data-game]").forEach(function (el) {
      var v = state.game[el.getAttribute("data-game")];
      el.value = v === null || v === undefined ? "" : v;
    });
    $all("[data-category]").forEach(function (cb) {
      cb.checked = state.game.categories.indexOf(Number(cb.value)) !== -1;
    });
    updateCustomCategory();
    updateCounters();
    updateHeading();
  }

  function updateHeading() {
    heading.textContent = state.game.title.trim() || "Nowa gra";
    var badge = document.getElementById("editor-status");
    badge.className = "status status--" + state.game.status;
    badge.textContent = STATUS_LABELS[state.game.status] || state.game.status;
    document.getElementById("btn-save").textContent = state.game.status === "draft" ? "Zapisz szkic" : "Zapisz zmiany";
  }

  function updateCustomCategory() {
    var other = $("[data-category][data-other]");
    customField.hidden = !(other && other.checked);
  }

  function updateCounters() {
    $all("[data-count-for]").forEach(function (span) {
      var input = document.getElementById(span.getAttribute("data-count-for"));
      span.textContent = input ? input.value.length : 0;
    });
  }

  $all("[data-game]").forEach(function (el) {
    el.addEventListener("input", function () {
      var field = el.getAttribute("data-game");
      var value = el.value;
      if (el.getAttribute("data-type") === "int") value = value === "" ? null : Number(value);
      state.game[field] = value;
      if (field === "title") updateHeading();
      updateCounters();
      markDirty();
    });
  });

  $all("[data-category]").forEach(function (cb) {
    cb.addEventListener("change", function () {
      state.game.categories = $all("[data-category]:checked").map(function (c) { return Number(c.value); });
      updateCustomCategory();
      markDirty();
    });
  });

  /* ================================================================
     5. Mapa
     ================================================================ */
  var map, routeLine, markers = {};

  function initMap() {
    mapReady = true;
    // doubleClickZoom wyłączony: kliknięcie dodaje punkt, więc dwuklik dodawałby punkt i przybliżał mapę
    map = L.map("map", { zoomControl: true, doubleClickZoom: false }).setView([boot.map.lat, boot.map.lng], boot.map.zoom);
    L.tileLayer(boot.map.tileUrl, { maxZoom: 19, attribution: boot.map.attribution }).addTo(map);
    routeLine = L.polyline([], { color: "#0b2545", weight: 3, opacity: 0.75, dashArray: "8 8" }).addTo(map);

    map.on("click", function (e) { addPoint(e.latlng.lat, e.latlng.lng); });

    renderMap();
    if (state.waypoints.length) {
      var bounds = L.latLngBounds(state.waypoints.map(function (w) { return [w.latitude, w.longitude]; }));
      map.fitBounds(bounds, { padding: [50, 50], maxZoom: 17 });
    }
  }

  function makeIcon(number, isSelected, hasTask, hasError) {
    return L.divIcon({
      className: "pin" + (isSelected ? " is-selected" : "") + (hasTask ? " has-task" : "") + (hasError ? " has-error" : ""),
      html: "<span>" + number + "</span>",
      iconSize: [34, 42],
      iconAnchor: [17, 42]
    });
  }

  function renderMap() {
    if (!mapReady) return;
    var seen = {};
    state.waypoints.forEach(function (wp, i) {
      seen[wp.key] = true;
      var icon = makeIcon(i + 1, wp.key === state.selectedKey, !!wp.task, hasErrors(i));
      var marker = markers[wp.key];
      if (!marker) {
        marker = L.marker([wp.latitude, wp.longitude], { draggable: true, icon: icon, keyboard: true, riseOnHover: true });
        marker.on("click", function () { select(wp.key, false); });
        marker.on("drag", function (e) {
          var p = e.target.getLatLng();
          wp.latitude = round7(p.lat);
          wp.longitude = round7(p.lng);
          updateRouteLine();
        });
        marker.on("dragend", function () {
          markDirty();
          select(wp.key, false);
          syncCoordInputs(wp);
          renderSummary();
          announce("Przesunięto punkt " + (findIndex(wp.key) + 1));
        });
        marker.addTo(map);
        markers[wp.key] = marker;
      } else {
        marker.setIcon(icon);
        marker.setLatLng([wp.latitude, wp.longitude]);
      }
      marker.setZIndexOffset(wp.key === state.selectedKey ? 1000 : 0);
      var el = marker.getElement();
      if (el) el.setAttribute("aria-label", "Punkt " + (i + 1) + ": " + wp.name);
    });
    Object.keys(markers).forEach(function (key) {
      if (!seen[key]) { map.removeLayer(markers[key]); delete markers[key]; }
    });
    updateRouteLine();
  }

  function updateRouteLine() {
    if (!mapReady) return;
    routeLine.setLatLngs(state.waypoints.map(function (w) { return [w.latitude, w.longitude]; }));
  }

  function routeLength() {
    var total = 0;
    for (var i = 1; i < state.waypoints.length; i++) total += distance(state.waypoints[i - 1], state.waypoints[i]);
    return total;
  }

  function renderSummary() {
    var n = state.waypoints.length;
    var summary = document.getElementById("route-summary");
    summary.textContent = n === 0
      ? "Brak punktów"
      : n + " " + pluralPoints(n) + (n > 1 ? " · ok. " + formatDistance(routeLength()) + " w linii prostej" : "");
    document.getElementById("tab-route-count").textContent = n;
  }

  function addPoint(lat, lng) {
    if (state.waypoints.length >= MAX_POINTS) {
      window.alert("Gra może mieć najwyżej " + MAX_POINTS + " punktów.");
      return;
    }
    var wp = {
      key: newKey(), id: null, name: "Lokalizacja " + (state.waypoints.length + 1),
      description: "", player_hint: "", latitude: round7(lat), longitude: round7(lng), task: null
    };
    state.waypoints.push(wp);
    markDirty();
    select(wp.key, false);
    announce("Dodano punkt " + state.waypoints.length + ": " + wp.name);
  }

  document.getElementById("btn-add-center").addEventListener("click", function () {
    if (!mapReady) initMap();
    var c = map.getCenter();
    addPoint(c.lat, c.lng);
    var nameInput = $('[data-wp="name"]');
    if (nameInput) nameInput.focus();
  });

  /* ================================================================
     6. Lista punktów
     ================================================================ */
  var listEl = document.getElementById("point-list");
  var dragKey = null;

  function hasErrors(index) {
    return state.errors.some(function (e) { return e.waypoint === index; });
  }

  function renderList() {
    listEl.textContent = "";
    document.getElementById("point-list-empty").hidden = state.waypoints.length > 0;

    state.waypoints.forEach(function (wp, i) {
      var li = document.createElement("li");
      li.className = "point-item" + (wp.key === state.selectedKey ? " is-selected" : "") + (hasErrors(i) ? " has-error" : "");
      li.draggable = true;
      li.setAttribute("data-key", wp.key);

      var handle = document.createElement("span");
      handle.className = "point-item__handle";
      handle.setAttribute("aria-hidden", "true");
      handle.textContent = "⋮⋮";

      var main = document.createElement("button");
      main.type = "button";
      main.className = "point-item__main";
      main.setAttribute("aria-current", wp.key === state.selectedKey ? "true" : "false");
      var num = document.createElement("span");
      num.className = "point-num";
      num.textContent = i + 1;
      var text = document.createElement("span");
      text.className = "point-item__text";
      var strong = document.createElement("strong");
      strong.textContent = wp.name || "(bez nazwy)";
      var small = document.createElement("small");
      small.textContent = wp.task ? "Zadanie: " + TASK_LABELS[wp.task.task_type] : "Bez zadania";
      text.appendChild(strong);
      text.appendChild(small);
      main.appendChild(num);
      main.appendChild(text);
      main.addEventListener("click", function () { select(wp.key, true); });

      var actions = document.createElement("span");
      actions.className = "point-item__actions";
      actions.appendChild(moveButton("↑", "Przesuń wyżej: " + wp.name, i === 0, function () { move(i, i - 1, "up"); }));
      actions.appendChild(moveButton("↓", "Przesuń niżej: " + wp.name, i === state.waypoints.length - 1, function () { move(i, i + 1, "down"); }));

      li.appendChild(handle);
      li.appendChild(main);
      li.appendChild(actions);
      bindDrag(li, wp.key);
      listEl.appendChild(li);
    });
    renderSummary();
  }

  function moveButton(label, aria, disabled, onClick) {
    var b = document.createElement("button");
    b.type = "button";
    b.className = "icon-btn icon-btn--sm";
    b.textContent = label;
    b.setAttribute("aria-label", aria);
    b.disabled = disabled;
    b.addEventListener("click", onClick);
    return b;
  }

  /** Zmiana kolejności punktu; focusDir przywraca fokus na przycisk po przerysowaniu. */
  function move(from, to, focusDir) {
    if (to < 0 || to >= state.waypoints.length || from === to) return;
    var item = state.waypoints.splice(from, 1)[0];
    state.waypoints.splice(to, 0, item);
    state.errors = []; // numery punktów w błędach przestały być aktualne
    renderErrors();
    markDirty();
    renderAll();
    announce(item.name + " jest teraz punktem " + (to + 1) + " z " + state.waypoints.length);
    if (focusDir) {
      var li = listEl.children[to];
      var btns = li ? li.querySelectorAll(".point-item__actions button") : [];
      var target = focusDir === "up" ? btns[0] : btns[1];
      if (target && target.disabled) target = focusDir === "up" ? btns[1] : btns[0];
      if (target) target.focus();
    }
  }

  function bindDrag(li, key) {
    li.addEventListener("dragstart", function (e) {
      dragKey = key;
      li.classList.add("is-dragging");
      e.dataTransfer.effectAllowed = "move";
      try { e.dataTransfer.setData("text/plain", key); } catch (err) { /* IE */ }
    });
    li.addEventListener("dragend", function () {
      dragKey = null;
      $all(".point-item", listEl).forEach(function (el) { el.classList.remove("is-dragging", "drop-before", "drop-after"); });
    });
    li.addEventListener("dragover", function (e) {
      if (!dragKey || dragKey === key) return;
      e.preventDefault();
      var rect = li.getBoundingClientRect();
      var after = e.clientY > rect.top + rect.height / 2;
      li.classList.toggle("drop-after", after);
      li.classList.toggle("drop-before", !after);
    });
    li.addEventListener("dragleave", function () { li.classList.remove("drop-before", "drop-after"); });
    li.addEventListener("drop", function (e) {
      e.preventDefault();
      if (!dragKey || dragKey === key) return;
      var from = findIndex(dragKey);
      var to = findIndex(key);
      if (li.classList.contains("drop-after")) to += 1;
      if (from < to) to -= 1;
      move(from, to, null);
    });
  }

  function select(key, panMap) {
    state.selectedKey = key;
    renderList();
    renderMap();
    renderDetails();
    var wp = selected();
    if (wp && panMap && mapReady) map.panTo([wp.latitude, wp.longitude]);
  }

  function renderAll() {
    renderList();
    renderMap();
    renderDetails();
  }

  /* ================================================================
     7. Szczegóły punktu i zadanie
     ================================================================ */
  var detailsEl = document.getElementById("point-details");
  var detailsEmpty = document.getElementById("details-empty");
  var detailsKey = null; // punkt aktualnie pokazany w formularzu

  function renderDetails() {
    var wp = selected();
    detailsEmpty.hidden = !!wp;
    if (!wp) {
      detailsEl.textContent = "";
      detailsKey = null;
      return;
    }
    var index = findIndex(wp.key);
    // Ten sam punkt – aktualizujemy tylko numer i błędy, żeby nie zgubić fokusu w polu
    if (detailsKey === wp.key && detailsEl.firstElementChild) {
      $("[data-point-number]", detailsEl).textContent = index + 1;
      showFieldErrors(detailsEl, index);
      return;
    }
    detailsKey = wp.key;

    var form = document.getElementById("tpl-point-details").content.firstElementChild.cloneNode(true);
    linkLabels(form);
    $("[data-point-number]", form).textContent = index + 1;
    form.addEventListener("submit", function (e) { e.preventDefault(); });

    // Pola punktu
    $all("[data-wp]", form).forEach(function (el) {
      var field = el.getAttribute("data-wp");
      el.value = wp[field] === null || wp[field] === undefined ? "" : wp[field];
      if (field === "latitude" || field === "longitude") {
        el.addEventListener("change", function () { applyCoords(wp, form); });
      } else {
        el.addEventListener("input", function () {
          wp[field] = el.value;
          markDirty();
          if (field === "name") { renderList(); renderMap(); }
        });
      }
    });

    $('[data-action="center"]', form).addEventListener("click", function () {
      if (mapReady) map.setView([wp.latitude, wp.longitude], Math.max(map.getZoom(), 17));
    });
    $('[data-action="delete"]', form).addEventListener("click", function () { deletePoint(wp); });

    // Zadanie
    var toggle = $("[data-task-toggle]", form);
    toggle.checked = !!wp.task;
    toggle.addEventListener("change", function () {
      if (toggle.checked) {
        wp.task = wp._removedTask || defaultTask();
      } else {
        wp._removedTask = wp.task; // pozwala przywrócić zadanie wyłączone przez pomyłkę
        wp.task = null;
      }
      markDirty();
      fillTask(form, wp);
      renderList();
      renderMap();
      if (toggle.checked) $('[data-task="title"]', form).focus();
    });

    $all("[data-task]", form).forEach(function (el) {
      el.addEventListener(el.tagName === "SELECT" ? "change" : "input", function () {
        if (!wp.task) return;
        var field = el.getAttribute("data-task");
        var value = el.value;
        if (el.getAttribute("data-type") === "int") value = value === "" ? null : Number(value);
        wp.task[field] = value;
        if (field === "task_type") onTaskTypeChange(form, wp);
        if (field === "unlock_mode") updateTaskVisibility(form, wp.task);
        markDirty();
      });
    });
    $('[data-action="add-option"]', form).addEventListener("click", function () {
      if (!wp.task || wp.task.options.length >= MAX_OPTIONS) return;
      wp.task.options.push({ text: "", is_correct: false });
      markDirty();
      renderOptions(form, wp);
      var inputs = $all("[data-opt-text]", form);
      inputs[inputs.length - 1].focus();
    });

    fillTask(form, wp);
    detailsEl.textContent = "";
    detailsEl.appendChild(form);
    showFieldErrors(form, index);
  }

  function applyCoords(wp, form) {
    var latEl = $('[data-wp="latitude"]', form), lngEl = $('[data-wp="longitude"]', form);
    var lat = parseFloat(latEl.value), lng = parseFloat(lngEl.value);
    var ok = isFinite(lat) && isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
    $("[data-coord-error]", form).hidden = ok;
    latEl.setAttribute("aria-invalid", String(!ok));
    lngEl.setAttribute("aria-invalid", String(!ok));
    if (!ok) return;
    wp.latitude = round7(lat);
    wp.longitude = round7(lng);
    markDirty();
    renderMap();
    renderSummary();
    if (mapReady) map.panTo([wp.latitude, wp.longitude]);
  }

  /** Po przeciągnięciu markera – aktualizacja pól współrzędnych w formularzu. */
  function syncCoordInputs(wp) {
    if (detailsKey !== wp.key) return;
    $('[data-wp="latitude"]', detailsEl).value = wp.latitude;
    $('[data-wp="longitude"]', detailsEl).value = wp.longitude;
    $("[data-coord-error]", detailsEl).hidden = true;
  }

  function fillTask(form, wp) {
    var box = $("[data-task-fields]", form);
    box.hidden = !wp.task;
    if (!wp.task) return;
    $all("[data-task]", form).forEach(function (el) {
      var v = wp.task[el.getAttribute("data-task")];
      el.value = v === null || v === undefined ? "" : v;
    });
    updateTaskVisibility(form, wp.task);
    renderOptions(form, wp);
  }

  function onTaskTypeChange(form, wp) {
    var t = wp.task;
    if (CHOICE.indexOf(t.task_type) !== -1 && t.options.length === 0) {
      t.options = [{ text: "", is_correct: true }, { text: "", is_correct: false }];
    }
    if (t.task_type === "single_choice") {
      var found = false;
      t.options.forEach(function (o) { o.is_correct = o.is_correct && !found; found = found || o.is_correct; });
    }
    if (t.task_type === "info") t.max_points = 0;
    else if (t.max_points === 0) t.max_points = 1;
    $('[data-task="max_points"]', form).value = t.max_points;
    updateTaskVisibility(form, t);
    renderOptions(form, wp);
    renderList();
  }

  function updateTaskVisibility(form, task) {
    $all("[data-show-for]", form).forEach(function (el) {
      el.hidden = el.getAttribute("data-show-for").split(" ").indexOf(task.task_type) === -1;
    });
    $all("[data-hide-for]", form).forEach(function (el) {
      el.hidden = el.getAttribute("data-hide-for").split(" ").indexOf(task.task_type) !== -1;
    });
    $all("[data-show-unlock]", form).forEach(function (el) {
      el.hidden = el.getAttribute("data-show-unlock") !== task.unlock_mode;
    });
    var mode = $("[data-options-mode]", form);
    if (mode) mode.textContent = task.task_type === "single_choice"
      ? "– zaznacz jedną poprawną"
      : "– zaznacz wszystkie poprawne";
  }

  function renderOptions(form, wp) {
    var list = $("[data-options]", form);
    list.textContent = "";
    if (!wp.task || CHOICE.indexOf(wp.task.task_type) === -1) return;
    var single = wp.task.task_type === "single_choice";
    var group = "correct-" + wp.key;

    wp.task.options.forEach(function (opt, i) {
      var row = document.getElementById("tpl-option").content.firstElementChild.cloneNode(true);
      var correct = $("[data-opt-correct]", row);
      var text = $("[data-opt-text]", row);
      correct.type = single ? "radio" : "checkbox";
      correct.name = group;
      correct.checked = !!opt.is_correct;
      text.value = opt.text;
      text.setAttribute("aria-label", "Odpowiedź " + (i + 1));
      correct.setAttribute("aria-label", "Odpowiedź " + (i + 1) + " jest poprawna");

      correct.addEventListener("change", function () {
        if (single) wp.task.options.forEach(function (o, j) { o.is_correct = j === i; });
        else opt.is_correct = correct.checked;
        markDirty();
      });
      text.addEventListener("input", function () { opt.text = text.value; markDirty(); });
      $("[data-opt-remove]", row).addEventListener("click", function () {
        wp.task.options.splice(i, 1);
        markDirty();
        renderOptions(form, wp);
      });
      list.appendChild(row);
    });
    $('[data-action="add-option"]', form).disabled = wp.task.options.length >= MAX_OPTIONS;
  }

  function deletePoint(wp) {
    var index = findIndex(wp.key);
    var msg = "Usunąć punkt " + (index + 1) + " „" + wp.name + "”" + (wp.task ? " razem z zadaniem" : "") + "?";
    if (!window.confirm(msg)) return;
    state.waypoints.splice(index, 1);
    state.errors = [];
    renderErrors();
    var next = state.waypoints[Math.min(index, state.waypoints.length - 1)];
    state.selectedKey = next ? next.key : null;
    markDirty();
    renderAll();
    announce("Usunięto punkt. Zostało " + state.waypoints.length + " " + pluralPoints(state.waypoints.length));
  }

  /** Podświetla w formularzu pola, których dotyczą błędy z serwera. */
  function showFieldErrors(form, index) {
    $all(".field.has-error", form).forEach(function (f) { f.classList.remove("has-error"); });
    state.errors.forEach(function (err) {
      if (err.waypoint !== index) return;
      var isTask = err.path.indexOf(".task.") !== -1;
      var selector = err.field === "options" ? "[data-options]" : (isTask ? '[data-task="' : '[data-wp="') + err.field + '"]';
      var el = form.querySelector(err.field === "options" ? selector : selector);
      var wrap = el && el.closest(".field, .options");
      if (wrap) wrap.classList.add("has-error");
    });
  }

  /* ================================================================
     8. Zapis i obsługa błędów
     ================================================================ */
  var saveBtn = document.getElementById("btn-save");
  var saveState = document.getElementById("save-state");
  var workspace = document.getElementById("editor-workspace");

  function setSaveState(kind, text) {
    saveState.setAttribute("data-state", kind);
    saveState.textContent = text;
  }

  function markDirty() {
    if (!state.dirty) {
      state.dirty = true;
      setSaveState("dirty", "Niezapisane zmiany");
    }
  }

  function buildPayload() {
    var g = state.game;
    return {
      lock_version: g.lock_version,
      title: g.title,
      short_description: g.short_description,
      description: g.description,
      estimated_duration_minutes: g.estimated_duration_minutes,
      difficulty: g.difficulty,
      completion_points: g.completion_points,
      categories: g.categories,
      custom_category: g.custom_category,
      available_from: g.available_from,
      available_until: g.available_until,
      waypoints: state.waypoints.map(function (w) {
        var t = w.task;
        return {
          id: w.id,
          name: w.name,
          description: w.description,
          player_hint: w.player_hint,
          latitude: w.latitude,
          longitude: w.longitude,
          task: t ? {
            task_type: t.task_type, title: t.title, description: t.description, hint: t.hint,
            max_points: t.max_points, expected_answer: t.expected_answer,
            evaluation_instructions: t.evaluation_instructions, media_requirement: t.media_requirement,
            media_max_seconds: t.media_max_seconds, unlock_mode: t.unlock_mode, unlock_radius_m: t.unlock_radius_m,
            options: CHOICE.indexOf(t.task_type) !== -1
              ? t.options.map(function (o) { return { text: o.text, is_correct: !!o.is_correct }; })
              : []
          } : null
        };
      })
    };
  }

  function save() {
    if (state.saving) return;

    // Szybka kontrola w przeglądarce – pełną walidację i tak wykonuje serwer
    if (!state.game.title.trim()) {
      showErrors("Nie można zapisać gry.", [{ path: "title", field: "title", waypoint: null, message: "Podaj tytuł gry." }]);
      return;
    }

    state.saving = true;
    saveBtn.disabled = true;
    saveBtn.setAttribute("aria-busy", "true");
    workspace.setAttribute("inert", "");
    workspace.classList.add("is-saving");
    setSaveState("saving", "Zapisywanie…");

    var isNew = !state.game.id;
    var url = isNew ? boot.urls.create : boot.urls.game.replace("{id}", state.game.id);
    var selectedIndex = findIndex(state.selectedKey);

    fetch(url, {
      method: isNew ? "POST" : "PUT",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-Token": csrfMeta ? csrfMeta.content : ""
      },
      body: JSON.stringify(buildPayload())
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (body) { return { res: res, body: body }; });
      })
      .then(function (r) {
        if (r.res.ok && r.body.data) {
          onSaved(r.body.data, isNew, selectedIndex);
        } else {
          onSaveError(r.res.status, r.body);
        }
      })
      .catch(function () {
        setSaveState("error", "Błąd zapisu");
        showErrors("Brak połączenia z serwerem. Zmiany NIE zostały zapisane – nie zamykaj strony i spróbuj ponownie.", []);
      })
      .then(function () {
        state.saving = false;
        saveBtn.disabled = false;
        saveBtn.removeAttribute("aria-busy");
        workspace.removeAttribute("inert");
        workspace.classList.remove("is-saving");
      });
  }

  function onSaved(game, isNew, selectedIndex) {
    loadGame(game);
    state.dirty = false;
    state.errors = [];
    detailsKey = null;
    var sel = state.waypoints[selectedIndex];
    state.selectedKey = sel ? sel.key : null;
    // markery zostały powiązane ze starymi kluczami – tworzymy je od nowa
    if (mapReady) {
      Object.keys(markers).forEach(function (k) { map.removeLayer(markers[k]); });
      markers = {};
    }
    renderErrors();
    fillInfoForm();
    renderAll();

    var time = new Date().toLocaleTimeString("pl-PL", { hour: "2-digit", minute: "2-digit" });
    setSaveState("saved", "Zapisano o " + time);
    announce("Gra została zapisana.");

    if (isNew) {
      var editUrl = boot.urls.editPage.replace("{id}", game.id);
      window.history.replaceState(null, "", editUrl);
      document.getElementById("btn-cancel").setAttribute("href", boot.urls.editPage.replace("/{id}/edycja", ""));
    }
  }

  function onSaveError(status, body) {
    var err = (body && body.error) || {};
    setSaveState("error", "Błąd zapisu");

    if (status === 422 && err.fields) {
      showErrors(err.message || "Popraw wskazane elementy.", err.fields);
    } else if (status === 401) {
      showErrors("Sesja wygasła. Zaloguj się ponownie w nowej karcie, a potem wróć tutaj i kliknij „Zapisz” – zmiany są nadal w tym oknie.", [], true);
    } else if (status === 409) {
      showErrors(err.message || "Gra została zmieniona w innym oknie.", []);
    } else if (status === 403) {
      showErrors(err.message || "Brak uprawnień do zapisu tej gry. Odśwież stronę i spróbuj ponownie.", []);
    } else {
      showErrors(err.message || "Nie udało się zapisać gry. Spróbuj ponownie za chwilę.", []);
    }
  }

  function showErrors(title, fields, withLoginLink) {
    state.errors = fields || [];
    var box = document.getElementById("save-errors");
    document.getElementById("save-errors-title").textContent = title;
    var list = document.getElementById("save-errors-list");
    list.textContent = "";

    state.errors.forEach(function (err) {
      var li = document.createElement("li");
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "link-btn";
      btn.textContent = err.message;
      btn.addEventListener("click", function () { goToError(err); });
      li.appendChild(btn);
      list.appendChild(li);
    });
    if (withLoginLink) {
      var li = document.createElement("li");
      var a = document.createElement("a");
      a.href = boot.urls.login;
      a.target = "_blank";
      a.rel = "noopener";
      a.textContent = "Otwórz logowanie w nowej karcie";
      li.appendChild(a);
      list.appendChild(li);
    }
    box.hidden = false;
    renderList();
    renderMap();
    if (detailsKey) showFieldErrors(detailsEl, findIndex(detailsKey));
    box.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function renderErrors() {
    if (!state.errors.length) document.getElementById("save-errors").hidden = true;
  }

  /** Kliknięcie błędu: przejście do zakładki, punktu i pola, którego dotyczy. */
  function goToError(err) {
    if (err.waypoint === null || err.waypoint === undefined) {
      showTab("info");
      var el = document.querySelector('[data-game="' + err.field + '"]') ||
               (err.field === "categories" ? document.querySelector("[data-category]") : null);
      if (el) el.focus();
      return;
    }
    showTab("route");
    var wp = state.waypoints[err.waypoint];
    if (!wp) return;
    select(wp.key, true);
    var isTask = err.path.indexOf(".task.") !== -1;
    var target = err.field === "options"
      ? $("[data-opt-text]", detailsEl)
      : $((isTask ? '[data-task="' : '[data-wp="') + err.field + '"]', detailsEl);
    if (target) target.focus();
  }

  saveBtn.addEventListener("click", save);
  document.addEventListener("keydown", function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s") {
      e.preventDefault();
      save();
    }
  });

  /* ================================================================
     9. Ochrona przed utratą zmian
     ================================================================ */
  window.addEventListener("beforeunload", function (e) {
    if (!state.dirty) return;
    e.preventDefault();
    e.returnValue = "";
  });

  /* ================================================================
     Start
     ================================================================ */
  loadGame(boot.game);
  fillInfoForm();
  renderList();
  renderSummary();
  showTab(boot.game ? "route" : "info");
  if (!boot.game) document.getElementById("f-title").focus();
})();
