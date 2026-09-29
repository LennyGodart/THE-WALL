/* Animationsseite: Liste, Vorschau mit Tempo, Abspielen, Von vorn und dem
   Schalter fuer reduzierte Bewegung. Alle dreizehn Eintraege bilden eine
   Radiogruppe mit Pfeiltasten (FEHLERLISTE 2.2). */
(function () {
  'use strict';
  var P = window.PX, TW = window.TW, pal = TW.PAL, A = window.TWAnim;
  var $ = function (sel) { return document.querySelector(sel); };
  var rows = Array.prototype.slice.call(document.querySelectorAll('[data-anim]'));
  var panel = $('[data-panel]');

  var COPY = {
    boot: [['Power on', 'Einschalten'], ['The wordmark builds up row by row, exactly the way a HUB75 panel really powers up: one line at a time from the top. A single dip at the end, then it settles.', 'Die Wortmarke baut sich zeilenweise auf, genau so wie ein HUB75-Panel wirklich hochfährt: Zeile für Zeile von oben. Am Ende ein einzelnes Absacken, dann steht sie.']],
    wifi: [['No Wi-Fi yet', 'Kein WLAN'], ['On first start. At the top what to do, at the bottom the setup network and its password in plain text. In between the wireless sign lights up arc by arc while a dash runs to the phone, where three dots are waiting. Once a phone has joined, it lights up and the panel says where to go next: OPEN IN A BROWSER, HTTP://4.3.2.1. Up to firmware 0.1.7 a QR code stood here; phone cameras did not pick it up reliably from the LED dots.', 'Beim ersten Start. Oben, was zu tun ist, unten Einrichtungsnetz und Passwort im Klartext. Dazwischen leuchtet das Funkzeichen Bogen für Bogen auf, während ein Strich zum Handy läuft, auf dem drei Punkte warten. Ist ein Handy verbunden, leuchtet es auf, und das Panel sagt, wohin es geht: IM BROWSER OEFFNEN, HTTP://4.3.2.1. Bis Firmware 0.1.7 stand hier ein QR-Code; die Kamera eines Handys fand ihn auf den LED-Punkten nicht zuverlässig.']],
    connecting: [['Connecting', 'Verbindet'], ['Several seconds pass between submitting and success. With nothing on screen you assume it crashed.', 'Zwischen Absenden und Erfolg liegen mehrere Sekunden. Ohne Anzeige denkt man, es sei abgestürzt.']],
    address: [['Showing its address', 'Adresse zeigen'], ['Wi-Fi is up but there is no key yet. The address large and calm, because you have to type it.', 'WLAN steht, aber noch kein Schlüssel. Die IP groß und ruhig, weil man sie abtippen muss.']],
    paired: [['Paired', 'Gekoppelt'], ['The unboxing moment, in three acts. The radar turns twice before it finds the first aircraft. Then the handshake types itself out line by line, with a cursor, until the last line reports in green how many aircraft are up. Then HELLO LENNY, and that stays until you pick a mode on the website.', 'Der Moment beim Auspacken, in drei Akten. Das Radar dreht zweimal und findet erst dann den ersten Flieger. Danach tippt sich die Anmeldung Zeile für Zeile ein, mit Cursor, bis unten grün steht wie viele Flugzeuge in der Luft sind. Dann HALLO LENNY, und das bleibt stehen, bis du auf der Webseite einen Modus wählst.']],
    modeswap: [['Mode change', 'Moduswechsel'], ['Every thirty seconds when cycling. The old content slides out to the left and the new one follows. Without that push the panel twitches all day.', 'In der Rotation alle 30 Sekunden. Der alte Inhalt wird nach links geschoben, der neue kommt nach. Ohne diesen Schub zuckt das Panel den ganzen Tag.']],
    waiting: [['Nothing flying', 'Nichts fliegt'], ['Rare within 40 nautical miles of Luxembourg, because freight flights at Findel and dense overflights keep the sky busy. When it does happen: an empty radar screen instead of an empty panel, with the clock still visible.', 'Im Umkreis von 40 Seemeilen um Luxemburg selten, weil Frachtflüge in Findel und dichter Überflugverkehr den Himmel füllen. Wenn es passiert: ein leerer Radarschirm statt eines leeren Panels, die Uhr bleibt sichtbar.']],
    note: [['New note', 'Neue Notiz'], ['Three flashes, then it settles. Enough to catch out of the corner of your eye, little enough not to annoy.', 'Dreimal blinken, dann ruhig stehen bleiben. Das ist genug, um es aus dem Augenwinkel zu merken, und wenig genug, um nicht zu nerven.']],
    resting: [['Resting', 'Ruhezustand'], ['The screen is never truly off. The clock glows faintly and dust drifts across. Three guests take turns: a night flight crossing the panel, a shooting star, and the moon phase in the corner. Only one per cycle, otherwise it turns into wallpaper.', 'Der Bildschirm ist nie wirklich aus. Die Uhr glüht schwach, Staub wandert durchs Bild. Dazu drei Gäste, die sich abwechseln: ein Nachtflug quert das Panel, eine Sternschnuppe, und die Mondphase in der Ecke. Immer nur einer pro Runde, sonst wird es Tapete.']],
    noserver: [['Server gone', 'Server weg'], ['The clock keeps running because it comes from the onboard RTC. A line at the bottom says what is missing, instead of a stale flight sitting there.', 'Die Uhr läuft weiter, weil sie aus der Echtzeituhr kommt. Unten eine Zeile, die sagt was fehlt, statt eines stehengebliebenen Flugs.']],
    nowifi: [['Wi-Fi gone', 'WLAN weg'], ['Different from a missing server: here a look at the router helps. That is why the network name is shown.', 'Anders als ein fehlender Server: hier hilft ein Blick auf den Router. Deshalb steht der Netzname dabei.']],
    updating: [['Updating', 'Update läuft'], ['The panel must not go dark here, or you assume it broke. A bar and a percentage. Unplugging is harmless: two partitions exist and the old version boots again, which is what the bottom line says.', 'Das Panel darf dabei nicht schwarz werden, sonst denkt man es sei kaputt. Ein Balken und ein Prozentwert. Ausstecken ist ungefährlich, weil zwei Partitionen da sind und die alte Version wieder startet. Das sagt auch die untere Zeile.']],
    poweroff: [['Power off', 'Ausschalten'], ['For when you genuinely want it off. A curtain closes from both edges toward the middle, the wordmark stays to the last.', 'Wenn man es wirklich aus haben will. Ein Vorhang schließt sich von beiden Rändern zur Mitte, die Wortmarke bleibt bis zuletzt stehen.']]
  };

  var state = { anim: 0, playing: !TW.reduce, speed: 100, reduce: TW.reduce };
  var t = 0, last = performance.now(), frameAt = 0;

  function current() { return A.list[state.anim]; }
  /* Der Ruhezustand hat drei Gaeste, je einer pro Runde von 14 Sekunden. Nach einer
     Runde zurueckzuspulen hiesse, immer nur den ersten zu zeigen (Befund B9). */
  function span(a) { return a.key === 'resting' ? a.dur * 3 : a.dur; }

  function renderText() {
    var a = current(), c = COPY[a.key];
    TW.text($('[data-title]'), c[0][0], c[0][1]);
    TW.text($('[data-note]'), c[1][0], c[1][1]);
    var len = a.key === 'resting' ? '3 × ' + a.dur.toFixed(1) : a.dur.toFixed(1);
    TW.text($('[data-timing]'),
      len + ' S · 12 FPS · ' + (a.loop ? 'LOOPS' : 'ONCE'),
      len + ' S · 12 FPS · ' + (a.loop ? 'WIEDERHOLT' : 'EINMAL'));
    var label = TW.t('Panel preview: ', 'Panel-Vorschau: ') + TW.t(c[0][0], c[0][1]);
    $('[data-panel] canvas').setAttribute('aria-label', label);
    rows.forEach(function (r) {
      var on = Number(r.getAttribute('data-anim')) === state.anim;
      r.setAttribute('aria-checked', on ? 'true' : 'false');
      r.setAttribute('tabindex', on ? '0' : '-1');
    });
    var play = $('[data-play]');
    play.setAttribute('data-running', state.playing ? 'true' : 'false');
    TW.text($('[data-play-label]'), state.playing ? 'Pause' : 'Play', state.playing ? 'Anhalten' : 'Abspielen');
    $('[data-reduce]').setAttribute('aria-checked', state.reduce ? 'true' : 'false');
    $('[data-speed-out]').textContent = (state.speed / 100).toFixed(2) + 'x';
  }

  function pick(i, focus) {
    state.anim = i;
    t = 0;
    last = performance.now();
    state.playing = !state.reduce;
    renderText();
    if (focus) rows[i].focus();
  }

  rows.forEach(function (r) {
    r.addEventListener('click', function () { pick(Number(r.getAttribute('data-anim')), false); });
    r.addEventListener('keydown', function (ev) {
      var i = state.anim;
      if (ev.key === 'ArrowDown' || ev.key === 'ArrowRight') { ev.preventDefault(); pick((i + 1) % rows.length, true); }
      if (ev.key === 'ArrowUp' || ev.key === 'ArrowLeft') { ev.preventDefault(); pick((i + rows.length - 1) % rows.length, true); }
      if (ev.key === 'Home') { ev.preventDefault(); pick(0, true); }
      if (ev.key === 'End') { ev.preventDefault(); pick(rows.length - 1, true); }
    });
  });

  $('[data-play]').addEventListener('click', function () {
    var a = current();
    if (state.playing) { state.playing = false; renderText(); return; }
    if (t >= a.dur && !a.loop) t = 0;
    last = performance.now();
    state.playing = true;
    state.reduce = false;
    renderText();
  });
  $('[data-restart]').addEventListener('click', function () {
    t = 0; last = performance.now(); state.playing = !state.reduce; renderText();
  });
  $('[data-speed]').addEventListener('input', function (ev) {
    state.speed = parseInt(ev.target.value, 10) || 100;
    renderText();
  });
  $('[data-reduce]').addEventListener('click', function () {
    state.reduce = !state.reduce;
    state.playing = !state.reduce;
    if (!state.reduce) { t = 0; last = performance.now(); }
    renderText();
  });

  function loop(now) {
    var dt = Math.min(0.2, (now - last) / 1000);
    last = now;
    var a = current();
    if (state.playing && !state.reduce) {
      t += dt * (state.speed / 100);
      if (t > span(a)) {
        if (a.loop) t -= span(a);
        else { t = a.dur; state.playing = false; renderText(); }
      }
    } else if (state.reduce) {
      t = a.dur;
    }
    if (now - frameAt > 83) {
      frameAt = now;
      var g = P.grid(128, 64);
      A.draw(a.key, g, t, { lang: TW.lang(), reduce: state.reduce });
      P.paint(panel.querySelector('canvas'), g, { px: TW.fit(panel, 128, 7, 2), gap: 1, off: pal.off, glow: a.key === 'resting' ? 0.3 : 0.65 });
      $('[data-clock]').textContent = (Math.round(t * 10) / 10).toFixed(1) + ' / ' + span(a).toFixed(1) + ' S';
    }
    requestAnimationFrame(loop);
  }

  TW.onLang(renderText);
  renderText();
  requestAnimationFrame(loop);
})();
