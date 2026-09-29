/* Laeuft im Kopf, bevor die Seite erscheint. Wer Deutsch gewaehlt hat, sieht so
   keinen englischen Zwischenstand. ui.js tauscht die Texte und gibt die Seite frei,
   spaetestens nach anderthalb Sekunden wird sie auch ohne ui.js sichtbar. */
(function () {
  try {
    if (localStorage.getItem('tw-lang') === 'de') {
      var root = document.documentElement;
      root.lang = 'de';
      root.classList.add('tw-de-pending');
      setTimeout(function () { root.classList.remove('tw-de-pending'); }, 1500);
    }
  } catch (e) { /* kein Speicher, dann bleibt es Englisch */ }
})();
