<?php
// Prueft Einstellungen und Rahmen eines Geraets ohne Netz: wann eine neue Notiz vor dem
// gewaehlten Modus steht und wann sie ihren Vorrang verliert (CHANGELOG 61).
// Aufruf aus dem Hauptverzeichnis:
//   TW_ENV=dev php tools/device-fixtures.php
// Exit-Code 1 bei einem Fehler.
declare(strict_types=1);
require __DIR__ . '/../web/.htapp/bootstrap.php';

$fail = 0;
function check(string $what, bool $ok): void
{
    global $fail;
    echo ($ok ? 'ok     ' : 'FEHLER ') . $what . "\n";
    if (!$ok) {
        $fail++;
    }
}

$t = 1790000000;
$s = device_defaults();
$s['applied'] = true;
$s['mode'] = 'notes';
$s['rotation'] = [];   // ein Modus ohne Wechsel, sonst zeigt das Panel die Rotation
$s['notes']['line1'] = 'Alles Gute';
$s['notes']['line2'] = 'zum Geburtstag';

// Wie lange eine Notiz vorn steht.
check('Vorrang: frisch geschrieben zehn Minuten', note_front_left(['note_at' => $t] + $s, $t) === NOTE_FRONT_SECONDS);
check('Vorrang: nach sechs Minuten noch vier', note_front_left(['note_at' => $t] + $s, $t + 360) === 240);
check('Vorrang: nach zehn Minuten vorbei', note_front_left(['note_at' => $t] + $s, $t + 600) === 0);
check('Vorrang: ohne Text keiner', note_front_left(['note_at' => $t, 'notes' => ['line1' => '', 'line2' => ' ']] + $s, $t) === 0);
check('Vorrang: ausgeblendet (0) keiner', note_front_left(['note_at' => 0] + $s, $t) === 0);

// Uebernehmen: neue Notiz setzt den Zeitpunkt, ein anderer Modus beendet den Vorrang.
$vorher = ['note_at' => 0, 'notes' => ['line1' => '', 'line2' => '', 'flash' => true]] + $s;
[$neu, $geaendert] = device_note_after_apply($s, $vorher, $t);
check('Uebernehmen: neue Notiz steht ab jetzt vorn', $geaendert && $neu['note_at'] === $t);
$mitNotiz = ['note_at' => $t] + $s;
[$uhr, $geaendert] = device_note_after_apply(['mode' => 'clock'] + $mitNotiz, $mitNotiz, $t + 30);
check('Uebernehmen: Wechsel auf die Uhr beendet den Vorrang sofort', !$geaendert && $uhr['note_at'] === 0 && note_front_left($uhr, $t + 30) === 0);
[$rot, $geaendert] = device_note_after_apply(['rotation' => ['flight', 'clock']] + $mitNotiz, $mitNotiz, $t + 30);
check('Uebernehmen: neue Rotation beendet den Vorrang ebenfalls', $rot['note_at'] === 0);
[$hell, $geaendert] = device_note_after_apply(['bright' => 90] + $mitNotiz, $mitNotiz, $t + 30);
check('Uebernehmen: nur Helligkeit laesst die Notiz eines Gastes vorn', $hell['note_at'] === $t);
[$leer, $geaendert] = device_note_after_apply(['notes' => ['line1' => '', 'line2' => '', 'flash' => true]] + $mitNotiz, $mitNotiz, $t + 30);
check('Uebernehmen: geleerte Notiz steht nicht mehr vorn', $geaendert && note_front_left($leer, $t + 30) === 0);

// Der Rahmen folgt dem: mit Vorrang die Notiz, danach der gewaehlte Modus.
$geraet = ['id' => 0, 'uid' => 'wall-test', 'name' => 'Test', 'note_rev' => 1, 'fw' => '0.2.0'];
$besitzer = ['id' => 987654, 'username' => 'test', 'lang' => 'de'];
$modus = static fn(array $e): string => (string) (frame_build($geraet, $e, $besitzer, false)['pages'][0]['mode'] ?? '');
check('Rahmen: Uhr gewaehlt, frische Notiz steht vorn', $modus(['mode' => 'clock', 'note_at' => time()] + $s) === 'notes');
check('Rahmen: nach dem Wechsel die Uhr', $modus(['mode' => 'clock', 'note_at' => 0] + $s) === 'clock');
check('Rahmen: die Vorschau zeigt immer den gewaehlten Modus', (string) (frame_build($geraet, ['mode' => 'clock', 'note_at' => time()] + $s, $besitzer, true)['pages'][0]['mode'] ?? '') === 'clock');

// Der Zaehler hinter den Grenzen (core/ratelimit.php), etwa eine Nominatim-Abfrage pro
// Sekunde fuer den ganzen Server: seit dem 26. September 2026 eine einzige Anweisung.
rl_reset('test:zaehler');
check('Zaehler: zwei erlaubt, der dritte nicht', rl_allow('test:zaehler', 2, 60) && rl_allow('test:zaehler', 2, 60) && !rl_allow('test:zaehler', 2, 60));
check('Zaehler: nachschauen zaehlt nicht mit', rl_blocked('test:zaehler', 2, 60) && rl_blocked('test:zaehler', 2, 60));
q('UPDATE rate_limits SET window_start = window_start - 61 WHERE k = ?', ['test:zaehler']);
check('Zaehler: nach dem Fenster wieder frei, von vorn gezaehlt', rl_allow('test:zaehler', 2, 60) && (int) qval('SELECT hits FROM rate_limits WHERE k = ?', ['test:zaehler']) === 1);
rl_reset('test:zaehler');

// Uhrzeit im Frame: die Mitte zwischen Anfrage und Antwort, nicht der Beginn des Rechnens.
check('Uhrzeit: eine Sekunde gerechnet, die Mitte', frame_clock_ms(1790000000.0, 1790000001.0) === 1790000000500);
check('Uhrzeit: ohne Rechenzeit dieselbe', frame_clock_ms(1790000000.25, 1790000000.25) === 1790000000250);

echo $fail === 0 ? "Alles gut\n" : $fail . " Fehler\n";
exit($fail === 0 ? 0 : 1);
