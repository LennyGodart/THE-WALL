/* Karte mit Umkreis, Ortssuche und Flugzeugen, im iframe der Geraeteseite.
   Jeder Wert hier ist Eingabe, ein Ortsname aus OpenStreetMap oder ein Rufzeichen
   aus adsb.lol, das jeder Sender setzen kann: nichts davon geht durch innerHTML.
   Nachrichten nur mit dem Elternfenster derselben Herkunft, gesendet an location.origin. */
(function () {
  'use strict';
  var L = window.L;
  var ORIGIN = location.origin;
  var de = document.documentElement.lang === 'de';
  var centre = [49.6116, 6.1319];
  var radiusNm = 40;
  var label = 'Luxembourg';

  var map = L.map('map', { center: centre, zoom: 9, zoomSnap: 0.25, zoomControl: true, attributionControl: true });
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright" rel="noreferrer" target="_blank">OpenStreetMap</a>',
    maxZoom: 18,
    referrerPolicy: 'strict-origin-when-cross-origin'
  }).addTo(map);

  var circle = L.circle(centre, { radius: radiusNm * 1852, color: '#FFAA00', weight: 1.6, fillColor: '#FFAA00', fillOpacity: 0.08 }).addTo(map);
  var pin = L.marker(centre, {
    draggable: true,
    keyboard: true,
    title: 'Drag to move the centre',
    zIndexOffset: 2000,
    icon: L.divIcon({ className: '', html: '<div class="pin"></div>', iconSize: [13, 13], iconAnchor: [6, 6] })
  }).addTo(map);

  var readout = document.getElementById('readout');
  var foot = document.querySelector('.foot');
  var traffic = document.getElementById('traffic');
  var liveBtn = document.getElementById('live');
  var q = document.getElementById('q');
  var go = document.getElementById('go');
  var form = document.getElementById('search');

  function T(en, dd) { return de ? dd : en; }

  function write(el, parts) {
    while (el.firstChild) el.removeChild(el.firstChild);
    parts.forEach(function (part, i) {
      var node = document.createElement(part.strong ? 'b' : 'span');
      node.textContent = part.text;
      if (part.dim) node.style.color = '#8B949C';
      el.appendChild(node);
      if (i < parts.length - 1) el.appendChild(document.createTextNode(' · '));
    });
  }

  function say(parts) { write(readout, parts); }

  function render(fit) {
    circle.setLatLng(centre).setRadius(radiusNm * 1852);
    pin.setLatLng(centre);
    // Oben liegt die Suche, unten die Leiste mit Umkreis und Verkehr: der Umkreis passt dazwischen.
    if (fit) map.fitBounds(circle.getBounds(), { paddingTopLeft: [18, 62], paddingBottomRight: [18, foot.offsetHeight + 30] });
    say([
      { text: radiusNm + ' NM', strong: true },
      { text: Math.round(radiusNm * 1.852) + ' KM', dim: true },
      { text: label },
      { text: centre[0].toFixed(4) + ', ' + centre[1].toFixed(4), dim: true }
    ]);
    if (acKey() !== acShownKey) { acShownKey = acKey(); acMoved(); }
  }

  function tell(msg) {
    if (window.parent !== window) window.parent.postMessage(msg, ORIGIN);
  }

  function moveTo(lat, lon, name, fit) {
    centre = [lat, lon];
    if (name) label = name;
    render(fit);
    tell({ type: 'location', lat: lat, lon: lon, label: label });
  }

  var custom = function () { return T('Custom point', 'Eigener Punkt'); };
  pin.on('dragend', function () { var p = pin.getLatLng(); moveTo(p.lat, p.lng, custom(), false); });
  map.on('click', function (ev) { moveTo(ev.latlng.lat, ev.latlng.lng, custom(), false); });

  /* Nominatim will hoechstens eine Anfrage pro Sekunde und keine Autovervollstaendigung.
     Deshalb nur auf Absenden, und ueber den Server. */
  var busy = false;
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var term = q.value.trim();
    if (!term || busy) return;
    busy = true;
    go.textContent = '...';
    fetch('/api/geo', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf() },
      body: JSON.stringify({ q: term, lang: de ? 'de' : 'en' })
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.status === 'ok') {
          moveTo(res.lat, res.lon, res.name, true);
        } else if (res && res.status === 'none') {
          say([{ text: T('Nothing found for ', 'Nichts gefunden für ') + '“' + term + '”' }]);
        } else {
          say([{ text: T('Search unavailable. Drag the pin instead.', 'Suche nicht erreichbar. Zieh stattdessen die Nadel.') }]);
        }
      })
      .catch(function () {
        say([{ text: T('Search unavailable. Drag the pin instead.', 'Suche nicht erreichbar. Zieh stattdessen die Nadel.') }]);
      })
      .then(function () {
        busy = false;
        go.textContent = T('Find', 'Suchen');
      });
  });

  /* ---------- Flugzeuge ----------
     Vom Server, aus demselben Zwischenspeicher wie das Panel. Alle zehn Sekunden,
     solange die Seite sichtbar ist und Live an ist. Die Marker springen, sie gleiten
     nicht. Der Schalter haelt an, nach 30 Minuten ohne Bedienung haelt die Karte
     selbst an, damit ein vergessener Tab nicht tagelang fragt. */
  var LIVE_MS = 10000;
  var IDLE_MS = 30 * 60 * 1000;
  var layer = L.layerGroup().addTo(map);
  var live = true;
  var altFilter = false;
  var milColour = true;
  /* Einheiten wie auf dem Panel, von der Geraeteseite per Nachricht filter. */
  var units = { alt: 'ft', spd: 'kmh', dist: 'nm' };
  var planes = {};
  var acList = [];
  var acState = 'wait';
  var acAt = null;
  var selected = '';
  var acTimer = 0;
  var acBusy = false;
  var acAgain = false;
  var acShownKey = '';
  var touched = Date.now();

  function acKey() { return centre[0].toFixed(4) + ',' + centre[1].toFixed(4) + ',' + radiusNm; }
  function ignored(ac) { return altFilter && typeof ac.alt === 'number' && ac.alt > 30000; }
  function acName(ac) { return ac.callsign || ac.reg || String(ac.hex).toUpperCase(); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function group(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0'); }

  function altText(ac) {
    if (units.alt === 'm') return group(Math.round(ac.alt * 0.3048 / 10) * 10) + ' M';
    return group(Math.round(ac.alt / 100) * 100) + ' FT';
  }
  function speedText(kt) {
    if (units.spd === 'kt') return kt + ' KT';
    if (units.spd === 'mph') return Math.round(kt * 1.15078) + ' MPH';
    return Math.round(kt * 1.852) + ' KM/H';
  }
  function distText(nm) {
    if (units.dist === 'km') return (nm * 1.852).toFixed(1) + ' KM';
    if (units.dist === 'mi') return (nm * 1.15078).toFixed(1) + ' MI';
    return nm.toFixed(1) + ' NM';
  }

  /* Alles zu einem Flugzeug, fuer den Tooltip des Markers. */
  function details(ac) {
    var out = [acName(ac)];
    if (ac.airline) out.push(ac.airline);
    if (ac.route) out.push(ac.route);
    if (ac.type) out.push(ac.type);
    if (typeof ac.alt === 'number') out.push(altText(ac));
    if (typeof ac.gs === 'number') out.push(speedText(ac.gs));
    if (typeof ac.dst === 'number') out.push(distText(ac.dst));
    return out.join(', ');
  }

  /* Kurz fuer die Verkehrszeile, damit sie auch schmal in zwei Zeilen passt. */
  function brief(ac) {
    var out = [acName(ac) + (ac.route ? ' ' + ac.route : (ac.airline ? ' ' + ac.airline : ''))];
    if (typeof ac.alt === 'number') out.push(altText(ac));
    if (typeof ac.dst === 'number') out.push(distText(ac.dst));
    return out.join(', ');
  }

  function acSchedule(ms) {
    clearTimeout(acTimer);
    acTimer = 0;
    if (live && !document.hidden) acTimer = setTimeout(acFetch, ms);
  }

  function pause(state) {
    live = false;
    clearTimeout(acTimer);
    acState = state;
    liveBtn.setAttribute('aria-pressed', 'false');
    acText();
  }

  function acFetch() {
    acTimer = 0;
    if (!live || document.hidden) return;
    if (Date.now() - touched > IDLE_MS) { pause('idle'); return; }
    if (acBusy) { acAgain = true; return; }
    acBusy = true;
    var key = acKey();
    var status = 0;
    fetch('/api/map/aircraft', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf() },
      body: JSON.stringify({ lat: centre[0], lon: centre[1], nm: radiusNm })
    })
      .then(function (r) { status = r.status; return r.json(); })
      .then(function (res) {
        if (!live || key !== acKey()) return;
        if (res && res.ok && Array.isArray(res.aircraft)) {
          acList = res.aircraft;
          acState = 'ok';
          acAt = new Date();
        } else {
          acList = [];
          acState = status === 429 ? 'limit' : 'error';
        }
        acDraw();
      })
      .catch(function () {
        if (!live || key !== acKey()) return;
        acList = [];
        acState = 'error';
        acDraw();
      })
      .then(function () {
        acBusy = false;
        var again = acAgain || key !== acKey();
        acAgain = false;
        if (live) acSchedule(again ? 300 : (acState === 'limit' ? 30000 : LIVE_MS));
      });
  }

  /* Standort oder Umkreis geaendert: alte Flugzeuge gehoeren nicht mehr dazu. */
  function acMoved() {
    acList = [];
    selected = '';
    acDraw();
    if (live) { acState = 'wait'; acText(); acSchedule(400); }
  }

  function acDraw() {
    var seen = {};
    var nearest = '';
    for (var i = 0; i < acList.length; i++) {
      if (!ignored(acList[i])) { nearest = String(acList[i].hex); break; }
    }
    acList.forEach(function (ac) {
      if (typeof ac.lat !== 'number' || typeof ac.lon !== 'number') return;
      var hex = String(ac.hex);
      seen[hex] = true;
      var p = planes[hex];
      if (!p) {
        var root = document.createElement('div');
        var shape = document.createElement('span');
        shape.className = 'ac-shape';
        shape.appendChild(document.createElement('i'));
        var tag = document.createElement('span');
        tag.className = 'ac-label';
        root.appendChild(shape);
        root.appendChild(tag);
        var marker = L.marker([ac.lat, ac.lon], {
          keyboard: false,
          bubblingMouseEvents: false,
          icon: L.divIcon({ className: 'ac', html: root, iconSize: [16, 16], iconAnchor: [8, 8] })
        }).addTo(layer);
        marker.on('click', function () {
          touched = Date.now();
          selected = selected === hex ? '' : hex;
          acDraw();
        });
        p = planes[hex] = { marker: marker, shape: shape, tag: tag };
      } else {
        p.marker.setLatLng([ac.lat, ac.lon]);
      }
      var near = hex === nearest;
      var sel = hex === selected;
      var el = p.marker.getElement();
      if (el) {
        el.classList.toggle('ac-near', near);
        el.classList.toggle('ac-sel', sel);
        el.classList.toggle('ac-mil', milColour && !!ac.military);
        el.classList.toggle('ac-dim', ignored(ac));
        el.title = details(ac);
      }
      p.shape.style.transform = typeof ac.track === 'number' ? 'rotate(' + Math.round(ac.track % 360) + 'deg)' : '';
      p.tag.textContent = acName(ac) + (ac.route ? ' ' + ac.route : '');
      p.marker.setZIndexOffset(sel ? 1500 : (near ? 1000 : 0));
    });
    Object.keys(planes).forEach(function (hex) {
      if (!seen[hex]) { layer.removeLayer(planes[hex].marker); delete planes[hex]; }
    });
    if (selected && !seen[selected]) selected = '';
    acText();
  }

  function acText() {
    var parts = [];
    if (acState === 'paused' || acState === 'idle') {
      parts.push({ text: T('Live paused', 'Live angehalten'), strong: true });
      if (acState === 'idle') parts.push({ text: T('after 30 minutes without use', 'nach 30 Minuten ohne Bedienung') });
      if (acAt && acList.length) parts.push({ text: T('as of ', 'Stand ') + pad(acAt.getHours()) + ':' + pad(acAt.getMinutes()) + ':' + pad(acAt.getSeconds()), dim: true });
    } else if (acState === 'wait') {
      parts.push({ text: T('Loading flights ...', 'Flüge werden geladen ...') });
    } else if (acState === 'error') {
      parts.push({ text: T('Flight data unavailable right now', 'Flugdaten gerade nicht erreichbar') });
    } else if (acState === 'limit') {
      parts.push({ text: T('Too many requests, trying again in 30 seconds', 'Zu viele Anfragen, in 30 Sekunden geht es weiter') });
    } else if (!acList.length) {
      parts.push({ text: T('No aircraft in the radius right now', 'Gerade kein Flugzeug im Umkreis') });
    } else {
      parts.push({ text: acList.length + ' ' + T('in the air', 'in der Luft'), strong: true });
    }
    var focus = null;
    if (acState !== 'wait' && acState !== 'error' && acState !== 'limit') {
      for (var i = 0; i < acList.length; i++) {
        var ac = acList[i];
        if (selected ? String(ac.hex) === selected : !ignored(ac)) { focus = ac; break; }
      }
    }
    if (focus) parts.push({ text: (selected ? T('Selected: ', 'Ausgewählt: ') : T('Nearest: ', 'Nächstes: ')) + brief(focus) });
    write(traffic, parts);
  }

  liveBtn.addEventListener('click', function () {
    touched = Date.now();
    if (live) { pause('paused'); return; }
    live = true;
    liveBtn.setAttribute('aria-pressed', 'true');
    acState = 'wait';
    acText();
    acSchedule(0);
  });
  document.addEventListener('visibilitychange', function () { if (!document.hidden && live) acSchedule(0); });
  ['pointerdown', 'keydown', 'wheel'].forEach(function (type) {
    document.addEventListener(type, function () { touched = Date.now(); }, { passive: true });
  });

  window.addEventListener('message', function (ev) {
    if (ev.source !== window.parent || ev.origin !== ORIGIN) return;
    var d = ev.data;
    if (!d || typeof d !== 'object') return;
    if (d.type === 'radius' && typeof d.nm === 'number') {
      radiusNm = Math.max(5, Math.min(150, d.nm));
      render(true);
    }
    if (d.type === 'center' && typeof d.lat === 'number' && typeof d.lon === 'number') {
      centre = [d.lat, d.lon];
      if (typeof d.label === 'string' && d.label) label = d.label.slice(0, 60);
      render(true);
    }
    if (d.type === 'filter') {
      altFilter = d.alt === true;
      milColour = d.mil !== false;
      if (d.units && typeof d.units === 'object') {
        units = {
          alt: d.units.alt === 'm' ? 'm' : 'ft',
          spd: ['kt', 'mph'].indexOf(d.units.spd) !== -1 ? d.units.spd : 'kmh',
          dist: ['km', 'mi'].indexOf(d.units.dist) !== -1 ? d.units.dist : 'nm'
        };
      }
      acDraw();
    }
    if (d.type === 'lang' && typeof d.lang === 'string') {
      de = d.lang === 'de';
      applyLang();
      if (label === 'Custom point' || label === 'Eigener Punkt') label = custom();
      render(false);
      acText();
    }
  });

  /* lang-early.js hat die Sprache aus localStorage schon gesetzt und die Seite
     verborgen. ui.js laeuft hier nicht, also tauscht die Karte selbst und gibt frei. */
  function applyLang() {
    document.documentElement.setAttribute('lang', de ? 'de' : 'en');
    q.setAttribute('aria-label', T('Search for a place', 'Ort suchen'));
    if (!busy) go.textContent = T('Find', 'Suchen');
    liveBtn.setAttribute('aria-label', T('Live flights', 'Live-Flüge'));
    document.title = T('Coverage radius', 'Abdeckung');
    pin.getElement() && pin.getElement().setAttribute('title', T('Drag to move the centre', 'Ziehen, um die Mitte zu verschieben'));
  }

  applyLang();
  document.documentElement.classList.remove('tw-de-pending');
  render(true);
  acText();
  tell({ type: 'map-ready' });
})();
