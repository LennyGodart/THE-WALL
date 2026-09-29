/* Einfuehrung ins Geraet, nachgebaut aus design/Intro.dc.html. Masse, Kamera und Zeiten
   stehen in design/tour/TOUR.md, was davon abweicht in CHANGELOG.md Abschnitt 37.

   Ein Dialog ueber der Seite: links das Steuerboard als CSS-3D-Modell und darunter das
   Panel mit den Animationen aus anim.js, rechts der Text der Station. Sechs Stationen und
   "Fertig".

   Seitendaten tour: {auto, seen, name, user, reduce, device, test}. auto oeffnet sie beim
   Laden (eigenes Geraet, auch ein Testgeraet, noch nie gesehen), sonst jeder Knopf mit
   data-tour-open. test aendert nur den Text der ersten Station. Beim
   Oeffnen merkt sich der Server, dass das Konto sie gesehen hat (/api/account/tour); dann
   kommt sie nicht mehr von selbst, auch in keinem anderen Browser. device steht nur auf
   der Einstellungsseite: dort fuehrt "Zur Geraeteseite" hinueber, auf der Geraeteseite
   schliesst derselbe Knopf nur.

   Texte: Englisch mit deutschem Gegenstueck, gesetzt ueber TW.text als textContent.
   Geraete- und Benutzername kommen nie ueber innerHTML ins Dokument. */
(function () {
  'use strict';
  if (!window.TW || !window.PX) return;
  var data = TW.data().tour;
  if (!data) return;

  var P = window.PX;
  var EASE = 'cubic-bezier(0.23,1,0.32,1)';
  var MONO = "'IBM Plex Mono',monospace";
  var OFF = '#151A1E';
  var reduce = TW.reduce || !!data.reduce;
  var wideQuery = window.matchMedia ? window.matchMedia('(min-width: 860px)') : null;

  /* ---------- Modell ---------- */

  /* Kamera je Station. Die Winkel sind gewaehlt, der Schwenk wird gerechnet: at ist der
     Punkt der Platine, um den es geht, in Board-Pixeln (5 px je Millimeter, Ursprung oben
     links). halo ist der Rahmen um das erklaerte Bauteil: links, oben, Breite, Hoehe, z. */
  var CAMS = {
    1: { rx: -24, ry: 26, z: -40, at: null, focus: '', halo: null },
    2: { rx: -32, ry: 34, z: 150, at: [9, 200], focus: 'usb', halo: [-14, 150, 50, 95, 22] },
    3: { rx: -34, ry: 30, z: 190, at: [-7, 64], focus: 'wheel', halo: [-28, 30, 42, 62, 28] },
    4: { rx: -34, ry: 26, z: 190, at: [68, 151], focus: 'en', halo: [52, 134, 30, 35, 14] },
    5: { rx: -22, ry: 20, z: 110, at: [112, 83], focus: 'module', halo: [61, 28, 99, 104, 20] },
    6: { rx: -18, ry: 14, z: 0, at: null, focus: '', halo: null },
    7: { rx: -24, ry: 26, z: -40, at: null, focus: '', halo: null }
  };

  /* Wohin ein Punkt der Platine nach der Drehung faellt, und die Gegenverschiebung, die ihn
     in die Mitte holt. Die Perspektive vergroessert bei translateZ, also wird geteilt. */
  function pan(cam) {
    if (!cam.at) return [0, 0];
    var rad = Math.PI / 180;
    var sx = (cam.at[0] - 162.5) * Math.cos(cam.ry * rad);
    var sy = (cam.at[1] - 144) * Math.cos(cam.rx * rad);
    var mag = 1400 / (1400 - cam.z);
    return [Math.round(-sx / mag), Math.round(-sy / mag)];
  }

  /* Werkstoffe: Deckflaeche, ihre Kante, Wand unten, Wand rechts, Wand links. */
  var MAT = {
    shield: ['#C9C6BC', '#DAD7CE', '#9A968C', '#DAD7CE', '#9A968C'],
    metal: ['#8B949C', '#A7B0B7', '#5C666E', '#A7B0B7', '#5C666E'],
    white: ['#C6CDD3', '#9BA4AB', '#A3ACB3', '#9BA4AB', '#A3ACB3'],
    black: ['#1B2126', '#2C353C', '#10161A', '#2C353C', '#10161A'],
    under: ['#0E1418', '#131A1F', '#0B1013', '#131A1F', '#0B1013'],
    key: ['#3D4952', '#4E5A63', '#242C32', '#4E5A63', '#242C32'],
    speaker: ['#15191D', '#262E35', '#0E1215', '#262E35', '#0E1215']
  };

  /* Quader: Name, links, oben, Breite, Tiefe, Hoehe, Werkstoff, Grundflaeche in z. Die
     Platine ist 1,6 mm dick, ihre Oberseite liegt bei z 8; die Buchsenleiste haengt darunter. */
  var BOXES = [
    ['module', 67, 35, 89, 98, 16, 'shield', 8],
    ['microsd', 167, 3, 82, 69, 10, 'metal', 8],
    ['rtc', 261, 3, 19, 14, 13, 'white', 8],
    ['hub75a', 280, 37, 45, 120, 45, 'black', 8],
    ['hub75b', 283, 40, 40, 110, 42, 'under', -42],
    ['vh4p', 285, 180, 40, 78, 40, 'white', 8],
    ['wheel', -23, 36, 33, 56, 25, 'key', 8],
    ['i2c', 5, 106, 19, 23, 13, 'white', 8],
    ['en', 58, 142, 22, 19, 10, 'key', 8],
    ['boot', 58, 184, 22, 19, 10, 'key', 8],
    ['usb', -10, 155, 37, 38, 17, 'metal', 8],
    ['power', -10, 207, 37, 37, 17, 'metal', 8],
    ['speaker', 81, 133, 203, 123, 40, 'speaker', 8],
    ['spkjack', 252, 264, 16, 15, 13, 'white', 8]
  ];

  /* Flache Teile: Name, links, oben, Breite, Tiefe, z, Farbe, Schein. */
  var FLATS = [
    ['pins', 89, 270, 142, 14, 15, '#C9A227', ''],
    ['mic2', 70, 271, 15, 13, 13, '#4E5A63', ''],
    ['mic1', 273, 271, 15, 13, 13, '#4E5A63', ''],
    ['led', 36, 18, 10, 6, 11, '#3DE07C', '0 0 8px rgba(61,224,124,0.8)']
  ];

  function el(tag, css, attrs) {
    var e = document.createElement(tag);
    if (css) e.style.cssText = css;
    if (attrs) Object.keys(attrs).forEach(function (k) { e.setAttribute(k, attrs[k]); });
    return e;
  }

  /* Abgedunkelt wird jede Flaeche einzeln, nie ein ganzes Bauteil: opacity auf einem
     Element mit preserve-3d macht seine Kinder flach. */
  function face(css, dims) {
    return el('div', 'position:absolute;' + css, dims ? { 'class': 'tour-face' } : null);
  }

  function buildBoard(world, parts) {
    var plate = el('div', 'position:absolute;left:0;top:0;width:325px;height:288px;transform-style:preserve-3d');
    plate.appendChild(face('inset:0;background:#12181C;box-shadow:inset 0 0 0 1px #1E262B;transform:translateZ(8px)'));
    plate.appendChild(face('left:0;top:288px;width:325px;height:8px;background:#0A0F12;transform-origin:0 0;transform:rotateX(90deg)'));
    plate.appendChild(face('left:325px;top:0;width:8px;height:288px;background:#0C1114;transform-origin:0 0;transform:rotateY(-90deg)'));
    plate.appendChild(face('left:0;top:0;width:8px;height:288px;background:#0C1114;transform-origin:0 0;transform:rotateY(-90deg)'));
    plate.appendChild(face('left:0;top:0;width:325px;height:8px;background:#0A0F12;transform-origin:0 0;transform:rotateX(90deg)'));
    world.appendChild(plate);
    [[6, 6], [307, 6], [6, 269], [307, 269]].forEach(function (h) {
      world.appendChild(face('left:' + h[0] + 'px;top:' + h[1] + 'px;width:13px;height:13px;border-radius:50%;background:#05080A;box-shadow:0 0 0 2px #2B3A42;transform:translateZ(9px)'));
    });
    BOXES.forEach(function (b) {
      var m = MAT[b[6]];
      var box = el('div', 'position:absolute;left:' + b[1] + 'px;top:' + b[2] + 'px;width:' + b[3] + 'px;height:' + b[4] + 'px;' +
        'transform-style:preserve-3d;transform:translateZ(' + b[7] + 'px)', { 'data-part': b[0] });
      box.appendChild(face('inset:0;background:' + m[0] + ';box-shadow:inset 0 0 0 1px ' + m[1] + ';transform:translateZ(' + b[5] + 'px)', true));
      box.appendChild(face('left:0;top:' + b[4] + 'px;width:' + b[3] + 'px;height:' + b[5] + 'px;background:' + m[2] + ';transform-origin:0 0;transform:rotateX(90deg)', true));
      box.appendChild(face('left:' + b[3] + 'px;top:0;width:' + b[5] + 'px;height:' + b[4] + 'px;background:' + m[3] + ';transform-origin:0 0;transform:rotateY(-90deg)', true));
      box.appendChild(face('left:0;top:0;width:' + b[5] + 'px;height:' + b[4] + 'px;background:' + m[4] + ';transform-origin:0 0;transform:rotateY(-90deg)', true));
      world.appendChild(box);
      parts[b[0]] = box;
    });
    FLATS.forEach(function (f) {
      var box = el('div', 'position:absolute;left:' + f[1] + 'px;top:' + f[2] + 'px;width:' + f[3] + 'px;height:' + f[4] + 'px;transform-style:preserve-3d', { 'data-part': f[0] });
      box.appendChild(face('inset:0;background:' + f[6] + ';transform:translateZ(' + f[5] + 'px)' + (f[7] ? ';box-shadow:' + f[7] : ''), true));
      world.appendChild(box);
    });
    /* Der Schein liegt als Rahmen ohne Fuellung ueber dem Bauteil und wird nie abgedunkelt. */
    var halo = el('div', 'position:absolute;border-radius:3px;pointer-events:none', { 'class': 'tour-halo' });
    world.appendChild(halo);
    return halo;
  }

  /* ---------- Panel ---------- */

  /* Was das Panel je Station zeigt. Ein Eintrag ist eine Animation aus anim.js, ein
     Bildschirm der Firmware oder dunkel, mit seiner Dauer in Sekunden; der letzte bleibt
     stehen. presses: Bauteil, Beginn, Dauer eines Drucks. still: der Eintrag, dessen
     Endbild bei reduzierter Bewegung steht (sonst der letzte).
     Station 3 und 4 laufen wie Firmware 0.1.5: kurz aufs Rad schaltet beim Loslassen aus,
     gehalten zaehlt es nach einer Sekunde herunter; EN startet neu, das Fenster steht ab
     dem ersten Bild ueber der Startanimation, der zweite Druck fuehrt zu ZURUECKGESETZT. */
  function planFor(step, sub) {
    switch (step) {
      case 1: return { items: [{ anim: 'paired' }] };
      case 2: return { items: [{ blank: 1.0 }, { anim: 'boot' }] };
      case 3: return {
        items: [{ screen: 'mark', dur: 0.9 }, { anim: 'poweroff', dur: 2.4 }, { blank: 1.3 },
          { screen: 'held', dur: 4.0 }, { screen: 'reset', dur: 2.5 }, { anim: 'wifi' }],
        presses: [['wheel', 0.7, 0.2], ['wheel', 3.6, 5.0]],
        still: 3
      };
      case 4: return {
        items: [{ screen: 'mark', dur: 0.9 }, { blank: 0.8 }, { screen: 'enwin', dur: 2.5 }, { blank: 0.8 },
          { screen: 'reset', dur: 2.5 }, { anim: 'wifi' }],
        presses: [['en', 0.7, 0.2], ['en', 4.0, 0.2]],
        still: 2
      };
      case 5: return { items: [{ anim: 'wifi', dur: 3.6 }, { anim: 'wifi', phone: true, dur: 3.6 }, { anim: 'connecting', dur: 2.8 }, { anim: 'address' }] };
      case 6: return { items: [{ anim: ['noserver', 'nowifi', 'updating'][sub] }] };
      default: return { items: [{ anim: 'waiting' }] };
    }
  }

  function itemDur(it) {
    return it.blank != null ? it.blank : it.dur != null ? it.dur : 4.2;
  }

  function animMeta(key) {
    var list = window.TWAnim ? window.TWAnim.list : [];
    for (var i = 0; i < list.length; i++) if (list[i].key === key) return list[i];
    return null;
  }

  function centre(g, y, s, col, z) {
    P.text(g, Math.round((128 - P.width(s, z || 1)) / 2), y, s, col, z || 1);
  }

  /* Firmware-Bildschirme wie in main.cpp, gleiche Zeilen, Farben und y-Werte. */
  function screenHeld(g, held) {
    var de = TW.lang() === 'de';
    var h = Math.min(held, 5);
    centre(g, 8, de ? 'LOSLASSEN BEHAELT' : 'RELEASE TO KEEP', '#8E9AA3');
    centre(g, 20, de ? 'WLAN UND SCHLUESSEL' : 'WI-FI AND KEY', '#F2F4F5');
    centre(g, 36, String(Math.max(1, Math.ceil(5 - h))), '#FF4A1C', 2);
    P.rect(g, 8, 58, Math.round(112 * h / 5), 2, '#FF4A1C');
  }

  /* t < 0: Endbild, Startanimation fertig und der Balken voll. */
  function screenEnWindow(g, t) {
    var de = TW.lang() === 'de';
    window.TWAnim.draw('boot', g, t < 0 ? 99 : Math.min(t, 2.6), { lang: TW.lang(), reduce: t < 0 });
    centre(g, 50, de ? 'EN NOCHMAL = RESET' : 'EN AGAIN = RESET', '#8E9AA3');
    var w = t < 0 ? 112 : Math.round(112 * Math.max(0, 5 - t) / 5);
    if (w > 0) P.rect(g, 8, 59, w, 2, '#FF4A1C');
  }

  function screenReset(g) {
    var de = TW.lang() === 'de';
    centre(g, 14, de ? 'ZURUECKGESETZT' : 'RESET DONE', '#FFAA00');
    centre(g, 30, de ? 'WLAN UND SCHLUESSEL' : 'WI-FI AND KEY', '#F2F4F5');
    centre(g, 42, de ? 'SIND VERGESSEN' : 'ARE FORGOTTEN', '#8E9AA3');
  }

  function paintItem(g, it, t) {
    var end = t < 0;
    if (it.blank != null || !window.TWAnim) return;
    if (it.screen === 'mark') { window.TWAnim.draw('boot', g, 99, { lang: TW.lang(), reduce: true }); return; }
    if (it.screen === 'held') { screenHeld(g, end ? 4.5 : 1 + t); return; }
    if (it.screen === 'enwin') { screenEnWindow(g, end ? -1 : t); return; }
    if (it.screen === 'reset') { screenReset(g); return; }
    var meta = animMeta(it.anim);
    var tt = end ? (meta && meta.loop ? meta.dur * 0.97 : 99) : t;
    window.TWAnim.draw(it.anim, g, tt, { lang: TW.lang(), reduce: reduce, phone: !!it.phone, user: data.user });
  }

  /* ---------- Texte ---------- */

  function stationText(step, sub) {
    var name = String(data.name || '');
    switch (step) {
      /* Ein Testgeraet hat keine Hardware und meldet sich nie: dort beginnt die Runde
         mit dem, was es ist, statt mit "ist verbunden". */
      case 1: return data.test ? {
        title: [name + ' is set up', name + ' ist eingerichtet'],
        paras: [
          ['This is a test device without hardware. Every mode, the preview and the settings work as they would for a real one, only the panel stays here on the page. Before you pick a mode, a round trip around the control board of a real device: which socket takes the power, what the two buttons and the wheel do, and what the panel says when something is missing.',
            'Das ist ein Testgerät ohne Hardware. Alle Modi, die Vorschau und die Einstellungen funktionieren wie bei einem echten, nur das Panel bleibt hier auf der Seite. Bevor du einen Modus wählst, eine Runde um das Steuerboard eines echten Geräts: welche Buchse den Strom bekommt, was die zwei Tasten und das Rad tun, und was das Panel sagt, wenn etwas fehlt.'],
          ['One minute. You can stop at any point and open it again later.',
            'Eine Minute. Du kannst jederzeit aufhören und sie später wieder aufrufen.']
        ]
      } : {
        title: [name + ' is connected', name + ' ist verbunden'],
        paras: [
          ['The device has checked in, and the website knows it now. Before you pick a mode, a round trip around the control board: which socket takes the power, what the two buttons and the wheel do, and what the panel says when something is missing.',
            'Das Gerät meldet sich, und die Webseite kennt es jetzt. Bevor du einen Modus wählst, eine Runde um das Steuerboard: welche Buchse den Strom bekommt, was die zwei Tasten und das Rad tun, und was das Panel sagt, wenn etwas fehlt.'],
          ['One minute. You can stop at any point and open it again later.',
            'Eine Minute. Du kannst jederzeit aufhören und sie später wieder aufrufen.']
        ]
      };
      case 2: return {
        title: ['One cable, two sockets', 'Ein Kabel, zwei Buchsen'],
        paras: [
          ['Power goes into the socket marked USB-C; a phone charger with 5 volts and 3 amps is enough. The second socket is marked POWER and stays empty.',
            'Der Strom kommt in die Buchse USB-C, ein Handy-Netzteil mit 5 Volt und 3 Ampere reicht. Die zweite Buchse heißt POWER und bleibt frei.'],
          ['From there the board passes the 5 volts on to the panel through the white terminal and the supplied power lead. So one cable feeds both.',
            'Von dort gibt das Board die 5 Volt über die weiße Klemme und das beiliegende Stromkabel an das Panel weiter. Ein Kabel versorgt also beides.'],
          ['Unplugging does no harm. Wi-Fi, key and settings stay stored, and an update in progress sits on a second partition: if the power drops, the old version boots again.',
            'Ausstecken schadet nichts. WLAN, Schlüssel und Einstellungen bleiben gespeichert, und ein laufendes Update liegt auf einer zweiten Partition: bricht der Strom weg, startet die alte Fassung wieder.']
        ]
      };
      case 3: return {
        title: ['The wheel: a tap or a hold', 'Das Rad: kurz oder gehalten'],
        paras: [
          ['A short press, under a second, switches the panel off and on again. Going off, a curtain closes toward the middle and the wordmark stays to the last; coming back, you get the start animation.',
            'Kurz drücken, unter einer Sekunde, schaltet das Panel aus und wieder an. Beim Ausschalten schließt sich ein Vorhang zur Mitte und die Wortmarke bleibt bis zuletzt stehen, beim Einschalten kommt die Startanimation.'],
          ['Holding it for five seconds makes the device forget Wi-Fi and key. From the first second the panel counts the remaining seconds down in red, 4, 3, 2, 1. Let go before that and nothing is lost.',
            'Fünf Sekunden halten vergisst WLAN und Schlüssel. Ab der ersten Sekunde zählt das Panel die restlichen Sekunden rot herunter, 4, 3, 2, 1. Wer vorher loslässt, behält alles.'],
          ['All three directions of the wheel do the same thing. Left, right and press are equivalent here.',
            'Alle drei Richtungen des Rads tun dasselbe. Links, rechts und drücken sind hier gleichwertig.']
        ]
      };
      case 4: return {
        title: ['EN restarts, twice resets', 'EN startet neu, zweimal setzt zurück'],
        paras: [
          ['Pressing EN once restarts the device, nothing more. Nothing is lost.',
            'Einmal EN drücken startet das Gerät neu, sonst nichts. Nichts geht verloren.'],
          ['To reset: press EN. Right after the restart the panel shows EN AGAIN = RESET below the start animation for five seconds, with a red bar shrinking away. Press EN again while it runs. The device then forgets Wi-Fi and key and opens its setup network again.',
            'Zurücksetzen geht so: EN drücken. Gleich nach dem Neustart steht unter der Startanimation fünf Sekunden lang EN NOCHMAL = RESET auf dem Panel, mit einem roten Balken, der schrumpft. Solange er läuft, EN noch einmal drücken. Dann vergisst das Gerät WLAN und Schlüssel und öffnet wieder sein Einrichtungsnetz.'],
          ['The window only appears if the device ran for at least ten seconds beforehand. The BOOT button beside it is not needed; it is only for flashing.',
            'Das Fenster erscheint nur, wenn das Gerät vorher mindestens zehn Sekunden lief. Die Taste BOOT daneben brauchst du nicht, sie ist nur zum Flashen.']
        ]
      };
      case 5: return {
        title: ["The device's own network", 'Das eigene Netz des Geräts'],
        paras: [
          ['After a reset and on the very first start, the device opens a Wi-Fi network called THE WALL SETUP, password 12345678. Both are on the panel, under CONNECT A PHONE.',
            'Nach dem Zurücksetzen und beim allerersten Start öffnet das Gerät ein WLAN namens THE WALL SETUP, Passwort 12345678. Beides steht auf dem Panel, unter HANDY VERBINDEN.'],
          ["Pick that network in your phone's Wi-Fi settings and type the password. Once the phone is connected it usually opens the page at 4.3.2.1 by itself. The panel then shows PHONE CONNECTED and the address HTTP://4.3.2.1.",
            'Wähle das Netz in den WLAN-Einstellungen deines Handys und tippe das Passwort ein. Sobald das Handy verbunden ist, öffnet es meist von selbst die Seite 4.3.2.1. Das Panel zeigt dann HANDY VERBUNDEN und die Adresse HTTP://4.3.2.1.'],
          ['There you pick your home network, 2.4 GHz only, and paste in the API key. You find it on the settings page of this website.',
            'Dort wählst du dein Heimnetz, nur 2,4 GHz, und setzt den API-Schlüssel ein. Den findest du auf der Einstellungsseite dieser Webseite.']
        ]
      };
      case 6: return {
        title: ['When something is missing, the panel says so', 'Wenn etwas fehlt, sagt es das Panel'],
        paras: [
          [
            ['The server is unreachable. The clock keeps running; it comes from the clock chip on the board and needs no network.',
              'Der Server ist nicht erreichbar. Die Uhr läuft weiter, sie kommt vom Uhrchip auf dem Board und braucht kein Netz.'],
            ['The Wi-Fi is gone. The panel says so at once, without the half minute wait, and it names the network.',
              'Das WLAN ist weg. Das sagt das Panel sofort, ohne die halbe Minute zu warten, und es nennt den Netznamen.'],
            ['An update arrives over Wi-Fi. You start it on the settings page with Update now.',
              'Ein Update kommt über WLAN. Ausgelöst wird es auf der Einstellungsseite mit Jetzt aktualisieren.']
          ][sub],
          [
            ['The last content received stays for about half a minute, then the panel says the server is missing. It does not go black.',
              'Der zuletzt empfangene Inhalt bleibt etwa eine halbe Minute stehen, dann sagt das Panel, dass der Server fehlt. Es wird nicht schwarz.'],
            ['When the network comes back the device reconnects by itself. Nothing to press.',
              'Kommt das Netz zurück, verbindet sich das Gerät von selbst. Du musst nichts drücken.'],
            ['The panel shows the progress and stays lit, so it does not look broken. You never need the cable again.',
              'Das Panel zeigt den Fortschritt und bleibt an, damit es nicht kaputt aussieht. Ein Kabel brauchst du nie wieder.']
          ][sub]
        ]
      };
      default: return {
        title: ['That was the device', 'Das war das Gerät'],
        paras: [
          ['You can open this round again at any time: on the device page at the bottom, beside the link to the settings, and on the settings page in the device section.',
            'Diese Runde findest du jederzeit wieder: auf der Geräteseite unten neben dem Verweis auf die Einstellungen, und auf der Einstellungsseite im Abschnitt Gerät.'],
          ['The website takes over from here. Pick a mode on the left, the preview shows it at once, and Apply sends it to the device.',
            'Weiter geht es auf der Webseite. Wähle links einen Modus, die Vorschau zeigt ihn sofort, und mit Übernehmen geht er an das Gerät.']
        ]
      };
    }
  }

  /* Was Modell und Panel zeigen, fuer Screenreader. Lagen wie im Modell: Rad, EN, BOOT und
     beide USB-C-Buchsen sitzen an der linken Kante. */
  function boardLabel(step) {
    return [
      null,
      ['The control board seen from above at an angle, 65 by 57.5 millimetres', 'Das Steuerboard von schräg oben, 65 mal 57,5 Millimeter'],
      ['The two USB-C sockets on the left edge: the upper one, marked USB-C, takes the power, POWER below it stays empty. On the right the white terminal to the panel',
        'Die beiden USB-C-Buchsen an der linken Kante: die obere mit der Aufschrift USB-C bekommt den Strom, POWER darunter bleibt frei. Rechts die weiße Klemme zum Panel'],
      ['The three-way wheel at the top of the left edge, highlighted', 'Das Drei-Wege-Rad oben an der linken Kante, hervorgehoben'],
      ['The EN button near the left edge, beside the USB-C socket, highlighted. BOOT sits just below it', 'Die Taste EN nahe der linken Kante, neben der Buchse USB-C, hervorgehoben. BOOT sitzt direkt darunter'],
      ['The radio module in the upper left of the board, ringed by a steady glow', 'Das Funkmodul oben links auf der Platine, mit einem ruhigen Schein umrandet'],
      ['The whole board at rest, seen from above at an angle', 'Das ganze Board ruhig von schräg oben'],
      ['The whole board seen from above at an angle', 'Das ganze Board von schräg oben']
    ][step];
  }

  function panelLabel(step, sub) {
    var who = String(data.user || '').toUpperCase();
    switch (step) {
      case 1: return ['Panel showing the sign-in and then HELLO ' + who, 'Panel zeigt die Anmeldung und danach HALLO ' + who];
      case 2: return ['Panel dark at first, then the start animation', 'Panel zuerst dunkel, dann die Startanimation'];
      case 3: return ['Panel switching off, then the countdown screen and RESET DONE', 'Panel schaltet ab, danach der Bildschirm mit dem Rückwärtszähler und ZURUECKGESETZT'];
      case 4: return ['Panel dark for a moment, then the start animation with EN AGAIN = RESET below it, after the second press RESET DONE',
        'Panel kurz dunkel, dann die Startanimation mit EN NOCHMAL = RESET darunter, nach dem zweiten Druck ZURUECKGESETZT'];
      case 5: return ['Panel showing CONNECT A PHONE with the setup network and its password, then PHONE CONNECTED and the address 4.3.2.1',
        'Panel zeigt HANDY VERBINDEN mit Einrichtungsnetz und Passwort, danach HANDY VERBUNDEN und die Adresse 4.3.2.1'];
      case 6: return [
        ['Panel reporting the server is missing', 'Panel meldet, dass der Server fehlt'],
        ['Panel reporting the Wi-Fi is missing', 'Panel meldet, dass das WLAN fehlt'],
        ['Panel showing the update progress', 'Panel zeigt den Fortschritt des Updates']
      ][sub];
      default: return ['Panel waiting for a mode', 'Panel wartet auf einen Modus'];
    }
  }

  var CAPTIONS = { 1: 'paired', 2: 'boot', 3: 'poweroff + hold', 4: 'boot + EN + reset', 5: 'wifi + connecting + address', 7: 'waiting' };

  /* aria-label in beiden Sprachen; ui.js tauscht es beim Umschalten ueber data-de-label. */
  function label(e, pair) {
    e.setAttribute('data-en-label', pair[0]);
    e.setAttribute('data-de-label', pair[1]);
    e.setAttribute('aria-label', TW.lang() === 'de' ? pair[1] : pair[0]);
  }

  /* ---------- Dialog ---------- */

  var root, board, world, halo, parts = {}, caption, canvas, panelBox, playBtn, playLabel, panelCaption;
  var stepEl, title, parasBox, subGroup, subBtns = [], backBtn, laterBtn, nextBtn;
  var step = 1, sub = 0, plan = planFor(1, 0);
  var isOpen = false, playing = !reduce, clock = 0, stepAt = 0, lastTick = 0, lastDraw = 0, raf = 0;
  var opener = null, locked = [], marked = !!data.seen;

  function build() {
    root = el('div', '', { 'class': 'tour', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'tour-title' });
    root.hidden = true;
    root.setAttribute('data-reduce', reduce ? 'true' : 'false');
    var card = el('div', 'width:100%;max-width:1180px;max-height:100%;display:flex;flex-direction:column;overflow:hidden;' +
      'background:#0B0D0F;border:1px solid #2C353C;border-radius:3px;box-shadow:0 30px 80px -24px rgba(0,0,0,0.95)');
    root.appendChild(card);

    /* Kopf: wo man steht, Sprache, Ueberspringen */
    var head = el('div', 'flex:0 0 auto;display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:14px 18px;border-bottom:1px solid #1B2126');
    var kicker = el('span', 'font-family:' + MONO + ';font-size:10px;letter-spacing:0.16em;text-transform:uppercase;color:#E09A1A');
    TW.text(kicker, 'Getting to know the device', 'Das Gerät kennenlernen');
    stepEl = el('span', 'font-family:' + MONO + ';font-size:11px;color:#8B949C;font-variant-numeric:tabular-nums');
    var langGroup = el('div', 'display:flex;align-items:center;gap:5px;padding:2px;border:1px solid #232A30;border-radius:2px;font-family:' + MONO + ';font-size:10px', { role: 'group' });
    label(langGroup, ['Language', 'Sprache']);
    ['en', 'de'].forEach(function (l) {
      var b = el('button', 'font-size:10px', { type: 'button', 'class': 'tw-lang', 'data-lang': l, lang: l, 'aria-pressed': TW.lang() === l ? 'true' : 'false' });
      b.textContent = l.toUpperCase();
      b.addEventListener('click', function () { TW.setLang(l); });
      langGroup.appendChild(b);
    });
    var skip = el('button', 'min-height:34px;padding:0 12px;border:1px solid #2C353C;border-radius:2px;background:transparent;cursor:pointer;' +
      'font-family:' + MONO + ';font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#B4BCC3', { type: 'button', 'class': 'tour-hit h-ghost-text' });
    TW.text(skip, 'Skip', 'Überspringen');
    skip.addEventListener('click', close);
    head.appendChild(kicker);
    head.appendChild(stepEl);
    head.appendChild(el('div', 'flex:1 0 0;min-width:0'));
    head.appendChild(langGroup);
    head.appendChild(skip);
    card.appendChild(head);

    /* Rumpf: links Modell und Panel, rechts der Text */
    var body = el('div', '', { 'class': 'tour-body' });
    var visual = el('div', '', { 'class': 'tour-visual' });
    var stage = el('div', '', { 'class': 'tour-stage' });
    board = el('div', 'position:absolute;inset:0;perspective:1400px;perspective-origin:50% 45%', { 'class': 'tour-board', role: 'img' });
    world = el('div', 'position:absolute;left:50%;top:50%;width:325px;height:288px;margin:-144px 0 0 -162px;transform-style:preserve-3d');
    halo = buildBoard(world, parts);
    ['wheel', 'en'].forEach(function (k) { parts[k].style.transition = reduce ? 'none' : 'transform 200ms ' + EASE; });
    board.appendChild(world);
    stage.appendChild(board);
    caption = el('p', 'position:absolute;left:12px;bottom:10px;margin:0;font-family:' + MONO + ';font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C');
    stage.appendChild(caption);
    visual.appendChild(stage);

    var panelWrap = el('div');
    var bezel = el('div', 'padding:7px;background:linear-gradient(#22282D,#15191C);border-radius:3px;box-shadow:0 16px 40px -16px rgba(0,0,0,0.9),inset 0 1px 0 rgba(255,255,255,0.06)');
    panelBox = el('div', 'background:#050607;border-radius:1px;overflow:hidden;display:flex;justify-content:center');
    canvas = el('canvas', '', { role: 'img' });
    panelBox.appendChild(canvas);
    bezel.appendChild(panelBox);
    panelWrap.appendChild(bezel);
    var controls = el('div', 'display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;margin-top:10px');
    playBtn = el('button', 'font-family:' + MONO + ';font-size:10px', { type: 'button', 'class': 'motion a-press' });
    playBtn.appendChild(el('span'));
    playLabel = el('span');
    playBtn.appendChild(playLabel);
    playBtn.addEventListener('click', function () { playing = !playing; renderPlay(); });
    panelCaption = el('span', 'font-family:' + MONO + ';font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C');
    controls.appendChild(playBtn);
    controls.appendChild(panelCaption);
    panelWrap.appendChild(controls);
    visual.appendChild(panelWrap);
    body.appendChild(visual);

    var textBox = el('div', 'padding:18px');
    title = el('h2', 'margin:0 0 14px;font-family:' + MONO + ';font-size:clamp(18px,2.4vw,23px);font-weight:600;line-height:1.25;' +
      'letter-spacing:-0.01em;color:#E8EAEC;outline:none', { id: 'tour-title', tabindex: '-1' });
    textBox.appendChild(title);
    subGroup = el('div', 'display:flex;flex-wrap:wrap;gap:5px;margin:0 0 16px', { role: 'group' });
    label(subGroup, ['Which state', 'Welcher Zustand']);
    [['Server gone', 'Server weg'], ['Wi-Fi gone', 'WLAN weg'], ['Update', 'Update']].forEach(function (pair, i) {
      var b = el('button', '', { type: 'button', 'class': 'tour-rail tour-hit', 'aria-pressed': i === 0 ? 'true' : 'false' });
      TW.text(b, pair[0], pair[1]);
      b.addEventListener('click', function () { setSub(i); });
      subBtns.push(b);
      subGroup.appendChild(b);
    });
    textBox.appendChild(subGroup);
    parasBox = el('div');
    textBox.appendChild(parasBox);
    body.appendChild(textBox);
    card.appendChild(body);

    /* Fuss: Zurueck, Spaeter oder Ueberspringen, Weiter */
    var foot = el('div', '', { 'class': 'tour-foot' });
    backBtn = el('button', 'font-size:12px;letter-spacing:0.1em', { type: 'button', 'class': 'btn-ghost' });
    TW.text(backBtn, 'Back', 'Zurück');
    backBtn.addEventListener('click', function () { if (step > 1) goTo(step - 1); });
    laterBtn = el('button', 'font-size:12px;letter-spacing:0.1em', { type: 'button', 'class': 'btn-ghost' });
    laterBtn.addEventListener('click', close);
    nextBtn = el('button', 'font-size:12px;letter-spacing:0.1em;padding:0 22px', { type: 'button', 'class': 'btn-solid' });
    nextBtn.addEventListener('click', function () { if (step < 7) goTo(step + 1); else finish(); });
    foot.appendChild(backBtn);
    foot.appendChild(el('div', 'flex:1 0 0;min-width:0'));
    foot.appendChild(laterBtn);
    foot.appendChild(nextBtn);
    card.appendChild(foot);

    root.addEventListener('keydown', trapTab);
    document.body.appendChild(root);
  }

  function wide() { return wideQuery ? wideQuery.matches : window.innerWidth >= 860; }

  /* Die Kamera sitzt als eine einzige transform auf der Welt. Station 1 dreht das Board
     beim Oeffnen in 2,5 Sekunden aus fast hochkant (-80 Grad) in die Schraegansicht. */
  function camera(mode) {
    var cam = CAMS[step];
    var p = pan(cam);
    var zoom = wide() ? 0.78 : 0.5;
    var at = function (ry) {
      return 'scale(' + zoom + ') translate3d(' + p[0] + 'px,' + p[1] + 'px,' + cam.z + 'px) rotateX(' + cam.rx + 'deg) rotateY(' + ry + 'deg)';
    };
    if (reduce || mode === 'jump') {
      world.style.transition = 'none';
      world.style.transform = at(cam.ry);
      return;
    }
    if (mode === 'swing') {
      world.style.transition = 'none';
      world.style.transform = at(-80);
      void window.getComputedStyle(world).transform;
      world.style.transition = 'transform 2500ms ' + EASE;
    } else {
      world.style.transition = 'transform 520ms ' + EASE;
    }
    world.style.transform = at(cam.ry);
  }

  function placeHalo(cam) {
    var h = cam.halo;
    if (!h) { halo.hidden = true; return; }
    halo.hidden = false;
    halo.style.left = h[0] + 'px';
    halo.style.top = h[1] + 'px';
    halo.style.width = h[2] + 'px';
    halo.style.height = h[3] + 'px';
    halo.style.transform = 'translateZ(' + h[4] + 'px)';
  }

  function renderPlay() {
    playBtn.setAttribute('data-running', playing ? 'true' : 'false');
    TW.text(playLabel, playing ? 'Pause' : 'Play', playing ? 'Anhalten' : 'Abspielen');
    /* Der Haltknopf haelt auch den pulsierenden Schein an. */
    root.setAttribute('data-paused', playing ? 'false' : 'true');
  }

  function renderText() {
    var tx = stationText(step, sub);
    TW.text(title, tx.title[0], tx.title[1]);
    subGroup.hidden = step !== 6;
    subBtns.forEach(function (b, i) { b.setAttribute('aria-pressed', i === sub ? 'true' : 'false'); });
    while (parasBox.firstChild) parasBox.removeChild(parasBox.firstChild);
    tx.paras.forEach(function (pair, i) {
      var p = el('p', i === 0
        ? 'margin:0 0 14px;font-size:15px;line-height:1.65;color:#C6CDD3;text-wrap:pretty'
        : 'margin:0 0 12px;font-size:14px;line-height:1.65;color:#8B949C;text-wrap:pretty');
      TW.text(p, pair[0], pair[1]);
      parasBox.appendChild(p);
    });
    label(canvas, panelLabel(step, sub));
    var cap = step === 6 ? ['noserver', 'nowifi', 'updating'][sub] : CAPTIONS[step];
    TW.text(panelCaption, cap, cap);
  }

  function render() {
    var cam = CAMS[step];
    TW.text(stepEl, step === 7 ? 'Done' : step + ' of 6', step === 7 ? 'Fertig' : step + ' von 6');
    if (cam.focus) board.setAttribute('data-focus', cam.focus); else board.removeAttribute('data-focus');
    label(board, boardLabel(step));
    placeHalo(cam);
    if (reduce) TW.text(caption, 'Fixed view', 'Feste Ansicht');
    else TW.text(caption, 'RX ' + cam.rx + '° · RY ' + cam.ry + '°', 'RX ' + cam.rx + '° · RY ' + cam.ry + '°');
    renderText();
    backBtn.disabled = step <= 1;
    backBtn.style.opacity = step <= 1 ? '0.4' : '';
    laterBtn.hidden = step === 7;
    TW.text(laterBtn, step === 1 ? 'Later' : 'Skip', step === 1 ? 'Später' : 'Überspringen');
    if (step === 1) TW.text(nextBtn, 'Start, 1 minute', 'Starten, 1 Minute');
    else if (step === 7) TW.text(nextBtn, 'To the device page', 'Zur Geräteseite');
    else TW.text(nextBtn, 'Next', 'Weiter');
  }

  /* WCAG 2.4.3: der Fokus springt beim Wechsel auf die Ueberschrift der Station. */
  function focusTitle() {
    requestAnimationFrame(function () { if (isOpen) title.focus(); });
  }

  function goTo(n) {
    step = Math.max(1, Math.min(7, n));
    sub = 0;
    stepAt = clock;
    plan = planFor(step, sub);
    render();
    camera('move');
    focusTitle();
  }

  function setSub(i) {
    sub = i;
    stepAt = clock;
    plan = planFor(step, sub);
    renderText();
  }

  /* ---------- Ablauf ---------- */

  function drawPanel(local) {
    var g = P.grid(128, 64);
    var items = plan.items;
    var item = items[items.length - 1], inItem = local;
    if (reduce) {
      /* Reduzierte Bewegung zeigt das Endbild, nie ein halbes. */
      item = items[plan.still != null ? plan.still : items.length - 1];
      inItem = -1;
    } else {
      var at = 0;
      for (var i = 0; i < items.length; i++) {
        var d = itemDur(items[i]);
        if (i === items.length - 1 || local < at + d) { item = items[i]; inItem = local - at; break; }
        at += d;
      }
    }
    paintItem(g, item, inItem);
    P.paint(canvas, g, { px: TW.fit(panelBox, 128, 6, 2), gap: 1, off: OFF, glow: 0.6 });
  }

  /* Ein Druck senkt die Taste 1 mm (5 px) fuer die Dauer des Drucks, hin und zurueck je
     200 ms. Bei reduzierter Bewegung steht die Taste der Station gedrueckt. */
  function updatePress(local) {
    var down = { wheel: false, en: false };
    if (reduce) {
      down.wheel = step === 3;
      down.en = step === 4;
    } else if (plan.presses) {
      plan.presses.forEach(function (pr) {
        if (local >= pr[1] && local < pr[1] + pr[2]) down[pr[0]] = true;
      });
    }
    Object.keys(down).forEach(function (k) {
      var want = down[k] ? 'translateZ(3px)' : 'translateZ(8px)';
      if (parts[k].style.transform !== want) parts[k].style.transform = want;
    });
  }

  /* 12 Bilder je Sekunde wie die Vorschau auf der Geraeteseite. Die Uhr steht, solange
     angehalten ist. */
  function tick(now) {
    if (!isOpen) return;
    if (playing && lastTick) clock += (now - lastTick) / 1000;
    lastTick = now;
    if (now - lastDraw > 83) {
      lastDraw = now;
      var local = Math.max(0, clock - stepAt);
      drawPanel(local);
      updatePress(local);
    }
    raf = requestAnimationFrame(tick);
  }

  /* ---------- Oeffnen und Schliessen ---------- */

  function lockPage(on) {
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

  function open() {
    if (isOpen) return;
    if (!root) build();
    opener = document.activeElement;
    isOpen = true;
    step = 1;
    sub = 0;
    stepAt = clock;
    plan = planFor(1, 0);
    root.hidden = false;
    lockPage(true);
    render();
    renderPlay();
    camera('swing');
    lastTick = 0;
    lastDraw = 0;
    raf = requestAnimationFrame(tick);
    focusTitle();
    if (!marked) {
      marked = true;
      TW.api('/api/account/tour', {});
    }
  }

  function close() {
    if (!isOpen) return;
    isOpen = false;
    cancelAnimationFrame(raf);
    root.hidden = true;
    lockPage(false);
    if (opener && opener.focus && document.contains(opener)) opener.focus();
    opener = null;
  }

  /* "Zur Geraeteseite": auf der Einstellungsseite hinueber, auf der Geraeteseite nur zu. */
  function finish() {
    if (data.device) {
      close();
      location.href = '/device/' + encodeURIComponent(String(data.device));
      return;
    }
    close();
  }

  /* Tab bleibt im Dialog. */
  function trapTab(ev) {
    if (ev.key !== 'Tab') return;
    var list = Array.prototype.filter.call(root.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])'), function (e) {
      return !e.disabled && !e.hidden && e.offsetParent !== null;
    });
    if (!list.length) return;
    var first = list[0], last = list[list.length - 1];
    if (ev.shiftKey && (document.activeElement === first || document.activeElement === title)) { ev.preventDefault(); last.focus(); }
    else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
  }

  /* Escape schliesst, Pfeil links und rechts blaettern, auch ohne Fokus im Dialog. */
  document.addEventListener('keydown', function (ev) {
    if (!isOpen || ev.altKey || ev.ctrlKey || ev.metaKey) return;
    if (ev.key === 'Escape') { ev.preventDefault(); close(); }
    else if (ev.key === 'ArrowRight' && step < 7) { ev.preventDefault(); goTo(step + 1); }
    else if (ev.key === 'ArrowLeft' && step > 1) { ev.preventDefault(); goTo(step - 1); }
  });

  TW.onResize(function () { if (isOpen) camera('jump'); });

  document.querySelectorAll('[data-tour-open]').forEach(function (b) {
    b.addEventListener('click', open);
  });

  window.TWTour = { open: open, close: close };

  if (data.auto) open();
})();
