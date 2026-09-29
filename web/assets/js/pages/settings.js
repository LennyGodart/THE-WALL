/* Einstellungen: Name und Zeitzone speichern sofort, Schluessel kopieren und
   erneuern, Freigaben, Passwort, Datenexport, Loeschen. Sicherheitsabfragen
   bekommen beim Oeffnen den Fokus, sonst bemerkt man sie mit Tastatur nicht. */
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

  function openDialog(dialog, opener) {
    dialog.hidden = false;
    dialog.focus();
    dialog._opener = opener;
  }
  function closeDialog(dialog) {
    dialog.hidden = true;
    if (dialog._opener) dialog._opener.focus();
  }

  /* ---------- Name und Zeitzone ---------- */
  var nameInput = $('[data-name]');
  if (nameInput && D.id) {
    var lastName = nameInput.value;
    var saveName = function () {
      var v = nameInput.value.trim();
      if (v === lastName) return;
      TW.api('/api/device/' + D.id + '/settings', { name: v }).then(function (res) {
        if (!res.ok) { fail($('[data-name-status]'), res); nameInput.value = lastName; return; }
        lastName = v;
        $$('[data-device-name]').forEach(function (el) { el.textContent = v; });
        TW.flash($('[data-name-status]'), 'Saved.', 'Gespeichert.', 2600, GREEN);
      });
    };
    nameInput.addEventListener('change', saveName);
    nameInput.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); saveName(); } });
  }
  var tz = $('[data-tz]');
  if (tz && D.id) {
    tz.addEventListener('change', function () {
      TW.api('/api/device/' + D.id + '/settings', { tz: tz.value }).then(function (res) {
        if (!res.ok) { fail($('[data-tz-status]'), res); return; }
        TW.flash($('[data-tz-status]'), 'Saved.', 'Gespeichert.', 2600, GREEN);
      });
    });
  }

  /* ---------- Firmware ---------- */
  var upd = $('[data-update]');
  if (upd && D.id) {
    var updating = false;
    upd.addEventListener('click', function () {
      if (updating) return;
      updating = true;
      upd.setAttribute('aria-disabled', 'true');
      TW.api('/api/device/' + D.id + '/update', {}).then(function (res) {
        if (!res.ok) { updating = false; upd.setAttribute('aria-disabled', 'false'); fail($('[data-fw-status]'), res); return; }
        TW.flash($('[data-fw-status]'), 'Downloading at the next check-in, the panel stays on.', 'Wird beim nächsten Abruf geladen, das Panel bleibt an.', 5000, GREEN);
      });
    });
  }

  /* ---------- Schluessel ---------- */
  var keyInput = $('[data-key]');
  var keyStatus = $('[data-key-status]');
  $('[data-copy-key]').addEventListener('click', function () {
    var done = function () { TW.flash(keyStatus, 'Copied.', 'Kopiert.', 2600, GREEN); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(keyInput.value).then(done, function () { keyInput.select(); done(); });
    } else { keyInput.select(); done(); }
  });
  var rotateDialog = $('[data-rotate-dialog]');
  var askRotate = $('[data-ask-rotate]');
  askRotate.addEventListener('click', function () { TW.text(keyStatus, '', ''); openDialog(rotateDialog, askRotate); });
  $('[data-cancel-rotate]').addEventListener('click', function () { closeDialog(rotateDialog); });
  $('[data-do-rotate]').addEventListener('click', function () {
    TW.api('/api/account/key', {}).then(function (res) {
      closeDialog(rotateDialog);
      if (!res.ok) { fail(keyStatus, res); return; }
      keyInput.value = res.data.key;
      TW.flash(keyStatus, 'Rotated. Replace it on every device now.', 'Neu erzeugt. Jetzt an jedem Gerät ersetzen.', 5000, GREEN);
    });
  });

  /* ---------- Freigaben ---------- */
  var inviteForm = $('[data-invite-form]');
  if (inviteForm && D.id) {
    var inviteStatus = $('[data-invite-status]');
    var linkBox = $('[data-invite-link]');
    inviteForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var email = $('[data-invite-email]').value.trim();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
        TW.flash(inviteStatus, 'Please enter a complete email address.', 'Bitte eine vollständige E-Mail-Adresse.', 0, RED);
        $('[data-invite-email]').focus();
        return;
      }
      TW.api('/api/device/' + D.id + '/invite', { email: email }).then(function (res) {
        if (!res.ok) { fail(inviteStatus, res); return; }
        $('[data-invite-email]').value = '';
        if (res.data.mailed) {
          linkBox.hidden = true;
          TW.flash(inviteStatus, 'Invitation sent.', 'Einladung verschickt.', 3200, GREEN);
        } else {
          linkBox.hidden = false;
          linkBox.querySelector('input').value = res.data.link;
          TW.text(inviteStatus, 'Invitation created, but no mail went out. Pass this link on yourself, it works for seven days.', 'Einladung angelegt, aber es ging keine Mail raus. Gib den Link selbst weiter, er gilt sieben Tage.');
          inviteStatus.style.color = GREEN;
        }
      });
    });
    var copyInvite = $('[data-copy-invite]');
    copyInvite.addEventListener('click', function () {
      var input = linkBox.querySelector('input');
      if (navigator.clipboard) navigator.clipboard.writeText(input.value).catch(function () { input.select(); });
      else input.select();
      TW.flash(inviteStatus, 'Copied.', 'Kopiert.', 2600, GREEN);
    });

    $$('[data-rights]').forEach(function (b) {
      b.addEventListener('click', function () {
        var user = b.getAttribute('data-user'), rights = b.getAttribute('data-rights');
        TW.api('/api/device/' + D.id + '/rights', { user_id: Number(user), rights: rights }).then(function (res) {
          if (!res.ok) { fail(inviteStatus, res); return; }
          $$('[data-rights][data-user="' + user + '"]').forEach(function (x) {
            x.setAttribute('aria-pressed', x.getAttribute('data-rights') === rights ? 'true' : 'false');
          });
          TW.flash(inviteStatus, 'Saved.', 'Gespeichert.', 2600, GREEN);
        });
      });
    });
    $$('[data-unshare-user]').forEach(function (b) {
      b.addEventListener('click', function () {
        var user = b.getAttribute('data-unshare-user');
        TW.api('/api/device/' + D.id + '/unshare', { user_id: Number(user) }).then(function (res) {
          if (!res.ok) { fail(inviteStatus, res); return; }
          var row = $('[data-share-row="' + user + '"]');
          if (row) row.remove();
          TW.flash(inviteStatus, 'Access removed.', 'Zugang entzogen.', 2600, GREEN);
          $('[data-invite-email]').focus();
        });
      });
    });
    $$('[data-unshare-invite]').forEach(function (b) {
      b.addEventListener('click', function () {
        var inv = b.getAttribute('data-unshare-invite');
        TW.api('/api/device/' + D.id + '/unshare', { invite_id: Number(inv) }).then(function (res) {
          if (!res.ok) { fail(inviteStatus, res); return; }
          var row = $('[data-invite-row="' + inv + '"]');
          if (row) row.remove();
          TW.flash(inviteStatus, 'Invitation withdrawn.', 'Einladung zurückgezogen.', 2600, GREEN);
          $('[data-invite-email]').focus();
        });
      });
    });
  }

  /* ---------- Home Assistant ----------
     Die Geraete-ID fuer die Einrichtung von Hand kopieren, gekoppelte Instanzen trennen. Ein
     getrenntes Home Assistant bekommt beim naechsten Abruf 401 und bietet neu koppeln an. */
  var haStatus = $('[data-ha-status]');
  var uidCopy = $('[data-copy-uid]');
  if (uidCopy) {
    uidCopy.addEventListener('click', function () {
      var input = $('[data-ha-uid]');
      var done = function () { TW.flash(haStatus, 'Copied.', 'Kopiert.', 2200, GREEN); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(done, function () { input.select(); done(); });
      } else {
        input.select();
        done();
      }
    });
  }
  $$('[data-ha-unlink]').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-ha-unlink');
      TW.api('/api/device/' + D.id + '/ha/unlink', { link: Number(id) }).then(function (res) {
        if (!res.ok) { fail(haStatus, res); return; }
        var row = $('[data-ha-row="' + id + '"]');
        if (row) row.remove();
        var empty = $('[data-ha-empty]');
        if (empty && !$('[data-ha-row]')) empty.hidden = false;
        TW.flash(haStatus, 'Unpaired. That Home Assistant can no longer control the device.', 'Getrennt. Dieses Home Assistant kann das Gerät nicht mehr steuern.', 3200, GREEN);
        if (uidCopy) uidCopy.focus();
      });
    });
  });

  /* ---------- Passwort ---------- */
  var pwForm = $('[data-password-form]');
  var pwOpen = $('[data-open-password]');
  var pwStatus = $('[data-password-status]');
  function hint(id, en, de, isError) {
    var el = document.getElementById(id);
    TW.text(el, en, de);
    el.style.color = isError ? RED : '#8B949C';
  }
  function resetPw() {
    $('#pw-current').value = '';
    $('#pw-new').value = '';
    $('#pw-current').setAttribute('aria-invalid', 'false');
    $('#pw-new').setAttribute('aria-invalid', 'false');
    hint('pw-current-hint', '', '', false);
    hint('pw-new-hint', 'At least 6 characters, including at least one letter', 'Mindestens 6 Zeichen, davon mindestens ein Buchstabe', false);
  }
  pwOpen.addEventListener('click', function () {
    var open = pwForm.hidden;
    pwForm.hidden = !open;
    pwOpen.setAttribute('aria-expanded', open ? 'true' : 'false');
    resetPw();
    if (open) $('#pw-current').focus();
  });
  $('[data-close-password]').addEventListener('click', function () {
    pwForm.hidden = true;
    pwOpen.setAttribute('aria-expanded', 'false');
    pwOpen.focus();
  });
  pwForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var cur = $('#pw-current'), nw = $('#pw-new');
    resetPwErrors();
    if (!cur.value) { cur.setAttribute('aria-invalid', 'true'); hint('pw-current-hint', 'Please enter your password', 'Bitte dein Passwort eingeben', true); cur.focus(); return; }
    if (nw.value.length < 6) { nw.setAttribute('aria-invalid', 'true'); hint('pw-new-hint', 'At least 6 characters', 'Mindestens 6 Zeichen', true); nw.focus(); return; }
    if (!/\p{L}/u.test(nw.value)) { nw.setAttribute('aria-invalid', 'true'); hint('pw-new-hint', 'At least one letter', 'Mindestens ein Buchstabe', true); nw.focus(); return; }
    TW.api('/api/account/password', { current: cur.value, password: nw.value }).then(function (res) {
      if (!res.ok) {
        var e = TW.errorText(res);
        var field = res.data && res.data.field === 'password' ? nw : cur;
        field.setAttribute('aria-invalid', 'true');
        hint(field === nw ? 'pw-new-hint' : 'pw-current-hint', e[0], e[1], true);
        field.focus();
        return;
      }
      pwForm.hidden = true;
      pwOpen.setAttribute('aria-expanded', 'false');
      resetPw();
      TW.flash(pwStatus, 'Saved. Other sessions are signed out.', 'Gespeichert. Andere Sitzungen sind abgemeldet.', 5000, GREEN);
      pwOpen.focus();
    });
  });
  function resetPwErrors() {
    $('#pw-current').setAttribute('aria-invalid', 'false');
    $('#pw-new').setAttribute('aria-invalid', 'false');
    hint('pw-current-hint', '', '', false);
  }

  /* ---------- Daten ---------- */
  var exportLink = $('[data-export]');
  exportLink.addEventListener('click', function () {
    TW.flash($('[data-export-status]'), 'Building the file, the download starts shortly.', 'Datei wird erzeugt, der Download startet gleich.', 4000, GREEN);
  });
  var exportMail = $('[data-export-mail]');
  var mailing = false;
  exportMail.addEventListener('click', function () {
    if (mailing || exportMail.disabled) return;
    mailing = true;
    exportMail.setAttribute('aria-disabled', 'true');
    TW.api('/api/account/export-mail', {}).then(function (res) {
      mailing = false;
      exportMail.setAttribute('aria-disabled', 'false');
      if (!res.ok) { fail($('[data-export-status]'), res); return; }
      TW.flash($('[data-export-status]'), 'Building the file and sending it to your address.', 'Datei wird erzeugt und an deine Adresse geschickt.', 4000, GREEN);
    });
  });

  /* ---------- Entfernen und Loeschen ---------- */
  var removeDialog = $('[data-remove-dialog]');
  var askRemove = $('[data-ask-remove]');
  if (removeDialog && D.id) {
    askRemove.addEventListener('click', function () { openDialog(removeDialog, askRemove); });
    $('[data-cancel-remove]').addEventListener('click', function () { closeDialog(removeDialog); });
    $('[data-do-remove]').addEventListener('click', function () {
      TW.api('/api/device/' + D.id + '/remove', {}).then(function (res) {
        if (!res.ok) { closeDialog(removeDialog); fail($('[data-remove-status]'), res); return; }
        location.href = '/device';
      });
    });
  }

  var deleteDialog = $('[data-delete-dialog]');
  var askDelete = $('[data-ask-delete]');
  askDelete.addEventListener('click', function () {
    openDialog(deleteDialog, askDelete);
    setTimeout(function () { $('#del-pass').focus(); }, 0);
  });
  $('[data-cancel-delete]').addEventListener('click', function () { $('#del-pass').value = ''; closeDialog(deleteDialog); });
  deleteDialog.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var pass = $('#del-pass');
    if (!pass.value) { pass.setAttribute('aria-invalid', 'true'); pass.focus(); return; }
    TW.api('/api/account/delete', { password: pass.value }).then(function (res) {
      if (!res.ok) {
        pass.setAttribute('aria-invalid', 'true');
        fail($('[data-delete-status]'), res);
        pass.focus();
        return;
      }
      location.href = '/';
    });
  });

  [rotateDialog, removeDialog, deleteDialog].forEach(function (dlg) {
    if (!dlg) return;
    dlg.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') closeDialog(dlg); });
  });

  if (location.hash === '#key') {
    var key = document.getElementById('key');
    if (key) setTimeout(function () { key.scrollIntoView({ block: 'start' }); keyInput.focus(); }, 60);
  }
})();
