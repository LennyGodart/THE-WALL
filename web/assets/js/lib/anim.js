/* Die dreizehn Geraete-Animationen, als Vorlage fuer die Firmware und fuer die
   Vorschau auf der Geraeteseite (waiting, resting). Jede ist eine Funktion der
   verstrichenen Zeit t in Sekunden, nichts wird gespeichert.

   window.TWAnim.list                   [{key, dur, loop}]
   window.TWAnim.draw(key, grid, t, o)  o: lang, reduce, now, iana, h24, bright, phone, user
   window.TWAnim.push/drop/swap/roll    Uebergaenge zwischen Seiten wie in anim.cpp

   Gegenueber dem Entwurf korrigiert: nur ein Absacken beim Einschalten statt
   zwoelf Blitzen pro Sekunde, Netzname Heimnetz-2G, beim Update kein
   "Nicht ausstecken" mehr, weil zwei Partitionen das ungefaehrlich machen. */
(function () {
  'use strict';
  var P = window.PX;
  var pal = { accent: '#FFAA00', white: '#F2F4F5', dim: '#8E9AA3', cyan: '#35D6FF', green: '#3DE07C', red: '#FF4A1C' };

  var LIST = [
    { key: 'boot', dur: 2.2, loop: false },
    { key: 'wifi', dur: 3.0, loop: true },
    { key: 'connecting', dur: 2.4, loop: true },
    { key: 'address', dur: 3.0, loop: true },
    { key: 'paired', dur: 15.0, loop: true },
    { key: 'modeswap', dur: 1.6, loop: true },
    { key: 'waiting', dur: 4.0, loop: true },
    { key: 'note', dur: 2.6, loop: false },
    { key: 'resting', dur: 14.0, loop: true },
    { key: 'noserver', dur: 3.2, loop: true },
    { key: 'nowifi', dur: 3.2, loop: true },
    { key: 'updating', dur: 5.0, loop: false },
    { key: 'poweroff', dur: 2.0, loop: false }
  ];

  function de(o) { return o.lang === 'de'; }
  function centre(g, y, s, col, z) { P.text(g, Math.round((128 - P.width(s, z || 1)) / 2), y, s, col, z || 1); }

  /* Von oben gesehen, Nase links, weil hier jedes Flugzeug nach links fliegt. */
  function plane(g, x, y, col) {
    for (var i = -3; i <= 2; i++) P.set(g, x + i, y, col);
    for (var j = -2; j <= 2; j++) P.set(g, x, y + j, col);
    P.set(g, x + 2, y - 1, col);
    P.set(g, x + 2, y + 1, col);
  }

  function nowText(o, fallback) {
    if (o.now && window.TWOps) return window.TWOps.clockText({ h24: o.h24 !== false, sec: false }, o.now, o.iana);
    return fallback;
  }

  var A = {};

  /* 01: Zeilenscan wie beim echten Hochfahren, am Ende ein einziges Absacken. */
  A.boot = function (g, t, dur) {
    var full = P.grid(128, 64);
    P.text(full, 16, 18, 'THE', pal.accent, 2);
    P.text(full, 64, 18, 'WALL', pal.white, 2);
    P.text(full, 34, 40, '128 X 64', pal.dim);
    var rows = Math.floor(64 * Math.min(1, t / (dur * 0.62)));
    for (var y = 0; y <= rows && y < 64; y++) {
      var edge = y === rows && t < dur * 0.62;
      for (var x = 0; x < 128; x++) {
        var col = full.data[y * 128 + x];
        if (col) P.set(g, x, y, edge ? pal.cyan : col);
      }
      if (edge) for (var x2 = 0; x2 < 128; x2 += 2) P.set(g, x2, y, pal.cyan);
    }
    if (t > dur * 0.62 && t < dur * 0.78) {
      var k = (t - dur * 0.62) / (dur * 0.16);
      if (k < 0.5) for (var i = 0; i < g.data.length; i++) if (g.data[i]) g.data[i] = '#3A2C00';
    }
  };

  /* Unter Helligkeit 96 verschwinden dunkle Toene auf dem Panel (CIE-Kurve der Panel-
     Bibliothek, dann die Helligkeit), dann gilt der hellere Ton. Wie night() in anim.cpp. */
  function night(o, day, dark) { return o.bright != null && o.bright < 96 ? dark : day; }

  /* 02: Einrichtungsnetz. Oben, was zu tun ist, unten Netzname und Passwort im Klartext,
     dazwischen eine kleine Szene: Funkboegen, die nacheinander aufleuchten, auf der
     gepunkteten Linie laeuft ein Strich zum Handy, auf dem Handy warten drei Punkte.
     Ist ein Handy verbunden, leuchtet es auf und zeigt die Seite. Bis Firmware 0.1.7
     stand hier ein QR-Code; auf den LED-Punkten fand ihn die Kamera eines Handys nicht
     zuverlaessig (CHANGELOG 39, 40, 42). Wie wifi() in anim.cpp. */
  A.wifi = function (g, t, dur, o) {
    var cx = 44, cy = 33;
    var phase = (t / dur) * 3;
    for (var arc = 0; arc < 3; arc++) {
      var r = 5 + arc * 5;
      var lit = o.reduce ? true : (phase % 3) >= arc;
      var col = lit ? pal.accent : '#3A2C00';
      for (var deg = 35; deg <= 145; deg += 3) {
        var rad = deg * Math.PI / 180;
        P.set(g, Math.round(cx + Math.cos(rad) * r), Math.round(cy - Math.sin(rad) * r), col);
      }
    }
    P.rect(g, cx - 1, cy - 1, 3, 3, pal.accent);
    P.rect(g, 0, 44, 128, 1, '#1B2126');
    if (o.phone) {
      /* Oben, dass das Handy drin ist, unten, wohin es jetzt geht. */
      centre(g, 1, de(o) ? 'HANDY VERBUNDEN' : 'PHONE CONNECTED', pal.green);
      P.rect(g, 62, 25, 15, 1, pal.cyan);
      P.frame(g, 78, 10, 22, 30, pal.white);
      P.rect(g, 86, 12, 6, 1, pal.white);
      P.rect(g, 81, 16, 16, 20, '#1A7A96');
      P.text(g, 83, 22, 'WI', pal.white);
      centre(g, 47, de(o) ? 'IM BROWSER OEFFNEN' : 'OPEN IN A BROWSER', pal.dim);
      centre(g, 56, 'HTTP://4.3.2.1', pal.accent);
      return;
    }
    centre(g, 1, de(o) ? 'HANDY VERBINDEN' : 'CONNECT A PHONE', pal.accent);
    /* Die Linie gepunktet, weil noch keine Verbindung steht. Der Strich laeuft zweimal je
       Durchlauf, die Punkte auf dem Handy wandern im selben Takt: jeder leuchtet 0,5 s. */
    for (var x = 62; x <= 76; x += 2) P.set(g, x, 25, '#1A7A96');
    if (!o.reduce) P.rect(g, 62 + Math.floor((((t / dur) * 2) % 1) * 14), 25, 2, 1, pal.cyan);
    P.frame(g, 78, 10, 22, 30, pal.dim);
    P.rect(g, 86, 12, 6, 1, pal.dim);
    var on = Math.floor((t / dur) * 6) % 3;
    for (var i = 0; i < 3; i++) P.rect(g, 83 + i * 5, 25, 2, 2, o.reduce || i === on ? pal.cyan : '#1A7A96');
    centre(g, 47, 'THE WALL SETUP', pal.white);
    /* "PASSWORT 12345678" als eine Zeile gemittelt, das Wort leiser als das Passwort. */
    var label = de(o) ? 'PASSWORT ' : 'PASSWORD ';
    var x0 = Math.round((128 - P.width(label + '12345678', 1)) / 2);
    P.text(g, x0, 56, label, pal.dim);
    P.text(g, x0 + P.width(label, 1) + 1, 56, '12345678', pal.accent);
  };

  /* 03: Verbindungsaufbau mit Netzname und Balken. */
  A.connecting = function (g, t, dur, o) {
    P.text(g, 2, 10, 'HEIMNETZ-2G', pal.white);
    var msg = de(o) ? 'VERBINDE' : 'CONNECTING';
    P.text(g, 2, 22, msg, pal.accent);
    var dots = o.reduce ? 3 : Math.floor((t / dur) * 8) % 4;
    for (var i = 0; i < dots; i++) P.text(g, 2 + P.width(msg, 1) + 4 + i * 6, 22, '.', pal.accent);
    var prog = Math.min(1, t / dur);
    P.rect(g, 2, 38, 124, 5, '#1B2126');
    P.rect(g, 2, 38, Math.round(124 * prog), 5, pal.accent);
    P.text(g, 2, 50, String(Math.round(prog * 100)) + '%', pal.dim);
  };

  /* 04: die Adresse, einzeilig und gerahmt, weil man sie abtippt. */
  A.address = function (g, t, dur, o) {
    centre(g, 6, de(o) ? 'IM BROWSER OEFFNEN' : 'OPEN IN A BROWSER', pal.dim);
    var ip = '192.168.1.42';
    P.frame(g, 8, 20, 112, 20, '#3A2C00');
    centre(g, 27, ip, pal.accent);
    centre(g, 47, de(o) ? 'SCHLUESSEL FEHLT' : 'NO KEY YET', pal.dim);
    var blink = o.reduce ? true : Math.floor(t * 2) % 2 === 0;
    P.rect(g, 8, 59, 112, 2, blink ? pal.green : '#1E3A2C');
  };

  /* 05: Auspacken in drei Akten: Radar, Anmeldung, Begruessung. */
  A.paired = function (g, t, dur, o) {
    var radarEnd = dur * 0.34, logEnd = dur * 0.74;
    /* Wie anim.cpp: der Name des Kontos. Ohne Konto (Startseite, Animationsseite) der Beispielname. */
    var who = String(o.user || 'LENNY').toUpperCase();
    if (t < radarEnd) {
      var cx = 64, cy = 28;
      [10, 20, 30].forEach(function (r) {
        for (var deg = 0; deg < 360; deg += 5) {
          var rad = deg * Math.PI / 180;
          P.set(g, Math.round(cx + Math.cos(rad) * r), Math.round(cy + Math.sin(rad) * r * 0.6), '#1E3A2C');
        }
      });
      /* Mit reduzierter Bewegung steht der Strahl, und der Fund blinkt nicht (wie anim.cpp). */
      var ang = o.reduce ? 1.2 : (t / radarEnd) * Math.PI * 4;
      for (var r2 = 2; r2 < 31; r2++) P.set(g, Math.round(cx + Math.cos(ang) * r2), Math.round(cy + Math.sin(ang) * r2 * 0.6), pal.green);
      P.set(g, cx, cy, pal.green);
      if (t > radarEnd * 0.66) {
        plane(g, 88, 18, pal.accent);
        /* Zwei Blitze pro Sekunde, unter der Grenze von drei (vorher genau drei). */
        if (!o.reduce && Math.floor(t * 4) % 2 === 0) {
          for (var dd = 4; dd < 9; dd++) {
            for (var dg = 0; dg < 360; dg += 30) {
              var rr = dg * Math.PI / 180;
              P.set(g, Math.round(88 + Math.cos(rr) * dd), Math.round(18 + Math.sin(rr) * dd * 0.6), '#6B4A00');
            }
          }
        }
        P.text(g, 2, 56, 'LGL9561  4.2NM', pal.dim);
      } else {
        P.text(g, 2, 56, de(o) ? 'SUCHE...' : 'SCANNING...', '#3A5A48');
      }
      return;
    }
    if (t < logEnd) {
      var lines = de(o)
        ? ['> WLAN HEIMNETZ-2G OK', '> SCHLUESSEL OK', '> KONTO ' + who, '> ADSB.LOL VERBUNDEN', '> 14 FLUGZEUGE NAH']
        : ['> WIFI HEIMNETZ-2G OK', '> KEY ACCEPTED', '> ACCOUNT ' + who, '> ADSB.LOL LINKED', '> 14 AIRCRAFT NEAR'];
      var span = logEnd - radarEnd;
      var per = span / (lines.length + 0.5);
      var local = t - radarEnd;
      for (var i = 0; i < lines.length; i++) {
        var start = i * per;
        if (local < start) break;
        var line = lines[i];
        var typed = o.reduce ? line.length : Math.min(line.length, Math.ceil(((local - start) / per) * line.length * 1.7));
        P.text(g, 2, 2 + i * 12, line.slice(0, typed), i === lines.length - 1 ? pal.green : pal.dim);
        if (typed < line.length) P.rect(g, 2 + typed * 6, 2 + i * 12, 5, 7, pal.accent);
      }
      return;
    }
    centre(g, 10, de(o) ? 'HALLO' : 'HELLO', pal.dim, 2);
    /* Doppelt gross passen 10 Zeichen, ein laengerer Name steht einfach gross. */
    centre(g, 28, who, pal.accent, P.width(who, 2) <= 128 ? 2 : 1);
    var lit = o.reduce ? true : Math.floor((t - logEnd) * 1.2) % 2 === 0;
    centre(g, 50, de(o) ? 'MODUS WAEHLEN' : 'PICK A MODE', lit ? pal.dim : '#2A3239');
  };

  function flightFrame(g) {
    P.rect(g, 2, 2, 32, 34, '#0B4DA2');
    P.text(g, 8, 10, 'LGL', '#DCE8F7');
    P.rect(g, 8, 22, 18, 1, '#DCE8F7');
    P.text(g, 38, 2, 'LUXAIR', pal.accent);
    P.text(g, 38, 14, 'LUX→LIS', pal.white);
    P.text(g, 38, 26, '737-800', pal.cyan);
    P.text(g, 2, 38, 'ALT:8.8KFT,SPD:720KMH', pal.white);
    P.text(g, 2, 50, 'TRK:214DEG,VR:+3.7M/S', pal.dim);
  }

  /* 06: der alte Inhalt wird nach links geschoben, der neue kommt nach. */
  A.modeswap = function (g, t, dur, o) {
    var shift = Math.round((t / dur) * 128);
    var a = P.grid(128, 64), b = P.grid(128, 64);
    flightFrame(a);
    P.text(b, 22, 18, '20:14', pal.accent, 2);
    P.text(b, 38, 40, de(o) ? 'FRE 25 SEP' : 'FRI 25 SEP', pal.dim);
    for (var y = 0; y < 64; y++) {
      for (var x = 0; x < 128; x++) {
        var from = a.data[y * 128 + x];
        if (from && x - shift >= 0) P.set(g, x - shift, y, from);
        var to = b.data[y * 128 + x];
        if (to) P.set(g, x - shift + 128, y, to);
      }
    }
    if (shift > 0 && shift < 128) for (var y2 = 0; y2 < 64; y2 += 2) P.set(g, 128 - shift, y2, '#2C353C');
  };

  /* 07: ein leerer Radarschirm statt eines leeren Panels. */
  A.waiting = function (g, t, dur, o) {
    var cx = 64, cy = 26;
    [9, 18, 27].forEach(function (r) {
      for (var deg = 0; deg < 360; deg += 6) {
        var rad = deg * Math.PI / 180;
        P.set(g, Math.round(cx + Math.cos(rad) * r), Math.round(cy + Math.sin(rad) * r * 0.6), '#1E3A2C');
      }
    });
    var ang = o.reduce ? 1.2 : (t / dur) * Math.PI * 2;
    for (var r2 = 2; r2 < 28; r2++) P.set(g, Math.round(cx + Math.cos(ang) * r2), Math.round(cy + Math.sin(ang) * r2 * 0.6), '#2C6B4F');
    P.set(g, cx, cy, pal.green);
    centre(g, 44, de(o) ? 'KEIN FLUGZEUG' : 'NO AIRCRAFT', pal.dim);
    centre(g, 55, nowText(o, '02:47'), pal.white);
  };

  /* 08: dreimal blinken, dann ruhig. Gut zwei Wechsel pro Sekunde, unter drei Blitzen. */
  A.note = function (g, t, dur, o) {
    var win = dur * 0.55, bright = false;
    if (!o.reduce && t < win) bright = Math.floor((t / win) * 3 * 2) % 2 === 0;
    centre(g, 18, 'ALLES GUTE', bright ? pal.white : pal.accent);
    centre(g, 34, 'ZUM GEBURTSTAG', bright ? pal.white : pal.dim);
    if (bright) P.frame(g, 0, 0, 128, 64, pal.accent);
  };

  /* 09: nie wirklich aus. Schwache Uhr, Staub, ab und zu ein Gast. */
  A.resting = function (g, t, dur, o) {
    centre(g, 22, nowText(o, '02:47'), night(o, '#6B4A00', '#B37800'), 2);
    var r = P.rnd(5);
    for (var i = 0; i < 26; i++) {
      var seed = r();
      var speed = 0.25 + seed * 0.5;
      var x = Math.round((seed * 128 + (o.reduce ? 0 : t * speed * 9)) % 128);
      var y = Math.round(r() * 64);
      if (!g.data[y * 128 + x]) P.set(g, x, y, night(o, '#243038', '#4E5A63'));
    }
    if (o.reduce) return;
    var slot = Math.floor(t / dur) % 3;
    var local = (t % dur) / dur;
    if (slot === 0 && local > 0.15 && local < 0.62) {
      var prog = (local - 0.15) / 0.47;
      var px = Math.round(140 - prog * 156), py = 52;
      plane(g, px, py, night(o, '#7A5200', '#B37800'));
      for (var k = 3; k < 16; k++) {
        if (px + k < 128 && px + k >= 0 && !g.data[py * 128 + px + k]) P.set(g, px + k, py, k < 8 ? night(o, '#3A2C00', '#6B4A00') : night(o, '#241B00', '#4A3800'));
      }
    }
    if (slot === 1 && local > 0.3 && local < 0.45) {
      var p2 = (local - 0.3) / 0.15;
      var sx = Math.round(10 + p2 * 100), sy = Math.round(6 + p2 * 12);
      for (var s = 0; s < 5; s++) P.set(g, sx - s, sy - Math.round(s * 0.6), s === 0 ? night(o, '#5A6670', '#8E9AA3') : night(o, '#2A3239', '#4E5A63'));
    }
    if (slot === 2 && local > 0.55) {
      var mx = 112, my = 12, rad = 6;
      for (var yy = -rad; yy <= rad; yy++) {
        for (var xx = -rad; xx <= rad; xx++) {
          if (Math.hypot(xx, yy) > rad) continue;
          if (xx > -2 + Math.sin(yy / rad * 1.2) * 2) P.set(g, mx + xx, my + yy, night(o, '#2E3238', '#4E5A63'));
        }
      }
    }
  };

  /* 10: die Uhr laeuft weiter, die Daten fehlen. */
  A.noserver = function (g, t, dur, o) {
    centre(g, 6, '20:14', pal.accent, 2);
    centre(g, 26, de(o) ? 'FRE 25 SEP' : 'FRI 25 SEP', pal.dim);
    P.rect(g, 0, 38, 128, 1, '#1B2126');
    var blink = o.reduce ? true : Math.floor(t * 1.4) % 2 === 0;
    centre(g, 44, de(o) ? 'KEIN SERVER' : 'NO SERVER', blink ? pal.red : '#5C1C0A');
    centre(g, 56, de(o) ? 'UHR LAEUFT WEITER' : 'CLOCK STILL RUNS', pal.dim);
  };

  /* 11: ein Blick auf den Router hilft, deshalb steht der Netzname dabei. */
  A.nowifi = function (g, t, dur, o) {
    var cx = 64, cy = 34;
    for (var arc = 0; arc < 3; arc++) {
      var r = 9 + arc * 8;
      for (var deg = 35; deg <= 145; deg += 2) {
        var rad = deg * Math.PI / 180;
        P.set(g, Math.round(cx + Math.cos(rad) * r), Math.round(cy - Math.sin(rad) * r), '#5C1C0A');
      }
    }
    P.rect(g, cx - 1, cy - 1, 3, 3, '#5C1C0A');
    for (var i = -14; i <= 14; i++) P.set(g, cx + i, cy - 12 + i, pal.red);
    centre(g, 45, de(o) ? 'WLAN WEG' : 'WI-FI LOST', pal.red);
    centre(g, 56, 'HEIMNETZ-2G', pal.dim);
  };

  /* 12: darf nicht schwarz werden. Ausstecken ist ungefaehrlich, das alte System startet wieder. */
  A.updating = function (g, t, dur, o) {
    P.text(g, 2, 6, 'UPDATE 0.5.0', pal.accent);
    var prog = Math.min(1, t / (dur * 0.9));
    P.rect(g, 2, 22, 124, 8, '#1B2126');
    P.rect(g, 2, 22, Math.round(124 * prog), 8, pal.accent);
    P.text(g, 2, 36, String(Math.round(prog * 100)) + '%', pal.white);
    P.text(g, 2, 52, de(o) ? 'ALTE VERSION BLEIBT' : 'OLD VERSION KEPT', pal.dim);
  };

  /* 13: ein Vorhang von beiden Raendern zur Mitte, die Wortmarke bis zuletzt. */
  A.poweroff = function (g, t, dur) {
    var full = P.grid(128, 64);
    P.text(full, 16, 25, 'THE', pal.accent, 2);
    P.text(full, 64, 25, 'WALL', pal.white, 2);
    var shrink = Math.round(32 * Math.min(1, t / dur));
    for (var y = shrink; y < 64 - shrink; y++) {
      for (var x = 0; x < 128; x++) {
        var col = full.data[y * 128 + x];
        if (col) P.set(g, x, y, col);
      }
    }
    if (shrink > 0 && shrink < 32) {
      for (var x2 = 0; x2 < 128; x2 += 2) {
        P.set(g, x2, shrink, '#3A2C00');
        P.set(g, x2, 63 - shrink, '#3A2C00');
      }
    }
  };

  /* Vorschau des Pixel-Editors, der erst nach dem 25. kommt. */
  A.pixeldemo = function (g) {
    var r = P.rnd(11);
    for (var y = 0; y < 64; y++) for (var x = 0; x < 128; x++) {
      var v = r();
      if (v > 0.93) P.set(g, x, y, pal.accent);
      else if (v > 0.88) P.set(g, x, y, pal.cyan);
      else if (v > 0.84) P.set(g, x, y, '#2A3239');
    }
    P.rect(g, 30, 26, 68, 13, '#050607');
    P.text(g, 34, 28, '8192 PIXEL', pal.white);
  };

  /* ---------- Uebergaenge wie in anim.cpp, fuer die Vorschau der Geraeteseite ----------
     g ist ein leeres Raster 128 x 64, from und to fertige Raster, k von 0 bis 1. */
  function px(g, x, y) { return g.data[y * 128 + x] || null; }
  function clamp(k) { return Math.min(1, Math.max(0, k)); }

  /* cubic-bezier wie auf der Webseite und in anim.cpp: EIO fuer Bewegung auf der Flaeche,
     EOUT fuer das, was einrollt. */
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
  var EIO = bez(0.77, 0, 0.175, 1), EOUT = bez(0.23, 1, 0.32, 1);

  /* ---------- Spotify: Walze, Karussell, Zeilen (anim.cpp, ab Firmware 0.2.0) ----------
     w: Fenster aus der Seite (fxw): c Cover, t Text, b Zeitleiste als [x0, y0, x1, y1],
     l Zeilen, p Zeilenabstand, s 1 = Textblock schiebt eine Zeile hoch. */
  /* Senkrecht: alt nach oben hinaus, neu von unten, um s Punkte bei Hoehe D. */
  function reelWin(g, from, to, win, s, D) {
    for (var y = win[1]; y <= win[3]; y++) {
      for (var x = win[0]; x <= win[2]; x++) {
        var c = null, yf = y + s, yt = y + s - D;
        if (yf <= win[3]) c = px(from, x, yf);
        if (yt >= win[1] && yt <= win[3]) { var ct = px(to, x, yt); if (ct) c = ct; }
        g.data[y * 128 + x] = c;
      }
    }
  }
  /* Waagerecht: alt nach links hinaus, neu von rechts. */
  function slideWin(g, from, to, win, s) {
    var D = win[2] - win[0] + 1;
    for (var y = win[1]; y <= win[3]; y++) {
      for (var x = win[0]; x <= win[2]; x++) {
        var xf = x + s;
        g.data[y * 128 + x] = xf <= win[2] ? px(from, xf, y) : px(to, xf - D, y);
      }
    }
  }
  /* Jede Textzeile rollt in ihrem Streifen hoch, 40 ms nach der vorigen, je 280 ms. */
  function rollLines(g, from, to, w, tMs) {
    var tw = w.t, p = w.p, lines = w.l || [];
    for (var y0 = tw[1]; y0 <= tw[3]; y0++) for (var x0 = tw[0]; x0 <= tw[2]; x0++) g.data[y0 * 128 + x0] = px(to, x0, y0);
    lines.forEach(function (ly, i) {
      var s = Math.round(EOUT(clamp((tMs - i * 40) / 280)) * p);
      var top = Math.max(tw[1], ly - 1), end = Math.min(tw[3] + 1, ly - 1 + p);
      for (var y = top; y < end; y++) {
        for (var x = tw[0]; x <= tw[2]; x++) {
          var yf = y + s;
          g.data[y * 128 + x] = yf < end ? px(from, x, yf) : px(to, x, y + s - p);
        }
      }
    });
  }
  /* Walze (reel) oder Karussell (carousel) in 560 ms. Das Cover bewegt sich als Ganzes, der
     Text folgt 120 ms spaeter. In der ersten Haelfte laeuft der alte Balken leer (oldProg, zum
     Zeitpunkt freezeMs gezeichnet), danach steht die neue Zeitleiste. */
  function song(g, from, to, w, k, kind, oldProg, freezeMs) {
    k = clamp(k);
    var i;
    for (i = 0; i < 128 * 64; i++) g.data[i] = to.data[i] || null;
    var c = w.c;
    if (c) {
      if (kind === 'carousel') slideWin(g, from, to, c, Math.round(EIO(k) * (c[2] - c[0] + 1)));
      else reelWin(g, from, to, c, Math.round(EIO(k) * (c[3] - c[1] + 1)), c[3] - c[1] + 1);
    }
    var tMs = k * 560 - 120;
    if (w.s) reelWin(g, from, to, w.t, Math.round(EOUT(clamp(tMs / 280)) * w.p), w.p);
    else rollLines(g, from, to, w, tMs);
    var b = w.b;
    if (b && k < 0.55 && oldProg && window.TWOps) {
      var tmp = P.grid(128, 64);
      var span = (oldProg.x1 | 0) - (oldProg.x | 0) + 1, d = Math.max(1, +oldProg.d || 1);
      var pos = oldProg.p != null ? +oldProg.p : freezeMs - (+oldProg.t0 || 0);
      pos = Math.max(0, Math.min(d, pos));
      var wF = Math.max(0, Math.min(span, Math.round(span * pos / d)));
      window.TWOps.drawProg(tmp, oldProg, freezeMs, Math.round(wF * (1 - EOUT(k / 0.55))));
      for (var y = b[1]; y <= b[3]; y++) for (var x = b[0]; x <= b[2]; x++) g.data[y * 128 + x] = tmp.data[y * 128 + x] || null;
    }
  }
  /* Ansage (lines) in 400 ms: alles steht sofort, nur die Textzeilen rollen einzeln herein. */
  function lines(g, from, to, w, k) {
    for (var i = 0; i < 128 * 64; i++) g.data[i] = to.data[i] || null;
    rollLines(g, from, to, w, clamp(k) * 400);
  }

  /* Wechsel des Modus in 1,6 s: der alte Inhalt geht nach links, der neue kommt nach. */
  function push(g, from, to, k) {
    var shift = Math.round(clamp(k) * 128);
    for (var y = 0; y < 64; y++) {
      for (var x = 0; x < 128; x++) {
        var a = px(from, x, y), b = px(to, x, y);
        if (a && x - shift >= 0) P.set(g, x - shift, y, a);
        if (b) P.set(g, x - shift + 128, y, b);
      }
    }
    if (shift > 0 && shift < 128) for (var y2 = 0; y2 < 64; y2 += 2) P.set(g, 128 - shift, y2, '#2C353C');
  }

  /* Einstieg in die Abfahrtstafel in 1,1 s: alles ausser den Zeilen sofort, Zeile i ab
     k = 0,12 + i * 0,14, rutscht in 0,16 herein, solange nur ihre Linie gedimmt. */
  function drop(g, to, bands, k) {
    k = clamp(k);
    var n = Math.min(6, bands.length);
    for (var y = 0; y < 64; y++) {
      var band = -1, x;
      for (var i = 0; i < n; i++) if (y >= bands[i] && y < bands[i] + 7) band = i;
      if (band >= 0) {
        var at = 0.12 + band * 0.14;
        if (k < at) continue;
        var off = Math.round((1 - Math.min(1, (k - at) / 0.16)) * 8);
        if (off > 0) {
          for (x = 2; x < 26; x++) if (px(to, x, y)) P.set(g, x + off * 2, y, '#3A2C00');
          continue;
        }
      }
      for (x = 0; x < 128; x++) if (px(to, x, y)) P.set(g, x, y, px(to, x, y));
    }
  }

  function chan(c) {
    if (!c) return [0, 0, 0];
    if (c.charAt(0) === '#') { var n = parseInt(c.slice(1, 7), 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; }
    var m = /rgb\((\d+),\s*(\d+),\s*(\d+)\)/.exec(c);
    return m ? [+m[1], +m[2], +m[3]] : [0, 0, 0];
  }

  /* Flugwechsel in 400 ms: rechter Block bis y 37 um 12 Pixel nach oben, Logo und untere
     Zeilen je Kanal gemischt, der Balken ab y 61 sofort neu. */
  function swap(g, from, to, k) {
    k = clamp(k);
    var shift = Math.round(k * 12);
    for (var y = 0; y < 64; y++) {
      for (var x = 0; x < 128; x++) {
        var a = px(from, x, y), b = px(to, x, y);
        if (y < 38 && x >= 38) {
          /* Jede Zeile rollt in ihrem eigenen Streifen von 12 Pixeln. */
          var slot = Math.floor(y / 12), ya = y - shift, yb = y + 12 - shift;
          if (a && ya >= 0 && Math.floor(ya / 12) === slot) P.set(g, x, ya, a);
          if (b && yb < 38 && Math.floor(yb / 12) === slot) P.set(g, x, yb, b);
        } else if (y >= 61) {
          if (b) P.set(g, x, y, b);
        } else if (a || b) {
          var ca = chan(a), cb = chan(b);
          var r = Math.round(ca[0] * (1 - k) + cb[0] * k), gr = Math.round(ca[1] * (1 - k) + cb[1] * k), bl = Math.round(ca[2] * (1 - k) + cb[2] * k);
          if (r || gr || bl) P.set(g, x, y, '#' + ((1 << 24) | (r << 16) | (gr << 8) | bl).toString(16).slice(1).toUpperCase());
        }
      }
    }
  }

  /* Dieselbe Abfahrt? Linie und Anfang des Ziels links von x 70; die Zeit rechts aendert
     sich jede Minute. Leere Baender sind nie gleich. */
  function sameRow(a, ya, b, yb) {
    var any = false;
    for (var r = 0; r < 7; r++) {
      for (var x = 0; x < 70; x++) {
        var pa = px(a, x, ya + r), pb = px(b, x, yb + r);
        if (pa !== pb) return false;
        if (pa) any = true;
      }
    }
    return any;
  }

  /* Neue Minute auf der Tafel in 240 ms: faellt eine Abfahrt weg, ruecken die anderen Zeilen
     nach, neue rollen im eigenen Band von unten herein; wo eine Zeile stehen bleibt, rollen nur
     die Spalten, die sich aendern. Wie anim::roll. */
  function roll(g, from, to, bands, k) {
    k = clamp(k);
    var off = Math.round(k * 8), n = Math.min(6, bands.length);
    var ok = function (top) { return top >= 0 && top <= 57; };
    var src = [], moves = false, i, j, r, x, top;
    for (i = 0; i < n; i++) {
      src[i] = -2;
      if (!ok(bands[i]) || sameRow(from, bands[i], to, bands[i])) { src[i] = -1; continue; }
      for (j = 0; j < n; j++) {
        if (j !== i && ok(bands[j]) && sameRow(from, bands[j], to, bands[i])) { src[i] = j; moves = true; break; }
      }
    }
    for (i = 0; i < 128 * 64; i++) g.data[i] = to.data[i] || null;
    for (i = 0; i < n; i++) {
      top = bands[i];
      if (!ok(top)) continue;
      if (src[i] >= 0 || (moves && src[i] === -2)) {
        for (r = 0; r < 7; r++) for (x = 0; x < 128; x++) P.set(g, x, top + r, null);
        continue;
      }
      for (x = 0; x < 128; x++) {
        var changed = false;
        for (r = 0; r < 7 && !changed; r++) changed = px(from, x, top + r) !== px(to, x, top + r);
        if (!changed) continue;
        for (r = 0; r < 7; r++) P.set(g, x, top + r, null);
        for (r = 0; r < 7; r++) {
          var a = px(from, x, top + r), b = px(to, x, top + r);
          if (a && r - off >= 0) P.set(g, x, top + r - off, a);
          if (b && r + 8 - off < 7) P.set(g, x, top + r + 8 - off, b);
        }
      }
    }
    if (!moves) return;
    var lo = 64, hi = 0;
    for (i = 0; i < n; i++) if (ok(bands[i])) { lo = Math.min(lo, bands[i]); hi = Math.max(hi, bands[i] + 7); }
    for (i = 0; i < n; i++) {
      top = bands[i];
      if (!ok(top)) continue;
      if (src[i] >= 0) {
        var y0 = Math.round(bands[src[i]] + (top - bands[src[i]]) * k);
        for (r = 0; r < 7; r++) {
          var yy = y0 + r;
          if (yy < lo || yy >= hi) continue;
          for (x = 0; x < 128; x++) if (px(to, x, top + r)) P.set(g, x, yy, px(to, x, top + r));
        }
      } else if (src[i] === -2) {
        for (r = 0; r < 7; r++) {
          var rr = r + 8 - off;
          if (rr >= 7) continue;
          for (x = 0; x < 128; x++) if (px(to, x, top + r)) P.set(g, x, top + rr, px(to, x, top + r));
        }
      }
    }
  }

  function draw(key, g, t, o) {
    o = o || {};
    var fn = A[key];
    if (!fn) return;
    var meta = null;
    for (var i = 0; i < LIST.length; i++) if (LIST[i].key === key) meta = LIST[i];
    var dur = meta ? meta.dur : 4;
    /* Wie anim.cpp: resting rechnet mit der ganzen Zeit, jede Schleife laeuft ueber ihre Dauer,
       und eine einmalige steht mit reduzierter Bewegung gleich im Endbild. */
    var local = t;
    if (key === 'resting') local = o.reduce ? dur : t;
    else if (meta && meta.loop) local = t % dur;
    else if (o.reduce) local = dur;
    fn(g, local, dur, o);
  }

  window.TWAnim = { list: LIST, draw: draw, push: push, drop: drop, swap: swap, roll: roll, song: song, lines: lines };
})();
