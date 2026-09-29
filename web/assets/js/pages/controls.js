/* Eingabe-Bibliothek: jede Karte funktioniert wirklich. Zustaende ueber
   aria-Attribute, Aussehen aus base.css. */
(function () {
  'use strict';
  var P = window.PX, TW = window.TW, pal = TW.PAL;
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* A5: verbleibende Zeichen */
  var a5 = $('#a5'), a5Count = $('[data-a5-count]');
  a5.addEventListener('input', function () {
    var left = 21 - a5.value.length;
    TW.text(a5Count, left + ' left', left + ' frei');
    a5Count.style.color = left <= 3 ? '#FFAA00' : '#8B949C';
  });

  /* B1 bis B5: ein gemeinsamer Helligkeitswert */
  var bright = 168;
  function drawB2() {
    var holder = $('[data-b2-bar]');
    var c = holder.querySelector('canvas');
    var cols = 32, rows = 5;
    var g = P.grid(cols, rows);
    var lit = Math.round((bright / 255) * cols);
    var level = Math.max(0.18, bright / 255);
    for (var x = 0; x < cols; x++) {
      var shade = x < lit ? 'rgb(' + Math.round(255 * level) + ',' + Math.round(170 * level) + ',0)' : '#1A1F24';
      for (var y = 0; y < rows; y++) P.set(g, x, y, shade);
    }
    P.paint(c, g, { px: TW.fit(holder, cols, 7, 3), gap: 1, off: null, glow: level * 0.8 });
  }
  function setBright(v) {
    bright = Math.max(0, Math.min(255, parseInt(v, 10) || 0));
    $$('[data-bright]').forEach(function (r) { if (r.value !== String(bright)) r.value = String(bright); });
    $$('[data-bright-out]').forEach(function (o) { o.textContent = String(bright); });
    var num = $('[data-bright-num]');
    if (document.activeElement !== num) num.value = String(bright);
    $$('[data-step]').forEach(function (b) { b.setAttribute('aria-pressed', Number(b.getAttribute('data-step')) === bright ? 'true' : 'false'); });
    drawB2();
  }
  $$('[data-bright]').forEach(function (r) { r.addEventListener('input', function () { setBright(r.value); }); });
  $('[data-bright-num]').addEventListener('input', function (ev) { setBright(ev.target.value); });
  $$('[data-step]').forEach(function (b) { b.addEventListener('click', function () { setBright(b.getAttribute('data-step')); }); });

  /* C1, C4, C5: Schalter */
  $$('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var on = b.getAttribute('aria-checked') !== 'true';
      b.setAttribute('aria-checked', on ? 'true' : 'false');
      var st = $('[data-led-state]', b);
      if (st) st.textContent = on ? 'ON' : 'OFF';
    });
  });

  /* C3 und D2: genau einer gedrueckt */
  $$('[data-two-group], [data-seg-group]').forEach(function (group) {
    var buttons = $$('button', group);
    buttons.forEach(function (b) {
      b.addEventListener('click', function () {
        buttons.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      });
    });
  });

  /* D3, D4, E1: Radiogruppen mit Pfeiltasten */
  $$('[data-radio-group]').forEach(function (group) {
    var radios = $$('[role="radio"]', group);
    function select(r, focus) {
      radios.forEach(function (x) {
        x.setAttribute('aria-checked', x === r ? 'true' : 'false');
        x.setAttribute('tabindex', x === r ? '0' : '-1');
      });
      if (focus) r.focus();
    }
    radios.forEach(function (r, i) {
      r.setAttribute('tabindex', r.getAttribute('aria-checked') === 'true' ? '0' : '-1');
      r.addEventListener('click', function () { select(r, false); });
      r.addEventListener('keydown', function (ev) {
        if (ev.key === 'ArrowRight' || ev.key === 'ArrowDown') { ev.preventDefault(); select(radios[(i + 1) % radios.length], true); }
        if (ev.key === 'ArrowLeft' || ev.key === 'ArrowUp') { ev.preventDefault(); select(radios[(i + radios.length - 1) % radios.length], true); }
      });
    });
  });

  /* D5: mehrere */
  $$('[data-multi]').forEach(function (b) {
    b.addEventListener('click', function () { b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true'); });
  });

  /* D4: Kacheln mit Panelvorschau */
  function drawTiles() {
    /* Sieben Zeichen ab x 1 enden bei Spalte 41: 43 breit, sonst fehlen der 1 und dem S Spalten. */
    var W = 43, H = 15;
    var scenes = {
      't-flight': function (g) { P.text(g, 1, 0, 'LGL9561', pal.accent); P.text(g, 1, 8, 'LUX→LIS', pal.white); },
      't-clock': function (g) { P.text(g, 5, 0, '20:14', pal.accent); P.text(g, 1, 8, TW.t('12 SEP', '12 SEP'), pal.dim); }
    };
    $$('[data-tile]').forEach(function (holder) {
      var g = P.grid(W, H);
      scenes[holder.getAttribute('data-tile')](g);
      P.paint(holder.querySelector('canvas'), g, { px: 2, gap: 1, off: pal.off, glow: 0.5 });
    });
  }

  /* E2 */
  var e2 = $('#e2');
  e2.addEventListener('input', function () { $('[data-e2-out]').textContent = e2.value.toUpperCase(); });

  /* E3 */
  var e3 = $('#e3'), chip = $('[data-e3-chip]');
  e3.addEventListener('input', function () {
    var v = e3.value.toUpperCase();
    var ok = /^#[0-9A-F]{6}$/.test(v);
    chip.style.background = ok ? v : '#1A1F24';
    e3.setAttribute('aria-invalid', ok ? 'false' : 'true');
  });

  /* E4: Farbton als LED-Reihe, S 85 %, L 55 % */
  function hsl(h) {
    var s = 0.85, l = 0.55;
    var C = (1 - Math.abs(2 * l - 1)) * s;
    var X = C * (1 - Math.abs(((h / 60) % 2) - 1));
    var m = l - C / 2, r = 0, g = 0, b = 0;
    if (h < 60) { r = C; g = X; } else if (h < 120) { r = X; g = C; } else if (h < 180) { g = C; b = X; }
    else if (h < 240) { g = X; b = C; } else if (h < 300) { r = X; b = C; } else { r = C; b = X; }
    var to = function (v) { return Math.round((v + m) * 255).toString(16).padStart(2, '0'); };
    return ('#' + to(r) + to(g) + to(b)).toUpperCase();
  }
  var e4 = $('#e4');
  function drawHue() {
    var holder = $('[data-hue]');
    var hue = parseInt(e4.value, 10) || 0;
    var cols = 32, rows = 5, g = P.grid(cols, rows);
    var sel = Math.round((hue / 359) * (cols - 1));
    for (var x = 0; x < cols; x++) {
      var col = hsl(Math.round((x / (cols - 1)) * 359));
      for (var y = 0; y < rows; y++) P.set(g, x, y, x === sel ? '#F2F4F5' : col);
    }
    P.paint(holder.querySelector('canvas'), g, { px: TW.fit(holder, cols, 7, 3), gap: 1, off: null, glow: 0.6 });
    $('[data-hue-out]').textContent = hue + '° · ' + hsl(hue);
  }
  e4.addEventListener('input', drawHue);

  /* E5: drei Farben vorn, der Rest hinter "Mehr" */
  var e5buttons = $$('[data-e5]'), more = $('[data-e5-more]'), moreInput = $('input', more);
  function setE5(hex) {
    hex = hex.toUpperCase();
    var standard = false;
    e5buttons.forEach(function (b) {
      var on = b.getAttribute('data-e5') === hex;
      if (on) standard = true;
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    /* Mit einer freien Farbe ist keins der drei gewaehlt, dann bleibt das erste im Tab-Weg. */
    e5buttons.forEach(function (b, i) {
      b.setAttribute('tabindex', b.getAttribute('aria-checked') === 'true' || (!standard && i === 0) ? '0' : '-1');
    });
    more.setAttribute('data-active', standard ? 'false' : 'true');
  }
  /* Einfachauswahl als Radiogruppe mit Pfeiltasten (Befund A16), "Mehr" steht ausserhalb. */
  e5buttons.forEach(function (b, i) {
    b.addEventListener('click', function () { setE5(b.getAttribute('data-e5')); });
    b.addEventListener('keydown', function (ev) {
      var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[ev.key];
      if (!step) return;
      ev.preventDefault();
      var next = e5buttons[(i + step + e5buttons.length) % e5buttons.length];
      setE5(next.getAttribute('data-e5'));
      next.focus();
    });
  });
  moreInput.addEventListener('input', function () { setE5(moreInput.value); });

  TW.onResize(function () { drawB2(); drawHue(); drawTiles(); });
  TW.onLang(drawTiles);
  setBright(168);
  drawHue();
  drawTiles();
})();
