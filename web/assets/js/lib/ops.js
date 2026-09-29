/* Zeichnet die Antwort des Servers auf ein 128x64-Raster, genau so, wie die
   Firmware sie zeichnen soll. Beschreibung der Befehle: .htapp/device/frame.php.
   Eine Seite gilt von from bis to (ms seit 1970), das Geraet zeigt die laufende.

   window.TWOps.pageAt(frame, jetztMs)      laufende Seite oder null
   window.TWOps.render(grid, seite, ctx)    ctx: now, iana, lang, logoUrl, flash, t, reduce */
(function () {
  'use strict';
  var P = window.PX;
  var logos = {};

  var DAYS = { en: ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'], de: ['SON', 'MON', 'DIE', 'MIT', 'DON', 'FRE', 'SAM'] };
  var MONTHS = {
    en: ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'],
    de: ['JAN', 'FEB', 'MRZ', 'APR', 'MAI', 'JUN', 'JUL', 'AUG', 'SEP', 'OKT', 'NOV', 'DEZ']
  };
  var WD = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 };

  function colour(c) { return '#' + String(c || 'FFAA00'); }
  function pad(n) { return String(n).padStart(2, '0'); }

  /* ---------- Spotify, ab Firmware 0.2.0: prog, count, disc wie live.cpp ---------- */
  var DIM = '#8E9AA3', WHITE = '#F2F4F5', DOTS = '#4E5A63', AMBER = '#FFAA00', GROOVE = '#3B444B', LABEL = '#B37800';
  function mmss(s) {
    s = Math.max(0, Math.floor(s));
    var r = s % 60;
    return Math.floor(s / 60) + ':' + (r < 10 ? '0' : '') + r;
  }
  /* Wie clamp() im Entwurf: erst unten, dann oben pruefen. */
  function clampTo(v, a, b) { return v < a ? a : v > b ? b : v; }
  /* cubic-bezier(0.77, 0, 0.175, 1), dieselbe Kurve wie in anim.cpp. */
  function bez(x1, y1, x2, y2) {
    function at(t, p1, p2) { return ((1 - 3 * p2 + 3 * p1) * t * t + (3 * p2 - 6 * p1) * t + 3 * p1) * t; }
    function slope(t, p1, p2) { return 3 * (1 - 3 * p2 + 3 * p1) * t * t + 2 * (3 * p2 - 6 * p1) * t + 3 * p1; }
    return function (x) {
      if (x <= 0) return 0;
      if (x >= 1) return 1;
      var t = x;
      for (var i = 0; i < 8; i++) {
        var s = slope(t, x1, x2);
        if (Math.abs(s) < 1e-6) break;
        var d = at(t, x1, x2) - x;
        if (Math.abs(d) < 1e-6) return at(t, y1, y2);
        t -= d / s;
      }
      var lo = 0, hi = 1;
      t = x;
      for (var j = 0; j < 30; j++) {
        var v = at(t, x1, x2);
        if (Math.abs(v - x) < 1e-6) break;
        if (v < x) lo = t; else hi = t;
        t = (lo + hi) / 2;
      }
      return at(t, y1, y2);
    };
  }
  var EIO = bez(0.77, 0, 0.175, 1);

  /* prog: Zeitleiste, die das Geraet selbst weiterzaehlt. Gespielt in c, Rest als Punkte
     (bernsteinfarben in den letzten zehn Sekunden mit soon), Kopf weiss, darueber 0:00 (mit s),
     die laufende Zeit ueber dem Kopf und das Ende. p: Position in der Pause, dann grau mit pl.
     nl ohne Zeiten, nh ohne Kopf. over: Breite erzwingen, fuer den leer laufenden Balken im
     Uebergang. */
  function drawProg(g, op, nowMs, over) {
    var x0 = op.x | 0, x1 = op.x1 | 0, span = x1 - x0 + 1, d = Math.max(1, +op.d || 1);
    var paused = op.p !== undefined && op.p !== null;
    var pos = paused ? Math.max(0, Math.min(d, +op.p)) : Math.max(0, Math.min(d, nowMs - (+op.t0 || 0)));
    var w = over != null ? over : Math.round(span * pos / d);
    w = Math.max(0, Math.min(span, w));
    var head = x0 + Math.min(span - 1, w);
    var soon = !!op.soon && !paused && d - pos <= 10000;
    if (!op.nl) {
      var cur = paused ? String(op.pl || 'PAUSED') : mmss(pos / 1000);
      var end = mmss(d / 1000);
      var cw = P.width(cur, 1), ew = P.width(end, 1), sw = P.width('0:00', 1);
      var left = x0 + (x0 === 0 ? 2 : 0);
      var cx = clampTo(Math.round(head - (cw - 1) / 2), left, x1 - cw);
      var ex = x1 - ew;
      if (op.s && cx > left + sw + 3) P.text(g, left, op.y, '0:00', DIM, 1, true);
      if (cx + cw + 3 < ex) P.text(g, ex, op.y, end, DIM, 1, true);
      P.text(g, cx, op.y, cur, paused ? DIM : WHITE, 1, true);
    }
    for (var i = w; i < span; i += 2) P.rect(g, x0 + i, op.b, 1, 2, soon ? AMBER : DOTS);
    if (w > 0) P.rect(g, x0, op.b, w, 2, paused ? DIM : colour(op.c || '3DE07C'));
    if (!op.nh) P.rect(g, head, op.hy, 1, op.b + 2 - op.hy, paused ? DIM : WHITE);
  }

  /* count: Minuten und Sekunden. v steht fest, to zaehlt bis dahin herunter, t0 seit dahin
     hoch (hoechstens m). a: l links ab x, r endet vor x, c mittig um x. pre steht davor. */
  function drawCount(g, op, nowMs) {
    var v;
    if (op.v !== undefined && op.v !== null) v = +op.v;
    else if (op.to !== undefined && op.to !== null) v = Math.max(0, Math.ceil((+op.to - nowMs) / 1000));
    else v = Math.max(0, Math.floor((nowMs - (+op.t0 || 0)) / 1000));
    if (op.m !== undefined && op.m !== null) v = Math.min(v, +op.m);
    var s = String(op.pre || '') + mmss(v);
    var w = P.width(s, 1), x = op.x | 0;
    if (op.a === 'r') x -= w;
    else if (op.a === 'c') x = Math.round(x - w / 2);
    P.text(g, x, op.y, s, colour(op.c), 1, true);
  }

  /* disc: die Platte aus dem Layout Platte. Nur rechts neben der Huelle (ab x), dreht mit
     33 1/3 Umdrehungen je Minute, ein heller Streifen zeigt es. sp: spielt; t0: Zeitpunkt des
     letzten Wechsels von Pause und Weiter, dann gleitet sie in 600 ms heraus oder hinein. */
  function drawDisc(g, op, nowMs, reduce) {
    var k = reduce ? 1 : Math.max(0, Math.min(1, (nowMs - (+op.t0 || 0)) / 600));
    var out = op.sp ? EIO(k) : 1 - EIO(k);
    var ang = reduce ? 0 : ((nowMs % 1800) / 1800) * 2 * Math.PI;
    var r = op.r | 0, cy = op.cy | 0, cx = (op.cx | 0) - Math.round((1 - out) * 17);
    for (var y = cy - r; y <= cy + r; y++) {
      for (var x = op.x | 0; x <= cx + r; x++) {
        var dx = x - cx, dy = y - cy, d = Math.sqrt(dx * dx + dy * dy), col = null;
        if (d > r + 0.3) continue;
        if (d > r - 1) col = DOTS;
        else if (d < 5.5) col = d < 2 ? null : LABEL;
        else if (Math.round(d) % 3 === 0) col = GROOVE;
        if (col === GROOVE && out > 0.98) {
          var a = Math.atan2(dy, dx), diff = Math.abs(((a - ang) % (2 * Math.PI) + 3 * Math.PI) % (2 * Math.PI) - Math.PI);
          if (diff < 0.4) col = DIM;
        }
        if (col) P.set(g, x, y, col);
      }
    }
  }

  /* Ein Zeitpunkt, eine Zone, alle Teile aus demselben Formatierer. */
  function parts(nowMs, iana) {
    var p = null;
    try {
      p = new Intl.DateTimeFormat('en-GB', {
        timeZone: iana || 'Europe/Luxembourg', hourCycle: 'h23',
        weekday: 'short', day: 'numeric', month: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      }).formatToParts(new Date(nowMs)).reduce(function (o, x) { o[x.type] = x.value; return o; }, {});
    } catch (e) { p = null; }
    if (!p || !p.hour) {
      var d = new Date(nowMs);
      p = { hour: pad(d.getHours()), minute: pad(d.getMinutes()), second: pad(d.getSeconds()), day: String(d.getDate()), month: String(d.getMonth() + 1), weekday: ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][d.getDay()] };
    }
    return p;
  }

  /* Wie clockText() in der Firmware: ohne h24 im Befehl 24 Stunden. Mit ap steht
     AM oder PM nicht im Text, der Aufrufer setzt es klein daneben. */
  function clockText(op, nowMs, iana) {
    var p = parts(nowMs, iana);
    var h24 = parseInt(p.hour, 10) % 24, h = h24, suffix = '';
    if (op.h24 === false) { suffix = h24 < 12 ? 'AM' : 'PM'; h = h24 % 12; if (h === 0) h = 12; }
    if (suffix && op.ap && (op.z || 1) > 1) suffix = '';
    return pad(h) + ':' + p.minute + (op.sec ? ':' + p.second : '') + (suffix ? ' ' + suffix : '');
  }

  function zoom(op) { return Math.max(1, Math.min(8, parseInt(op.z, 10) || 1)); }

  /* "FRI 25 SEP", der Tag ohne fuehrende Null (FEHLERLISTE 4.3). */
  function dateText(lang, nowMs, iana) {
    var p = parts(nowMs, iana);
    var l = lang === 'de' ? 'de' : 'en';
    var wd = WD[String(p.weekday).slice(0, 3).toLowerCase()];
    if (wd == null) wd = new Date(nowMs).getDay();
    return DAYS[l][wd] + ' ' + parseInt(p.day, 10) + ' ' + MONTHS[l][parseInt(p.month, 10) - 1];
  }

  /* Logos kommen als 3 264 Byte RGB888, zeilenweise, Schwarz ist aus. */
  function loadLogo(code, url) {
    logos[code] = 'loading';
    fetch(url.replace('{code}', encodeURIComponent(code)), { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.arrayBuffer() : null; })
      .then(function (b) { logos[code] = b && b.byteLength === 32 * 34 * 3 ? new Uint8Array(b) : null; })
      .catch(function () { logos[code] = null; });
  }

  function drawLogo(g, op, url) {
    var code = op.code || '';
    var L = logos[code];
    if (code && L === undefined && url) loadLogo(code, url);
    if (L && L.length === 32 * 34 * 3) {
      for (var i = 0; i < 32 * 34; i++) {
        var r = L[i * 3], gr = L[i * 3 + 1], b = L[i * 3 + 2];
        if (r | gr | b) P.set(g, op.x + (i % 32), op.y + Math.floor(i / 32), 'rgb(' + r + ',' + gr + ',' + b + ')');
      }
      return;
    }
    P.rect(g, op.x, op.y, 32, 34, '#1E262C');
    if (code) P.text(g, op.x + 6, op.y + 14, code, '#8E9AA3');
  }

  /* Base64 ohne atob, damit dieselbe Datei im Browser und in Node laeuft. */
  var B64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
  function unbase64(str) {
    var clean = String(str || '').replace(/[^A-Za-z0-9+/]/g, '');
    var out = [], acc = 0, bits = 0;
    for (var i = 0; i < clean.length; i++) {
      acc = (acc << 6) | B64.indexOf(clean.charAt(i));
      bits += 6;
      if (bits >= 8) { bits -= 8; out.push((acc >> bits) & 0xFF); }
    }
    return out;
  }

  /* bmp: die Karte als Bild mit vier Bit je Punkt. 0 ist durchsichtig, sonst zeigt die
     Zahl in die Palette. So kostet ein Bild von 128 x 51 rund 3,3 KB, mit Base64 4,4 KB. */
  function drawBmp(g, op) {
    var w = op.w | 0, h = op.h | 0;
    if (w <= 0 || h <= 0) return;
    var pal = (op.p || []).map(function (c) { return colour(c); });
    var bytes = unbase64(op.d);
    for (var y = 0; y < h; y++) {
      for (var x = 0; x < w; x++) {
        var i = y * w + x;
        var b = bytes[i >> 1];
        if (b === undefined) return;
        var v = (i & 1) === 0 ? (b >> 4) & 0x0F : b & 0x0F;
        if (v === 0) continue;
        var col = pal[v - 1];
        if (col) P.set(g, (op.x | 0) + x, (op.y | 0) + y, col);
      }
    }
  }

  /* dot: ein Punkt, der zwischen zwei Abrufen weiterlaeuft. vx und vy sind LEDs je
     Sekunde, t0 der Zeitpunkt der Position. Genau so rechnet die Firmware. */
  function drawDot(g, op, ctx) {
    /* Hoechstens eine Minute weiter, wie live.cpp: danach stuende der Punkt sonst irgendwo. */
    var dt = op.t0 ? Math.min(60, Math.max(0, (ctx.now - op.t0) / 1000)) : 0;
    if (ctx.reduce) dt = 0;
    var x = Math.round((op.x || 0) + (op.vx || 0) * dt);
    var y = Math.round((op.y || 0) + (op.vy || 0) * dt);
    var col = colour(op.c);
    for (var dy = -2; dy <= 2; dy++) {
      for (var dx = -2; dx <= 2; dx++) {
        if (Math.abs(dx) === 2 || Math.abs(dy) === 2) P.set(g, x + dx, y + dy, null);
      }
    }
    P.rect(g, x - 1, y - 1, 3, 3, col);
    P.set(g, x, y, '#F2F4F5');
  }

  function render(g, page, ctx) {
    if (!page) return;
    var texts = 0;
    /* Neue Notiz: beide Zeilen blinken weiss, dazu ein Rahmen in Bernstein, wie in live.cpp. */
    var flash = !!ctx.flash && !ctx.reduce && page.flash !== undefined;
    (page.ops || []).forEach(function (op) {
      switch (op.t) {
        case 'text': {
          var s = String(op.s == null ? '' : op.s);
          var z = zoom(op);
          var x = op.x === 'c' ? Math.round((128 - P.width(s, z)) / 2) : op.x;
          var col = colour(op.c);
          if (flash && texts < 2) col = '#F2F4F5';
          texts++;
          P.text(g, x, op.y, s, col, z, true);
          break;
        }
        case 'ticker': {
          /* Laufschrift: der Versatz kommt aus der Uhr, damit ein Seitenwechsel sie
             nicht zuruecksetzt. Angehalten steht sie am Anfang. */
          var ts = String(op.s == null ? '' : op.s) + '      ';
          var tw = P.width(ts, 1) + 1;
          var shift = ctx.reduce ? 0 : Math.floor(((ctx.now / 1000) * (op.v || 14)) % tw);
          var copies = Math.ceil((128 - op.x) / tw) + 1;
          for (var k = 0; k < copies; k++) P.text(g, op.x - shift + k * tw, op.y, ts, colour(op.c), 1, true);
          break;
        }
        case 'rect':
        case 'bar': {
          /* dot: nur jede n-te Spalte, etwa der Rest einer Flugstrecke als Punkte. 000000 loescht,
             wie in der Firmware, wo Schwarz aus heisst. */
          var dot = parseInt(op.dot, 10) || 0;
          var rc = String(op.c || '').toUpperCase() === '000000' ? null : colour(op.c);
          if (dot >= 2 && dot <= 16) {
            for (var dx = 0; dx < op.w; dx += dot) P.rect(g, op.x + dx, op.y, 1, op.h || 2, rc);
          } else {
            P.rect(g, op.x, op.y, op.w, op.h || 2, rc);
          }
          break;
        }
        case 'frame':
          P.frame(g, op.x, op.y, op.w, op.h, colour(op.c));
          break;
        case 'bmp':
          drawBmp(g, op);
          break;
        case 'dot':
          drawDot(g, op, ctx);
          break;
        case 'prog':
          drawProg(g, op, ctx.now, ctx.progOver);
          break;
        case 'count':
          drawCount(g, op, ctx.now);
          break;
        case 'disc':
          drawDisc(g, op, ctx.now, !!ctx.reduce);
          break;
        case 'logo':
          drawLogo(g, op, ctx.logoUrl);
          break;
        case 'clock': {
          var txt = clockText(op, ctx.now, ctx.iana);
          var cz = zoom(op);
          if (op.seg) {
            /* Geisterrahmen nur hinter Ziffern, in der Farbe aus g (ab Firmware 0.2.2), sonst Bernstein. */
            var ghost = op.g ? colour(op.g) : '#2A2000';
            for (var i = 0; i < txt.length; i++) {
              if (txt[i] < '0' || txt[i] > '9') continue;
              P.frame(g, op.x + i * 6 * cz, op.y, 5 * cz, 7 * cz, ghost);
            }
          }
          /* Ohne Sekunden blinkt der Doppelpunkt im Sekundentakt, wie in live.cpp. */
          if (!op.sec && !op.seg && !ctx.reduce && txt.charAt(2) === ':' && parseInt(parts(ctx.now, ctx.iana).second, 10) % 2 === 1) {
            txt = txt.slice(0, 2) + ' ' + txt.slice(3);
          }
          P.text(g, op.x, op.y, txt, colour(op.c), cz, true);
          if (op.h24 === false && op.ap && cz > 1) {
            var hour = parseInt(parts(ctx.now, ctx.iana).hour, 10) % 24;
            P.text(g, op.x + P.width(txt, cz) + 4, op.y + 7 * cz - 7, hour < 12 ? 'AM' : 'PM', colour(op.c), 1, true);
          }
          break;
        }
        case 'date': {
          var d = dateText(op.lang || ctx.lang, ctx.now, ctx.iana);
          P.text(g, Math.round((128 - P.width(d, 1)) / 2), op.y, d, colour(op.c), 1, true);
          break;
        }
        case 'anim':
          if (window.TWAnim) {
            window.TWAnim.draw(op.id, g, ctx.t || 0, {
              now: ctx.now, iana: ctx.iana, lang: op.lang || ctx.lang, h24: op.h24 !== false, reduce: !!ctx.reduce, bright: ctx.bright
            });
          }
          break;
      }
    });
    if (flash) P.frame(g, 0, 0, 128, 64, '#FFAA00');
  }

  function pageAt(frame, nowMs) {
    if (!frame || !frame.pages || !frame.pages.length) return null;
    var best = null;
    for (var i = 0; i < frame.pages.length; i++) {
      var p = frame.pages[i];
      if (p.from <= nowMs && nowMs < p.to) return p;
      if (p.from <= nowMs) best = p;
    }
    return best || frame.pages[0];
  }

  window.TWOps = { render: render, pageAt: pageAt, clockText: clockText, dateText: dateText, drawProg: drawProg };
})();
