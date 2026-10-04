<?php
// Prueft Einstellungen und Rahmen eines Geraets ohne Netz: wann eine neue Notiz vor dem
// gewaehlten Modus steht und wann sie ihren Vorrang verliert (CHANGELOG 61), und in welchem
// Takt die Rotation wechselt (CHANGELOG 71).
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

// Takt der Rotation (cycle): bis zum 4. Oktober 2026 fest 30 Sekunden, seitdem 10 bis 600.
check('Takt: Vorgabe 30 Sekunden, auch fuer alte Einstellungen ohne cycle', device_defaults()['cycle'] === 30 && frame_cycle([]) === 30 && frame_cycle(['cycle' => null]) === 30);
$takt = static fn(mixed $wert, array $vorher = [], string $rolle = 'owner'): int => (int) device_sanitize(['cycle' => $wert], $vorher + $s, $rolle)[0]['cycle'];
check('Takt: unter 10 wird 10, ueber 600 wird 600', $takt(5) === 10 && $takt(3600) === 600);
check('Takt: 45 bleibt 45, auch als Text, 25 zwischen zwei Stufen ebenso', $takt('45') === 45 && $takt(25) === 25);
check('Takt: Unsinn laesst den bisherigen Wert', $takt('oft', ['cycle' => 90]) === 90 && $takt(null, ['cycle' => 90]) === 90);
check('Takt: Gaeste mit Leserecht aendern ihn nicht', $takt(10, [], 'view') === 30);
[$neu, $geaendert] = device_note_after_apply(['cycle' => 60] + $mitNotiz, $mitNotiz, $t + 30);
check('Uebernehmen: ein anderer Takt beendet den Vorrang der Notiz', !$geaendert && $neu['note_at'] === 0);

// Der Rahmen wechselt im eingestellten Takt, an der Uhr ausgerichtet. Uhr und Nahverkehr ohne
// Haltestelle brauchen dafuer kein Netz.
$wechsel = static function (int $sekunden) use ($geraet, $besitzer, $s): array {
    $e = ['mode' => 'clock', 'rotation' => ['clock', 'transit'], 'cycle' => $sekunden, 'note_at' => 0] + $s;
    return array_values(array_filter(frame_build($geraet, $e, $besitzer, false)['pages'], static fn(array $p): bool => isset($p['fx'])));
};
$w = $wechsel(10);
check('Rahmen: bei 10 Sekunden zwei oder drei Wechsel in 26 Sekunden', count($w) >= 2 && count($w) <= 3);
check('Rahmen: jeder Wechsel auf einer vollen Zehnersekunde', !array_filter($w, static fn(array $p): bool => $p['from'] % 10000 !== 0));
check('Rahmen: Uhr und Nahverkehr wechseln sich ab, der Nahverkehr faellt ein', count($w) >= 2 && $w[0]['mode'] !== $w[1]['mode'] && in_array('drop', array_column($w, 'fx'), true));
$w = $wechsel(600);
check('Rahmen: bei zehn Minuten hoechstens ein Wechsel, auf einer vollen Zehnminute', count($w) <= 1 && !array_filter($w, static fn(array $p): bool => $p['from'] % 600000 !== 0));

// Der Zaehler hinter den Grenzen (core/ratelimit.php), etwa eine Nominatim-Abfrage pro
// Sekunde fuer den ganzen Server: seit dem 26. September 2026 eine einzige Anweisung.
rl_reset('test:zaehler');
check('Zaehler: zwei erlaubt, der dritte nicht', rl_allow('test:zaehler', 2, 60) && rl_allow('test:zaehler', 2, 60) && !rl_allow('test:zaehler', 2, 60));
check('Zaehler: nachschauen zaehlt nicht mit', rl_blocked('test:zaehler', 2, 60) && rl_blocked('test:zaehler', 2, 60));
q('UPDATE rate_limits SET window_start = window_start - 61 WHERE k = ?', ['test:zaehler']);
check('Zaehler: nach dem Fenster wieder frei, von vorn gezaehlt', rl_allow('test:zaehler', 2, 60) && (int) qval('SELECT hits FROM rate_limits WHERE k = ?', ['test:zaehler']) === 1);
rl_reset('test:zaehler');

// Segment-Uhr: die Geisterrahmen haben die Farbe der Uhr, ein Sechstel so hell (ab Firmware 0.2.2).
// Gerundet wie in device.js (Math.round): 255 / 6 = 42,5 wird 43.
check('Segment-Uhr: Bernstein bleibt dunkles Bernstein', clock_ghost('FFAA00') === '2B1C00');
check('Segment-Uhr: Cyan wird dunkles Cyan, Weiss dunkles Grau', clock_ghost('#35D6FF') === '09242B' && clock_ghost('FFFFFF') === '2B2B2B');
$op = op_clock(10, 20, 2, '35D6FF', true, false, true);
check('Segment-Uhr: der Befehl traegt g', ($op['g'] ?? '') === '09242B');
check('Andere Zifferblaetter ohne g', !isset(op_clock(10, 20, 2, '35D6FF', true, false, false)['g']));

// Uhrzeit im Frame: die Mitte zwischen Anfrage und Antwort, nicht der Beginn des Rechnens.
check('Uhrzeit: eine Sekunde gerechnet, die Mitte', frame_clock_ms(1790000000.0, 1790000001.0) === 1790000000500);
check('Uhrzeit: ohne Rechenzeit dieselbe', frame_clock_ms(1790000000.25, 1790000000.25) === 1790000000250);

echo $fail === 0 ? "Alles gut\n" : $fail . " Fehler\n";
exit($fail === 0 ? 0 : 1);
