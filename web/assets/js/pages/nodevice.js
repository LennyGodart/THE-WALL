/* Seite "Noch kein Geraet": fragt alle vier Sekunden, ob das erste Geraet aufgetaucht ist,
   und wechselt dann auf die Geraeteseite, wo sich die Einfuehrung von selbst oeffnet.
   Im Hintergrund wird nicht gefragt, nach einer Stunde oder ohne Anmeldung hoert es auf. */
(function () {
  'use strict';
  if (!window.TW || !TW.data().waiting) return;
  var started = Date.now();
  var busy = false;
  var timer = setInterval(check, 4000);

  function stop() { clearInterval(timer); timer = null; }

  function check() {
    if (!timer) return;
    if (Date.now() - started > 3600 * 1000) { stop(); return; }
    if (document.hidden || busy) return;
    busy = true;
    TW.api('/api/devices').then(function (res) {
      busy = false;
      if (res.status === 401) { stop(); return; }
      if (res.ok && res.data.devices && res.data.devices.length) {
        stop();
        location.href = '/device';
      }
    });
  }

  document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
})();
