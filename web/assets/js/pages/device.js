/* Geraeteseite. Der Entwurf lebt hier im Browser, bis "Uebernehmen" gedrueckt
   wird. Die Vorschau holt fuer jeden Entwurf die Zeichenbefehle vom Server und
   zeichnet sie mit ops.js, also genau das, was das Panel bekaeme. */
(function () {
  'use strict';
  var P = window.PX, TW = window.TW, pal = TW.PAL;
  var D = TW.data();
  if (!D.id) return;

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var clone = function (o) { return JSON.parse(JSON.stringify(o)); };

  var role = D.role;
  var readOnly = role === 'view';
  var applied = clone(D.settings);
  var draft = clone(D.settings);
  var neverApplied = !D.applied;
  var modes = D.modes;

  var frame = null, offset = 0, fetchTimer = null, refreshTimer = null, seq = 0, appliedAt = null;
  /* Uebergaenge wie auf dem Geraet (main.cpp): Schieben zwischen Modi, Einfallen der Tafel,
     Flugwechsel, neue Minute. Ausgeloest nur, wenn im Lauf der Zeit die naechste Seite drankommt,
     nicht nach einer Aenderung auf der Seite: die soll sofort stehen. */
  var FX_MS = { push: 1600, drop: 1100, swap: 400, roll: 240, reel: 560, carousel: 560, lines: 400 };
  var shown = null, lastGrid = null, trans = null, editPending = false, editCut = false, shownProg = null, shownPage = null;
  var running = !TW.reduce, t0 = performance.now(), frozenT = 0, frozenMs = Date.now();
  var noteT = null;

  var panel = $('[data-panel]');
  var canvas = panel.querySelector('canvas');
  var applyBtn = $('[data-apply]');
  var applyStatus = $('[data-apply-status]');
  var dirtyBadge = $('[data-dirty]');
  var motionBtn = $('[data-motion]');
  var motionLabel = $('[data-motion-label]');

  var TITLES = {
    flight: ['Flight mode', 'Flugzeugmodus'], clock: ['Clock mode', 'Uhrmodus'], weather: ['Weather mode', 'Wettermodus'],
    transit: ['Departures', 'Nahverkehr'], spotify: ['Spotify', 'Spotify'], notes: ['Notes', 'Notizen'], pixel: ['Pixel editor', 'Pixel-Editor']
  };
  var PREVIEW = {
    flight: ['Preview: flight mode', 'Vorschau: Flugzeugmodus'], clock: ['Preview: clock', 'Vorschau: Uhr'], weather: ['Preview: weather', 'Vorschau: Wetter'],
    transit: ['Preview: departures', 'Vorschau: Nahverkehr'], spotify: ['Preview: Spotify', 'Vorschau: Spotify'], notes: ['Preview: notes', 'Vorschau: Notizen'], pixel: ['Preview: pixel editor', 'Vorschau: Pixel-Editor']
  };
  var ROT = { flight: ['Flight', 'Flug'], clock: ['Clock', 'Uhr'], weather: ['Weather', 'Wetter'], transit: ['Departures', 'Nahverkehr'], spotify: ['Spotify', 'Spotify'] };

  function get(path) {
    return path.split('.').reduce(function (o, k) { return o == null ? undefined : o[k]; }, draft);
  }
  function set(path, value) {
    var keys = path.split('.'), o = draft;
    for (var i = 0; i < keys.length - 1; i++) o = o[keys[i]];
    o[keys[keys.length - 1]] = value;
  }
  /* Timer und Wecker aendern ihre eigenen Schnittstellen sofort, nie "Uebernehmen". */
  function comparable(s) {
    var c = clone(s);
    delete c.applied; delete c.lang; delete c.note_at; delete c.timers; delete c.alarm;
    return JSON.stringify(c);
  }
  function isDirty() { return neverApplied || comparable(draft) !== comparable(applied); }
  /* Nur schicken, was sich seit dem Laden geaendert hat, in den Modi je Feld. Sonst setzt ein
     alter Tab eine Notiz, die ein Gast inzwischen geschickt hat, oder eine Zeitzone aus den
     Einstellungen still zurueck (Fehlerliste 24.09.2026, P1.8). Der Standort geht immer ganz,
     der Server will Breite und Laenge zusammen. */
  function changes() {
    var out = {}, same = function (a, b) { return JSON.stringify(a) === JSON.stringify(b); };
    Object.keys(draft).forEach(function (k) {
      if (k === 'applied' || k === 'lang' || k === 'note_at' || k === 'timers' || k === 'alarm') return;
      var a = draft[k], b = applied[k];
      if (same(a, b)) return;
      if (k !== 'location' && a && b && typeof a === 'object' && typeof b === 'object' && !Array.isArray(a)) {
        var part = {};
        Object.keys(a).forEach(function (s) { if (!same(a[s], b[s])) part[s] = a[s]; });
        out[k] = part;
      } else {
        out[k] = a;
      }
    });
    return out;
  }
  function notesDirty() {
    return draft.notes.line1 !== applied.notes.line1 || draft.notes.line2 !== applied.notes.line2;
  }
  function canApply() {
    if (readOnly) return draft.mode === 'notes' && notesDirty();
    return isDirty();
  }

  /* ---------- Darstellung der Bedienelemente ---------- */
  function render() {
    $$('[data-set]').forEach(function (el) {
      var path = el.getAttribute('data-set');
      var v = get(path);
      if (el.getAttribute('role') === 'switch') {
        el.setAttribute('aria-checked', v ? 'true' : 'false');
      } else if (el.hasAttribute('data-value')) {
        var raw = el.getAttribute('data-value');
        var match = path === 'clock.h24' ? (raw === '1') === !!v : raw === String(v);
        el.setAttribute(el.getAttribute('role') === 'radio' ? 'aria-checked' : 'aria-pressed', match ? 'true' : 'false');
      } else if (el.type === 'range' || el.tagName === 'SELECT') {
        if (el.value !== String(v)) el.value = String(v);
      } else if (el.type === 'color') {
        if (el.value.toUpperCase() !== String(v).toUpperCase()) el.value = String(v).toLowerCase();
      } else if (el.type === 'text') {
        if (document.activeElement !== el && el.value !== String(v)) el.value = String(v);
      }
    });
    $$('[data-rotation]').forEach(function (b) {
      b.setAttribute('aria-pressed', draft.rotation.indexOf(b.getAttribute('data-rotation')) !== -1 ? 'true' : 'false');
    });
    var colour = String(draft.clock.color).toUpperCase();
    var standard = ['#FFAA00', '#35D6FF', '#3DE07C'];
    $$('[data-colour]').forEach(function (b) {
      b.setAttribute('aria-checked', b.getAttribute('data-colour') === colour ? 'true' : 'false');
    });
    var more = $('[data-more]');
    if (more) more.setAttribute('data-active', standard.indexOf(colour) === -1 ? 'true' : 'false');

    $$('[data-out="bright"]').forEach(function (o) { o.textContent = String(draft.bright); });
    $$('[data-out="radius"]').forEach(function (o) { o.textContent = String(draft.flight.radius); });
    $$('[data-out="dwell"]').forEach(function (o) { o.textContent = String(draft.flight.dwell); });
    $$('[data-out="view"]').forEach(function (o) { o.textContent = String(draft.flight.view); });
    var round = 4 * (parseInt(draft.flight.view, 10) || 4);
    var viewNote = $('[data-view-note]');
    if (viewNote) TW.text(viewNote, 'Route, departure and arrival, position, metrics: one round takes ' + round + ' s. A new flight starts with the route.', 'Route, Abflug und Ankunft, Position, Messwerte: eine Runde dauert ' + round + ' s. Ein neuer Flug beginnt mit der Route.');
    $$('[data-out="place"]').forEach(function (o) { o.textContent = draft.location.place; });
    var lat = Number(draft.location.lat), lon = Number(draft.location.lon);
    var coords = Math.abs(lat).toFixed(4) + '°' + (lat >= 0 ? 'N' : 'S') + ' ' + Math.abs(lon).toFixed(4) + '°' + (lon >= 0 ? 'E' : 'W');
    $$('[data-out="coords"]').forEach(function (o) { o.textContent = coords; });
    var tag = $('[data-radius-tag]');
    if (tag) tag.textContent = 'ADSB.LOL · ' + draft.flight.radius + ' NM';

    var r = draft.flight.radius;
    var note = r >= 40 ? ['Covers all of Luxembourg, plus Trier and Metz.', 'Deckt ganz Luxemburg ab, plus Trier und Metz.']
      : r >= 20 ? ['Roughly the centre and Findel.', 'Etwa das Zentrum und Findel.']
        : ['Only what is nearly overhead.', 'Nur was fast direkt über dir ist.'];
    TW.text($('[data-radius-note]'), note[0], note[1]);

    viewsSummary();
    var rot = draft.rotation;
    var names = rot.map(function (k) { return ROT[k] || [k, k]; });
    TW.text($('[data-rot-summary]'),
      rot.length < 2 ? 'One mode, no cycling.' : 'Cycles through ' + names.map(function (n) { return n[0]; }).join(', ') + '.',
      rot.length < 2 ? 'Ein Modus, kein Wechsel.' : 'Wechselt zwischen ' + names.map(function (n) { return n[1]; }).join(', ') + '.');

    ['line1', 'line2'].forEach(function (k) {
      var left = 21 - String(draft.notes[k]).length;
      var el = $('[data-count="' + k + '"]');
      /* "3 frei" statt "3": Vorleser sagen sonst nur eine Zahl (Befund A16). */
      if (el) { TW.text(el, left + ' left', left + ' frei'); el.style.color = left <= 3 ? '#FFAA00' : '#8B949C'; }
    });

    renderTransit();
    renderSpotify();

    $$('[data-panel-for]').forEach(function (p) { p.hidden = p.getAttribute('data-panel-for') !== draft.mode; });
    var title = TITLES[draft.mode] || [draft.mode, draft.mode];
    var head = $('[data-settings-title]');
    if (head && head.dataset.en !== title[0]) { TW.text(head, title[0], title[1]); requestAnimationFrame(TW.drawHeadings); }
    var pl = PREVIEW[draft.mode] || title;
    canvas.setAttribute('data-en-label', pl[0]);
    canvas.setAttribute('data-de-label', pl[1]);
    canvas.setAttribute('aria-label', TW.t(pl[0], pl[1]));

    var dirty = isDirty();
    TW.text(dirtyBadge, dirty ? 'Not applied' : 'On the device', dirty ? 'Nicht übernommen' : 'Auf dem Gerät');
    dirtyBadge.style.background = dirty ? 'rgba(255,170,0,0.16)' : 'rgba(61,224,124,0.14)';
    dirtyBadge.style.color = dirty ? '#FFC44D' : '#3DE07C';
    var ok = canApply();
    applyBtn.style.cursor = ok ? 'pointer' : 'default';
    applyBtn.style.background = ok ? '#FFAA00' : '#1E252A';
    applyBtn.style.color = ok ? '#08090A' : '#8B949C';
    applyBtn.setAttribute('aria-disabled', ok ? 'false' : 'true');

    drawBar();
    drawFaces();
    drawSpotifyTiles();
    rovingTabs();
  }

  function drawBar() {
    TW.brightBar($('[data-bar]'), draft.bright);
  }

  function drawFaces() {
    var txt = '20:14', px = 2, gap = 1;
    [['small', 1], ['big', 2], ['seg', 2]].forEach(function (d) {
      var holder = $('[data-face="' + d[0] + '"]');
      if (!holder || holder.offsetParent === null) return;
      var c = holder.querySelector('canvas');
      var scale = d[1];
      var avail = holder.clientWidth || (holder.parentNode && holder.parentNode.clientWidth) || 240;
      var cols = Math.max(P.width(txt, scale) + 4, Math.floor((avail + gap) / (px + gap)));
      var rows = 7 * scale + 2;
      var g = P.grid(cols, rows);
      var x = Math.max(0, Math.round((cols - P.width(txt, scale)) / 2));
      var y = Math.max(0, Math.round((rows - 7 * scale) / 2));
      if (d[0] === 'seg') {
        for (var i = 0; i < txt.length; i++) {
          if (txt[i] === ':') continue;
          P.frame(g, x + i * 6 * scale, y, 5 * scale, 7 * scale, '#2A2000');
        }
      }
      P.text(g, x, y, txt, draft.clock.color, scale);
      P.paint(c, g, { px: px, gap: gap, off: pal.off, glow: 0.5 });
    });
  }

  /* ---------- Eingaben ---------- */
  function changed(path) {
    if (applyStatus.dataset.en) TW.text(applyStatus, '', '');
    if (path && path.indexOf('notes.line') === 0) noteT = performance.now();
    if (path === 'flight.alt' || path === 'flight.mil' || (path && path.indexOf('flight.u') === 0)) pushFilter();
    render();
    schedulePreview(path && (path.indexOf('notes.') === 0 || path === 'bright') ? 250 : 350);
  }

  $$('[data-set]').forEach(function (el) {
    var path = el.getAttribute('data-set');
    if (el.tagName === 'BUTTON') {
      el.addEventListener('click', function () {
        if (el.disabled) return;
        if (el.getAttribute('role') === 'switch') set(path, !get(path));
        else if (path === 'clock.h24') set(path, el.getAttribute('data-value') === '1');
        else set(path, el.getAttribute('data-value'));
        changed(path);
      });
      return;
    }
    var evt = el.tagName === 'SELECT' ? 'change' : 'input';
    el.addEventListener(evt, function () {
      var v = el.value;
      if (el.type === 'range') v = parseInt(v, 10) || 0;
      if (path === 'flight.pin') { v = v.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 12); if (el.value !== v) el.value = v; }
      if (path.indexOf('notes.line') === 0) v = v.slice(0, 21);
      if (el.type === 'color') v = v.toUpperCase();
      set(path, v);
      changed(path);
    });
  });

  $$('[data-rotation]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.disabled) return;
      var k = b.getAttribute('data-rotation');
      var i = draft.rotation.indexOf(k);
      if (i === -1) draft.rotation.push(k); else draft.rotation.splice(i, 1);
      changed('rotation');
    });
  });

  /* ---------- Ansichten waehlen ----------
     Vier Vorschauen nebeneinander, jede rechnet der Server mit genau dieser einen Ansicht
     und den Daten von jetzt. Der Dialog aendert nur den Entwurf, gespeichert wird wie alles
     andere mit "Uebernehmen". Die Seite dahinter bekommt inert, Tab bleibt im Dialog. */
  var VIEW_ORDER = ['route', 'progress', 'position', 'metrics', 'map'];
  var VIEW_NAMES = {
    route: ['Route', 'Route'], progress: ['Departure and arrival', 'Abflug und Ankunft'],
    position: ['Position', 'Position'], metrics: ['Metrics', 'Messwerte'], map: ['Map', 'Karte']
  };

  function views() {
    if (!Array.isArray(draft.flight.views) || !draft.flight.views.length) draft.flight.views = VIEW_ORDER.slice();
    return draft.flight.views;
  }
  function viewsSummary() {
    var el = $('[data-views-summary]');
    if (!el) return;
    var on = VIEW_ORDER.filter(function (v) { return views().indexOf(v) !== -1; });
    if (on.length === VIEW_ORDER.length) TW.text(el, 'All four, one after the other.', 'Alle vier, eine nach der anderen.');
    else if (on.length === 1) TW.text(el, 'Only ' + VIEW_NAMES[on[0]][0] + ', no change.', 'Nur ' + VIEW_NAMES[on[0]][1] + ', kein Wechsel.');
    else TW.text(el, on.map(function (v) { return VIEW_NAMES[v][0]; }).join(', ') + '.', on.map(function (v) { return VIEW_NAMES[v][1]; }).join(', ') + '.');
  }

  /* Ein Fenster mit grossen Vorschauen. Zweimal benutzt: einmal fuer die Ansichten des
     Flugmodus, einmal fuer die Modi selbst. Jede Kachel holt sich ihr Bild einzeln vom
     Server (/api/preview mit dem passenden Entwurf) und zeichnet es mit ops.js, also
     genau das, was das Panel bekaeme. Die Seite dahinter bekommt inert, Tab bleibt drin,
     Escape schliesst, der Fokus geht zurueck auf den Knopf. */
  function makePicker(root, openSel, o) {
    var openBtns = $$(openSel);
    if (!root || !openBtns.length) return null;
    var tiles = $$('[' + o.canvasAttr + ']', root).map(function (c) {
      return { id: c.getAttribute(o.canvasAttr), canvas: c, box: c.parentNode };
    });
    var timer = null, seq = 0, locked = [], opener = null;

    function note(id, en, de) {
      var el = $('[' + o.noteAttr + '="' + id + '"]', root);
      if (el) TW.text(el, en, de);
    }
    /* Solange noch kein Bild da ist, steht das Wort LAEDT auf der Kachel. Schwarze
       Flaechen sehen aus, als waere etwas kaputt. */
    function placeholder(t) {
      if (t.gefuellt) return;
      var g = P.grid(128, 64);
      var wort = TW.lang() === 'de' ? 'LAEDT' : 'LOADING';
      P.text(g, Math.round((128 - P.width(wort, 1)) / 2), 28, wort, '#4E5A63', 1, true);
      P.paint(t.canvas, g, { px: TW.fit(t.box, 128, 6, 2), gap: 1, off: pal.off, glow: 0.4 });
    }
    /* Eine Anfrage nach der anderen. Parallel bringt nichts: der Server beantwortet sie
       ohnehin nacheinander, und dann steht jede Kachel schwarz, bis alle fertig sind.
       Gemessen am 20. September 2026: parallel 534 ms bis zum ersten Bild, nacheinander
       37 ms bis zur ersten Kachel und 188 ms bis zur letzten. */
    function fetchAll() {
      clearTimeout(timer);
      if (root.hidden) return;
      if (document.hidden) { timer = setTimeout(fetchAll, 2000); return; }
      var mine = ++seq;
      tiles.forEach(placeholder);
      var i = 0;
      var weiter = function () {
        if (mine !== seq) return;
        if (i >= tiles.length) {
          var now = new Date();
          var hh = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
          TW.text($(o.stateSel, root), 'Live, ' + hh, 'Live, ' + hh);
          timer = setTimeout(fetchAll, o.interval || 10000);
          return;
        }
        var t = tiles[i++];
        TW.text($(o.stateSel, root), 'Loading ' + i + '/' + tiles.length, 'Laedt ' + i + '/' + tiles.length);
        TW.api('/api/preview/' + D.id, { settings: o.draftFor(t.id), lang: TW.lang() }).then(function (res) {
          if (mine !== seq) return;
          if (res.ok && res.data.frame) {
            var f = res.data.frame;
            var page = window.TWOps.pageAt(f, f.now);
            var g = P.grid(128, 64);
            window.TWOps.render(g, page, {
              now: f.now, iana: draft.tz, lang: TW.lang(), logoUrl: '/api/logo/{code}', reduce: true, bright: f.bright
            });
            P.paint(t.canvas, g, { px: TW.fit(t.box, 128, 6, 2), gap: 1, off: pal.off, glow: 0.7 });
            t.gefuellt = true;
            var texts = o.noteFor(t.id, page) || ['', ''];
            note(t.id, texts[0], texts[1]);
          }
          weiter();
        });
      };
      weiter();
    }
    function lock(on) {
      if (on) {
        Array.prototype.forEach.call(document.body.children, function (c) {
          if (c === root || c.tagName === 'SCRIPT' || c.hasAttribute('inert')) return;
          c.setAttribute('inert', '');
          locked.push(c);
        });
        document.documentElement.style.overflow = 'hidden';
      } else {
        locked.forEach(function (c) { c.removeAttribute('inert'); });
        locked = [];
        document.documentElement.style.overflow = '';
      }
    }
    function show() {
      opener = document.activeElement;
      root.hidden = false;
      lock(true);
      if (o.onShow) o.onShow();
      fetchAll();
      /* Erst nach dem Sperren fokussieren: inert auf dem Knopf, der gerade den Fokus
         hatte, wirft ihn sonst auf den Seitenanfang zurueck. */
      setTimeout(function () { $(o.closeSel, root).focus(); }, 0);
    }
    function hide() {
      root.hidden = true;
      clearTimeout(timer);
      seq++;
      lock(false);
      if (opener && opener.focus && document.contains(opener)) opener.focus();
    }
    /* Den Dialog oeffnen koennen mehrere Knoepfe: einer in der Modi-Spalte, einer im
       Abschnitt des Modus. Zurueck geht der Fokus auf den, der ihn geoeffnet hat. */
    openBtns.forEach(function (b) {
      b.addEventListener('click', function () { if (!b.disabled) show(); });
    });
    $$(o.closeSel + ', ' + o.doneSel, root).forEach(function (b) { b.addEventListener('click', hide); });
    root.addEventListener('click', function (ev) { if (ev.target === root) hide(); });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !root.hidden) { ev.preventDefault(); hide(); }
    });
    root.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Tab') return;
      var f = $$('button:not(:disabled)', root);
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
      else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
    });
    document.addEventListener('visibilitychange', function () { if (!document.hidden && !root.hidden) fetchAll(); });
    return { refresh: fetchAll, open: show, close: hide, offen: function () { return !root.hidden; } };
  }

  /* Die Karte braucht bmp und dot, also Firmware 0.1.9. Aeltere Geraete bekommen sie vom
     Server gar nicht erst; im Fenster steht dann, woran es liegt. */
  function kartenAnsichtMoeglich() {
    var fw = String(D.fw || ''), need = String(D.mapFw || '0.1.9');
    if (!fw) return false;
    var a = fw.split('.').map(Number), b = need.split('.').map(Number);
    for (var i = 0; i < 3; i++) { if ((a[i] || 0) !== (b[i] || 0)) return (a[i] || 0) > (b[i] || 0); }
    return true;
  }

  var vp = $('[data-viewpicker]');
  var viewPicker = makePicker(vp, '[data-views-open]', {
    canvasAttr: 'data-vp-canvas', noteAttr: 'data-vp-note', stateSel: '[data-vp-state]',
    closeSel: '[data-vp-close]', doneSel: '[data-vp-done]', interval: 10000,
    draftFor: function (id) {
      var d = clone(draft);
      d.mode = 'flight';
      d.flight.views = [id];
      return d;
    },
    noteFor: function (id, page) {
      if (id === 'map' && !kartenAnsichtMoeglich()) {
        return ['The map needs firmware ' + (D.mapFw || '0.1.9') + '. The device has ' + (D.fw || 'none') + ', so it does not get this view yet.',
          'Die Karte braucht Firmware ' + (D.mapFw || '0.1.9') + '. Auf dem Gerät läuft ' + (D.fw || 'keine') + ', so lange bekommt es diese Ansicht nicht.'];
      }
      var pid = (page && page.id) || '';
      if (pid.indexOf('empty:') === 0) return ['Nothing is flying right now. This is what the panel shows instead.', 'Gerade fliegt nichts. Das zeigt das Panel stattdessen.'];
      if (pid.indexOf(':' + id) === -1) return ['This flight has no known route, so the panel shows another view.', 'Dieser Flug hat keine bekannte Route, deshalb zeigt das Panel eine andere Ansicht.'];
      return null;
    },
    onShow: function () {
      $$('[data-vp-view]', vp).forEach(function (b) {
        b.setAttribute('aria-checked', views().indexOf(b.getAttribute('data-vp-view')) !== -1 ? 'true' : 'false');
      });
    }
  });

  if (vp) {
    $$('[data-vp-view]', vp).forEach(function (b) {
      b.addEventListener('click', function () {
        var v = b.getAttribute('data-vp-view'), list = views(), i = list.indexOf(v);
        if (i === -1) draft.flight.views = VIEW_ORDER.filter(function (x) { return x === v || list.indexOf(x) !== -1; });
        else if (list.length > 1) draft.flight.views = list.filter(function (x) { return x !== v; });
        else return;                      /* die letzte Ansicht bleibt an, sonst bliebe das Panel leer */
        b.setAttribute('aria-checked', draft.flight.views.indexOf(v) !== -1 ? 'true' : 'false');
        changed('flight.views');
        if (viewPicker) viewPicker.refresh();
      });
    });
  }

  var modePicker = makePicker($('[data-modepicker]'), '[data-modes-open]', {
    canvasAttr: 'data-mp-canvas', noteAttr: 'data-mp-note', stateSel: '[data-mp-state]',
    closeSel: '[data-mp-close]', doneSel: '[data-mp-done]', interval: 15000,
    draftFor: function (id) {
      var d = clone(draft);
      d.mode = id;
      return d;
    },
    noteFor: function (id) {
      var m = modes && modes[id];
      if (m && m.available === false) return ['Not set up yet, so the panel would skip it.', 'Noch nicht eingerichtet, das Panel würde ihn überspringen.'];
      return null;
    }
  });
  /* Auswahl und Rotation im Fenster aendern den Entwurf ueber dieselben Knoepfe wie die
     Spalte daneben; danach sollen die Bilder dazu passen. */
  if (modePicker) {
    $$('[data-modepicker] [data-set="mode"], [data-modepicker] [data-rotation]').forEach(function (b) {
      b.addEventListener('click', function () { modePicker.refresh(); });
    });
  }

  $$('[data-colour]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.disabled) return;
      draft.clock.color = b.getAttribute('data-colour');
      changed('clock.color');
    });
  });

  /* ---------- Nahverkehr ----------
     Haltestellen, Namen und Linien kommen von mobiliteit.lu oder aus der Eingabe:
     alles nur als textContent. Die Liste der gewaehlten Haltestellen wird nur neu
     gebaut, wenn sich die Auswahl aendert, sonst verloere das Namensfeld beim
     Tippen den Fokus. */
  var T_MODES = ['train', 'tram', 'bus'];
  var T_TINT = { bus: '#FFAA00', train: '#35D6FF', tram: '#3DE07C' };
  var T_WORD = { bus: ['BUS', 'BUS'], train: ['TRAIN', 'ZUG'], tram: ['TRAM', 'TRAM'] };
  var T_MAX = 3;
  var MONO = "font-family:'IBM Plex Mono',monospace;";
  var tStopsBox = $('[data-t-stops]');
  var tNearBox = $('[data-t-near]');
  var tFind = $('[data-t-find]');
  var tFindStatus = $('[data-t-find-status]');
  var tLinesWrap = $('[data-t-lines-wrap]');
  var tLinesBox = $('[data-t-lines]');
  var tLinesToggle = $('[data-t-lines-toggle]');
  var tNear = null, tNearKey = '', tFinding = false, tStopsSig = null, tNearSig = null, tLinesSig = null;

  function el(tag, style, text) {
    var n = document.createElement(tag);
    if (style) n.setAttribute('style', style);
    if (text != null) n.textContent = text;
    return n;
  }
  function isSchool(code) { return /^[A-Z]\d{2}$/.test(String(code)); }
  /* Der Kopf zeigt den Namen neben einem Quadrat je Verkehrsmittel: 18 Zeichen, mit drei
     Quadraten 17 (transit_header() auf dem Server). Mehr erlaubt das Feld nicht. */
  function shortMax() {
    var t = draft.transit, present = {};
    t.stops.forEach(function (s) { (s.modes || []).forEach(function (m) { present[m] = true; }); });
    var ticks = T_MODES.filter(function (m) { return present[m] && t.modes.indexOf(m) !== -1; }).length;
    return ticks >= 3 ? 17 : 18;
  }
  function modeWords(modes) {
    var list = modes || [];
    return [list.map(function (m) { return (T_WORD[m] || [m])[0]; }).join(', '), list.map(function (m) { return (T_WORD[m] || [m, m])[1]; }).join(', ')];
  }
  function square(modes) {
    return el('span', 'width:9px;height:9px;border-radius:1px;flex:0 0 auto;background:' + (T_TINT[(modes || [])[0]] || T_TINT.bus));
  }
  function byCode(a, b) { return a.localeCompare(b, 'en', { numeric: true }); }

  function stopRow(stop, i) {
    var row = el('div', 'display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:14px;background:#0B0D0F');
    row.appendChild(square(stop.modes));
    var info = el('div', 'flex:1 1 200px;min-width:0');
    var n = (stop.lines || []).length, w = modeWords(stop.modes);
    info.appendChild(el('p', 'margin:0 0 2px;font-size:14px;color:#E8EAEC', stop.name));
    info.appendChild(el('p', 'margin:0;' + MONO + 'font-size:11px;color:#8B949C', TW.t(w[0] + ' · ' + n + (n === 1 ? ' line' : ' lines'), w[1] + ' · ' + n + (n === 1 ? ' Linie' : ' Linien'))));
    row.appendChild(info);

    var field = el('div', 'flex:0 1 190px;min-width:150px');
    var head = el('div', 'display:flex;align-items:baseline;gap:8px;margin-bottom:5px');
    var label = el('label', 'flex:1;' + MONO + 'font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:#8B949C', TW.t('Name on the panel', 'Name auf dem Panel'));
    label.htmlFor = 't-short-' + stop.id;
    var left = el('span', MONO + 'font-size:10px;font-variant-numeric:tabular-nums;color:#8B949C');
    left.setAttribute('data-t-left', String(i));
    head.appendChild(label);
    head.appendChild(left);
    var input = el('input', 'min-height:44px;padding:11px 12px;font-size:13px;' + MONO);
    input.className = 'field';
    input.type = 'text';
    input.id = 't-short-' + stop.id;
    input.maxLength = shortMax();
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.value = stop.short || '';
    input.disabled = readOnly;
    input.setAttribute('data-t-short', String(i));
    field.appendChild(head);
    field.appendChild(input);
    row.appendChild(field);

    var remove = el('button', 'min-height:44px;padding:0 12px;border:0;background:transparent;cursor:pointer;' + MONO + 'font-size:11px;letter-spacing:0.06em;text-transform:uppercase;color:#8B949C;transition:color 160ms ease', TW.t('Remove', 'Entfernen'));
    remove.type = 'button';
    remove.className = 'h-bright';
    remove.disabled = readOnly;
    remove.setAttribute('aria-label', TW.t('Remove ', 'Entfernen: ') + stop.name);
    remove.setAttribute('data-t-remove', String(i));
    row.appendChild(remove);
    return row;
  }

  function nearRow(stop) {
    var row = el('div', 'display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:12px 14px;background:#0B0D0F');
    row.appendChild(square(stop.modes));
    var info = el('div', 'flex:1 1 220px;min-width:0');
    var n = (stop.lines || []).length, w = modeWords(stop.modes);
    var dist = stop.dist_m == null ? '' : stop.dist_m + ' m · ';
    info.appendChild(el('p', 'margin:0 0 2px;font-size:14px;color:#E8EAEC', stop.name));
    info.appendChild(el('p', 'margin:0;' + MONO + 'font-size:11px;color:#8B949C', TW.t(dist + w[0] + ' · ' + n + (n === 1 ? ' line' : ' lines'), dist + w[1] + ' · ' + n + (n === 1 ? ' Linie' : ' Linien'))));
    row.appendChild(info);
    var chosen = draft.transit.stops.some(function (s) { return s.id === stop.id; });
    var add = el('button', null, chosen ? TW.t('Added', 'Gewählt') : TW.t('Add', 'Hinzufügen'));
    add.type = 'button';
    add.className = 'btn-ghost';
    add.disabled = readOnly || chosen || draft.transit.stops.length >= T_MAX;
    add.setAttribute('aria-label', (chosen ? TW.t('Added: ', 'Gewählt: ') : TW.t('Add ', 'Hinzufügen: ')) + stop.name);
    add.setAttribute('data-t-add', stop.id);
    row.appendChild(add);
    return row;
  }

  function renderTransit() {
    if (!tStopsBox || !draft.transit) return;
    var t = draft.transit;
    TW.text($('[data-t-count]'), t.stops.length + ' of ' + T_MAX, t.stops.length + ' von ' + T_MAX);

    var sig = t.stops.map(function (s) { return s.id; }).join(',') + '|' + TW.lang();
    if (sig !== tStopsSig) {
      tStopsSig = sig;
      pushStopsMap();
      while (tStopsBox.firstChild) tStopsBox.removeChild(tStopsBox.firstChild);
      if (!t.stops.length) {
        tStopsBox.appendChild(el('p', 'margin:0;padding:14px;background:#0B0D0F;font-size:13.5px;color:#8B949C', TW.t('No stop chosen yet. Pick one on the map above, or search near the device below.', 'Noch keine Haltestelle gewählt. Wähle eine auf der Karte oben, oder suche unten in der Nähe des Geräts.')));
      }
      t.stops.forEach(function (s, i) { tStopsBox.appendChild(stopRow(s, i)); });
    }
    var limit = shortMax();
    $$('[data-t-short]', tStopsBox).forEach(function (input) {
      var i = input.getAttribute('data-t-short');
      var s = t.stops[Number(i)];
      if (!s) return;
      if (input.maxLength !== limit) input.maxLength = limit;
      if (document.activeElement !== input && input.value !== s.short) input.value = s.short;
      var left = limit - String(s.short || '').length;
      var c = $('[data-t-left="' + i + '"]', tStopsBox);
      if (c) { TW.text(c, left + ' left', left + ' frei'); c.style.color = left <= 3 ? '#FFAA00' : '#8B949C'; }
    });

    if (tFind) {
      var place = String(draft.location.place || '');
      TW.text(tFind, tFinding ? 'Searching ...' : 'Stops near ' + place, tFinding ? 'Suche läuft ...' : 'Haltestellen bei ' + place);
    }
    var nearSig = tNear ? tNearKey + '|' + sig + '|' + t.stops.length : '';
    if (nearSig !== tNearSig) {
      tNearSig = nearSig;
      while (tNearBox.firstChild) tNearBox.removeChild(tNearBox.firstChild);
      (tNear || []).forEach(function (s) { tNearBox.appendChild(nearRow(s)); });
      tNearBox.hidden = !(tNear && tNear.length);
    }

    $$('[data-t-mode]').forEach(function (b) {
      b.setAttribute('aria-pressed', t.modes.indexOf(b.getAttribute('data-t-mode')) !== -1 ? 'true' : 'false');
    });

    var codes = [];
    t.stops.forEach(function (s) {
      (s.lines || []).forEach(function (l) {
        if (t.modes.indexOf(l.mode) === -1 || (!t.school && isSchool(l.code)) || codes.indexOf(l.code) !== -1) return;
        codes.push(l.code);
      });
    });
    codes.sort(byCode);
    if (tLinesWrap) {
      tLinesWrap.hidden = !codes.length;
      var lsig = codes.join(',') + '|' + (readOnly ? 1 : 0);
      if (lsig !== tLinesSig) {
        tLinesSig = lsig;
        while (tLinesBox.firstChild) tLinesBox.removeChild(tLinesBox.firstChild);
        codes.forEach(function (code) {
          var b = el('button', null, code);
          b.type = 'button';
          b.className = 'chip line';
          b.disabled = readOnly;
          b.setAttribute('data-t-line', code);
          tLinesBox.appendChild(b);
        });
      }
      $$('[data-t-line]', tLinesBox).forEach(function (b) {
        b.setAttribute('aria-pressed', t.hide.indexOf(b.getAttribute('data-t-line')) === -1 ? 'true' : 'false');
      });
      var open = tLinesToggle.getAttribute('aria-expanded') === 'true';
      var hidden = codes.filter(function (c) { return t.hide.indexOf(c) !== -1; }).length;
      TW.text(tLinesToggle,
        (open ? 'Close list' : 'Choose lines') + ' · ' + (codes.length - hidden) + ' of ' + codes.length,
        (open ? 'Liste schließen' : 'Linien wählen') + ' · ' + (codes.length - hidden) + ' von ' + codes.length);
    }

    var rows = Number(t.rows);
    $$('[data-out="t-rows"]').forEach(function (o) { o.textContent = String(rows); });
    $$('[data-out="t-walk"]').forEach(function (o) { o.textContent = String(t.walk); });
    var rn = rows >= 5
      ? ['Five rows fill the panel. The bottom strip goes, so notes and the clock have nowhere to sit.', 'Fünf Zeilen füllen das Panel. Der Streifen unten fällt weg, Meldungen und Uhr haben dann keinen Platz.']
      : rows === 3
        ? ['Three rows with more air. Easiest to read from two metres away.', 'Drei Zeilen, größerer Abstand. Aus zwei Metern Entfernung am besten lesbar.']
        : ['Four rows plus the bottom strip. The recommendation.', 'Vier Zeilen plus Streifen unten. Der Vorschlag.'];
    TW.text($('[data-t-rows-note]'), rn[0], rn[1]);
  }

  if (tStopsBox) {
    tStopsBox.addEventListener('input', function (ev) {
      var i = ev.target.getAttribute('data-t-short');
      if (i == null || readOnly) return;
      var s = draft.transit.stops[Number(i)];
      if (!s) return;
      s.short = ev.target.value.slice(0, shortMax());
      changed('transit.stops');
    });
    tStopsBox.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-t-remove]');
      if (!b || b.disabled) return;
      draft.transit.stops.splice(Number(b.getAttribute('data-t-remove')), 1);
      changed('transit.stops');
      if (tFind) tFind.focus();
    });
    tNearBox.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-t-add]');
      if (!b || b.disabled) return;
      var id = b.getAttribute('data-t-add');
      var found = (tNear || []).filter(function (s) { return s.id === id; })[0];
      if (!found || draft.transit.stops.length >= T_MAX || draft.transit.stops.some(function (s) { return s.id === id; })) return;
      draft.transit.stops.push({ id: found.id, name: found.name, short: found.short, modes: found.modes.slice(), lines: found.lines.slice() });
      changed('transit.stops');
      var input = document.getElementById('t-short-' + id);
      if (input) input.focus();
    });
    tFind.addEventListener('click', function () {
      if (tFinding || readOnly) return;
      tFinding = true;
      renderTransit();
      var place = String(draft.location.place || '');
      TW.api('/api/transit/stops', { lat: Number(draft.location.lat), lon: Number(draft.location.lon) }).then(function (res) {
        tFinding = false;
        if (!res.ok) {
          var e = TW.errorText(res, 'mobiliteit.lu is not answering right now.', 'mobiliteit.lu antwortet gerade nicht.');
          TW.flash(tFindStatus, e[0], e[1], 0, '#FF7A54');
        } else {
          tNear = res.data.stops || [];
          tNearKey = String(Date.now());
          var n = tNear.length;
          TW.flash(tFindStatus,
            n ? n + (n === 1 ? ' stop' : ' stops') + ' within a kilometre of ' + place + ', nearest first.' : 'No stop within a kilometre of ' + place + '.',
            n ? n + (n === 1 ? ' Haltestelle' : ' Haltestellen') + ' im Umkreis von einem Kilometer um ' + place + ', die nächste zuerst.' : 'Keine Haltestelle im Umkreis von einem Kilometer um ' + place + '.',
            0, '#8B949C');
        }
        renderTransit();
      });
    });
    $$('[data-t-mode]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (b.disabled) return;
        var m = b.getAttribute('data-t-mode');
        var on = draft.transit.modes.indexOf(m) !== -1;
        draft.transit.modes = T_MODES.filter(function (x) { return x === m ? !on : draft.transit.modes.indexOf(x) !== -1; });
        changed('transit.modes');
      });
    });
    tLinesBox.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-t-line]');
      if (!b || b.disabled) return;
      var code = b.getAttribute('data-t-line');
      var hide = draft.transit.hide.filter(function (c) { return c !== code; });
      if (hide.length === draft.transit.hide.length) hide.push(code);
      draft.transit.hide = hide.sort(byCode);
      changed('transit.hide');
    });
    tLinesToggle.addEventListener('click', function () {
      var open = tLinesToggle.getAttribute('aria-expanded') !== 'true';
      tLinesToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      tLinesBox.hidden = !open;
      renderTransit();
    });
  }

  /* ---------- Haltestellenkarte ----------
     Eigene Seite im iframe wie die Standortkarte. Sie meldet stop-toggle mit der ID,
     die Geraeteseite holt Name, Kurzname und Linien vom Server und schickt die
     Auswahl zurueck. Nur das eigene Kartenfenster derselben Herkunft zaehlt. */
  var tMapFrame = null, tMapReady = false;
  function pushStopsMap() {
    if (!tMapFrame || !tMapReady || !tMapFrame.contentWindow) return;
    var w = tMapFrame.contentWindow, o = location.origin;
    w.postMessage({ type: 'lang', lang: TW.lang() }, o);
    w.postMessage({ type: 'center', lat: Number(draft.location.lat), lon: Number(draft.location.lon) }, o);
    w.postMessage({ type: 'chosen', ids: draft.transit.stops.map(function (s) { return s.id; }), max: T_MAX, readOnly: readOnly }, o);
  }
  function toggleStop(id) {
    var list = draft.transit.stops;
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === id) {
        list.splice(i, 1);
        changed('transit.stops');
        return;
      }
    }
    if (list.length >= T_MAX) { pushStopsMap(); return; }
    TW.api('/api/transit/stop', { id: id }).then(function (res) {
      var st = res.ok && res.data ? res.data.stop : null;
      if (!st) {
        var e = TW.errorText(res, 'That stop could not be added.', 'Diese Haltestelle ließ sich nicht hinzufügen.');
        TW.flash(tFindStatus, e[0], e[1], 0, '#FF7A54');
        pushStopsMap();
        return;
      }
      if (draft.transit.stops.length >= T_MAX || draft.transit.stops.some(function (s) { return s.id === st.id; })) { pushStopsMap(); return; }
      draft.transit.stops.push({ id: st.id, name: st.name, short: st.short, modes: st.modes.slice(), lines: st.lines.slice() });
      changed('transit.stops');
    });
  }
  var tLoadMap = $('[data-t-load-map]');
  if (tLoadMap) {
    tLoadMap.addEventListener('click', function () {
      if (tMapFrame) return;
      tMapFrame = document.createElement('iframe');
      tMapFrame.src = '/map/stops';
      tMapFrame.title = TW.t('Map of every stop, with search', 'Karte aller Haltestellen, mit Suche');
      tMapFrame.setAttribute('style', 'width:100%;height:clamp(340px,42vw,480px);border:1px solid #1B2126;border-radius:2px;display:block;margin-bottom:16px;background:#050607');
      $('[data-t-map-slot]').appendChild(tMapFrame);
      $('[data-t-map-pending]').hidden = true;
    });
  }
  window.addEventListener('message', function (ev) {
    if (!tMapFrame || ev.source !== tMapFrame.contentWindow || ev.origin !== location.origin) return;
    var d = ev.data;
    if (!d || typeof d !== 'object') return;
    if (d.type === 'stops-ready') { tMapReady = true; pushStopsMap(); }
    if (d.type === 'stop-toggle' && typeof d.id === 'string' && /^\d{3,12}$/.test(d.id) && !readOnly) toggleStop(d.id);
  });

  /* ---------- Uebernehmen und Verwerfen ---------- */
  /* Waehrend der Anfrage nicht disabled setzen: ein deaktivierter Knopf verliert den Fokus. */
  var applying = false;
  applyBtn.addEventListener('click', function () {
    if (applying || !canApply()) return;
    applying = true;
    applyBtn.setAttribute('aria-busy', 'true');
    TW.api('/api/device/' + D.id + '/apply', { settings: changes(), lang: TW.lang() }).then(function (res) {
      applying = false;
      applyBtn.removeAttribute('aria-busy');
      if (!res.ok) {
        var e = TW.errorText(res);
        TW.flash(applyStatus, e[0], e[1], 0, '#FF7A54');
        return;
      }
      var keepMode = draft.mode;
      applied = clone(res.data.settings);
      draft = clone(res.data.settings);
      if (readOnly) draft.mode = keepMode;
      neverApplied = false;
      setNoteLeft(res.data.noteLeft);
      TW.flash(applyStatus, 'Sent to the device.', 'An das Gerät geschickt.', 3200, '#3DE07C');
      appliedAt = performance.now();
      render();
      schedulePreview(0);
    });
  });

  $('[data-revert]').addEventListener('click', function () {
    draft = clone(applied);
    TW.text(applyStatus, '', '');
    render();
    pushMap();
    schedulePreview(0);
  });

  /* ---------- Notiz vorn ----------
     Eine neue Notiz steht bis zu zehn Minuten vor dem gewaehlten Modus. Die Vorschau zeigt den
     Modus, das Panel die Notiz: der Satz unter der Vorschau sagt das, der Knopf blendet sie aus.
     Einen neuen Modus zu uebernehmen, beendet den Vorrang ebenfalls (api_apply). */
  var noteBox = $('[data-note-front]'), noteText = $('[data-note-front-text]'), noteHide = $('[data-note-hide]');
  var noteLeft = Number(D.noteLeft) || 0, noteLeftAt = performance.now(), noteHiding = false;
  function setNoteLeft(s) {
    noteLeft = Number(s) || 0;
    noteLeftAt = performance.now();
    renderNoteFront();
  }
  function renderNoteFront() {
    if (!noteBox) return;
    var left = applied.mode === 'notes' ? 0 : Math.max(0, noteLeft - (performance.now() - noteLeftAt) / 1000);
    if (left <= 0) {
      if (noteText.textContent !== '') TW.text(noteText, '', '');
      if (!noteHide.hidden && document.activeElement === noteHide) applyBtn.focus();
      noteHide.hidden = true;
      noteBox.style.marginTop = '0';
      return;
    }
    var min = Math.max(1, Math.ceil(left / 60));
    TW.text(noteText,
      'The panel is showing the new note for about ' + min + (min === 1 ? ' more minute' : ' more minutes') + ', then the chosen mode comes back.',
      'Auf dem Panel steht noch etwa ' + min + (min === 1 ? ' Minute' : ' Minuten') + ' die neue Notiz, danach wieder der gewählte Modus.');
    noteHide.hidden = false;
    noteBox.style.marginTop = '16px';
  }
  if (noteHide) {
    noteHide.addEventListener('click', function () {
      if (noteHiding) return;
      noteHiding = true;
      noteHide.setAttribute('aria-busy', 'true');
      TW.api('/api/device/' + D.id + '/note/hide', {}).then(function (res) {
        noteHiding = false;
        noteHide.removeAttribute('aria-busy');
        if (!res.ok) {
          var e = TW.errorText(res);
          TW.flash(applyStatus, e[0], e[1], 0, '#FF7A54');
          return;
        }
        setNoteLeft(0);
        TW.flash(applyStatus, 'The panel shows the chosen mode again.', 'Das Panel zeigt wieder den gewählten Modus.', 3200, '#3DE07C');
      });
    });
  }
  setInterval(renderNoteFront, 15000);
  renderNoteFront();

  /* ---------- Timer, Wecker, Klingeln ----------
     Gelten sofort, ohne "Uebernehmen". Ein laufender Timer steht in jedem Modus in einer Ecke
     des Panels, die Vorschau zeigt sie auch. Klingelt etwas, gehoert dem Klingeln das ganze
     Panel: der Satz unter der Vorschau sagt das, der Knopf stoppt es. Die Restzeit rechnet der
     Browser mit der Uhr des Servers (skew), sonst ginge sie bei falsch gestellter Uhr nach. */
  var ring = D.ring || { timers: [], alarm: null, now: Math.floor(Date.now() / 1000) };
  var skew = (Number(ring.now) || Date.now() / 1000) * 1000 - Date.now();
  var timerList = $('[data-timer-list]'), timerError = $('[data-timer-error]');
  var timerMin = $('#tw-min'), timerLabel = $('#tw-label');
  var ringText = $('[data-ring-front-text]'), ringStop = $('[data-ring-stop]'), ringBox = $('[data-ring-front]');
  var alarmSwitch = $('[data-alarm-on]'), alarmTime = $('#al-time'), alarmNext = $('[data-alarm-next]'), alarmError = $('[data-alarm-error]');
  var ringCheck = null, timerBusy = false;

  function nowS() { return (Date.now() + skew) / 1000; }
  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  function leftText(s) {
    s = Math.max(0, Math.ceil(s));
    var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
    return (h ? h + ':' + pad2(m) : String(m)) + ':' + pad2(s % 60);
  }
  function ariaPair(el, en, de) {
    el.setAttribute('data-en-label', en);
    el.setAttribute('data-de-label', de);
    el.setAttribute('aria-label', TW.lang() === 'de' ? de : en);
  }
  function timersRunning() {
    var n = nowS();
    return (ring.timers || []).filter(function (t) { return t.end > n; });
  }
  function ringingTimers() {
    var n = nowS();
    return (ring.timers || []).filter(function (t) { return t.end <= n; });
  }

  function setRing(r) {
    if (!r || !r.timers) return;
    var before = timersRunning().length;
    ring = r;
    if (r.now) skew = r.now * 1000 - Date.now();
    renderTimers();
    renderAlarm();
    renderRing();
    scheduleRingCheck();
    // Die Ecke in der Vorschau kommt und geht mit dem Timer.
    if (timersRunning().length !== before) schedulePreview(0);
  }

  function renderTimers() {
    if (!timerList) return;
    var list = timersRunning();
    var ids = list.map(function (t) { return t.id; }).join(',');
    if (timerList.getAttribute('data-ids') !== ids) {
      timerList.setAttribute('data-ids', ids);
      timerList.textContent = '';
      timerList.style.marginTop = list.length ? '14px' : '6px';
      list.forEach(function (t) {
        var li = document.createElement('li');
        li.style.cssText = 'display:flex;align-items:center;gap:12px;padding:8px 8px 8px 12px;background:#0B0D0F';
        var name = document.createElement('span');
        name.style.cssText = 'flex:1;min-width:0;font-size:14px;color:#E8EAEC;overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
        name.textContent = t.label || 'Timer';
        var left = document.createElement('span');
        left.setAttribute('data-left', String(t.end));
        left.style.cssText = "font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;color:#3DE07C;font-variant-numeric:tabular-nums";
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'btn-subtle';
        TW.text(cancel, 'Cancel', 'Abbrechen');
        ariaPair(cancel, 'Cancel timer ' + (t.label || ''), 'Timer ' + (t.label || '') + ' abbrechen');
        cancel.addEventListener('click', function () { timerCancel(t.id); });
        li.appendChild(name);
        li.appendChild(left);
        li.appendChild(cancel);
        timerList.appendChild(li);
      });
    }
    var n = nowS();
    $$('[data-left]', timerList).forEach(function (el) {
      el.textContent = leftText(Number(el.getAttribute('data-left')) - n);
    });
  }

  function renderRing() {
    if (!ringText) return;
    var timers = ringingTimers();
    var alarmOn = ring.alarm && ring.alarm.ringing;
    if (!timers.length && !alarmOn) {
      if (ringText.textContent !== '') TW.text(ringText, '', '');
      if (ringStop && !ringStop.hidden && document.activeElement === ringStop) applyBtn.focus();
      if (ringStop) ringStop.hidden = true;
      ringBox.style.marginTop = '0';
      return;
    }
    if (alarmOn) {
      TW.text(ringText, 'The alarm is ringing. The whole panel shows it until you stop it, at most 15 minutes.', 'Der Wecker klingelt. Das ganze Panel zeigt es, bis du stoppst, höchstens 15 Minuten.');
    } else {
      var name = timers[0].label ? ' ' + timers[0].label : '';
      TW.text(ringText, 'Timer' + name + ' has run out. The whole panel shows it until you stop it, at most 15 minutes.', 'Timer' + name + ' ist abgelaufen. Das ganze Panel zeigt es, bis du stoppst, höchstens 15 Minuten.');
    }
    if (ringStop) ringStop.hidden = readOnly;
    ringBox.style.marginTop = '16px';
  }

  function renderAlarm() {
    var a = ring.alarm;
    if (!a || !alarmSwitch) return;
    alarmSwitch.setAttribute('aria-checked', a.on ? 'true' : 'false');
    if (document.activeElement !== alarmTime && alarmTime.value !== a.time) alarmTime.value = a.time;
    $$('[data-alarm-day]').forEach(function (b) {
      b.setAttribute('aria-pressed', a.days.indexOf(Number(b.getAttribute('data-alarm-day'))) !== -1 ? 'true' : 'false');
    });
    if (!a.on || !a.next) {
      TW.text(alarmNext, 'Off.', 'Aus.');
      alarmNext.style.color = '#8B949C';
      return;
    }
    var d = new Date(a.next * 1000), tz = draft.tz || applied.tz || 'UTC', f = function (l) {
      try {
        return d.toLocaleString(l === 'de' ? 'de-DE' : 'en-GB', { weekday: 'long', hour: '2-digit', minute: '2-digit', timeZone: tz });
      } catch (e) {
        return d.toLocaleString();
      }
    };
    TW.text(alarmNext, 'Next ring: ' + f('en') + '.', 'Klingelt als Nächstes: ' + f('de') + '.');
    alarmNext.style.color = '#FFAA00';
  }

  /* Klingeln kommt ohne Zutun: kurz nach dem naechsten Ende oder der Weckzeit nachfragen. */
  function scheduleRingCheck() {
    clearTimeout(ringCheck);
    var n = nowS(), next = null;
    timersRunning().forEach(function (t) { if (next === null || t.end < next) next = t.end; });
    if (ring.alarm && ring.alarm.on && ring.alarm.next && (next === null || ring.alarm.next < next)) next = ring.alarm.next;
    // Klingelt gerade etwas, hoert es nach 15 Minuten von selbst auf: dann auch nachsehen.
    if (next === null || next - n > 3600) return;
    ringCheck = setTimeout(refreshStatus, Math.max(1000, (next - n) * 1000 + 1200));
  }

  function timerStart(seconds, name) {
    if (timerBusy) return;
    if (!(seconds >= 1 && seconds <= 86400)) {
      TW.text(timerError, 'Minutes from 1 to 1440, please.', 'Bitte Minuten von 1 bis 1440.');
      timerMin.focus();
      return;
    }
    timerBusy = true;
    TW.text(timerError, '', '');
    TW.api('/api/device/' + D.id + '/timer', { seconds: seconds, label: name || '' }).then(function (res) {
      timerBusy = false;
      if (!res.ok) {
        var e = TW.errorText(res);
        TW.text(timerError, e[0], e[1]);
        return;
      }
      setRing(res.data);
      TW.flash(applyStatus, 'Timer started. It shows in a corner of the panel.', 'Timer läuft. Er steht in einer Ecke des Panels.', 3200, '#3DE07C');
    });
  }
  function timerCancel(id) {
    TW.api('/api/device/' + D.id + '/timer/cancel', { id: id }).then(function (res) {
      if (!res.ok) {
        var e = TW.errorText(res);
        TW.text(timerError, e[0], e[1]);
        return;
      }
      setRing(res.data);
      if (timerMin) timerMin.focus();
    });
  }

  $$('[data-timer-start]').forEach(function (b) {
    b.addEventListener('click', function () { timerStart(Number(b.getAttribute('data-timer-start')), timerLabel ? timerLabel.value.trim() : ''); });
  });
  var timerGo = $('[data-timer-go]');
  if (timerGo) {
    var goFromInput = function () { timerStart(Math.round(Number(timerMin.value) * 60), timerLabel.value.trim()); };
    timerGo.addEventListener('click', goFromInput);
    [timerMin, timerLabel].forEach(function (el) {
      el.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); goFromInput(); } });
    });
  }
  if (ringStop) {
    ringStop.addEventListener('click', function () {
      TW.api('/api/device/' + D.id + '/ring/stop', {}).then(function (res) {
        if (!res.ok) {
          var e = TW.errorText(res);
          TW.flash(applyStatus, e[0], e[1], 0, '#FF7A54');
          return;
        }
        setRing(res.data);
        TW.flash(applyStatus, 'Stopped. The panel shows the chosen mode again.', 'Gestoppt. Das Panel zeigt wieder den gewählten Modus.', 3200, '#3DE07C');
      });
    });
  }

  function alarmSave(change) {
    TW.text(alarmError, '', '');
    TW.api('/api/device/' + D.id + '/alarm', change).then(function (res) {
      if (!res.ok) {
        var e = TW.errorText(res);
        TW.text(alarmError, e[0], e[1]);
        renderAlarm();
        if (change.time !== undefined) alarmTime.focus();
        return;
      }
      setRing(res.data);
    });
  }
  if (alarmSwitch) {
    alarmSwitch.addEventListener('click', function () { alarmSave({ on: alarmSwitch.getAttribute('aria-checked') !== 'true' }); });
    alarmTime.addEventListener('change', function () { if (alarmTime.value) alarmSave({ time: alarmTime.value }); });
    $$('[data-alarm-day]').forEach(function (b) {
      b.addEventListener('click', function () {
        var days = ring.alarm.days.slice(), d = Number(b.getAttribute('data-alarm-day')), i = days.indexOf(d);
        if (i === -1) days.push(d); else days.splice(i, 1);
        if (!days.length) {
          TW.text(alarmError, 'Pick at least one day.', 'Mindestens einen Tag wählen.');
          return;
        }
        alarmSave({ days: days });
      });
    });
  }

  /* Restzeit jede Sekunde, solange ein Timer laeuft und die Seite sichtbar ist. */
  setInterval(function () {
    if (document.hidden || !(ring.timers || []).length) return;
    renderTimers();
    renderRing();
  }, 1000);
  TW.onLang(function () { renderAlarm(); renderRing(); });
  renderTimers();
  renderAlarm();
  renderRing();
  scheduleRingCheck();

  /* ---------- Vorschau ---------- */
  function schedulePreview(ms) {
    clearTimeout(fetchTimer);
    editPending = true;
    fetchTimer = setTimeout(fetchPreview, ms);
  }

  var previewWaiting = false;
  function fetchPreview() {
    clearTimeout(refreshTimer);
    /* Unsichtbar wartet die Vorschau, jede Anfrage baut den Frame samt Diensten (Befund C5). */
    if (document.hidden) { previewWaiting = true; return; }
    var mine = ++seq;
    TW.api('/api/preview/' + D.id, { settings: draft, lang: TW.lang() }).then(function (res) {
      if (mine !== seq) return;
      if (res.ok && res.data.frame) {
        frame = res.data.frame;
        offset = frame.now - Date.now();
        if (editPending) { editCut = true; trans = null; }
        editPending = false;
      }
      refreshTimer = setTimeout(fetchPreview, ((frame && frame.ttl) || 10) * 1000);
    });
  }

  function drawPreview() {
    var g = P.grid(128, 64);
    var nowMs = running ? Date.now() + offset : frozenMs;
    var t = running ? (performance.now() - t0) / 1000 : frozenT;
    if (frame) {
      var page = window.TWOps.pageAt(frame, nowMs);
      var age = noteT == null ? 999 : (performance.now() - noteT) / 1000;
      var flash = draft.notes.flash && running && age < 1.5 && Math.floor(age * 4) % 2 === 0;
      var still = !running || !!draft.reduce || TW.reduce;
      var ctx = {
        now: nowMs, iana: draft.tz, lang: TW.lang(), logoUrl: '/api/logo/{code}',
        flash: flash, t: t, reduce: still, bright: frame.bright
      };
      var A = window.TWAnim, pnow = performance.now();
      if (page && shown && lastGrid && !still && A && A.roll) {
        var fx = page.fx || '', bands = Array.isArray(page.bands) ? page.bands : null;
        /* Anderer Modus, auch gleich nach einem Klick: Einstieg wie auf dem Panel (push, beim
           Nahverkehr drop). Sonst nach einer Aenderung ein harter Schnitt, damit sie sofort steht. */
        if (page.mode && shown.mode && page.mode !== shown.mode) {
          var enter = fx || page.enter || 'push';
          trans = enter === 'drop' && bands ? { kind: 'drop', bands: bands, at: pnow } : { kind: 'push', from: lastGrid, at: pnow };
        } else if (!editCut && shown.from !== page.from) {
          if (fx === 'push' || fx === 'swap') trans = { kind: fx, from: lastGrid, at: pnow };
          else if ((fx === 'reel' || fx === 'carousel' || fx === 'lines') && page.fxw) {
            /* Spotify: die alte Seite wird weiter live gezeichnet, sonst stuenden zwei Laufschriften
               versetzt uebereinander. Der alte Balken laeuft leer, zum Zeitpunkt des Wechsels. */
            trans = { kind: fx, from: lastGrid, page: shownPage, w: page.fxw, at: pnow, prog: shownProg, freeze: nowMs };
          }
          else if (fx === 'drop' && bands) trans = { kind: 'drop', bands: bands, at: pnow };
          else if (!fx && bands && page.id && page.id === shown.id) trans = { kind: 'roll', from: lastGrid, bands: bands, at: pnow };
          else trans = null;
        }
      }
      if (page) {
        shown = { from: page.from, id: page.id || '', mode: page.mode || '' };
        shownProg = (page.ops || []).filter(function (o) { return o.t === 'prog'; })[0] || null;
        shownPage = page;
        editCut = false;
      }
      if (trans && !still && pnow - trans.at < FX_MS[trans.kind]) {
        var inc = P.grid(128, 64);
        window.TWOps.render(inc, page, ctx);
        var k = (pnow - trans.at) / FX_MS[trans.kind];
        if (trans.kind === 'push') A.push(g, trans.from, inc, k);
        else if (trans.kind === 'swap') A.swap(g, trans.from, inc, k);
        else if (trans.kind === 'reel' || trans.kind === 'carousel' || trans.kind === 'lines') {
          var old = trans.from;
          if (trans.page) { old = P.grid(128, 64); window.TWOps.render(old, trans.page, ctx); }
          if (trans.kind === 'lines') A.lines(g, old, inc, trans.w, k);
          else A.song(g, old, inc, trans.w, k, trans.kind, trans.prog, trans.freeze);
        }
        else if (trans.kind === 'drop') A.drop(g, inc, trans.bands, k);
        else A.roll(g, trans.from, inc, trans.bands, k);
      } else {
        trans = null;
        window.TWOps.render(g, page, ctx);
      }
      lastGrid = { w: 128, h: 64, data: g.data.slice() };
    }
    /* Nach "Uebernehmen" zwei Sekunden lang ein Balken unten: so lange braucht das Panel
       hoechstens (Revision alle zwei Sekunden). Nur in der Vorschau, nicht auf dem Geraet. */
    if (appliedAt !== null && running && !TW.reduce) {
      var since = performance.now() - appliedAt;
      if (since < 2000) {
        P.rect(g, 0, 61, 128, 2, '#14222B');
        P.rect(g, 0, 61, Math.round(128 * since / 2000), 2, '#3DE07C');
      } else {
        appliedAt = null;
      }
    }
    var level = Math.max(0.12, draft.bright / 255);
    P.paint(canvas, g, { px: TW.fit(panel, 128, 7, 2), gap: 1, off: pal.off, glow: level * 0.75 });
    canvas.style.opacity = String(0.35 + level * 0.65);
  }

  var last = 0;
  function loop(now) {
    if (now - last > 83) { last = now; drawPreview(); }
    requestAnimationFrame(loop);
  }

  function renderMotion() {
    motionBtn.hidden = TW.reduce;
    motionBtn.setAttribute('data-running', running ? 'true' : 'false');
    TW.text(motionLabel, running ? 'Pause preview' : 'Resume preview', running ? 'Vorschau anhalten' : 'Vorschau starten');
  }
  motionBtn.addEventListener('click', function () {
    if (running) { frozenT = (performance.now() - t0) / 1000; frozenMs = Date.now() + offset; }
    else { t0 = performance.now() - frozenT * 1000; }
    running = !running;
    renderMotion();
  });

  /* ---------- Karte ---------- */
  var mapFrame = null, mapReady = false;
  function pushMap() {
    if (!mapFrame || !mapReady || !mapFrame.contentWindow) return;
    var target = location.origin;
    mapFrame.contentWindow.postMessage({ type: 'lang', lang: TW.lang() }, target);
    mapFrame.contentWindow.postMessage({ type: 'center', lat: Number(draft.location.lat), lon: Number(draft.location.lon), label: draft.location.place }, target);
    mapFrame.contentWindow.postMessage({ type: 'radius', nm: draft.flight.radius }, target);
    pushFilter();
  }

  /* Hoehenfilter, Militaerfarbe und Einheiten gelten auch fuer die Flugzeuge auf der Karte. */
  function pushFilter() {
    if (!mapFrame || !mapReady || !mapFrame.contentWindow) return;
    var f = draft.flight;
    mapFrame.contentWindow.postMessage({
      type: 'filter', alt: !!f.alt, mil: !!f.mil,
      units: { alt: f.ualt, spd: f.uspd, dist: f.udist }
    }, location.origin);
  }

  var loadMap = $('[data-load-map]');
  if (loadMap) {
    loadMap.addEventListener('click', function () {
      if (mapFrame) return;
      mapFrame = document.createElement('iframe');
      mapFrame.src = '/map';
      mapFrame.title = TW.t('Coverage radius and live flights around the device', 'Abdeckung und Live-Flüge um das Gerät');
      // Hoeher als im Entwurf (220 bis 300 px): Suchleiste und Verkehrszeile decken sonst die Haelfte des Umkreises ab.
      mapFrame.setAttribute('style', 'width:100%;height:clamp(280px,32vw,380px);border:0;border-top:1px solid #1B2126;display:block;background:#050607');
      $('[data-map-slot]').appendChild(mapFrame);
      $('[data-map-pending]').hidden = true;
    });
  }

  /* Nur das eingebundene Kartenfenster derselben Herkunft darf den Standort setzen. */
  window.addEventListener('message', function (ev) {
    if (!mapFrame || ev.source !== mapFrame.contentWindow || ev.origin !== location.origin) return;
    var d = ev.data;
    if (!d || typeof d !== 'object') return;
    if (d.type === 'map-ready') { mapReady = true; pushMap(); }
    if (d.type === 'location' && typeof d.lat === 'number' && typeof d.lon === 'number') {
      if (readOnly) return;
      draft.location = { lat: Math.round(d.lat * 100000) / 100000, lon: Math.round(d.lon * 100000) / 100000, place: String(d.label || TW.t('Custom point', 'Eigener Punkt')).slice(0, 60) };
      changed('location');
    }
  });

  var radiusInput = $('#f-radius');
  if (radiusInput) radiusInput.addEventListener('input', function () {
    if (mapFrame && mapReady) mapFrame.contentWindow.postMessage({ type: 'radius', nm: draft.flight.radius }, location.origin);
  });

  /* ---------- Geraeteumschalter ---------- */
  var sw = $('[data-switcher]');
  var swBtn = $('[data-switcher-toggle]');
  var swMenu = $('[data-switcher-menu]');
  var caret = $('[data-caret]');
  function setMenu(open) {
    swMenu.hidden = !open;
    swBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    caret.style.transform = 'rotate(' + (open ? '180deg' : '0deg') + ')';
  }
  swBtn.addEventListener('click', function () { setMenu(swMenu.hidden); });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !swMenu.hidden) { setMenu(false); swBtn.focus(); }
  });
  document.addEventListener('pointerdown', function (ev) {
    if (!swMenu.hidden && !sw.contains(ev.target)) setMenu(false);
  });

  /* ---------- Statusleiste ---------- */
  /* Alle 30 Sekunden, solange die Seite sichtbar ist: so sieht man beim Einrichten,
     wann das Geraet zum ersten Mal fragt, ohne neu zu laden. */
  var telemetry = $('[data-telemetry]');
  var activeDot = $('[data-active-dot]');
  function refreshStatus() {
    if (document.hidden || !telemetry) return;
    TW.api('/api/device/' + D.id + '/status').then(function (res) {
      if (!res.ok || !res.data.items) return;
      if (typeof res.data.noteLeft === 'number') setNoteLeft(res.data.noteLeft);
      if (res.data.timers) setRing(res.data);
      var tag = $('[data-radius-tag]', telemetry);
      $$('[data-telemetry-item]', telemetry).forEach(function (el) { el.remove(); });
      res.data.items.forEach(function (pair) {
        var span = document.createElement('span');
        span.setAttribute('data-telemetry-item', '');
        TW.text(span, String(pair[0]), String(pair[1]));
        telemetry.insertBefore(span, tag);
      });
      if (activeDot) {
        activeDot.style.background = res.data.online ? '#3DE07C' : '#4A565F';
        activeDot.classList.toggle('blink-24', !!res.data.online);
      }
    });
  }
  setInterval(refreshStatus, 30000);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) return;
    refreshStatus();
    if (previewWaiting) { previewWaiting = false; schedulePreview(0); }
  });

  /* ---------- Radiogruppen mit Pfeiltasten ----------
     role="radiogroup" verspricht Pfeiltasten (Befund A15), wie in der Bibliothek.
     Nur das gewaehlte Feld liegt in der Tab-Reihenfolge. */
  function rovingTabs() {
    $$('[role="radiogroup"]').forEach(function (grp) {
      var radios = $$('[role="radio"]', grp);
      var on = radios.filter(function (r) { return r.getAttribute('aria-checked') === 'true'; })[0] || radios[0];
      radios.forEach(function (r) { r.setAttribute('tabindex', r === on ? '0' : '-1'); });
    });
  }
  $$('[role="radiogroup"]').forEach(function (grp) {
    grp.addEventListener('keydown', function (ev) {
      var step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[ev.key];
      if (!step && ev.key !== 'Home' && ev.key !== 'End') return;
      var radios = $$('[role="radio"]', grp).filter(function (r) { return !r.disabled; });
      var i = radios.indexOf(document.activeElement);
      if (i === -1 || !radios.length) return;
      ev.preventDefault();
      var next = ev.key === 'Home' ? 0 : ev.key === 'End' ? radios.length - 1 : (i + step + radios.length) % radios.length;
      radios[next].focus();
      radios[next].click();
    });
  });

  /* ---------- Spotify ----------
     Die Verbindung gehoert dem Besitzer des Geraets, Gaeste sehen nur den Stand. Namen und
     Titel kommen von Spotify und landen nur als textContent im Dokument. */
  var SP_ORDER = ['album', 'queue', 'cover'];
  var SP_NAMES = { album: ['album', 'Album'], queue: ['up next', 'Als Nächstes'], cover: ['cover only', 'Nur Cover'] };
  var SP_BACK = {
    connected: ['Spotify is connected.', 'Spotify ist verbunden.'],
    denied: ['You cancelled at Spotify. Nothing changed.', 'Bei Spotify abgebrochen. Es hat sich nichts geändert.'],
    failed: ['Spotify did not accept the sign-in. Please try again.', 'Spotify hat die Anmeldung nicht angenommen. Bitte noch einmal versuchen.'],
    exchange: ['Spotify did not accept the sign-in. Please try again.', 'Spotify hat die Anmeldung nicht angenommen. Bitte noch einmal versuchen.'],
    not_allowed: ['Your Spotify account is not enabled for the app of this server yet. The operator adds it in the Spotify dashboard under User Management.', 'Dein Spotify-Konto ist für die App dieses Servers noch nicht freigeschaltet. Der Betreiber trägt es im Spotify-Dashboard unter User Management ein.'],
    full: ['Five accounts are connected already. Spotify allows no more in development mode.', 'Es sind schon fünf Konten verbunden. Mehr lässt Spotify im Entwicklungsmodus nicht zu.'],
    expired: ['The sign-in took too long or came from another page. Please try again.', 'Die Anmeldung hat zu lange gedauert oder kam von einer anderen Seite. Bitte noch einmal versuchen.'],
    setup: ['Spotify is not set up on this server yet.', 'Spotify ist auf diesem Server noch nicht eingerichtet.'],
    slow: ['Too many attempts. Wait a few minutes.', 'Zu viele Versuche. Ein paar Minuten warten.'],
    network: ['Spotify is not reachable right now.', 'Spotify ist gerade nicht erreichbar.']
  };
  var spStatus = $('[data-sp-status]'), spNow = $('[data-sp-now]'), spNowText = $('[data-sp-now-text]'), spOpen = $('[data-sp-open]');
  var spConnect = $('[data-sp-connect]'), spDisconnect = $('[data-sp-disconnect]'), spDirty = $('[data-sp-dirty]');
  var spTimer = null, spBack = null, spLoaded = false;

  function renderSpotify() {
    if (!draft.spotify) return;
    $$('[data-sp-view]').forEach(function (b) {
      b.setAttribute('aria-pressed', draft.spotify.views.indexOf(b.getAttribute('data-sp-view')) !== -1 ? 'true' : 'false');
    });
    $$('[data-out="sp-view"]').forEach(function (o) { o.textContent = String(draft.spotify.view); });
    var note = $('[data-sp-views-note]');
    if (note) {
      var n = parseInt(draft.spotify.view, 10) || 10, picked = SP_ORDER.filter(function (v) { return draft.spotify.views.indexOf(v) !== -1; });
      TW.text(note,
        picked.length ? 'Now playing, then ' + picked.map(function (v) { return SP_NAMES[v][0]; }).join(', then now playing, then ') + ', ' + n + ' s each. The announcement and the change of track always show now playing.' : 'Only now playing. Pick views above to mix them in, ' + n + ' s each.',
        picked.length ? 'Läuft, dann ' + picked.map(function (v) { return SP_NAMES[v][1]; }).join(', dann Läuft, dann ') + ', je ' + n + ' s. Ansage und Titelwechsel stehen immer in „Läuft“.' : 'Nur „Läuft“. Oben Ansichten wählen, die im Wechsel dazukommen, je ' + n + ' s.');
    }
    if (draft.mode === 'spotify' && !spLoaded) { spLoaded = true; spotifyStatus(); }
  }

  /* Kleine Panels fuer die Layout-Kacheln. Der Server rechnet sie mit einem erfundenen Titel
     und einem gerechneten Cover, fester Zeitpunkt: die Kacheln stehen still. */
  function drawSpotifyTiles() {
    var demo = D.spotifyDemo;
    if (!demo || !demo.layouts || !window.TWOps) return;
    Object.keys(demo.layouts).forEach(function (id) {
      var holder = $('[data-sp-layout="' + id + '"]');
      if (!holder || holder.offsetParent === null) return;
      var g = P.grid(128, 64);
      window.TWOps.render(g, { ops: demo.layouts[id] }, { now: demo.now, iana: draft.tz, lang: TW.lang(), reduce: true, t: 0 });
      var w = holder.clientWidth || 256, gap = w >= 255 ? 1 : 0;
      var px = gap ? Math.max(1, Math.min(3, Math.floor((w + 1) / 128) - 1)) : 1;
      P.paint(holder.querySelector('canvas'), g, { px: px, gap: gap, off: pal.off, glow: 0.5 });
    });
  }

  function spotifyStatus() {
    if (!spStatus) return;
    clearTimeout(spTimer);
    if (document.hidden || draft.mode !== 'spotify') { spLoaded = false; return; }
    TW.api('/api/device/' + D.id + '/spotify').then(function (res) {
      if (!res.ok || !res.data.spotify) { spTimer = setTimeout(spotifyStatus, 30000); return; }
      var s = res.data.spotify, msg;
      if (!s.configured) msg = ['Spotify is not set up on this server yet. The app credentials go into the admin area.', 'Spotify ist auf diesem Server noch nicht eingerichtet. Der App-Zugang gehört in den Admin-Bereich.'];
      else if (!s.mine && !s.linked) msg = ['This device shows the Spotify account of ' + s.owner + ', and none is connected yet.', 'Dieses Gerät zeigt das Spotify-Konto von ' + s.owner + ', verbunden ist noch keins.'];
      else if (!s.mine) msg = ['This device shows what plays in the Spotify account of ' + s.owner + '.', 'Dieses Gerät zeigt, was im Spotify-Konto von ' + s.owner + ' läuft.'];
      else if (!s.linked) msg = s.full ? SP_BACK.full : ['Not connected. The panel shows your music once you connect Spotify.', 'Nicht verbunden. Das Panel zeigt deine Musik, sobald du Spotify verbindest.'];
      else if (s.error === 'not_allowed') msg = ['Connected as ' + s.name + ', but Spotify refuses the account: it is not enabled for the app of this server yet.', 'Verbunden als ' + s.name + ', aber Spotify lehnt das Konto ab: es ist für die App dieses Servers noch nicht freigeschaltet.'];
      else if (s.error === 'revoked') msg = ['The access was withdrawn in Spotify. Connect again.', 'Der Zugang wurde in Spotify zurückgezogen. Bitte neu verbinden.'];
      else msg = ['Connected as ' + s.name + '.', 'Verbunden als ' + s.name + '.'];
      if (spBack) msg = [spBack[0] + ' ' + msg[0], spBack[1] + ' ' + msg[1]];
      TW.text(spStatus, msg[0], msg[1]);
      if (spConnect) spConnect.hidden = !s.configured || !s.mine || (s.linked && s.error !== 'revoked') || (!s.linked && s.full);
      if (spDisconnect) spDisconnect.hidden = !s.mine || !s.linked;
      if (s.now) {
        TW.text(spNowText, (s.now.playing ? 'Playing: ' : 'Paused: ') + s.now.title + ' · ' + s.now.artists, (s.now.playing ? 'Läuft: ' : 'Pause: ') + s.now.title + ' · ' + s.now.artists);
        spOpen.hidden = !s.now.url;
        if (s.now.url) spOpen.href = s.now.url;
      }
      spNow.hidden = !s.now;
      spTimer = setTimeout(spotifyStatus, 15000);
    });
  }

  $$('[data-sp-view]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.disabled) return;
      var v = b.getAttribute('data-sp-view'), list = draft.spotify.views.slice(), i = list.indexOf(v);
      if (i === -1) list.push(v); else list.splice(i, 1);
      draft.spotify.views = SP_ORDER.filter(function (x) { return list.indexOf(x) !== -1; });
      changed('spotify.views');
    });
  });
  if (spConnect) {
    /* Der Weg zu Spotify verlaesst die Seite: ein Entwurf ginge verloren. */
    spConnect.addEventListener('submit', function (ev) {
      if (!isDirty() || neverApplied) return;
      ev.preventDefault();
      spDirty.hidden = false;
      applyBtn.focus();
    });
  }
  if (spDisconnect) {
    spDisconnect.addEventListener('click', function () {
      spDisconnect.setAttribute('aria-busy', 'true');
      TW.api('/api/spotify/disconnect', {}).then(function (res) {
        spDisconnect.removeAttribute('aria-busy');
        if (!res.ok) { var e = TW.errorText(res); TW.text(spStatus, e[0], e[1]); return; }
        spBack = null;
        spotifyStatus();
        schedulePreview(0);
        if (spConnect) spConnect.querySelector('button').focus();
      });
    });
  }
  /* Rueckweg von Spotify: ?spotify=connected und so weiter, einmal zeigen, dann aus der Adresse. */
  (function () {
    var m = /[?&]spotify=([a-z_]+)/.exec(location.search);
    if (!m || !SP_BACK[m[1]]) return;
    spBack = SP_BACK[m[1]];
    try { history.replaceState(null, '', location.pathname + '#spotify'); } catch (e) { /* bleibt stehen */ }
  })();
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && draft.mode === 'spotify') spotifyStatus();
  });

  TW.onLang(function () { render(); renderMotion(); pushMap(); pushStopsMap(); schedulePreview(150); if (draft.mode === 'spotify') spotifyStatus(); });
  TW.onResize(function () { drawBar(); drawFaces(); drawSpotifyTiles(); });

  render();
  renderMotion();
  fetchPreview();
  requestAnimationFrame(loop);
})();
