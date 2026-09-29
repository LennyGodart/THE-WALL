// Die Seite, die das Geraet selbst ausliefert. Nachbau des Entwurfs Setup.dc.html.
// Englisch im Markup, Deutsch in data-de, getauscht wird nur textContent.
// Netznamen kommen aus der Luft und landen nie als HTML im Dokument.
#pragma once

static const char PORTAL_PAGE[] = R"WALLPAGE(<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>THE WALL Setup</title>
<style>
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:#08090A;color:#E8EAEC;font-family:"IBM Plex Mono",ui-monospace,"SF Mono",Menlo,Consolas,monospace;-webkit-font-smoothing:antialiased}
a{color:#FFAA00;text-decoration:none}
a:hover{color:#FFC44D}
::selection{background:#FFAA00;color:#08090A}
canvas{display:block;image-rendering:pixelated}
input,button{font-family:inherit}
input::placeholder{color:#8B949C}
a:focus-visible,button:focus-visible,input:focus-visible{outline:2px solid #FFC44D;outline-offset:2px}
.skip{position:fixed;top:8px;left:8px;z-index:60;padding:12px 18px;background:#FFAA00;color:#08090A;font-size:13px;font-weight:600;border-radius:2px;transform:translateY(-200%);transition:transform 160ms cubic-bezier(.23,1,.32,1)}
.skip:focus{transform:none}
.wrap{min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:0 16px}
.page{width:100%;max-width:520px;padding:clamp(24px,5vw,48px) 0}
.head{display:flex;flex-wrap:wrap;align-items:center;gap:10px 12px;padding-bottom:16px;border-bottom:1px solid #1B2126;margin-bottom:clamp(24px,4vw,36px)}
.caps{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#8B949C}
.ip{margin-left:auto;font-size:11px;color:#8B949C}
.langs{display:flex;border:1px solid #2C353C;border-radius:2px;overflow:hidden}
.seg{min-height:44px;min-width:44px;padding:0 11px;border:0;cursor:pointer;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;background:transparent;color:#B4BCC3}
.seg+.seg{border-left:1px solid #2C353C}
.seg[aria-pressed="true"]{background:#FFAA00;color:#08090A;font-weight:600}
.rows{display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;margin-bottom:clamp(24px,4vw,36px)}
.row{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:14px;background:#0B0D0F}
.lamp{flex:0 0 auto;width:9px;height:9px;border-radius:1px;display:block;background:#4A565F}
.lamp[data-s="ok"]{background:#3DE07C;box-shadow:0 0 9px rgba(61,224,124,.7)}
.lamp[data-s="bad"]{background:#FF4A1C;box-shadow:0 0 9px rgba(255,74,28,.6)}
.rowlabel{flex:1;min-width:140px;font-size:13px}
.result{font-size:11.5px;letter-spacing:.06em;text-align:right;color:#8B949C}
.result[data-s="ok"]{color:#3DE07C}
.result[data-s="bad"]{color:#FF7A54}
.eyebrow{margin:0 0 6px;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#E09A1A}
h1{margin:0 0 10px;font-size:clamp(21px,4vw,27px);font-weight:600;letter-spacing:-.01em;line-height:1.2}
.lead{margin:0 0 clamp(28px,4vw,40px);font-size:13.5px;line-height:1.65;color:#8B949C}
.lost{margin:-16px 0 24px;font-size:11.5px;line-height:1.5;color:#FF7A54;min-height:17px}
.steps{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;scroll-margin-top:16px}
.step{background:#0B0D0F;padding:clamp(18px,3vw,24px)}
.stephead{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.num{flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:2px;font-size:12px;font-weight:600;background:#1E252A;color:#B4BCC3}
.num[data-done="true"]{background:#3DE07C;color:#08090A}
.steptitle{flex:1;font-size:13.5px}
.tag{font-size:10px;letter-spacing:.1em;text-transform:uppercase;padding:3px 7px;border-radius:1px;background:#1E252A;color:#8B949C}
.tag[data-done="true"]{background:rgba(61,224,124,.14);color:#3DE07C}
.nets{display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;border-radius:2px;margin-bottom:14px}
.net{display:flex;align-items:center;gap:11px;width:100%;min-height:46px;padding:11px 13px;border:0;cursor:pointer;background:#0B0D0F;color:#E8EAEC;transition:background 160ms ease}
.net[aria-checked="true"]{background:#141A1E}
.dot{flex:0 0 auto;width:11px;height:11px;border-radius:1px;border:1px solid #4A565F;transition:background 160ms ease,border-color 160ms ease}
.net[aria-checked="true"] .dot{border-color:#FFAA00;background:#FFAA00}
.netname{flex:1;min-width:0;text-align:left;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.netinfo{font-size:11px;color:#B4BCC3;white-space:nowrap}
.netinfo[data-strong="true"]{color:#3DE07C}
.empty{padding:13px;font-size:12px;color:#8B949C;background:#0B0D0F}
.label{display:block;margin:14px 0 7px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#B4BCC3}
.field{display:flex;align-items:stretch}
.input{flex:1;min-width:0;min-height:48px;padding:13px 14px;background:#0E1215;color:#E8EAEC;font-size:15px;border:1px solid #2C353C;border-radius:2px;outline:none;transition:border-color 160ms ease,box-shadow 160ms ease}
.input:focus{border-color:#FFAA00;box-shadow:0 0 0 3px rgba(255,170,0,.14)}
.input[aria-invalid="true"]{border-color:#FF4A1C}
.withbtn{border-top-right-radius:0;border-bottom-right-radius:0;border-right:0}
.side{min-height:48px;padding:0 14px;border:1px solid #2C353C;border-left:0;border-radius:0 2px 2px 0;background:#0E1215;color:#B4BCC3;font-size:11px;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;white-space:nowrap}
.chip{flex:0 0 auto;display:flex;align-items:center;padding:0 12px;background:#050607;border:1px solid #1E2A24;border-right:0;border-radius:2px 0 0 2px;font-size:15px;color:#3DE07C}
.withchip{border-top-left-radius:0;border-bottom-left-radius:0}
.note{margin:10px 0 0;font-size:11.5px;line-height:1.5;color:#8B949C}
.error{margin:8px 0 0;font-size:11.5px;line-height:1.5;color:#FF7A54;min-height:0}
.solid{width:100%;min-height:50px;margin-top:18px;border:0;border-radius:2px;cursor:pointer;font-size:12.5px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;background:#FFAA00;color:#08090A;transition:background 160ms ease}
.solid:disabled{background:#1E252A;color:#8B949C;cursor:default}
.status{margin:12px 0 0;font-size:11.5px;line-height:1.5;min-height:17px;color:#3DE07C}
.status[data-s="bad"]{color:#FF7A54}
.local{margin-top:clamp(24px,4vw,36px);border:1px solid #1B2126;background:#0B0D0F}
.localhead{padding:16px clamp(18px,3vw,24px);border-bottom:1px solid #1B2126}
.localbody{padding:clamp(18px,3vw,24px);display:flex;flex-direction:column;gap:22px}
.switchrow{display:flex;align-items:center;gap:14px}
.switch{position:relative;flex:0 0 auto;width:46px;height:26px;padding:0;border:1px solid #2C353C;border-radius:2px;cursor:pointer;background:#0E1215;transition:background 180ms cubic-bezier(.23,1,.32,1),border-color 180ms ease}
.switch::before{content:"";position:absolute;inset:-9px -6px}
.switch[aria-checked="true"]{border-color:#FFAA00;background:rgba(255,170,0,.18)}
.knob{position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:1px;display:block;background:#4A565F;transition:transform 220ms cubic-bezier(.23,1,.32,1),background 180ms ease}
.switch[aria-checked="true"] .knob{background:#FFAA00;transform:translateX(20px)}
.ghost{min-height:44px;padding:0 16px;border:1px solid #2C353C;border-radius:2px;background:transparent;cursor:pointer;font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;color:#B4BCC3}
output{font-size:14px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums}
input[type=range]{-webkit-appearance:none;appearance:none;width:100%;height:28px;background:transparent;cursor:pointer;margin:0}
input[type=range]::-webkit-slider-runnable-track{height:4px;background:#1E252A;border-radius:2px}
input[type=range]::-moz-range-track{height:4px;background:#1E252A;border-radius:2px}
input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;margin-top:-6px;background:#FFAA00;border:0;border-radius:2px}
input[type=range]::-moz-range-thumb{width:16px;height:16px;background:#FFAA00;border:0;border-radius:2px}
dl{margin:clamp(24px,4vw,36px) 0 0;display:grid;grid-template-columns:auto 1fr;font-size:11.5px}
dt{padding:9px 18px 9px 0;border-top:1px solid #1B2126;color:#8B949C}
dd{margin:0;padding:9px 0;border-top:1px solid #1B2126;color:#C6CDD3;text-align:right;overflow-wrap:anywhere}
dt.last,dd.last{border-bottom:1px solid #1B2126}
.foot{display:flex;flex-wrap:wrap;gap:14px 24px;margin-top:clamp(28px,4vw,40px);padding-top:20px;border-top:1px solid #1B2126;font-size:11px;letter-spacing:.06em}
.foot a{color:#8B949C}
.foot a:hover{color:#E8EAEC}
.foot span{margin-left:auto;color:#8B949C}
[hidden]{display:none!important}
@media (prefers-reduced-motion:reduce){*{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
</style>
</head>
<body>
<a class="skip" href="#steps" data-de="Zu den Schritten springen">Skip to the steps</a>
<div class="wrap"><main class="page">

<div class="head">
  <canvas id="mark" role="img" aria-label="THE WALL"></canvas>
  <span class="caps">Setup</span>
  <span class="ip" id="ip"></span>
  <div class="langs" role="group" aria-label="Language" data-de-label="Sprache">
    <button class="seg" type="button" data-lang="en" aria-pressed="true">EN</button>
    <button class="seg" type="button" data-lang="de" aria-pressed="false">DE</button>
  </div>
</div>

<div class="rows">
  <div class="row"><span class="lamp" id="wifi-lamp"></span><span class="rowlabel" data-de="WLAN">Wi-Fi</span><span class="result" id="wifi-result"></span></div>
  <div class="row"><span class="lamp" id="key-lamp"></span><span class="rowlabel" data-de="Konto">Account</span><span class="result" id="key-result"></span></div>
</div>

<p class="lost" id="lost" role="status" aria-live="polite"></p>

<p class="eyebrow" data-de="Auf dem Gerät">On the device</p>
<h1 data-de="Diese Seite liefert das Panel selbst aus">This page is served by the panel itself</h1>
<p class="lead" data-de="Sie liegt im Gerät und braucht kein Internet. Zwei Dinge einstellen, dann läuft alles über die Webseite.">It lives inside the device and needs no internet. Set two things here, then everything runs from the website.</p>

<ol class="steps" id="steps">
  <li class="step">
    <div class="stephead">
      <span class="num" id="step1-num">1</span>
      <span class="steptitle" data-de="WLAN wählen">Pick your Wi-Fi</span>
      <span class="tag" id="step1-tag"></span>
    </div>
    <div class="nets" id="nets" role="radiogroup" aria-label="Available networks" data-de-label="Verfügbare Netze"></div>
    <label class="label" for="wifi-ssid" data-de="Netzname">Network name</label>
    <div class="field"><input class="input" id="wifi-ssid" type="text" maxlength="32" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="ssid-error"></div>
    <p class="error" id="ssid-error" role="alert"></p>
    <label class="label" for="wifi-pass" data-de="WLAN-Passwort">Wi-Fi password</label>
    <div class="field">
      <input class="input withbtn" id="wifi-pass" type="password" maxlength="63" autocomplete="off" aria-describedby="pass-error">
      <button class="side" type="button" id="pass-toggle" aria-pressed="false"></button>
    </div>
    <p class="error" id="pass-error" role="alert"></p>
    <p class="note" data-de="Nur 2,4 GHz. 5-GHz-Netze tauchen hier gar nicht erst auf, das Board sieht sie nicht.">2.4 GHz only. 5 GHz networks do not appear here at all, the board cannot see them.</p>
    <button class="solid" type="button" id="connect"></button>
    <p class="status" id="wifi-status" role="status" aria-live="polite"></p>
  </li>
  <li class="step">
    <div class="stephead">
      <span class="num" id="step2-num">2</span>
      <span class="steptitle" data-de="Schlüssel einsetzen">Paste the key</span>
      <span class="tag" id="step2-tag"></span>
    </div>
    <p class="note" style="margin:0 0 4px;font-size:13px;line-height:1.6" data-de="Den Schlüssel findest du auf thewall.godart.lu unter Einstellungen. Damit weiß das Gerät, zu welchem Konto es gehört.">Find the key on thewall.godart.lu under Settings. It tells the device which account it belongs to.</p>
    <label class="label" for="api-key" data-de="API-Schlüssel">API key</label>
    <div class="field">
      <span class="chip" aria-hidden="true">&gt;</span>
      <input class="input withchip" id="api-key" type="text" maxlength="40" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="tw_live_..." aria-describedby="key-hint">
    </div>
    <p class="note" id="key-hint" role="alert"></p>
    <button class="solid" type="button" id="pair"></button>
    <p class="status" id="pair-status" role="status" aria-live="polite"></p>
  </li>
</ol>

<section class="local" aria-labelledby="local-title">
  <div class="localhead">
    <p class="caps" id="local-title" style="margin:0 0 4px;letter-spacing:.14em;color:#B4BCC3" data-de="Ohne Konto">Without an account</p>
    <p class="note" style="margin:0;font-size:12.5px;line-height:1.55" data-de="Diese drei Dinge gehen immer, auch wenn du das Gerät nie mit der Webseite verbindest.">These three work regardless, even if you never connect the device to the website.</p>
  </div>
  <div class="localbody">
    <div>
      <div class="switchrow">
        <button class="switch" type="button" role="switch" id="clock" aria-checked="true" aria-labelledby="clock-label" aria-describedby="clock-note"><span class="knob"></span></button>
        <span id="clock-label" style="flex:1;font-size:13.5px" data-de="Uhr aus der Echtzeituhr">Clock from the onboard RTC</span>
      </div>
      <p class="note" id="clock-note" data-de="Läuft, solange kein Konto verbunden ist. Gestellt wird sie vom Server, ohne Konto vom Zeitdienst pool.ntp.org. Ohne Pufferbatterie an J1 vergisst sie die Zeit beim Ausstecken.">Shown while no account is linked. The server sets it, without an account the time service pool.ntp.org does. Without a backup battery on J1 it forgets the time when unplugged.</p>
    </div>
    <div>
      <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:8px">
        <label for="bright" class="caps" style="flex:1;letter-spacing:.14em;color:#B4BCC3" data-de="Helligkeit">Brightness</label>
        <output for="bright" id="bright-out">140</output>
      </div>
      <canvas id="bar" aria-hidden="true" style="margin-bottom:1px"></canvas>
      <input id="bright" type="range" min="8" max="255" step="1" value="140">
      <p class="note" data-de="Gilt, bis der Server eine Helligkeit schickt. Danach stellst du sie auf der Webseite ein.">Applies until the server sends a brightness. After that, set it on the website.</p>
    </div>
    <div>
      <button class="ghost" type="button" id="test" data-de="Testbild zeigen">Show a test pattern</button>
      <p class="status" id="test-status" role="status" aria-live="polite"></p>
    </div>
  </div>
</section>

<dl>
  <dt data-de="Geräte-ID">Device ID</dt><dd id="d-id"></dd>
  <dt>MAC</dt><dd id="d-mac"></dd>
  <dt>Firmware</dt><dd id="d-fw"></dd>
  <dt>Panel</dt><dd id="d-panel"></dd>
  <dt class="last" data-de="Einrichtungsnetz">Setup network</dt><dd class="last">THE WALL SETUP</dd>
</dl>

<p class="note" style="margin-top:24px;line-height:1.6" data-de="Das Einrichtungsnetz und sein Passwort stehen beim Start auf dem Panel.">The setup network and its password appear on the panel at startup.</p>

<div class="foot">
  <a href="https://thewall.godart.lu/" data-de="Zur Webseite">To the website</a>
  <a href="https://thewall.godart.lu/legal#privacy" data-de="Datenschutz">Privacy</a>
  <span id="foot-id"></span>
</div>

</main></div>
<script>
(function () {
  'use strict';
  var lang = 'en', state = null, nets = [], picked = '', firstState = true, lost = 0;
  var wifiError = '', keyError = false, pairNoWifi = false, passShown = false, testTimer = null, brightTimer = null;
  var KEY = /^tw_live_[0-9a-f]{16}$/;
  function $(id) { return document.getElementById(id); }
  function T(en, de) { return lang === 'de' ? de : en; }

  function api(path, body) {
    var opts = body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : { cache: 'no-store' };
    return fetch(path, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, data: j }; });
    });
  }

  /* Pixelschrift fuer die Wortmarke, nur die sechs Buchstaben aus pixelfont.js. */
  var G = {
    T: '#####/..#../..#../..#../..#../..#../..#..', H: '#...#/#...#/#...#/#####/#...#/#...#/#...#',
    E: '#####/#..../#..../####./#..../#..../#####', W: '#...#/#...#/#...#/#.#.#/#.#.#/##.##/#...#',
    A: '.###./#...#/#...#/#####/#...#/#...#/#...#', L: '#..../#..../#..../#..../#..../#..../#####'
  };
  function drawMark() {
    var c = $('mark'), px = 2, gap = 1, step = px + gap, w = 56, h = 9;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    c.width = (w * step - gap) * dpr; c.height = (h * step - gap) * dpr;
    c.style.width = (w * step - gap) + 'px'; c.style.height = (h * step - gap) + 'px';
    var ctx = c.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    [['THE', 0, '#FFAA00'], ['WALL', 24, '#F2F4F5']].forEach(function (part) {
      ctx.fillStyle = part[2];
      for (var i = 0; i < part[0].length; i++) {
        var rows = G[part[0][i]].split('/');
        for (var r = 0; r < 7; r++) for (var k = 0; k < 5; k++)
          if (rows[r][k] === '#') ctx.fillRect((part[1] + i * 6 + k) * step, (1 + r) * step, px, px);
      }
    });
  }

  function drawBar() {
    var c = $('bar'), px = 3, gap = 1, rows = 3;
    var width = c.parentNode.clientWidth || 300;
    var cols = Math.max(16, Math.floor((width + gap) / (px + gap)));
    var b = parseInt($('bright').value, 10) || 0;
    var lit = Math.round((b / 255) * cols), level = Math.max(0.16, b / 255);
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    c.width = (cols * (px + gap) - gap) * dpr; c.height = (rows * (px + gap) - gap) * dpr;
    c.style.width = (cols * (px + gap) - gap) + 'px'; c.style.height = (rows * (px + gap) - gap) + 'px';
    var ctx = c.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    for (var x = 0; x < cols; x++) {
      ctx.fillStyle = x < lit ? 'rgb(' + Math.round(255 * level) + ',' + Math.round(170 * level) + ',0)' : '#1A1F24';
      for (var y = 0; y < rows; y++) ctx.fillRect(x * (px + gap), y * (px + gap), px, px);
    }
  }

  function applyLang(l) {
    lang = l === 'de' ? 'de' : 'en';
    document.documentElement.setAttribute('lang', lang);
    document.querySelectorAll('[data-de]').forEach(function (el) {
      if (el.dataset.en === undefined) el.dataset.en = el.textContent;
      el.textContent = lang === 'de' ? el.dataset.de : el.dataset.en;
    });
    // Beschriftungen fuer Vorleser wechseln mit, sonst hiesse die Gruppe auf Deutsch weiter "Language".
    document.querySelectorAll('[data-de-label]').forEach(function (el) {
      if (el.dataset.enLabel === undefined) el.dataset.enLabel = el.getAttribute('aria-label') || '';
      el.setAttribute('aria-label', lang === 'de' ? el.dataset.deLabel : el.dataset.enLabel);
    });
    document.querySelectorAll('[data-lang]').forEach(function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-lang') === lang ? 'true' : 'false');
    });
    render();
    renderNets();
  }

  function selectedNet() {
    for (var i = 0; i < nets.length; i++) if (nets[i].ssid === $('wifi-ssid').value) return nets[i];
    return null;
  }

  function renderNets() {
    var box = $('nets');
    while (box.firstChild) box.removeChild(box.firstChild);
    if (!nets.length) {
      var p = document.createElement('div');
      p.className = 'empty';
      p.textContent = T('Searching for networks ...', 'Suche Netze ...');
      box.appendChild(p);
      return;
    }
    nets.forEach(function (n) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'net';
      b.setAttribute('role', 'radio');
      b.setAttribute('aria-checked', n.ssid === picked ? 'true' : 'false');
      var dot = document.createElement('span'); dot.className = 'dot';
      var name = document.createElement('span'); name.className = 'netname'; name.textContent = n.ssid;
      var info = document.createElement('span'); info.className = 'netinfo';
      info.textContent = (n.open ? T('open, ', 'offen, ') : '') + '−' + Math.abs(n.rssi) + ' dBm';
      if (n.rssi > -60) info.setAttribute('data-strong', 'true');
      b.appendChild(dot); b.appendChild(name); b.appendChild(info);
      b.addEventListener('click', function () {
        picked = n.ssid;
        $('wifi-ssid').value = n.ssid;
        wifiError = '';
        renderNets();
        render();
        $('wifi-pass').focus();
      });
      box.appendChild(b);
    });
  }

  // Nur bei Aenderung schreiben: render() laeuft alle zwei Sekunden, und ein Vorleser
  // wuerde Live-Bereiche sonst jedes Mal neu vorlesen, auch mit gleichem Text.
  function put(id, text) { var el = $(id); if (el.textContent !== text) el.textContent = text; }
  function set(id, en, de) { put(id, T(en, de)); }

  function render() {
    var s = state || { wifi: { state: 'none' }, key: { state: 'none' } };
    var w = s.wifi || {}, k = s.key || {};
    var wifiOk = w.state === 'ok';
    var keyOk = k.state === 'ok';

    put('ip', location.hostname);
    put('d-id', s.id || ''); put('foot-id', s.id || '');
    put('d-mac', s.mac || ''); put('d-fw', s.fw || '');
    put('d-panel', s.panel || '');

    var wl = wifiOk ? 'ok' : (w.state === 'wrong' || w.state === 'notfound' || w.state === 'failed') ? 'bad' : 'idle';
    $('wifi-lamp').setAttribute('data-s', wl); $('wifi-result').setAttribute('data-s', wl);
    var ssid = w.ssid || '', dbm = '−' + Math.abs(w.rssi || 0) + ' dBm';
    if (wifiOk) set('wifi-result', 'Connected to ' + ssid + ', ' + dbm, 'Verbunden mit ' + ssid + ', ' + dbm);
    else if (w.state === 'wrong') set('wifi-result', 'Password rejected', 'Passwort abgelehnt');
    else if (w.state === 'notfound') set('wifi-result', 'Network not found', 'Netz nicht gefunden');
    else if (w.state === 'failed') set('wifi-result', 'No connection', 'Keine Verbindung');
    else if (w.state === 'busy') set('wifi-result', 'Connecting ...', 'Verbinde ...');
    else set('wifi-result', 'Not connected', 'Nicht verbunden');

    var kl = keyOk ? 'ok' : (k.state === 'bad' || k.state === 'full') ? 'bad' : 'idle';
    $('key-lamp').setAttribute('data-s', kl); $('key-result').setAttribute('data-s', kl);
    var user = k.user || '';
    if (keyOk) set('key-result', 'Paired with ' + user, 'Verbunden mit ' + user);
    else if (k.state === 'bad') set('key-result', 'Key rejected', 'Schlüssel abgelehnt');
    else if (k.state === 'full') set('key-result', '20 devices on this account', '20 Geräte auf dem Konto');
    else if (k.state === 'offline') set('key-result', 'Server not reachable', 'Server nicht erreichbar');
    else if (k.state === 'busy') set('key-result', 'Checking ...', 'Prüfe ...');
    else set('key-result', 'No key set', 'Kein Schlüssel eingesetzt');

    $('step1-num').setAttribute('data-done', wifiOk ? 'true' : 'false');
    $('step1-tag').setAttribute('data-done', wifiOk ? 'true' : 'false');
    set('step1-tag', wifiOk ? 'Done' : 'Open', wifiOk ? 'Fertig' : 'Offen');
    $('step2-num').setAttribute('data-done', keyOk ? 'true' : 'false');
    $('step2-tag').setAttribute('data-done', keyOk ? 'true' : 'false');
    set('step2-tag', keyOk ? 'Done' : 'Open', keyOk ? 'Fertig' : 'Offen');

    set('pass-toggle', passShown ? 'Hide' : 'Show', passShown ? 'Verbergen' : 'Anzeigen');
    $('pass-toggle').setAttribute('aria-label', T(passShown ? 'Hide password' : 'Show password', passShown ? 'Passwort verbergen' : 'Passwort anzeigen'));

    var busy = w.state === 'busy';
    $('connect').disabled = busy;
    set('connect', busy ? 'Connecting ...' : 'Connect', busy ? 'Verbinde ...' : 'Verbinden');
    var st = $('wifi-status');
    st.removeAttribute('data-s');
    if (wifiOk && w.ip && s.ap && !keyOk) set('wifi-status', 'Connected. The device now sits at ' + w.ip + ' on your network. Stay on THE WALL SETUP for step 2, even if your phone reports no internet.', 'Verbunden. Das Gerät hat jetzt die Adresse ' + w.ip + ' in deinem Netz. Bleib für Schritt 2 im Netz THE WALL SETUP, auch wenn das Handy kein Internet meldet.');
    else if (wifiOk && w.ip) set('wifi-status', 'Connected. The device now sits at ' + w.ip + ' on your network.', 'Verbunden. Das Gerät hat jetzt die Adresse ' + w.ip + ' in deinem Netz.');
    else if (w.state === 'wrong') { st.setAttribute('data-s', 'bad'); set('wifi-status', 'The password was rejected. The panel keeps showing its setup network.', 'Das Passwort wurde abgelehnt. Das Panel zeigt weiter sein Einrichtungsnetz.'); }
    else if (w.state === 'notfound') { st.setAttribute('data-s', 'bad'); set('wifi-status', 'The device cannot see this network. Is it a 2.4 GHz network within reach?', 'Das Gerät sieht dieses Netz nicht. Ist es ein 2,4-GHz-Netz in Reichweite?'); }
    else if (w.state === 'failed') { st.setAttribute('data-s', 'bad'); set('wifi-status', 'No connection within 30 seconds. Please try again.', 'Keine Verbindung in 30 Sekunden. Bitte nochmal versuchen.'); }
    else put('wifi-status', '');

    put('ssid-error', wifiError === 'ssid' ? T('Please pick or type a network name.', 'Bitte ein Netz wählen oder den Namen eintippen.') : '');
    $('wifi-ssid').setAttribute('aria-invalid', wifiError === 'ssid' ? 'true' : 'false');
    var passMsg = wifiError === 'empty' ? T('Please enter the Wi-Fi password.', 'Bitte das WLAN-Passwort eingeben.')
      : wifiError === 'short' ? T('A Wi-Fi password has at least eight characters.', 'Ein WLAN-Passwort hat mindestens acht Zeichen.') : '';
    put('pass-error', passMsg);
    $('wifi-pass').setAttribute('aria-invalid', passMsg ? 'true' : 'false');

    var key = $('api-key').value.trim();
    var shapeBad = key.length > 0 && !KEY.test(key);
    $('api-key').setAttribute('aria-invalid', shapeBad || keyError ? 'true' : 'false');
    var hint = $('key-hint');
    if (shapeBad || keyError) {
      hint.style.color = '#FF7A54';
      set('key-hint', 'A key starts with tw_live_ followed by 16 characters.', 'Der Schlüssel beginnt mit tw_live_ und hat danach 16 Zeichen.');
    } else {
      hint.style.color = '';
      if (k.set && !key) set('key-hint', 'A key is stored. Paste a new one to replace it.', 'Ein Schlüssel ist gespeichert. Ein neuer ersetzt ihn.');
      else set('key-hint', 'It is stored on the device and leaves your network only for thewall.godart.lu.', 'Er wird im Gerät gespeichert und verlässt dein Netz nur zu thewall.godart.lu.');
    }
    var checking = k.state === 'busy';
    $('pair').disabled = checking;
    set('pair', checking ? 'Checking ...' : 'Pair', checking ? 'Prüfe ...' : 'Verbinden');
    var ps = $('pair-status');
    ps.removeAttribute('data-s');
    if (keyOk) set('pair-status', 'Done. The panel is now linked to ' + user + '.', 'Fertig. Das Panel gehört jetzt zu ' + user + '.');
    else if (k.state === 'bad') { ps.setAttribute('data-s', 'bad'); set('pair-status', 'The server does not know this key. Copy it again from Settings.', 'Der Server kennt diesen Schlüssel nicht. Bitte nochmal aus den Einstellungen kopieren.'); }
    else if (k.state === 'full') { ps.setAttribute('data-s', 'bad'); set('pair-status', 'This account already has 20 devices. Remove one on the website first.', 'Auf dem Konto sind schon 20 Geräte. Erst eins auf der Webseite entfernen.'); }
    else if (k.state === 'offline') { ps.setAttribute('data-s', 'bad'); set('pair-status', 'The key is stored, the server did not answer yet. The device keeps trying.', 'Der Schlüssel ist gespeichert, der Server hat noch nicht geantwortet. Das Gerät versucht es weiter.'); }
    else if (pairNoWifi) { ps.setAttribute('data-s', 'bad'); set('pair-status', 'Step 1 first, the device needs Wi-Fi for this.', 'Erst Schritt 1, das Gerät braucht WLAN dafür.'); }
    else put('pair-status', '');

    put('lost', lost > 1 ? T('Connection to the panel lost, trying again ...', 'Verbindung zum Panel unterbrochen, versuche es weiter ...') : '');
  }

  function poll() {
    api('/api/state').then(function (res) {
      lost = 0;
      state = res.data;
      if (firstState) {
        firstState = false;
        applyLang(state.lang);
        $('bright').value = state.bright; $('bright-out').textContent = state.bright; drawBar();
        $('clock').setAttribute('aria-checked', state.clock ? 'true' : 'false');
        if (state.wifi && state.wifi.ssid && !$('wifi-ssid').value) { $('wifi-ssid').value = state.wifi.ssid; picked = state.wifi.ssid; }
      }
      render();
    }).catch(function () { lost++; render(); }).then(function () { setTimeout(poll, 2000); });
  }

  function scan() {
    api('/api/scan').then(function (res) {
      nets = (res.data && res.data.nets) || [];
      renderNets();
      /* Jede Suche stoert das Einrichtungsnetz ein, zwei Sekunden lang, deshalb nur einmal pro Minute. */
      setTimeout(scan, res.data && res.data.scanning ? 1500 : 60000);
    }).catch(function () { setTimeout(scan, 4000); });
  }

  document.querySelectorAll('[data-lang]').forEach(function (b) {
    b.addEventListener('click', function () {
      applyLang(b.getAttribute('data-lang'));
      api('/api/lang', { lang: lang }).catch(function () {});
    });
  });

  $('pass-toggle').addEventListener('click', function () {
    passShown = !passShown;
    $('wifi-pass').type = passShown ? 'text' : 'password';
    $('pass-toggle').setAttribute('aria-pressed', passShown ? 'true' : 'false');
    render();
  });
  $('wifi-ssid').addEventListener('input', function () { picked = $('wifi-ssid').value; wifiError = ''; renderNets(); render(); });
  $('wifi-pass').addEventListener('input', function () { wifiError = ''; render(); });

  $('connect').addEventListener('click', function () {
    var ssid = $('wifi-ssid').value, pass = $('wifi-pass').value, net = selectedNet();
    if (!ssid) { wifiError = 'ssid'; render(); $('wifi-ssid').focus(); return; }
    if (!pass && !(net && net.open)) { wifiError = 'empty'; render(); $('wifi-pass').focus(); return; }
    if (pass && pass.length < 8) { wifiError = 'short'; render(); $('wifi-pass').focus(); return; }
    wifiError = '';
    if (state) state.wifi = { state: 'busy', ssid: ssid };
    render();
    api('/api/wifi', { ssid: ssid, pass: pass }).catch(function () {});
  });

  $('api-key').addEventListener('input', function () { keyError = false; pairNoWifi = false; render(); });
  $('pair').addEventListener('click', function () {
    var key = $('api-key').value.trim();
    if (!KEY.test(key)) { keyError = true; render(); $('api-key').focus(); return; }
    if (!state || !state.wifi || state.wifi.state !== 'ok') { pairNoWifi = true; render(); return; }
    keyError = false;
    pairNoWifi = false;
    state.key = { state: 'busy', set: true };
    render();
    api('/api/key', { key: key }).then(function (res) {
      if (res.ok) $('api-key').value = '';
    }).catch(function () {});
  });

  $('clock').addEventListener('click', function () {
    var on = $('clock').getAttribute('aria-checked') !== 'true';
    $('clock').setAttribute('aria-checked', on ? 'true' : 'false');
    api('/api/clock', { on: on }).catch(function () {});
  });

  $('bright').addEventListener('input', function () {
    $('bright-out').textContent = $('bright').value;
    drawBar();
    clearTimeout(brightTimer);
    brightTimer = setTimeout(function () { api('/api/bright', { v: parseInt($('bright').value, 10) || 0 }).catch(function () {}); }, 150);
  });

  $('test').addEventListener('click', function () {
    api('/api/test', {}).then(function () {
      set('test-status', 'Running for ten seconds on the panel.', 'Läuft zehn Sekunden auf dem Panel.');
      clearTimeout(testTimer);
      testTimer = setTimeout(function () { $('test-status').textContent = ''; }, 10000);
    }).catch(function () {});
  });

  window.addEventListener('resize', function () { drawMark(); drawBar(); });
  applyLang('en');
  drawMark();
  drawBar();
  renderNets();
  poll();
  scan();
})();
</script>
</body>
</html>
)WALLPAGE";
