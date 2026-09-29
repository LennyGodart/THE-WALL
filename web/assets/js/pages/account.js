/* Kontoseite: das Panel links reagiert auf das Formular, die Reiter wechseln
   ohne Neuladen zwischen Anmelden, Registrieren und Passwort vergessen.
   Geprueft wird hier vorab, entschieden wird auf dem Server. */
(function () {
  'use strict';
  var P = window.PX, TW = window.TW, pal = TW.PAL;
  var data = TW.data();
  var $ = function (sel) { return document.querySelector(sel); };
  var form = $('[data-form]');
  var panel = $('[data-panel]');
  var fields = { username: $('#tw-user'), email: $('#tw-mail'), password: $('#tw-pass'), password2: $('#tw-pass2') };
  var hints = { username: $('#tw-user-hint'), email: $('#tw-mail-hint'), password: $('#tw-pass-hint'), password2: $('#tw-pass2-hint') };
  var statusEl = $('[data-status]');
  var submitBtn = $('[data-submit]');
  var motionBtn = $('[data-motion]');
  var motionLabel = $('[data-motion-label]');

  var state = {
    mode: data.mode || 'login',
    done: !!data.done,
    canRegister: !!data.canRegister,
    focus: null,
    passShown: false,
    running: !TW.reduce,
    mailX: null
  };

  var COPY = {
    heads: {
      login: ['Welcome back', 'Willkommen zurück'],
      register: ['Create your account', 'Konto anlegen'],
      reset: ['Forgotten password', 'Passwort vergessen']
    },
    leads: {
      login: ['Sign in to manage your devices.', 'Melde dich an, um deine Geräte zu verwalten.'],
      register: ['You get a key afterwards. Paste it into your device and the two know each other.', 'Danach bekommst du einen Schlüssel, den du in dein Gerät einsetzt. Das verbindet beide.'],
      registerClosed: ['New accounts need an invitation link at the moment. Ask the person who runs this site.', 'Neue Konten gibt es gerade nur mit Einladungslink. Frag die Person, die diese Seite betreibt.'],
      reset: ['Enter your email address. We send you a link that lets you set a new password.', 'Gib deine E-Mail-Adresse ein. Wir schicken dir einen Link, mit dem du ein neues Passwort setzen kannst.']
    },
    submits: {
      login: ['Sign in', 'Anmelden'],
      register: ['Create account', 'Konto anlegen'],
      reset: ['Send the link', 'Link schicken']
    },
    actions: { login: '/account/login', register: '/account/register', reset: '/account/reset' },
    userHint: ['3 to 20 characters: letters, numbers, underscore', '3 bis 20 Zeichen, Buchstaben, Zahlen, Unterstrich'],
    mailHint: ['For confirmation and password links', 'Für die Bestätigung und für Passwort-Links'],
    passHintNew: ['At least 6 characters, including at least one letter', 'Mindestens 6 Zeichen, davon mindestens ein Buchstabe'],
    pass2Hint: ['Once more, to be sure', 'Zur Sicherheit noch einmal'],
    errUser: ['Please use 3 to 20 allowed characters', 'Bitte einen Benutzernamen mit 3 bis 20 erlaubten Zeichen'],
    errUserTaken: ['That name is taken', 'Dieser Name ist schon vergeben'],
    errMail: ['That email address looks incomplete', 'Diese E-Mail-Adresse sieht nicht vollständig aus'],
    errPassShort: ['At least 6 characters', 'Mindestens 6 Zeichen'],
    errPassLetter: ['At least one letter', 'Mindestens ein Buchstabe'],
    errPassEmpty: ['Please enter your password', 'Bitte dein Passwort eingeben'],
    errPass2: ['The two passwords do not match', 'Die beiden Passwörter sind nicht gleich'],
    fixErrors: ['Please check the highlighted fields.', 'Bitte prüfe die markierten Felder.'],
    working: ['Working...', 'Moment...'],
    strength: [['too short', 'zu kurz'], ['weak', 'schwach'], ['okay', 'ok'], ['good', 'gut'], ['strong', 'stark']]
  };

  /* ---------- Formular ---------- */
  function setHint(name, pair, isError) {
    var el = hints[name];
    if (!el) return;
    TW.text(el, pair[0], pair[1]);
    el.style.color = isError ? '#FF7A54' : '#8B949C';
  }

  function resetHints() {
    setHint('username', COPY.userHint, false);
    setHint('email', COPY.mailHint, false);
    setHint('password', state.mode === 'register' || state.mode === 'newpass' ? COPY.passHintNew : ['', ''], false);
    setHint('password2', COPY.pass2Hint, false);
    spokenScore = -1;
    Object.keys(fields).forEach(function (k) { if (fields[k]) fields[k].setAttribute('aria-invalid', 'false'); });
  }

  function strength() {
    var v = fields.password ? fields.password.value : '';
    if (!v || v.length < 6) return v.length ? 1 : 0;
    var s = 2;
    if (v.length >= 10) s++;
    if (/[0-9]/.test(v) && /[a-z]/i.test(v)) s++;
    if (/[^a-z0-9]/i.test(v) && v.length >= 12) s++;
    return Math.min(5, s);
  }

  /* Die Staerke steht sonst nur im Balken (aria-hidden) und im Canvas. Als Satz im Hinweis
     unter dem Feld hoert sie auch ein Vorleser, aber nur, wenn sich das Wort aendert. */
  var spokenScore = -1;
  function speakStrength(score) {
    if (state.mode !== 'register' && state.mode !== 'newpass') return;
    if (!fields.password || fields.password.getAttribute('aria-invalid') === 'true' || score === spokenScore) return;
    spokenScore = score;
    if (!fields.password.value) { setHint('password', COPY.passHintNew, false); return; }
    var w = COPY.strength[Math.max(0, score - 1)];
    setHint('password', [COPY.passHintNew[0] + '. Strength: ' + w[0] + '.', COPY.passHintNew[1] + '. Stärke: ' + w[1] + '.'], false);
  }

  function renderMeter() {
    var meter = $('[data-meter]');
    if (!meter) return;
    var score = strength();
    speakStrength(score);
    var col = score >= 4 ? '#3DE07C' : score >= 3 ? '#FFAA00' : score >= 2 ? '#E09A1A' : '#FF4A1C';
    Array.prototype.forEach.call(meter.children, function (seg, i) {
      seg.style.background = i < score ? col : '#1E252A';
    });
  }

  /* Wechsel zwischen den drei Formularen, die ohne Link erreichbar sind. */
  function setMode(mode) {
    if (mode === state.mode && !state.done) return;
    if (['login', 'register', 'reset'].indexOf(mode) === -1) return;
    state.mode = mode;
    state.done = false;
    state.mailX = null;
    var closed = mode === 'register' && !state.canRegister;

    document.querySelectorAll('[data-when]').forEach(function (el) {
      var modes = el.getAttribute('data-when').split(' ');
      var show = modes.indexOf(mode) !== -1;
      if (closed && el.getAttribute('data-when') !== 'login reset') show = false;
      if (el.hasAttribute('data-meter')) show = mode === 'register';
      el.hidden = !show;
    });
    var tabs = $('[data-tabs]');
    tabs.hidden = !(mode === 'login' || mode === 'register');
    tabs.querySelectorAll('.tab').forEach(function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-go') === mode ? 'true' : 'false');
    });
    $('[data-back]').hidden = mode !== 'reset';
    $('[data-switch]').hidden = !(mode === 'login' || mode === 'register');

    var head = $('[data-head]');
    TW.text(head, COPY.heads[mode][0], COPY.heads[mode][1]);
    var lead = closed ? COPY.leads.registerClosed : COPY.leads[mode];
    TW.text($('[data-lead]'), lead[0], lead[1]);
    TW.text(submitBtn, COPY.submits[mode][0], COPY.submits[mode][1]);
    submitBtn.hidden = closed;
    TW.text($('[data-switch-text]'), mode === 'register' ? 'Already have an account?' : 'No account yet?', mode === 'register' ? 'Schon ein Konto?' : 'Noch kein Konto?');
    TW.text($('[data-switch-go]'), mode === 'register' ? 'Sign in' : 'Create one', mode === 'register' ? 'Anmelden' : 'Konto anlegen');
    form.setAttribute('action', COPY.actions[mode]);
    if (fields.password) {
      fields.password.setAttribute('autocomplete', mode === 'register' ? 'new-password' : 'current-password');
      fields.password.setAttribute('placeholder', mode === 'register' ? '••••••' : '');
      fields.password.value = '';
    }
    TW.text(statusEl, '', '');
    resetHints();
    renderMeter();
    try { history.replaceState(null, '', mode === 'login' ? '/account' : '/account?mode=' + mode); } catch (e) { /* egal */ }
    requestAnimationFrame(TW.drawHeadings);
  }

  document.querySelectorAll('[data-go]').forEach(function (b) {
    b.addEventListener('click', function () {
      var target = b.getAttribute('data-go');
      if (state.mode === 'newpass' || state.mode === 'verified') { location.href = target === 'login' ? '/account' : '/account?mode=' + target; return; }
      setMode(target);
    });
  });
  var switchGo = $('[data-switch-go]');
  if (switchGo) switchGo.addEventListener('click', function () { setMode(state.mode === 'register' ? 'login' : 'register'); });

  function validate() {
    var e = {}, m = state.mode;
    var v = function (k) { return fields[k] ? fields[k].value : ''; };
    if (m === 'register') {
      if (!/^[a-z0-9_]{3,20}$/i.test(v('username'))) e.username = COPY.errUser;
      else if (v('username').toLowerCase() === 'admin') e.username = COPY.errUserTaken;
    }
    if (m === 'login' || m === 'register' || m === 'reset') {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v('email'))) e.email = COPY.errMail;
    }
    if (m === 'register' || m === 'newpass') {
      if (v('password').length < 6) e.password = COPY.errPassShort;
      else if (!/\p{L}/u.test(v('password'))) e.password = COPY.errPassLetter;
    } else if (m === 'login' && !v('password')) {
      e.password = COPY.errPassEmpty;
    }
    if (m === 'newpass' && !e.password && v('password') !== v('password2')) e.password2 = COPY.errPass2;
    return e;
  }

  function showErrors(errors) {
    var order = ['username', 'email', 'password', 'password2'];
    var first = null;
    order.forEach(function (k) {
      if (!fields[k]) return;
      if (errors[k]) {
        fields[k].setAttribute('aria-invalid', 'true');
        setHint(k, errors[k], true);
        if (!first) first = fields[k];
      }
    });
    if (first) {
      TW.text(statusEl, COPY.fixErrors[0], COPY.fixErrors[1]);
      statusEl.style.color = '#FF7A54';
      first.focus();
    }
  }

  if (form) {
    form.addEventListener('submit', function (ev) {
      if (state.mode === 'verified') return;
      resetHints();
      var errors = validate();
      if (Object.keys(errors).length) {
        ev.preventDefault();
        showErrors(errors);
        return;
      }
      var langField = form.querySelector('[data-lang-field]');
      if (langField) langField.value = TW.lang();
      TW.text(submitBtn, COPY.working[0], COPY.working[1]);
      submitBtn.style.background = '#C98A0F';
    });
  }

  Object.keys(fields).forEach(function (k) {
    var f = fields[k];
    if (!f) return;
    f.addEventListener('focus', function () {
      state.focus = k === 'username' ? 'user' : k === 'email' ? 'mail' : 'pass';
      state.done = false;
    });
    f.addEventListener('blur', function () { state.focus = null; });
    f.addEventListener('input', function () {
      if (f.getAttribute('aria-invalid') === 'true') {
        f.setAttribute('aria-invalid', 'false');
        if (k === 'username') setHint(k, COPY.userHint, false);
        if (k === 'email') setHint(k, COPY.mailHint, false);
        if (k === 'password') { setHint(k, state.mode === 'login' ? ['', ''] : COPY.passHintNew, false); spokenScore = -1; }
        if (k === 'password2') setHint(k, COPY.pass2Hint, false);
      }
      if (k === 'password') renderMeter();
    });
  });

  var toggle = $('[data-pass-toggle]');
  if (toggle) {
    toggle.addEventListener('click', function () {
      state.passShown = !state.passShown;
      [fields.password, fields.password2].forEach(function (f) { if (f) f.type = state.passShown ? 'text' : 'password'; });
      toggle.setAttribute('aria-pressed', state.passShown ? 'true' : 'false');
      toggle.setAttribute('data-en-label', state.passShown ? 'Hide password' : 'Show password');
      toggle.setAttribute('data-de-label', state.passShown ? 'Passwort verbergen' : 'Passwort anzeigen');
      toggle.setAttribute('aria-label', TW.t(toggle.getAttribute('data-en-label'), toggle.getAttribute('data-de-label')));
      TW.text(toggle.firstElementChild, state.passShown ? 'Hide' : 'Show', state.passShown ? 'Verbergen' : 'Anzeigen');
    });
  }

  /* ---------- Panel ---------- */
  function planeSprite(g, x, y, col) {
    for (var i = -3; i <= 2; i++) P.set(g, x + i, y, col);
    for (var j = -2; j <= 2; j++) P.set(g, x, y + j, col);
    P.set(g, x + 2, y - 1, col);
    P.set(g, x + 2, y + 1, col);
  }

  function sceneRadar(g, t) {
    var cx = 40, cy = 32;
    [12, 22, 32].forEach(function (r) {
      for (var a = 0; a < 360; a += 4) {
        var rad = a * Math.PI / 180;
        P.set(g, Math.round(cx + Math.cos(rad) * r), Math.round(cy + Math.sin(rad) * r * 0.62), '#1E3A2C');
      }
    });
    P.set(g, cx, cy, pal.green);
    var sweep = state.running ? (t * 1.1) % (Math.PI * 2) : 2.1;
    for (var r = 2; r < 33; r++) {
      P.set(g, Math.round(cx + Math.cos(sweep) * r), Math.round(cy + Math.sin(sweep) * r * 0.62), r > 26 ? '#2C6B4F' : pal.green);
    }
    var planes = [{ a: 0.6, r: 26, code: 'LGL9561' }, { a: 3.4, r: 17, code: 'CLX742' }, { a: 5.1, r: 30, code: 'DLH88N' }];
    var near = planes[0];
    planes.forEach(function (pl, i) {
      var drift = state.running ? t * (0.12 + i * 0.04) : 0;
      var a = pl.a + drift;
      var x = Math.round(cx + Math.cos(a) * pl.r);
      var y = Math.round(cy + Math.sin(a) * pl.r * 0.62);
      /* Winkel seit der Strahl vorbei ist: hell direkt danach, dann zwei Stufen Nachleuchten.
         Vorher war es umgekehrt, der Blip ging aus, sobald der Strahl ankam. */
      var since = ((sweep - a) % (Math.PI * 2) + Math.PI * 2) % (Math.PI * 2);
      planeSprite(g, x, y, since < 0.6 ? pal.accent : since < 1.8 ? '#B07600' : '#6B4A00');
      if (pl.r < near.r) near = pl;
    });
    P.text(g, 84, 6, 'RADAR', pal.cyan);
    P.text(g, 84, 18, near.code, pal.white);
    P.text(g, 84, 30, '40NM', pal.dim);
    P.rect(g, 82, 2, 1, 40, '#1B2126');
    P.text(g, 2, 56, 'LUX 49.61N 6.13E', pal.dim);
  }

  function sceneName(g, t) {
    var name = (fields.username.value || '').toUpperCase().slice(0, 10) || '?';
    P.text(g, 4, 8, TW.t('HELLO', 'HALLO'), pal.dim, 2);
    var w = P.width(name, 2);
    P.text(g, Math.max(2, Math.round((128 - w) / 2)), 30, name, pal.accent, 2);
    var caret = !state.running || Math.floor(t * 2) % 2 === 0;
    if (caret && state.focus === 'user') P.rect(g, Math.min(124, Math.round((128 - w) / 2) + w + 3), 30, 2, 14, pal.accent);
    P.rect(g, 0, 60, 128, 1, '#1B2126');
    P.text(g, 2, 56, TW.t('YOUR DEVICE GREETING', 'GRUSS AUF DEM GERAET'), pal.dim);
  }

  function sceneMail(g) {
    var mail = (fields.email.value || '').toUpperCase();
    P.frame(g, 30, 14, 68, 30, pal.cyan);
    for (var i = 0; i < 34; i++) {
      P.set(g, 30 + i, 14 + Math.round(i * 0.44), pal.cyan);
      P.set(g, 97 - i, 14 + Math.round(i * 0.44), pal.cyan);
    }
    if (mail) {
      var w = P.width(mail, 1);
      if (state.mailX == null) state.mailX = 128;
      if (state.running) {
        state.mailX -= 2;
        if (state.mailX < -w) state.mailX = 128;
      } else {
        state.mailX = Math.max(2, Math.round((128 - w) / 2));
      }
      P.text(g, Math.round(state.mailX), 52, mail, pal.white);
    } else {
      P.text(g, 26, 52, TW.t('CONFIRMATION', 'BESTAETIGUNG'), pal.dim);
    }
  }

  function scenePass(g) {
    var v = fields.password.value;
    var n = Math.min(20, v.length);
    var rating = state.mode === 'register' || state.mode === 'newpass';
    var score = strength();
    var col = !rating ? pal.accent : score >= 4 ? pal.green : score >= 3 ? pal.accent : score >= 2 ? '#E09A1A' : pal.red;
    for (var i = 0; i < n; i++) {
      P.rect(g, 6 + (i % 10) * 12, 14 + Math.floor(i / 10) * 14, 7, 7, col);
    }
    if (!n) P.text(g, 34, 16, TW.t('PASSWORD', 'PASSWORT'), pal.dim);
    if (rating) {
      P.rect(g, 6, 46, 116, 4, '#1B2126');
      P.rect(g, 6, 46, Math.round(116 * (score / 5)), 4, col);
      var word = COPY.strength[Math.max(0, score - 1)];
      P.text(g, 6, 54, TW.t(word[0], word[1]).toUpperCase(), pal.dim);
    } else {
      P.text(g, 6, 54, n + TW.t(' CHARACTERS', ' ZEICHEN'), pal.dim);
    }
  }

  function sceneDone(g, t) {
    var pts = [[44, 32], [47, 35], [50, 38], [53, 35], [56, 32], [59, 29], [62, 26], [65, 23], [68, 20]];
    var upto = !state.running ? pts.length : Math.min(pts.length, Math.floor(t * 14));
    for (var i = 0; i < upto; i++) P.rect(g, pts[i][0], pts[i][1], 3, 3, pal.green);
    var m = state.mode;
    var word = (m === 'register' || m === 'reset') ? TW.t('CHECK YOUR MAIL', 'MAIL UNTERWEGS')
      : m === 'newpass' ? TW.t('SAVED', 'GESPEICHERT') : TW.t('WELCOME', 'WILLKOMMEN');
    P.text(g, Math.round((128 - P.width(word, 1)) / 2), 50, word, pal.white);
  }

  var t0 = performance.now(), last = 0, frozenT = 0, doneAt = null;
  function drawPanel(t) {
    var c = panel && panel.querySelector('canvas[data-px="panel"]');
    if (!c) return;
    var g = P.grid(128, 64);
    if (state.done || state.mode === 'verified') {
      /* Zeit ab dem Moment, in dem "geschafft" erscheint: das Haekchen zeichnet sich einmal in 0,64 s. */
      if (doneAt === null) doneAt = t;
      sceneDone(g, t - doneAt);
    } else if (state.focus === 'user' || (state.focus === null && fields.username && fields.username.value && state.mode === 'register')) sceneName(g, t);
    else if (state.focus === 'mail') sceneMail(g);
    else if (state.focus === 'pass') scenePass(g);
    else if (state.mode === 'reset') sceneMail(g);
    else sceneRadar(g, t);
    if (!state.done && state.mode !== 'verified') doneAt = null;
    var inMail = state.focus === 'mail' || (state.mode === 'reset' && !state.done && state.focus !== 'pass');
    if (!inMail) state.mailX = null;
    P.paint(c, g, { px: TW.fit(panel, 128, 6, 2), gap: 1, off: pal.off, glow: 0.6 });
  }

  /* 12 Bilder pro Sekunde, als Stilentscheidung: das Panel springt sichtbar. */
  function loop(now) {
    if (now - last > 83) {
      last = now;
      var t = state.running ? (now - t0) / 1000 : frozenT;
      drawPanel(t);
    }
    requestAnimationFrame(loop);
  }

  function renderMotion() {
    if (!motionBtn) return;
    motionBtn.hidden = TW.reduce;
    motionBtn.setAttribute('data-running', state.running ? 'true' : 'false');
    TW.text(motionLabel, state.running ? 'Pause motion' : 'Resume motion', state.running ? 'Bewegung anhalten' : 'Bewegung starten');
  }
  if (motionBtn) {
    motionBtn.addEventListener('click', function () {
      state.running = !state.running;
      if (state.running) t0 = performance.now() - frozenT * 1000;
      else frozenT = (performance.now() - t0) / 1000;
      renderMotion();
    });
  }

  TW.onLang(function () { renderMotion(); });

  /* Token aus der Adresse entfernen, sobald die Seite ihn nicht mehr braucht. */
  if (data.strip) {
    try { history.replaceState(null, '', '/account' + (state.mode !== 'login' ? '?mode=' + state.mode : '')); } catch (e) { /* egal */ }
  }

  renderMeter();
  renderMotion();
  requestAnimationFrame(loop);

  if (data.errors && Object.keys(data.errors).length) {
    var order = ['username', 'email', 'password', 'password2'];
    for (var i = 0; i < order.length; i++) {
      if (data.errors[order[i]] && fields[order[i]]) { fields[order[i]].focus(); break; }
    }
  }
})();
