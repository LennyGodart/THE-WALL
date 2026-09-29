/* THE WALL, gemeinsamer Browser-Code fuer jede Seite.
   Stellt window.TW bereit: Sprache, Wortmarke, Pixel-Ueberschriften, LED-Balken,
   Anfragen an den Server mit CSRF-Token und kleine Helfer.

   Sprache: Englisch steht im Markup, Deutsch in data-de. Getauscht wird
   textContent, nie innerHTML. Die Wahl liegt in localStorage (kein Cookie). */
(function () {
  'use strict';

  var PAL = { accent: '#FFAA00', white: '#F2F4F5', dim: '#8E9AA3', cyan: '#35D6FF', green: '#3DE07C', red: '#FF4A1C', off: '#151A1E' };
  var langListeners = [];
  var resizeListeners = [];
  var reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  function lang() {
    return document.documentElement.lang === 'de' ? 'de' : 'en';
  }

  function t(en, de) {
    return lang() === 'de' ? de : en;
  }

  function swapAttr(el, attr, key, l) {
    var enKey = 'en' + key;
    var deKey = 'de' + key;
    if (el.dataset[enKey] === undefined) el.dataset[enKey] = el.getAttribute(attr) || '';
    el.setAttribute(attr, l === 'de' ? el.dataset[deKey] : el.dataset[enKey]);
  }

  function applyLang(l) {
    document.querySelectorAll('[data-de]').forEach(function (el) {
      if (el.dataset.en === undefined) el.dataset.en = el.textContent;
      el.textContent = l === 'de' ? el.dataset.de : el.dataset.en;
    });
    document.querySelectorAll('[data-de-label]').forEach(function (el) { swapAttr(el, 'aria-label', 'Label', l); });
    document.querySelectorAll('[data-de-placeholder]').forEach(function (el) { swapAttr(el, 'placeholder', 'Placeholder', l); });
    document.querySelectorAll('[data-de-title]').forEach(function (el) { swapAttr(el, 'title', 'Title', l); });
    document.documentElement.lang = l;
    document.querySelectorAll('.tw-lang').forEach(function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-lang') === l ? 'true' : 'false');
    });
    langListeners.forEach(function (fn) { try { fn(l); } catch (e) { console.error(e); } });
  }

  function setLang(l) {
    try { localStorage.setItem('tw-lang', l); } catch (e) { /* ohne Speicher gilt die Wahl nur fuer diese Seite */ }
    applyLang(l);
  }

  /* Text eines Elements in beiden Sprachen setzen, fuer Inhalte aus JavaScript. */
  function text(el, en, de) {
    if (!el) return;
    el.dataset.en = en;
    el.dataset.de = de === undefined ? en : de;
    el.textContent = lang() === 'de' ? el.dataset.de : el.dataset.en;
  }

  function fit(container, cols, maxPx, minPx) {
    var w = container ? container.clientWidth : 600;
    var px = Math.floor((w + 1) / cols) - 1;
    return Math.max(minPx || 2, Math.min(maxPx, px));
  }

  function drawMarks() {
    if (!window.PX) return;
    document.querySelectorAll('canvas[data-px="mark"]').forEach(function (c) {
      var g = PX.grid(56, 9);
      PX.text(g, 0, 1, 'THE', PAL.accent);
      PX.text(g, 24, 1, 'WALL', PAL.white);
      PX.paint(c, g, { px: 2, gap: 1, off: null, glow: 0.5 });
    });
  }

  /* Ueberschriften auf dem LED-Raster. Groessen von gross nach klein, effektiver
     Zeichenvorschub 6 * scale * (px + gap): 48, 36, 30, 24, 18. */
  var HEAD_SIZES = [
    { scale: 2, px: 3 }, { scale: 2, px: 2 },
    { scale: 1, px: 4 }, { scale: 1, px: 3 }, { scale: 1, px: 2 }
  ];

  function drawHeadings() {
    if (!window.PX) return;
    var gap = 1, lead = 2, maxLines = 3;
    document.querySelectorAll('[data-pxhead]').forEach(function (h) {
      var holder = h.previousElementSibling;
      if (!holder || !holder.hasAttribute('data-pxwrap')) return;
      var canvas = holder.querySelector('canvas');
      if (!canvas) return;
      var copy = h.textContent.trim();
      if (!copy) return;
      var avail = holder.clientWidth || holder.parentNode.clientWidth || 560;
      var longest = copy.toUpperCase().split(/\s+/).reduce(function (m, w) { return Math.max(m, w.length); }, 0);
      var chosen = HEAD_SIZES[HEAD_SIZES.length - 1], lines = null;
      for (var i = 0; i < HEAD_SIZES.length; i++) {
        var s = HEAD_SIZES[i];
        var cols = Math.max(24, Math.floor((avail + gap) / (s.px + gap)));
        var per = Math.floor((cols + s.scale) / (6 * s.scale));
        if (per < longest) continue;
        var cand = PX.wrap(copy, cols, s.scale);
        if (cand.length <= maxLines) { chosen = s; lines = cand; break; }
      }
      var colsFinal = Math.max(24, Math.floor((avail + gap) / (chosen.px + gap)));
      if (!lines) lines = PX.wrap(copy, colsFinal, chosen.scale);
      var rows = lines.length * (7 + lead) * chosen.scale - lead * chosen.scale;
      var g = PX.grid(colsFinal, rows);
      PX.block(g, 0, 0, lines, PAL.white, chosen.scale, lead);
      PX.paint(canvas, g, { px: chosen.px, gap: gap, off: null, glow: 0.3 });
    });
  }

  /* Ein Wert als LED-Reihe, drei Zeilen hoch. share 0..1. */
  function ledBar(holder, share, colour, glow, px, rows) {
    if (!holder || !window.PX) return;
    var c = holder.querySelector('canvas');
    if (!c) return;
    px = px || 3; rows = rows || 3;
    var gap = 1;
    var cols = Math.max(16, Math.floor(((holder.clientWidth || 240) + gap) / (px + gap)));
    var g = PX.grid(cols, rows);
    var lit = Math.round(share * cols);
    for (var x = 0; x < cols; x++) {
      for (var y = 0; y < rows; y++) PX.set(g, x, y, x < lit ? colour : '#1A1F24');
    }
    PX.paint(c, g, { px: px, gap: gap, off: null, glow: glow == null ? 0.55 : glow });
  }

  /* Helligkeit als LED-Reihe in Bernstein, dunkler bei kleinen Werten. */
  function brightBar(holder, value) {
    var level = Math.max(0.16, value / 255);
    var on = 'rgb(' + Math.round(255 * level) + ',' + Math.round(170 * level) + ',0)';
    ledBar(holder, value / 255, on, level * 0.9);
  }

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  /* JSON an den Server. Liefert immer ein Objekt {ok, status, data}. */
  function api(url, body, method) {
    var opts = {
      method: method || (body === undefined ? 'GET' : 'POST'),
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    };
    if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.headers['X-CSRF-Token'] = csrf();
      opts.body = JSON.stringify(body);
    }
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (data) {
        return { ok: r.ok && data.ok !== false, status: r.status, data: data };
      });
    }).catch(function () {
      return { ok: false, status: 0, data: { error: { en: 'No connection to the server.', de: 'Keine Verbindung zum Server.' } } };
    });
  }

  /* Fehlertext aus einer Antwort in der aktuellen Sprache. */
  function errorText(res, fallbackEn, fallbackDe) {
    var e = res && res.data && res.data.error;
    if (e && e.en) return [e.en, e.de || e.en];
    return [fallbackEn || 'That did not work.', fallbackDe || 'Das hat nicht geklappt.'];
  }

  /* Statuszeile setzen und nach ms wieder leeren. */
  var flashTimers = new WeakMap();
  function flash(el, en, de, ms, colour) {
    if (!el) return;
    text(el, en, de);
    if (colour) el.style.color = colour;
    if (flashTimers.has(el)) clearTimeout(flashTimers.get(el));
    if (ms) {
      flashTimers.set(el, setTimeout(function () { text(el, '', ''); }, ms));
    }
  }

  function pageData() {
    var el = document.getElementById('tw-data');
    if (!el) return {};
    try { return JSON.parse(el.textContent); } catch (e) { return {}; }
  }

  function onLang(fn) { langListeners.push(fn); }

  var resizeTimer = null;
  function onResize(fn) { resizeListeners.push(fn); }
  function fireResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      drawMarks();
      drawHeadings();
      resizeListeners.forEach(function (fn) { try { fn(); } catch (e) { console.error(e); } });
    }, 120);
  }

  window.TW = {
    PAL: PAL,
    reduce: reduce,
    lang: lang,
    t: t,
    text: text,
    setLang: setLang,
    onLang: onLang,
    onResize: onResize,
    fit: fit,
    drawHeadings: drawHeadings,
    ledBar: ledBar,
    brightBar: brightBar,
    api: api,
    errorText: errorText,
    flash: flash,
    data: pageData,
    csrf: csrf
  };

  document.querySelectorAll('.tw-lang').forEach(function (b) {
    b.addEventListener('click', function () { setLang(b.getAttribute('data-lang')); });
  });

  onLang(function () { requestAnimationFrame(drawHeadings); });

  var stored = null;
  try { stored = localStorage.getItem('tw-lang'); } catch (e) { stored = null; }
  if (stored === 'de') applyLang('de');
  document.documentElement.classList.remove('tw-de-pending');

  drawMarks();
  drawHeadings();
  window.addEventListener('resize', fireResize);
  if (window.ResizeObserver) {
    var ro = new ResizeObserver(fireResize);
    ro.observe(document.body);
  }
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function () { drawHeadings(); });
  }
})();
