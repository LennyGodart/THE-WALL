/* Startseite: Wortmarke mit Einschalt-Scan, Demo-Panel mit acht Bildschirmen vom Server
   (welcome_demo_pages: vier Ansichten eines Flugs, Abfahrten, Uhr, Wetter, Notiz), Kacheln
   der Modi. Oeffentliche Seite, deshalb Farbbloecke statt echter Logos. */
(function () {
  'use strict';
  var P = window.PX, TW = window.TW, pal = TW.PAL, OPS = window.TWOps;
  var DEMO = (TW.data() || {}).demo || {};
  var IANA = 'Europe/Luxembourg';
  var hero = document.querySelector('[data-hero]');
  var panel = document.querySelector('[data-panel]');
  var modesBox = document.querySelector('[data-modes]');
  var cycleBtn = document.querySelector('[data-cycle]');
  var cycleLabel = document.querySelector('[data-cycle-label]');
  var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-view]'));

  var state = { view: 0, cycling: !TW.reduce, booted: false };
  var cycleTimer = null, planeTimer = null, raf = null;

  var labelKey = '';

  /* ---------- Wortmarke ---------- */
  function drawHeroFrame(t, planeX) {
    var c = hero && hero.querySelector('canvas[data-px="hero"]');
    if (!c) return;
    var cols = 116, rows = 22;
    var g = P.grid(cols, rows);
    var full = P.grid(cols, rows);
    P.text(full, 11, 4, 'THE', pal.accent, 2);
    P.text(full, 59, 4, 'WALL', pal.white, 2);
    var revealed = Math.floor(rows * Math.min(1, t * 1.25));
    for (var y = 0; y < rows; y++) {
      if (y > revealed) continue;
      var edge = y === revealed && t < 1;
      for (var x = 0; x < cols; x++) {
        var col = full.data[y * cols + x];
        if (col) P.set(g, x, y, edge ? pal.cyan : col);
      }
      if (edge) for (var x2 = 0; x2 < cols; x2 += 2) P.set(g, x2, y, pal.cyan);
    }
    if (planeX != null) {
      var px = Math.round(planeX);
      for (var i = -3; i <= 2; i++) P.set(g, px + i, 11, pal.cyan);
      for (var j = -2; j <= 2; j++) P.set(g, px, 11 + j, pal.cyan);
      P.set(g, px + 2, 10, pal.cyan);
      P.set(g, px + 2, 12, pal.cyan);
      for (var k = 4; k < 11; k++) if (px + k < cols) {
        if (!g.data[11 * cols + px + k]) P.set(g, px + k, 11, k < 7 ? '#1E5C6E' : '#12333D');
      }
    }
    var size = TW.fit(hero, cols, 9, 3);
    P.paint(c, g, { px: size, gap: Math.max(1, Math.round(size / 6)), off: null, glow: 0.75 });
  }

  function startHero() {
    if (TW.reduce) { drawHeroFrame(1); state.booted = true; return; }
    var start = performance.now();
    var tick = function (now) {
      var t = Math.min(1, (now - start) / 1500);
      drawHeroFrame(t);
      if (t < 1) raf = requestAnimationFrame(tick);
      else { state.booted = true; startPlane(); }
    };
    raf = requestAnimationFrame(tick);
  }

  /* Das Flugzeug quert die Wortmarke genau einmal, gut vier Sekunden. Bewegung,
     die laenger als fuenf Sekunden laeuft, braeuchte einen Haltknopf. */
  function startPlane() {
    var x = 124;
    var step = function () {
      x -= 1.6;
      drawHeroFrame(1, x);
      if (x > -10) planeTimer = setTimeout(step, 55);
      else drawHeroFrame(1);
    };
    planeTimer = setTimeout(step, 900);
  }

  /* ---------- Demo-Panel ---------- */
  function pages() {
    var list = DEMO[TW.lang()] || DEMO.en || [];
    return Array.isArray(list) ? list : [];
  }

  /* Was auf dem Panel steht, als Satz fuer Vorleser: Texte, Uhrzeit und Datum der Seite. */
  function describe(page, now) {
    var parts = [];
    (page.ops || []).forEach(function (op) {
      if (op.t === 'text' && op.s) parts.push(String(op.s));
      else if (op.t === 'clock') parts.push(OPS.clockText(op, now, IANA));
      else if (op.t === 'date') parts.push(OPS.dateText(op.lang || TW.lang(), now, IANA));
    });
    return parts.join(', ');
  }

  function drawPanel() {
    var c = panel && panel.querySelector('canvas[data-px="panel"]');
    if (!c || !OPS) return;
    var list = pages();
    var page = list[state.view] || null;
    var now = Date.now();
    var g = P.grid(128, 64);
    if (page) OPS.render(g, page, { now: now, iana: IANA, lang: TW.lang(), reduce: TW.reduce });
    var size = TW.fit(panel, 128, 6, 2);
    P.paint(c, g, { px: size, gap: 1, off: pal.off, glow: 0.6 });
    /* Die Beschreibung nur bei einem neuen Bildschirm oder einer neuen Minute setzen. */
    var tab = tabs[state.view];
    var name = tab ? tab.querySelector('span:nth-child(2)').textContent : '';
    var key = TW.lang() + state.view + Math.floor(now / 60000);
    if (key !== labelKey) {
      labelKey = key;
      var de = TW.lang() === 'de';
      var head = (de ? 'Panel, Bildschirm ' : 'Panel, screen ') + (state.view + 1) + (de ? ' von ' : ' of ') + tabs.length + ', ' + name + ': ';
      c.setAttribute('aria-label', head + (page ? describe(page, now) : ''));
    }
  }

  function renderTabs() {
    tabs.forEach(function (b) {
      b.setAttribute('aria-pressed', Number(b.getAttribute('data-view')) === state.view ? 'true' : 'false');
    });
    if (cycleBtn) {
      cycleBtn.setAttribute('data-running', state.cycling ? 'true' : 'false');
      TW.text(cycleLabel, state.cycling ? 'Pause cycling' : 'Resume cycling', state.cycling ? 'Wechsel anhalten' : 'Wechsel starten');
    }
  }

  function startCycle() {
    stopCycle();
    cycleTimer = setInterval(function () {
      state.view = (state.view + 1) % tabs.length;
      renderTabs();
      drawPanel();
    }, 4600);
  }

  function stopCycle() {
    if (cycleTimer) { clearInterval(cycleTimer); cycleTimer = null; }
  }

  tabs.forEach(function (b) {
    b.addEventListener('click', function () {
      stopCycle();
      state.view = Number(b.getAttribute('data-view'));
      state.cycling = false;
      renderTabs();
      drawPanel();
    });
  });

  if (cycleBtn) {
    cycleBtn.addEventListener('click', function () {
      state.cycling = !state.cycling;
      if (state.cycling) startCycle(); else stopCycle();
      renderTabs();
    });
  }

  /* ---------- Kacheln ---------- */
  function drawModes() {
    if (!modesBox) return;
    var W = 60, H = 23;
    var L = function (n) { return n * 8; };
    var scenes = {
      'm-flight': function (g) {
        P.text(g, 1, L(0), 'LGL9561', pal.accent);
        P.text(g, 1, L(1), 'LUX→LIS', pal.white);
        P.text(g, 1, L(2), '737 MAX 8', pal.cyan, 1, true);
      },
      'm-track': function (g) {
        /* Wie auf dem Panel: der Ort unter dem Flugzeug, Geflogenes gruen, der Rest als Punkte. */
        P.text(g, 1, L(0), 'LUX→LIS', pal.white);
        P.text(g, 1, L(1), 'COGNAC', pal.dim);
        var done = Math.round(W * 0.38);
        for (var dx = done; dx < W; dx += 2) P.rect(g, dx, H - 2, 1, 2, '#4E5A63');
        P.rect(g, 0, H - 2, done, 2, pal.green);
      },
      'm-clock': function (g) {
        P.text(g, 4, L(0), '20:14', pal.accent, 1);
        P.text(g, 1, L(2), '12 SEP 26', pal.dim);
      },
      'm-weather': function (g) {
        P.text(g, 1, L(0), '18', pal.accent);
        P.text(g, 13, L(0), '°C', pal.dim);
        P.rect(g, 42, L(0) + 1, 5, 5, pal.accent);
        P.text(g, 1, L(2), 'LUX CLEAR', pal.dim);
      },
      'm-notes': function (g) {
        P.text(g, 1, L(0), 'MILCH', pal.white);
        P.text(g, 1, L(1), 'BROT', pal.white);
        P.text(g, 1, L(2), 'AKKU', pal.dim);
      },
      'm-pixel': function (g) {
        var r = P.rnd(7);
        for (var y = 0; y < H; y++) for (var x = 0; x < W; x++) {
          var v = r();
          if (v > 0.82) P.set(g, x, y, pal.accent);
          else if (v > 0.7) P.set(g, x, y, pal.cyan);
          else if (v > 0.6) P.set(g, x, y, '#2A3239');
        }
      },
      'm-ticker': function (g) {
        P.text(g, 4, L(1), 'ALLES GUT', pal.accent);
        P.set(g, 0, L(1) + 3, pal.cyan); P.set(g, W - 1, L(1) + 3, pal.cyan);
      },
      'm-timer': function (g) {
        P.text(g, 4, L(0), '00:47', pal.accent);
        P.text(g, 1, L(2), 'COUNTDOWN', pal.dim);
      },
      'm-music': function (g) {
        [5, 12, 8, 17, 11, 6, 15, 9, 13, 7].forEach(function (h, i) {
          P.rect(g, 2 + i * 6, H - h, 4, h, i % 2 ? pal.cyan : pal.accent);
        });
      },
      'm-transit': function (g) {
        /* Wie auf dem Panel: Linie in der Farbe des Verkehrsmittels, Ziel weiss, rechts die
           Minuten in Echtzeit. Die verspaetete Fahrt erkennt man nur an der Farbe der Zahl. */
        [['16', pal.accent, 'GARE', '4', pal.white], ['RE', pal.cyan, 'TRIER', '9', pal.accent], ['T1', pal.green, 'STADE', '12', pal.white]].forEach(function (r, i) {
          P.text(g, 1, L(i), r[0], r[1]);
          P.text(g, 16, L(i), r[2], pal.white);
          P.text(g, W - 1 - P.width(r[3], 1), L(i), r[3], r[4]);
        });
      },
      'm-image': function (g) {
        var r = P.rnd(21);
        for (var y = 0; y < H; y++) for (var x = 0; x < W; x++) {
          var d = Math.hypot(x - 30, y - 11);
          if (d < 9 + r() * 2) P.set(g, x, y, d < 5 ? pal.accent : '#8C5A00');
          else if (r() > 0.86) P.set(g, x, y, '#232B31');
        }
      }
    };
    modesBox.querySelectorAll('canvas[data-px]').forEach(function (c) {
      var fn = scenes[c.getAttribute('data-px')];
      if (!fn) return;
      var g = P.grid(W, H);
      fn(g);
      P.paint(c, g, { px: 2, gap: 1, off: pal.off, glow: 0.55 });
    });
  }

  TW.onLang(function () { drawPanel(); renderTabs(); });
  TW.onResize(function () { drawHeroFrame(1); drawPanel(); drawModes(); });

  /* Uhr und Streifen der Abfahrten laufen mit, einmal pro Sekunde, nur im sichtbaren Tab. */
  setInterval(function () { if (!document.hidden) drawPanel(); }, 1000);

  drawModes();
  drawPanel();
  renderTabs();
  startHero();
  if (state.cycling) startCycle();
})();
