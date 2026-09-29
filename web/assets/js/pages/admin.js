/* Admin-Bereich: LED-Balken der Budgets, Testgeraete je Konto, Rollout, Automatik,
   Registrierung, Einladungslinks, SMTP-Zugang mit Test-Mail, Schluessel fuer
   mobiliteit.lu mit Test und Datenprobe, Impressum. */
(function () {
  'use strict';
  var TW = window.TW;
  var D = TW.data();
  var $ = function (sel) { return document.querySelector(sel); };
  var $$ = function (sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); };
  var GREEN = '#3DE07C', RED = '#FF7A54';

  function fail(el, res) {
    var e = TW.errorText(res);
    TW.flash(el, e[0], e[1], 0, RED);
  }

  function drawBudgets() {
    $$('[data-budget]').forEach(function (holder) {
      var share = parseFloat(holder.getAttribute('data-share')) || 0;
      TW.ledBar(holder, Math.max(share, 1 / 64), holder.getAttribute('data-colour'), 0.55);
    });
  }

  /* ---------- Testgeraete je Konto ---------- */
  var accountsStatus = $('[data-accounts-status]');
  $$('[data-test-toggle]').forEach(function (btn) {
    var busy = false;
    btn.addEventListener('click', function () {
      if (busy) return;
      busy = true;
      btn.setAttribute('aria-disabled', 'true');
      var on = btn.getAttribute('data-on') !== 'true';
      TW.api('/api/admin/test-device', { user: parseInt(btn.getAttribute('data-user'), 10), on: on }).then(function (res) {
        busy = false;
        btn.setAttribute('aria-disabled', 'false');
        if (!res.ok) { fail(accountsStatus, res); return; }
        var d = res.data;
        btn.setAttribute('data-on', d.test ? 'true' : 'false');
        var who = btn.getAttribute('data-name') || d.username;
        TW.text(btn, d.test ? 'Remove' : 'Add', d.test ? 'Entfernen' : 'Hinzufügen');
        btn.setAttribute('data-en-label', d.test ? 'Remove test device from ' + who : 'Add test device for ' + who);
        btn.setAttribute('data-de-label', d.test ? 'Testgerät von ' + who + ' entfernen' : 'Testgerät für ' + who + ' hinzufügen');
        btn.setAttribute('aria-label', TW.t(btn.getAttribute('data-en-label'), btn.getAttribute('data-de-label')));
        var row = btn.closest('[data-account]');
        var count = row && row.querySelector('[data-account-devices]');
        if (count) count.textContent = String(d.devices);
        TW.flash(accountsStatus,
          d.test ? 'Test device added for ' + d.username + '. Their device page shows it now.' : 'Test device removed from ' + d.username + '.',
          d.test ? 'Testgerät für ' + d.username + ' angelegt. Die Geräteseite zeigt es jetzt.' : 'Testgerät von ' + d.username + ' entfernt.',
          6000, GREEN);
      });
    });
  });

  /* ---------- Firmware hochladen ----------
     Die Datei geht roh als application/octet-stream, der Server liest die Version
     aus ihr selbst. Danach laedt die Seite neu, damit Version und Zaehler stimmen. */
  var fwFile = $('[data-fw-file]');
  var fwUpload = $('[data-fw-upload]');
  var fwStatus = $('[data-fw-status]');
  var fwBusy = false;
  if (fwUpload) {
    fwUpload.addEventListener('click', function () {
      if (fwBusy) return;
      var file = fwFile.files && fwFile.files[0];
      if (!file) { TW.flash(fwStatus, 'Choose firmware.bin first.', 'Erst firmware.bin auswählen.', 0, '#FF7A54'); fwFile.focus(); return; }
      if (file.size > 4 * 1024 * 1024) { TW.flash(fwStatus, 'The file is larger than 4 MB.', 'Die Datei ist größer als 4 MB.', 0, '#FF7A54'); return; }
      fwBusy = true;
      fwUpload.setAttribute('aria-busy', 'true');
      TW.text(fwStatus, 'Uploading ...', 'Wird hochgeladen ...');
      var meta = document.querySelector('meta[name="csrf-token"]');
      fetch('/api/admin/firmware-upload', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/octet-stream', 'Accept': 'application/json', 'X-CSRF-Token': meta ? meta.getAttribute('content') : '' },
        body: file
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j && j.ok, data: j, status: r.status }; }); })
        .catch(function () { return { ok: false, data: null, status: 0 }; })
        .then(function (res) {
          fwBusy = false;
          fwUpload.removeAttribute('aria-busy');
          if (!res.ok) {
            var err = res.data && res.data.error ? [res.data.error.en, res.data.error.de] : ['The upload did not go through.', 'Das Hochladen hat nicht geklappt.'];
            TW.flash(fwStatus, err[0], err[1], 0, '#FF7A54');
            return;
          }
          var v = String(res.data.version);
          TW.flash(fwStatus, 'Version ' + v + ' is on the server. Reloading ...', 'Version ' + v + ' liegt auf dem Server. Seite lädt neu ...', 0, '#3DE07C');
          setTimeout(function () { location.reload(); }, 1200);
        });
    });
  }

  /* ---------- Rollout ---------- */
  var rollout = $('[data-rollout]');
  var rolloutOut = $('[data-rollout-out]');
  var rolloutNote = $('[data-rollout-note]');
  function renderRollout() {
    var v = parseInt(rollout.value, 10) || 0;
    rolloutOut.textContent = String(v);
    var buckets = D.buckets || [];
    var n = buckets.length || D.devices || 0;
    var hits = buckets.filter(function (d) { return d.b < v; });
    var hit = hits.length;
    /* Namen als Text, sie kommen von den Nutzern. */
    var names = hits.map(function (d) { return d.name; }).join(', ');
    var version = D.latest || '';
    if (v === 0) {
      TW.text(rolloutNote, 'Nobody receives it. Use this to halt a rollout in progress.', 'Niemand bekommt es. Zum Anhalten eines laufenden Rollouts.');
    } else if (v === 100) {
      if (n === 0) TW.text(rolloutNote, 'No device has checked in yet.', 'Noch hat sich kein Gerät gemeldet.');
      else if (n === 1) TW.text(rolloutNote, 'The one device gets it.', 'Das eine Gerät bekommt es.');
      else TW.text(rolloutNote, 'All ' + n + ' devices. Only after one of them has run a day without trouble.', 'Alle ' + n + ' Geräte. Erst nachdem eines davon einen Tag stabil gelaufen ist.');
    } else {
      TW.text(rolloutNote,
        hit + ' of ' + n + ' devices receive ' + (version || 'the new build') + (hit ? ' (' + names + ')' : '') + ', the rest stay where they are.',
        hit + ' von ' + n + ' Geräten bekommen ' + (version || 'die neue Fassung') + (hit ? ' (' + names + ')' : '') + ', der Rest bleibt, wo er ist.');
    }
  }
  var rolloutTimer = null;
  rollout.addEventListener('input', function () {
    renderRollout();
    clearTimeout(rolloutTimer);
    rolloutTimer = setTimeout(function () {
      TW.api('/api/admin/firmware', { rollout: parseInt(rollout.value, 10) }).then(function (res) {
        if (!res.ok) { fail($('[data-rollout-status]'), res); return; }
        TW.flash($('[data-rollout-status]'), 'Saved.', 'Gespeichert.', 2000, GREEN);
      });
    }, 400);
  });

  var auto = $('[data-auto]');
  auto.addEventListener('click', function () {
    var next = auto.getAttribute('aria-checked') !== 'true';
    auto.setAttribute('aria-checked', next ? 'true' : 'false');
    TW.api('/api/admin/firmware', { auto: next }).then(function (res) {
      if (!res.ok) { auto.setAttribute('aria-checked', next ? 'false' : 'true'); fail($('[data-rollout-status]'), res); }
    });
  });

  /* ---------- Registrierung ---------- */
  var reg = $('[data-registration]');
  /* Der Schalter sagt, was An bedeutet. Die Zeile darunter nennt den Zustand. */
  function renderReg() {
    var open = reg.getAttribute('aria-checked') === 'true';
    TW.text($('[data-reg-note]'),
      open ? 'On. Anyone with their own hardware can sign up, at most 5 sign-ups per hour per IP.' : 'Off. New accounts only through an invitation link, existing ones are untouched.',
      open ? 'An. Jeder mit eigener Hardware kann sich registrieren, höchstens 5 Anmeldungen je Stunde und IP.' : 'Aus. Neue Konten nur über einen Einladungslink, bestehende bleiben unberührt.');
  }
  reg.addEventListener('click', function () {
    var next = reg.getAttribute('aria-checked') !== 'true';
    reg.setAttribute('aria-checked', next ? 'true' : 'false');
    renderReg();
    TW.api('/api/admin/registration', { mode: next ? 'open' : 'invite' }).then(function (res) {
      if (!res.ok) { reg.setAttribute('aria-checked', next ? 'false' : 'true'); renderReg(); fail($('[data-signup-status]'), res); }
    });
  });

  var signupForm = $('[data-signup-form]');
  var signupBox = $('[data-signup-link]');
  signupForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var email = $('[data-signup-email]').value.trim();
    TW.api('/api/admin/signup-link', { email: email }).then(function (res) {
      if (!res.ok) { fail($('[data-signup-status]'), res); return; }
      signupBox.hidden = false;
      signupBox.querySelector('input').value = res.data.link;
      TW.flash($('[data-signup-status]'), 'Link created. It works once, for seven days.', 'Link erzeugt. Er gilt einmal, sieben Tage lang.', 6000, GREEN);
    });
  });
  $('[data-copy-signup]').addEventListener('click', function () {
    var input = signupBox.querySelector('input');
    if (navigator.clipboard) navigator.clipboard.writeText(input.value).catch(function () { input.select(); });
    else input.select();
    TW.flash($('[data-signup-status]'), 'Copied.', 'Kopiert.', 2600, GREEN);
  });

  /* ---------- SMTP ---------- */
  var smtpForm = $('[data-smtp-form]');
  var smtpToggle = $('[data-smtp-toggle]');
  var security = 'starttls';
  $$('[data-security]').forEach(function (b) {
    if (b.getAttribute('aria-pressed') === 'true') security = b.getAttribute('data-security');
    b.addEventListener('click', function () {
      security = b.getAttribute('data-security');
      $$('[data-security]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      var port = $('#smtp-port');
      if (security === 'ssl' && port.value === '587') port.value = '465';
      if (security === 'starttls' && port.value === '465') port.value = '587';
    });
  });
  smtpToggle.addEventListener('click', function () {
    var open = smtpForm.hidden;
    smtpForm.hidden = !open;
    smtpToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) $('#smtp-host').focus();
  });

  function markInvalid(ids) {
    ['smtp-host', 'smtp-port', 'smtp-from', 'smtp-name'].forEach(function (id) { $('#' + id).setAttribute('aria-invalid', 'false'); });
    var map = { host: 'smtp-host', port: 'smtp-port', from_email: 'smtp-from', from_name: 'smtp-name' };
    var first = null;
    (ids || []).forEach(function (k) {
      var el = map[k] && $('#' + map[k]);
      if (el) { el.setAttribute('aria-invalid', 'true'); if (!first) first = el; }
    });
    if (first) first.focus();
  }

  function paintTag(tag, kind, en, de) {
    var colours = { ok: ['rgba(61,224,124,0.14)', '#3DE07C'], warn: ['rgba(255,170,0,0.16)', '#FFC44D'], bad: ['rgba(255,74,28,0.14)', '#FF7A54'], off: ['#1E252A', '#B4BCC3'] };
    tag.style.background = colours[kind][0];
    tag.style.color = colours[kind][1];
    TW.text(tag, en, de);
  }

  function setTag(kind, en, de) { paintTag($('[data-smtp-tag]'), kind, en, de); }

  smtpForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var body = {
      host: $('#smtp-host').value.trim(),
      port: parseInt($('#smtp-port').value, 10) || 0,
      security: security,
      user: $('#smtp-user').value.trim(),
      pass: $('#smtp-pass').value,
      from_email: $('#smtp-from').value.trim(),
      from_name: $('#smtp-name').value.trim()
    };
    var missing = [];
    if (!body.host) missing.push('host');
    if (!body.port) missing.push('port');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(body.from_email)) missing.push('from_email');
    if (missing.length) {
      markInvalid(missing);
      TW.flash($('[data-smtp-status]'), 'Please check the highlighted fields.', 'Bitte prüfe die markierten Felder.', 0, RED);
      return;
    }
    TW.api('/api/admin/smtp', body).then(function (res) {
      if (!res.ok) { markInvalid(res.data && res.data.fields); fail($('[data-smtp-status]'), res); return; }
      markInvalid([]);
      $('#smtp-pass').value = '';
      if (res.data.smtp && res.data.smtp.has_password) {
        $('#smtp-pass').setAttribute('placeholder', '••••••••');
        TW.text($('#smtp-pass-hint'), 'Saved and encrypted. Leave empty to keep it.', 'Gespeichert und verschlüsselt. Leer lassen, um ihn zu behalten.');
      }
      $('[data-smtp-summary]').textContent = body.host + ':' + body.port + ' · ' + security.toUpperCase();
      setTag('warn', 'Untested', 'Ungetestet');
      TW.flash($('[data-smtp-status]'), 'Saved. Send a test mail next.', 'Gespeichert. Jetzt eine Test-Mail schicken.', 5000, GREEN);
    });
  });

  var testBtn = $('[data-smtp-test]');
  var testing = false;
  testBtn.addEventListener('click', function () {
    var to = $('[data-test-to]').value.trim();
    if (testing) return;
    testing = true;
    testBtn.setAttribute('aria-disabled', 'true');
    TW.text($('[data-test-status]'), 'Sending...', 'Wird gesendet...');
    $('[data-test-status]').style.color = '#8B949C';
    TW.api('/api/admin/smtp-test', { to: to }).then(function (res) {
      testing = false;
      testBtn.setAttribute('aria-disabled', 'false');
      if (!res.ok) { setTag('bad', 'Test failed', 'Test fehlgeschlagen'); fail($('[data-test-status]'), res); return; }
      setTag('ok', 'Tested', 'Getestet');
      TW.flash($('[data-test-status]'), 'Sent to ' + res.data.to + '. Check the inbox and the spam folder.', 'An ' + res.data.to + ' geschickt. Posteingang und Spam-Ordner prüfen.', 9000, GREEN);
    });
  });

  /* ---------- mobiliteit.lu ---------- */
  /* Der Schluessel geht nur hin, nie zurueck. Alles aus der Antwort kommt von
     aussen und landet deshalb nur als textContent im Dokument. */
  var transitForm = $('[data-transit-form]');
  var transitToggle = $('[data-transit-toggle]');
  var transitStatus = $('[data-transit-status]');
  var transitKey = $('#transit-key');
  var transitTag = $('[data-transit-tag]');
  var transitTest = $('[data-transit-test]');
  var transitSample = $('[data-transit-sample]');
  var MODES = { bus: ['Bus', 'Bus'], train: ['Train', 'Zug'], tram: ['Tram', 'Tram'], other: ['Other', 'Sonstiges'] };
  var MODES_MANY = { bus: ['buses', 'Busse'], train: ['trains', 'Züge'], tram: ['trams', 'Trams'], other: ['others', 'Sonstige'] };
  var transitBusy = false;
  var sampleText = '';

  transitToggle.addEventListener('click', function () {
    var open = transitForm.hidden;
    transitForm.hidden = !open;
    transitToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) transitKey.focus();
  });

  function modeCounts(byMode) {
    var en = [], de = [];
    Object.keys(byMode || {}).forEach(function (k) {
      var n = byMode[k];
      var m = n === 1 ? (MODES[k] || MODES.other) : (MODES_MANY[k] || MODES_MANY.other);
      en.push(n + ' ' + (n === 1 ? m[0].toLowerCase() : m[0]));
      de.push(n + ' ' + m[1]);
    });
    return [en.join(', '), de.join(', ')];
  }

  function busyTransit(btn, on) {
    transitBusy = on;
    [transitTest, transitSample].forEach(function (b) { b.setAttribute('aria-disabled', on ? 'true' : 'false'); });
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  transitForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var key = transitKey.value.trim();
    if (!key) {
      if (transitForm.getAttribute('data-has-key') === 'true') {
        TW.flash(transitStatus, 'Nothing to save, the saved key stays.', 'Nichts zu speichern, der gespeicherte Schlüssel bleibt.', 4000, GREEN);
        return;
      }
      transitKey.setAttribute('aria-invalid', 'true');
      transitKey.focus();
      TW.flash(transitStatus, 'Please paste the key from the mail of the ATP.', 'Bitte den Schlüssel aus der Mail der ATP einfügen.', 0, RED);
      return;
    }
    TW.api('/api/admin/transit', { key: key }).then(function (res) {
      if (!res.ok) { transitKey.setAttribute('aria-invalid', 'true'); transitKey.focus(); fail(transitStatus, res); return; }
      transitKey.setAttribute('aria-invalid', 'false');
      transitKey.value = '';
      transitKey.setAttribute('placeholder', '••••••••');
      transitForm.setAttribute('data-has-key', 'true');
      TW.text($('#transit-key-hint'), 'Saved and encrypted. A new one replaces it.', 'Gespeichert und verschlüsselt. Ein neuer ersetzt ihn.');
      paintTag(transitTag, 'warn', 'Untested', 'Ungetestet');
      TW.flash(transitStatus, 'Saved. Press Test next.', 'Gespeichert. Jetzt auf Testen drücken.', 6000, GREEN);
    });
  });

  transitTest.addEventListener('click', function () {
    if (transitBusy) return;
    busyTransit(transitTest, true);
    transitStatus.style.color = '#8B949C';
    TW.text(transitStatus, 'Asking mobiliteit.lu...', 'Frage mobiliteit.lu...');
    TW.api('/api/admin/transit-test', {}).then(function (res) {
      busyTransit(transitTest, false);
      if (!res.ok) {
        if (res.data && res.data.transit && res.data.transit.tested_at) paintTag(transitTag, 'bad', 'Test failed', 'Test fehlgeschlagen');
        fail(transitStatus, res);
        return;
      }
      var d = res.data;
      paintTag(transitTag, 'ok', 'Tested', 'Getestet');
      var counts = modeCounts(d.by_mode);
      TW.flash(transitStatus,
        'The key works. ' + d.stops.length + ' stops near Luxembourg station, next departures: ' + (counts[0] || 'none') + '. Used today: ' + d.quota.day + ' of 5,000.',
        'Der Schlüssel geht. ' + d.stops.length + ' Haltestellen am Bahnhof Luxemburg, nächste Abfahrten: ' + (counts[1] || 'keine') + '. Heute verbraucht: ' + d.quota.day + ' von 5 000.',
        0, GREEN);
      var box = $('[data-transit-result]');
      var list = $('[data-transit-list]');
      TW.text($('[data-transit-result-head]'), 'Next departures at ' + d.stop, 'Nächste Abfahrten an ' + d.stop);
      list.textContent = '';
      d.departures.forEach(function (dep) {
        var li = document.createElement('li');
        li.style.cssText = "display:flex;flex-wrap:wrap;gap:4px 14px;padding:10px 12px;background:#0B0D0F;font-family:'IBM Plex Mono',monospace;font-size:12px;color:#C6CDD3";
        var time = document.createElement('span');
        time.style.cssText = 'flex:0 0 44px;color:#FFAA00;font-variant-numeric:tabular-nums';
        time.textContent = dep.time;
        var line = document.createElement('span');
        line.style.cssText = 'flex:0 0 96px;min-width:0;color:#E8EAEC';
        line.textContent = dep.label;
        var dir = document.createElement('span');
        dir.style.cssText = 'flex:1 1 180px;min-width:0';
        dir.textContent = dep.direction;
        var info = document.createElement('span');
        info.style.cssText = 'flex:0 0 auto;color:#8B949C';
        var m = MODES[dep.mode] || MODES.other;
        var extraEn = [m[0]], extraDe = [m[1]];
        if (dep.platform) { extraEn.push('platform ' + dep.platform); extraDe.push('Gleis ' + dep.platform); }
        if (dep.delay_min) { extraEn.push('+' + dep.delay_min + ' min'); extraDe.push('+' + dep.delay_min + ' Min'); }
        if (dep.additional) { extraEn.push('additional'); extraDe.push('Zusatzfahrt'); }
        if (dep.replacement) { extraEn.push('replacement'); extraDe.push('Ersatzverkehr'); }
        if (dep.redirected) { extraEn.push('changed'); extraDe.push('geändert'); }
        if (dep.cancelled) { extraEn.push('cancelled'); extraDe.push('fällt aus'); info.style.color = '#FF7A54'; }
        TW.text(info, extraEn.join(' · '), extraDe.join(' · '));
        li.appendChild(time);
        li.appendChild(line);
        li.appendChild(dir);
        li.appendChild(info);
        list.appendChild(li);
      });
      box.hidden = false;
    });
  });

  transitSample.addEventListener('click', function () {
    if (transitBusy) return;
    busyTransit(transitSample, true);
    transitStatus.style.color = '#8B949C';
    TW.text(transitStatus, 'Collecting departures at six stops, this takes a few seconds...', 'Sammle Abfahrten an sechs Haltestellen, das dauert ein paar Sekunden...');
    TW.api('/api/admin/transit-sample', {}).then(function (res) {
      busyTransit(transitSample, false);
      if (!res.ok) { fail(transitStatus, res); return; }
      var s = res.data.sample;
      sampleText = JSON.stringify(s, null, 2);
      var counts = modeCounts(s.stats.by_mode);
      TW.flash(transitStatus, 'Sample ready.', 'Probe fertig.', 4000, GREEN);
      TW.text($('[data-transit-sample-head]'),
        s.stats.stops + ' stops, ' + s.stats.departures + ' departures (' + counts[0] + '), ' + s.stats.with_realtime + ' with realtime, ' + s.stats.delayed_1min_or_more + ' delayed. Longest destination: ' + s.stats.longest.direction.chars + ' characters.' + (s.errors.length ? ' Not reached: ' + s.errors.length + '.' : ''),
        s.stats.stops + ' Haltestellen, ' + s.stats.departures + ' Abfahrten (' + counts[1] + '), ' + s.stats.with_realtime + ' mit Echtzeit, ' + s.stats.delayed_1min_or_more + ' verspätet. Längstes Ziel: ' + s.stats.longest.direction.chars + ' Zeichen.' + (s.errors.length ? ' Nicht erreicht: ' + s.errors.length + '.' : ''));
      $('[data-transit-sample-box]').hidden = false;
    });
  });

  $('[data-transit-download]').addEventListener('click', function () {
    if (!sampleText) return;
    var stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '');
    var url = URL.createObjectURL(new Blob([sampleText], { type: 'application/json' }));
    var a = document.createElement('a');
    a.href = url;
    a.download = 'thewall-nahverkehr-' + stamp + '.json';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  });

  $('[data-transit-copy]').addEventListener('click', function () {
    if (!sampleText || !navigator.clipboard) return;
    navigator.clipboard.writeText(sampleText).then(function () {
      TW.flash(transitStatus, 'Copied.', 'Kopiert.', 2600, GREEN);
    }, function () {
      TW.flash(transitStatus, 'Copying is blocked in this browser, use the download.', 'Kopieren ist in diesem Browser gesperrt, bitte den Download nehmen.', 0, RED);
    });
  });

  /* ---------- Spotify ----------
     Client-ID und Secret gehen nur hin. Das Secret kommt nie zurueck. */
  var spotifyForm = $('[data-spotify-form]');
  if (spotifyForm) {
    var spotifyStatus = $('[data-spotify-status]'), spotifyTag = $('[data-spotify-tag]');
    var spotifyId = $('#spotify-id'), spotifySecret = $('#spotify-secret'), spotifyTest = $('[data-spotify-test]');
    var spotifyToggle = $('[data-spotify-toggle]');
    spotifyToggle.addEventListener('click', function () {
      var open = spotifyForm.hidden;
      spotifyForm.hidden = !open;
      spotifyToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) spotifyId.focus();
    });
    $('[data-spotify-copy]').addEventListener('click', function () {
      var input = $('[data-spotify-redirect]');
      if (navigator.clipboard) navigator.clipboard.writeText(input.value).then(function () {
        TW.flash(spotifyStatus, 'Copied.', 'Kopiert.', 2600, GREEN);
      }, function () { input.select(); });
      else input.select();
    });
    spotifyForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var id = spotifyId.value.trim(), secret = spotifySecret.value.trim();
      [spotifyId, spotifySecret].forEach(function (f) { f.setAttribute('aria-invalid', 'false'); });
      TW.api('/api/admin/spotify', { client_id: id, secret: secret }).then(function (res) {
        if (!res.ok) {
          var bad = (res.data && res.data.fields) || [];
          var first = bad.indexOf('client_id') !== -1 ? spotifyId : bad.indexOf('secret') !== -1 ? spotifySecret : null;
          if (first) { first.setAttribute('aria-invalid', 'true'); first.focus(); }
          fail(spotifyStatus, res);
          return;
        }
        spotifySecret.value = '';
        spotifySecret.setAttribute('placeholder', '••••••••');
        spotifyForm.setAttribute('data-has-secret', 'true');
        TW.text($('#spotify-secret-hint'), 'Saved and encrypted. Leave empty to keep it.', 'Gespeichert und verschlüsselt. Leer lassen, um es zu behalten.');
        paintTag(spotifyTag, 'warn', 'Untested', 'Ungetestet');
        TW.flash(spotifyStatus, 'Saved. Press Test next.', 'Gespeichert. Jetzt auf Testen drücken.', 6000, GREEN);
      });
    });
    var spotifyBusy = false;
    spotifyTest.addEventListener('click', function () {
      if (spotifyBusy) return;
      spotifyBusy = true;
      spotifyTest.setAttribute('aria-busy', 'true');
      spotifyStatus.style.color = '#8B949C';
      TW.text(spotifyStatus, 'Asking Spotify...', 'Frage Spotify...');
      TW.api('/api/admin/spotify-test', {}).then(function (res) {
        spotifyBusy = false;
        spotifyTest.removeAttribute('aria-busy');
        if (!res.ok) {
          if (res.data && res.data.spotify && res.data.spotify.tested_at) paintTag(spotifyTag, 'bad', 'Test failed', 'Test fehlgeschlagen');
          fail(spotifyStatus, res);
          return;
        }
        paintTag(spotifyTag, 'ok', 'Tested', 'Getestet');
        TW.flash(spotifyStatus, res.data.en, res.data.de, 0, GREEN);
      });
    });
  }

  /* ---------- Impressum ---------- */
  var imprintForm = $('[data-imprint-form]');
  imprintForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var body = {
      name: $('#imp-name').value.trim(),
      address: $('#imp-address').value.trim(),
      email: $('#imp-email').value.trim(),
      repo: $('#imp-repo').value.trim()
    };
    TW.api('/api/admin/imprint', body).then(function (res) {
      if (!res.ok) { fail($('[data-imprint-status]'), res); return; }
      $('[data-ua]').textContent = res.data.user_agent;
      var uaTag = $('[data-ua-tag]');
      var set = !!body.email;
      uaTag.style.background = set ? 'rgba(61,224,124,0.14)' : 'rgba(255,170,0,0.16)';
      uaTag.style.color = set ? '#3DE07C' : '#FFC44D';
      TW.text(uaTag, set ? 'Set' : 'Contact missing', set ? 'Gesetzt' : 'Kontakt fehlt');
      TW.flash($('[data-imprint-status]'), 'Saved.', 'Gespeichert.', 3000, GREEN);
    });
  });

  TW.onResize(drawBudgets);
  TW.onLang(function () { renderRollout(); renderReg(); });
  drawBudgets();
  renderRollout();
  renderReg();
})();
