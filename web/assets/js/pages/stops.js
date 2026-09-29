/* Haltestellenkarte im iframe der Geraeteseite. Die Liste aller Haltestellen kommt
   vom Server (services/stops.php), gesucht wird hier im Browser, ohne Abfrage nach
   aussen. Namen kommen von mobiliteit.lu: nur als textContent, nie innerHTML.
   Nachrichten nur mit dem Elternfenster derselben Herkunft, gesendet an location.origin.

   Von der Geraeteseite:  lang, center {lat, lon}, chosen {ids, max, readOnly}
   An die Geraeteseite:   stops-ready, stop-toggle {id} */
(function () {
  'use strict';
  var L = window.L;
  var ORIGIN = location.origin;
  var de = document.documentElement.lang === 'de';
  /* Farben wie auf dem Panel: Bus Bernstein, Zug Cyan, Tram Gruen. Bits aus services/stops.php. */
  var TINT = { 1: '#35D6FF', 2: '#3DE07C', 4: '#FFAA00' };

  var map = L.map('map', { center: [49.6116, 6.1319], zoom: 10, zoomSnap: 0.25, preferCanvas: true });
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright" rel="noreferrer" target="_blank">OpenStreetMap</a> · mobiliteit.lu, CC BY 4.0',
    maxZoom: 18,
    referrerPolicy: 'strict-origin-when-cross-origin'
  }).addTo(map);
  var canvas = L.canvas({ padding: 0.3 });
  var layer = L.layerGroup().addTo(map);

  var q = document.getElementById('q');
  var go = document.getElementById('go');
  var form = document.getElementById('search');
  var hitsBox = document.getElementById('hits');
  var readout = document.getElementById('readout');
  var legend = document.getElementById('legend');

  var stops = [], byId = {}, markers = {};
  var chosen = [], max = 3, readOnly = false;
  var state = 'loading', progress = 0, builtAt = 0;
  var openId = '', popup = null, centred = false, timer = 0, busy = false;
  var hits = [];

  function T(en, dd) { return de ? dd : en; }
  function tell(msg) { if (window.parent !== window) window.parent.postMessage(msg, ORIGIN); }
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }
  function group(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0'); }

  function tint(m) { return m & 1 ? TINT[1] : (m & 2 ? TINT[2] : TINT[4]); }
  function size() {
    var z = map.getZoom();
    return z >= 15 ? 6 : z >= 13 ? 4.5 : z >= 11 ? 3.2 : 2.2;
  }
  function isChosen(id) { return chosen.indexOf(id) !== -1; }
  function style(s) {
    var on = isChosen(s[0]);
    return {
      renderer: canvas, radius: on ? size() + 3 : size(),
      color: on ? '#F2F4F5' : '#08090A', weight: on ? 2 : 0.8, opacity: 1,
      fillColor: tint(s[4]), fillOpacity: on ? 1 : 0.85
    };
  }
  function modeWords(m) {
    var out = [];
    if (m & 4) out.push('BUS');
    if (m & 1) out.push(T('TRAIN', 'ZUG'));
    if (m & 2) out.push('TRAM');
    return out.join(' · ');
  }

  /* ---------- Punkte ---------- */
  function drawAll() {
    stops.forEach(function (s) {
      var mk = markers[s[0]];
      if (!mk) {
        mk = L.circleMarker([s[2], s[3]], style(s));
        mk.on('click', function () { openStop(s[0], false); });
        mk.addTo(layer);
        markers[s[0]] = mk;
      } else {
        mk.setStyle(style(s));
        mk.setRadius(style(s).radius);
      }
      if (isChosen(s[0])) mk.bringToFront();
    });
  }
  map.on('zoomend', function () {
    stops.forEach(function (s) { var mk = markers[s[0]]; if (mk) mk.setRadius(style(s).radius); });
  });

  function popupContent(s) {
    var box = document.createElement('div');
    box.className = 'stop-pop';
    var name = document.createElement('p');
    name.className = 'stop-name';
    name.textContent = s[1];
    var meta = document.createElement('p');
    meta.className = 'stop-meta';
    meta.textContent = modeWords(s[4]);
    box.appendChild(name);
    box.appendChild(meta);
    var on = isChosen(s[0]);
    var full = !on && chosen.length >= max;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'stop-btn';
    btn.setAttribute('data-on', on ? 'true' : 'false');
    btn.textContent = on ? T('Remove', 'Entfernen') : (full ? T('Three chosen already', 'Schon drei gewählt') : T('Add', 'Hinzufügen'));
    btn.disabled = readOnly || full;
    btn.addEventListener('click', function () {
      if (btn.disabled) return;
      btn.disabled = true;
      tell({ type: 'stop-toggle', id: s[0] });
    });
    box.appendChild(btn);
    return box;
  }

  function openStop(id, fly) {
    var s = byId[id];
    if (!s) return;
    openId = id;
    if (fly) map.setView([s[2], s[3]], Math.max(map.getZoom(), 15));
    popup = L.popup({ className: 'stop-popup', offset: [0, -4], autoPanPadding: [20, 70] })
      .setLatLng([s[2], s[3]])
      .setContent(popupContent(s))
      .openOn(map);
  }
  map.on('popupclose', function () { openId = ''; });

  function refreshPopup() {
    if (!openId || !byId[openId] || !popup || !map.hasLayer(popup)) return;
    popup.setContent(popupContent(byId[openId]));
  }

  /* ---------- Suche ----------
     In der Liste selbst, auch waehrend des Tippens: sie liegt schon im Browser, keine
     Anfrage geht nach aussen. Ein Ort (der Teil vor dem Komma) fasst seine
     Haltestellen zusammen und zoomt auf alle. */
  function norm(t) {
    return String(t).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
  }
  function placeOf(name) {
    var i = name.indexOf(',');
    return i > 0 ? name.slice(0, i).trim() : '';
  }
  function search(term) {
    var n = norm(term);
    if (n.length < 2) return [];
    var places = {}, first = [], rest = [];
    stops.forEach(function (s) {
      var hay = norm(s[1]);
      var at = hay.indexOf(n);
      if (at === -1) return;
      var place = placeOf(s[1]);
      if (place && norm(place).indexOf(n) === 0) (places[place] = places[place] || []).push(s);
      (at === 0 || hay.charAt(at - 1) === ' ' ? first : rest).push(s);
    });
    var out = Object.keys(places).sort(function (a, b) { return places[b].length - places[a].length; }).slice(0, 3).map(function (p) {
      return { kind: 'place', label: p, list: places[p] };
    });
    first.concat(rest).slice(0, 8).forEach(function (s) { out.push({ kind: 'stop', s: s }); });
    return out;
  }

  function renderHits() {
    while (hitsBox.firstChild) hitsBox.removeChild(hitsBox.firstChild);
    hits.forEach(function (h, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'hit';
      b.setAttribute('data-hit', String(i));
      var dot = document.createElement('span');
      dot.className = 'hit-dot';
      dot.style.background = h.kind === 'place' ? '#8B949C' : tint(h.s[4]);
      var label = document.createElement('span');
      label.className = 'hit-label';
      label.textContent = h.kind === 'place' ? h.label : h.s[1];
      var meta = document.createElement('span');
      meta.className = 'hit-meta';
      meta.textContent = h.kind === 'place'
        ? group(h.list.length) + ' ' + T(h.list.length === 1 ? 'stop' : 'stops', h.list.length === 1 ? 'Haltestelle' : 'Haltestellen')
        : modeWords(h.s[4]);
      b.appendChild(dot);
      b.appendChild(label);
      b.appendChild(meta);
      hitsBox.appendChild(b);
    });
    hitsBox.hidden = !hits.length;
    q.setAttribute('aria-expanded', hits.length ? 'true' : 'false');
  }

  function pickHit(i) {
    var h = hits[i];
    if (!h) return;
    if (h.kind === 'place') {
      map.closePopup();
      var b = L.latLngBounds(h.list.map(function (s) { return [s[2], s[3]]; }));
      map.fitBounds(b.pad(0.2), { paddingTopLeft: [18, 70], paddingBottomRight: [18, 60], maxZoom: 15 });
      say([{ text: h.label, strong: true }, { text: group(h.list.length) + ' ' + T('stops', 'Haltestellen') }]);
    } else {
      openStop(h.s[0], true);
    }
    hits = [];
    renderHits();
  }

  q.addEventListener('input', function () {
    hits = search(q.value);
    renderHits();
  });
  q.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowDown' && hits.length) {
      ev.preventDefault();
      hitsBox.querySelector('[data-hit="0"]').focus();
    }
    if (ev.key === 'Escape') { hits = []; renderHits(); }
  });
  hitsBox.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-hit]');
    if (b) pickHit(Number(b.getAttribute('data-hit')));
  });
  hitsBox.addEventListener('keydown', function (ev) {
    var b = ev.target.closest('[data-hit]');
    if (!b) return;
    var i = Number(b.getAttribute('data-hit'));
    var next = null;
    if (ev.key === 'ArrowDown') next = hitsBox.querySelector('[data-hit="' + (i + 1) + '"]');
    if (ev.key === 'ArrowUp') next = i === 0 ? q : hitsBox.querySelector('[data-hit="' + (i - 1) + '"]');
    if (ev.key === 'Escape') { hits = []; renderHits(); q.focus(); return; }
    if (next) { ev.preventDefault(); next.focus(); }
  });
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    hits = search(q.value);
    if (hits.length) pickHit(0);
    else say([{ text: T('Nothing found for ', 'Nichts gefunden für ') + '“' + q.value.trim() + '”' }]);
  });

  /* ---------- Anzeige unten ---------- */
  function write(el, parts) {
    while (el.firstChild) el.removeChild(el.firstChild);
    parts.forEach(function (part, i) {
      var node = document.createElement(part.strong ? 'b' : 'span');
      node.textContent = part.text;
      if (part.colour) node.style.color = part.colour;
      el.appendChild(node);
      if (i < parts.length - 1) el.appendChild(document.createTextNode(' · '));
    });
  }
  function say(parts) { write(readout, parts); }

  function status() {
    if (state === 'nokey') return say([{ text: T('No key for mobiliteit.lu yet', 'Noch kein Schlüssel für mobiliteit.lu') }]);
    if (state === 'error') return say([{ text: T('Stops unavailable right now', 'Haltestellen gerade nicht erreichbar') }]);
    if (state === 'loading') return say([{ text: T('Loading stops ...', 'Haltestellen werden geladen ...') }]);
    var parts = [];
    if (state === 'building') parts.push({ text: T('Collecting every stop in the country', 'Alle Haltestellen des Landes werden gesammelt') + ', ' + progress + ' %', strong: true });
    if (state === 'paused') parts.push({ text: T('Paused to spare the quota, carries on later', 'Pausiert, damit das Kontingent reicht, geht später weiter'), strong: true });
    parts.push({ text: group(stops.length) + ' ' + T('stops', 'Haltestellen') });
    parts.push({ text: chosen.length + ' ' + T('of', 'von') + ' ' + max + ' ' + T('chosen', 'gewählt') });
    say(parts);
  }
  function drawLegend() {
    write(legend, [
      { text: 'BUS', colour: TINT[4] },
      { text: T('TRAIN', 'ZUG'), colour: TINT[1] },
      { text: 'TRAM', colour: TINT[2] },
      { text: T('Click a dot to add it', 'Punkt anklicken zum Hinzufügen') }
    ]);
  }

  /* ---------- Laden ----------
     Solange der Server das Raster abfragt, bringt jeder Abruf ein Stueck weiter.
     Unsichtbar wartet die Karte, pausiert fragt sie erst nach einer Minute wieder. */
  function schedule(ms) {
    clearTimeout(timer);
    timer = setTimeout(load, ms);
  }
  function load() {
    if (busy) return;
    if (document.hidden) { schedule(3000); return; }
    busy = true;
    fetch('/api/transit/all', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf() },
      body: '{}'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        busy = false;
        if (!res || !res.ok) { state = 'error'; status(); schedule(60000); return; }
        if (!res.configured) { state = 'nokey'; status(); return; }
        var list = Array.isArray(res.stops) ? res.stops : [];
        if (list.length !== stops.length || res.built_at !== builtAt) {
          stops = list;
          byId = {};
          stops.forEach(function (s) { byId[s[0]] = s; });
          drawAll();
          builtAt = res.built_at || 0;
        }
        progress = res.progress || 0;
        state = res.building ? (res.paused ? 'paused' : 'building') : 'ready';
        status();
        if (res.building) schedule(res.paused ? 60000 : 600);
        if (q.value) { hits = search(q.value); renderHits(); }
      })
      .catch(function () { busy = false; state = 'error'; status(); schedule(60000); });
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden && (state === 'building' || state === 'paused')) schedule(0); });

  /* ---------- Nachrichten der Geraeteseite ---------- */
  window.addEventListener('message', function (ev) {
    if (ev.source !== window.parent || ev.origin !== ORIGIN) return;
    var d = ev.data;
    if (!d || typeof d !== 'object') return;
    if (d.type === 'lang' && typeof d.lang === 'string') {
      de = d.lang === 'de';
      applyLang();
    }
    if (d.type === 'center' && typeof d.lat === 'number' && typeof d.lon === 'number' && !centred) {
      centred = true;
      map.setView([d.lat, d.lon], 14);
    }
    if (d.type === 'chosen' && Array.isArray(d.ids)) {
      chosen = d.ids.filter(function (x) { return typeof x === 'string'; }).slice(0, 12);
      if (typeof d.max === 'number') max = d.max;
      readOnly = d.readOnly === true;
      drawAll();
      refreshPopup();
      status();
    }
  });

  function applyLang() {
    document.documentElement.setAttribute('lang', de ? 'de' : 'en');
    q.setAttribute('aria-label', T('Find a village or stop', 'Ort oder Haltestelle suchen'));
    go.textContent = T('Find', 'Suchen');
    hitsBox.setAttribute('aria-label', T('Matching stops', 'Passende Haltestellen'));
    document.title = T('Stops', 'Haltestellen');
    renderHits();
    refreshPopup();
    drawLegend();
    status();
  }

  applyLang();
  document.documentElement.classList.remove('tw-de-pending');
  load();
  tell({ type: 'stops-ready' });
})();
