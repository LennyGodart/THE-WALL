/* Fehlerseite: ein Flugzeug verlaesst das Bild. Die Bewegung laeuft laenger als
   fuenf Sekunden, deshalb gibt es den Knopf zum Anhalten (CLAUDE.md). */
(function () {
  'use strict';
  var P = window.PX, TW = window.TW, pal = TW.PAL;
  var panel = document.querySelector('[data-panel]');
  var btn = document.querySelector('[data-motion]');
  var label = document.querySelector('[data-motion-label]');
  var status = String((TW.data().status) || 404);
  var running = !TW.reduce;
  var t0 = performance.now(), last = 0, frozenAt = 90;

  function draw(t) {
    var c = panel && panel.querySelector('canvas[data-px="panel"]');
    if (!c) return;
    var g = P.grid(128, 64);
    P.text(g, Math.round((128 - P.width(status, 2)) / 2), 6, status, pal.accent, 2);
    var msg = status === '404' ? TW.t('NOT FOUND', 'NICHT GEFUNDEN') : TW.t('ERROR', 'FEHLER');
    P.text(g, Math.round((128 - P.width(msg, 1)) / 2), 28, msg, pal.white);
    var x = Math.round(running ? 140 - ((t * 16) % 150) : frozenAt);
    var y = 46;
    for (var i = -3; i <= 2; i++) P.set(g, x + i, y, pal.cyan);
    for (var j = -2; j <= 2; j++) P.set(g, x, y + j, pal.cyan);
    P.set(g, x + 2, y - 1, pal.cyan);
    P.set(g, x + 2, y + 1, pal.cyan);
    for (var k = 4; k < 22; k++) {
      if (x + k < 128 && !g.data[y * 128 + x + k]) P.set(g, x + k, y, k < 10 ? '#1E5C6E' : '#12333D');
    }
    P.rect(g, 0, 61, 128, 2, '#14222B');
    P.paint(c, g, { px: TW.fit(panel, 128, 6, 2), gap: 1, off: pal.off, glow: 0.6 });
    return x;
  }

  function loop(now) {
    if (running && now - last > 83) {
      last = now;
      frozenAt = draw((now - t0) / 1000);
    }
    requestAnimationFrame(loop);
  }

  function renderBtn() {
    if (!btn) return;
    btn.hidden = TW.reduce;
    btn.setAttribute('data-running', running ? 'true' : 'false');
    TW.text(label, running ? 'Pause motion' : 'Resume motion', running ? 'Bewegung anhalten' : 'Bewegung starten');
  }

  if (btn) {
    btn.addEventListener('click', function () {
      running = !running;
      if (running) t0 = performance.now() - ((140 - frozenAt) / 16) * 1000;
      else draw(0);
      renderBtn();
    });
  }

  TW.onLang(function () { draw((performance.now() - t0) / 1000); renderBtn(); });
  TW.onResize(function () { draw((performance.now() - t0) / 1000); });
  frozenAt = draw(0) || 90;
  renderBtn();
  requestAnimationFrame(loop);
})();
