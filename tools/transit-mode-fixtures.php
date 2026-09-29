<?php
// Prueft den Nahverkehrsmodus (modes/transit.php) gegen die Werte aus dem Entwurf Transit.dc.html,
// ohne Netz: Spalten von rechts, Farben, Zeichen, Filter, Streifen, Stoerung, Einstellungen.
// Aufruf aus dem Hauptverzeichnis:
//   TW_ENV=dev php tools/transit-mode-fixtures.php
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

// 14. September 2026, 13:46:18 in Luxemburg, der Moment der Probe.
$now = (new DateTimeImmutable('2026-09-14 13:46:18', new DateTimeZone('Europe/Luxembourg')))->getTimestamp();
$minute = intdiv($now, 60) * 60;
$at = static fn(int $min): string => transit_iso($minute + $min * 60);

function dep(array $o): array
{
    return array_replace([
        'mode' => 'bus', 'label' => 'Bus 10', 'line' => '10', 'category' => 'Bus',
        'direction' => 'Steinsel, Bourgaass', 'direction_split' => ['place' => 'Steinsel', 'stop' => 'Bourgaass'],
        'planned' => null, 'realtime' => null, 'delay_min' => 0,
        'platform' => null, 'platform_realtime' => null, 'platform_hidden' => true,
        'cancelled' => false, 'part_cancelled' => false, 'additional' => false, 'replacement' => false, 'notes' => [],
    ], $o);
}

function ctx(array $transit, string $lang = 'en', int $bright = 168): array
{
    $s = device_defaults();
    $s['transit'] = array_replace($s['transit'], $transit);
    // Ohne Nachtabsenkung, sonst hingen die gedimmten Toene von der Uhrzeit des Laufs ab.
    $s['clock']['night'] = false;
    $s['bright'] = $bright;
    return ['settings' => $s, 'device' => ['id' => 0], 'owner' => [], 'lang' => $lang, 'preview' => true];
}

function ok_board(array $deps, ?array $next = null): array
{
    return ['configured' => true, 'boards' => [['ok' => true, 'departures' => $deps] + ($next ? ['next' => $next] : [])]];
}

function find_op(array $ops, string $s, ?int $y = null): ?array
{
    foreach ($ops as $o) {
        if (($o['s'] ?? null) === $s && ($y === null || $o['y'] === $y)) {
            return $o;
        }
    }
    return null;
}

$bus = ['id' => '200405036', 'name' => 'Luxembourg, Gare Centrale routière', 'short' => '', 'modes' => ['bus'], 'lines' => []];
$train = ['id' => '200405060', 'name' => 'Luxembourg, Gare Centrale', 'short' => '', 'modes' => ['train'], 'lines' => []];
$tram = ['id' => '200405059', 'name' => 'Luxembourg, Gare Centrale (Tram)', 'short' => '', 'modes' => ['tram'], 'lines' => []];

// Kopf und eine Buszeile mit Minuten.
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([dep(['realtime' => $at(4), 'planned' => $at(4)])]), $now);
check('Kopf: Kurzname aus dem Namen, grau bei x 2, y 1', ($o = find_op($ops, 'GARE CENTRALE BUS')) !== null && $o['x'] === 2 && $o['y'] === 1 && $o['c'] === C_DIM);
check('Kopf: ein Quadrat 5x5 bei x 122 in Bernstein', in_array(op_rect(122, 2, 5, 5, C_ACCENT), $ops, true));
check('Kopf: Linie bei y 9', in_array(op_rect(0, 9, 128, 1, C_LINE), $ops, true));
check('Zeile: Minute 4 rechtsbuendig an 126', ($o = find_op($ops, '4', 12)) !== null && $o['x'] === 121 && $o['c'] === C_WHITE);
check('Zeile: Linie 10 bei x 2 in Bernstein', ($o = find_op($ops, '10', 12)) !== null && $o['x'] === 2 && $o['c'] === C_ACCENT);
check('Zeile: Ziel ohne Ort bei x 28', ($o = find_op($ops, 'BOURGAASS', 12)) !== null && $o['x'] === 28 && $o['c'] === C_WHITE);
check('Streifen: Linie bei y 55, Uhr bei x 2, y 56', in_array(op_rect(0, 55, 128, 1, C_LINE), $ops, true) && count(array_filter($ops, static fn($o) => $o['t'] === 'clock' && $o['x'] === 2 && $o['y'] === 56)) === 1);
check('Streifen: MOBILITEIT rechts bis 125, gedimmt', ($o = find_op($ops, 'MOBILITEIT', 56)) !== null && $o['x'] === 66 && $o['c'] === TRANSIT_DIM_TEXT);

// Ziel bekommt, was frei bleibt: 15 Zeichen mit Minuten, 11 mit Uhrzeit.
$long = dep(['realtime' => $at(4), 'direction' => 'Kirchberg, Rout Bréck - Pafendall (Bus)', 'direction_split' => ['place' => 'Kirchberg', 'stop' => 'Rout Bréck - Pafendall (Bus)']]);
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([$long]), $now);
check('Minuten: Ziel auf 15 Zeichen, ohne (Bus), ohne Akzent', find_op($ops, 'ROUT BRECK - PA', 12) !== null);
$ops = transit_ops(ctx(['stops' => [$bus], 'fmt' => 'clock']), ok_board([array_replace($long, ['delay_min' => 3])]), $now);
check('Uhrzeit: 13:50 bei x 97, verspaetet bernstein', ($o = find_op($ops, '13:50', 12)) !== null && $o['x'] === 97 && $o['c'] === C_ACCENT);
check('Uhrzeit: Ziel auf 11 Zeichen', find_op($ops, 'ROUT BRECK ', 12) !== null);

// Zug: Gattung mit drei Zeichen, Ort statt GARE, Gleis vor der Zeit.
$rb = dep(['mode' => 'train', 'label' => 'RB 5063', 'line' => 'RB', 'category' => 'RB', 'direction' => 'Athus, Gare', 'direction_split' => ['place' => 'Athus', 'stop' => 'Gare'],
    'realtime' => $at(4), 'platform' => '3 A-D', 'platform_realtime' => '3AD', 'platform_hidden' => false]);
$ops = transit_ops(ctx(['stops' => [$train]]), ok_board([$rb]), $now);
check('Zug: Gattung RB in Cyan', ($o = find_op($ops, 'RB', 12)) !== null && $o['c'] === C_CYAN);
check('Zug: Ziel ist der Ort, ab x 22', ($o = find_op($ops, 'ATHUS', 12)) !== null && $o['x'] === 22);
check('Zug: Gleis 3AD grau bei x 101, kein Wechsel', ($o = find_op($ops, '3AD', 12)) !== null && $o['x'] === 101 && $o['c'] === C_DIM);
$ops = transit_ops(ctx(['stops' => [$train]]), ok_board([array_replace($rb, ['platform' => '11', 'platform_realtime' => '12'])]), $now);
check('Zug: Gleiswechsel 11 auf 12 bernstein', ($o = find_op($ops, '12', 12)) !== null && $o['c'] === C_ACCENT);
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([dep(['realtime' => $at(4), 'platform' => 'E', 'platform_hidden' => false])]), $now);
check('Bus: kein Steig, auch wenn er nicht versteckt ist', find_op($ops, 'E', 12) === null);

// Zeichen.
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([dep(['realtime' => $at(4), 'cancelled' => true])]), $now);
check('Ausfall: X rot an 121, keine Zeit, Ziel rot', ($o = find_op($ops, 'X', 12)) !== null && $o['x'] === 121 && $o['c'] === C_RED && find_op($ops, '4', 12) === null && find_op($ops, 'BOURGAASS', 12)['c'] === C_RED);
$note = [['type' => 'I', 'text' => 'Hinweis'], ['type' => 'R', 'text' => "Annulations d'arrêts intermédiaires"]];
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([dep(['realtime' => $at(4), 'part_cancelled' => true, 'notes' => $note])]), $now);
check('Teilausfall: ! bernstein vor der Zeit, Zeit bleibt', ($o = find_op($ops, '!', 12)) !== null && $o['x'] === 113 && $o['c'] === C_ACCENT && find_op($ops, '4', 12) !== null);
$tick = array_values(array_filter($ops, static fn($o) => $o['t'] === 'ticker'));
check('Meldung: Laufschrift bei y 56, R vor I, umgeschrieben', count($tick) === 1 && $tick[0]['s'] === "ANNULATIONS D'ARRETS INTERMEDIAIRES" && $tick[0]['y'] === 56 && $tick[0]['v'] === 14);
// Die Meldung gehoert zur Zeile mit Zeichen, nicht zur ersten mit Hinweis (Befund B3).
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([
    dep(['realtime' => $at(2), 'notes' => [['type' => 'I', 'text' => 'Changement horaire possible']]]),
    dep(['realtime' => $at(6), 'part_cancelled' => true, 'notes' => [['type' => 'R', 'text' => 'Arret supprime']]]),
]), $now);
$tick = array_values(array_filter($ops, static fn($o) => $o['t'] === 'ticker'));
check('Meldung: vom Teilausfall in Zeile 2, nicht vom Hinweis in Zeile 1', count($tick) === 1 && $tick[0]['s'] === 'ARRET SUPPRIME');
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([
    dep(['realtime' => $at(2), 'notes' => [['type' => 'I', 'text' => 'Changement horaire possible']]]),
]), $now);
check('Meldung: Zeile ohne Zeichen bekommt keine Laufschrift', !array_filter($ops, static fn($o) => $o['t'] === 'ticker'));
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([
    dep(['realtime' => $at(2), 'additional' => true, 'notes' => [['type' => 'I', 'text' => 'Zusatz']]]),
    dep(['realtime' => $at(5), 'cancelled' => true, 'notes' => [['type' => 'R', 'text' => 'Ausfall']]]),
]), $now);
$tick = array_values(array_filter($ops, static fn($o) => $o['t'] === 'ticker'));
check('Meldung: Ausfall vor Zusatzfahrt', count($tick) === 1 && $tick[0]['s'] === 'AUSFALL');
$ops = transit_ops(ctx(['stops' => [$bus], 'notes' => false]), ok_board([dep(['realtime' => $at(4), 'part_cancelled' => true, 'notes' => $note])]), $now);
check('Meldungen aus: Uhr statt Laufschrift, Zeichen bleibt', !array_filter($ops, static fn($o) => $o['t'] === 'ticker') && find_op($ops, '!', 12) !== null);
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([dep(['realtime' => $at(4), 'additional' => true]), dep(['realtime' => $at(5), 'replacement' => true])]), $now);
check('Zusatzfahrt + gruen, Ersatzverkehr E cyan', find_op($ops, '+', 12)['c'] === C_GREEN && find_op($ops, 'E', 24)['c'] === C_CYAN);
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([dep(['realtime' => $at(4), 'redirected' => true])]), $now);
check('Geaenderte Fahrt: kein Zeichen', count(array_filter($ops, static fn($o) => ($o['y'] ?? 0) === 12)) === 3);

// Zeit und Filter.
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([dep(['realtime' => $at(0)]), dep(['realtime' => $at(-1)]), dep(['realtime' => $at(75)])]), $now);
check('Jetzt: NOW, schon weg: faellt raus', find_op($ops, 'NOW', 12) !== null && find_op($ops, '75', 24)['c'] === C_DIM && count(array_filter($ops, static fn($o) => ($o['s'] ?? '') === '10')) === 2);
$ops = transit_ops(ctx(['stops' => [$bus]], 'de'), ok_board([dep(['realtime' => $at(0)])]), $now);
check('Deutsch: JETZT', find_op($ops, 'JETZT', 12) !== null);
$ops = transit_ops(ctx(['stops' => [$bus], 'walk' => 4]), ok_board([dep(['realtime' => $at(3)]), dep(['realtime' => $at(6), 'line' => '22'])]), $now);
check('Fussweg 4: Abfahrt in 3 faellt raus, in 6 zeigt 2', find_op($ops, '10') === null && find_op($ops, '2', 12) !== null && find_op($ops, '22', 12) !== null);
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([dep(['realtime' => $at(2), 'line' => 'K01']), dep(['realtime' => $at(3)])]), $now);
check('Schulbusse aus: K01 fehlt', find_op($ops, 'K01') === null && find_op($ops, '10', 12) !== null);
$ops = transit_ops(ctx(['stops' => [$bus], 'school' => true]), ok_board([dep(['realtime' => $at(2), 'line' => 'K01'])]), $now);
check('Schulbusse an: K01 da', find_op($ops, 'K01', 12) !== null);
$ops = transit_ops(ctx(['stops' => [$bus], 'hide' => ['10']]), ok_board([dep(['realtime' => $at(2)]), dep(['realtime' => $at(3), 'line' => '22'])]), $now);
check('Ausgeblendete Linie 10 fehlt', find_op($ops, '10') === null && find_op($ops, '22', 12) !== null);
$ops = transit_ops(ctx(['stops' => [$bus], 'modes' => ['train']]), ok_board([dep(['realtime' => $at(2)])]), $now);
check('Bus abgewaehlt: keine Abfahrt, kein Quadrat', find_op($ops, 'KEINE ABFAHRTEN') === null && find_op($ops, 'NO DEPARTURES') !== null && !in_array(op_rect(122, 2, 5, 5, C_ACCENT), $ops, true));
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([dep(['realtime' => $at(9)]), dep(['realtime' => $at(2), 'line' => '22'])]), $now);
check('Nach Echtzeit sortiert', find_op($ops, '22', 12) !== null && find_op($ops, '10', 24) !== null);

// Zeilen: drei mit mehr Luft, fuenf ohne Streifen.
$five = array_map(static fn($m) => dep(['realtime' => $at($m)]), [1, 2, 3, 4, 5, 6]);
$ops = transit_ops(ctx(['stops' => [$bus], 'rows' => 3]), ok_board($five), $now);
check('3 Zeilen bei y 13, 27, 41', find_op($ops, '1', 13) !== null && find_op($ops, '2', 27) !== null && find_op($ops, '3', 41) !== null && find_op($ops, '4') === null);
$ops = transit_ops(ctx(['stops' => [$bus], 'rows' => 5]), ok_board($five), $now);
check('5 Zeilen bei y 12 bis 52, kein Streifen', find_op($ops, '5', 52) !== null && !in_array(op_rect(0, 55, 128, 1, C_LINE), $ops, true) && !array_filter($ops, static fn($o) => $o['t'] === 'clock'));

// Bahnhof: drei Haltestellen reihum, je Runde die frueheste zuerst.
$boards = ['configured' => true, 'boards' => [
    ['ok' => true, 'departures' => [$rb, array_replace($rb, ['realtime' => $at(17), 'category' => 'RE', 'line' => 'RE'])]],
    ['ok' => true, 'departures' => [dep(['mode' => 'tram', 'line' => 'T1', 'category' => 'Tram', 'realtime' => $at(3), 'direction_split' => ['place' => 'Kirchberg', 'stop' => 'Luxexpo']])]],
    ['ok' => true, 'departures' => [dep(['realtime' => $at(1)]), dep(['realtime' => $at(1), 'line' => '27']), dep(['realtime' => $at(2), 'line' => '23'])]],
]];
$ops = transit_ops(ctx(['stops' => [array_replace($train, ['short' => 'Gare Centrale']), $tram, $bus]]), $boards, $now);
$codes = array_map(static fn($y) => find_op(array_values(array_filter($ops, static fn($o) => ($o['x'] ?? -1) === 2 && ($o['y'] ?? -1) === $y)), (string) (array_values(array_filter($ops, static fn($o) => ($o['x'] ?? -1) === 2 && ($o['y'] ?? -1) === $y))[0]['s'] ?? ''), $y)['s'] ?? '', [12, 24, 36, 48]);
check('Bahnhof: Bus, Tram, Zug, dann wieder Bus', $codes === ['10', 'T1', 'RB', '27']);
check('Bahnhof: drei Quadrate bei 108, 115, 122 (Zug, Tram, Bus)', in_array(op_rect(108, 2, 5, 5, C_CYAN), $ops, true) && in_array(op_rect(115, 2, 5, 5, C_GREEN), $ops, true) && in_array(op_rect(122, 2, 5, 5, C_ACCENT), $ops, true));
check('Bahnhof: Kopf aus dem eigenen Namen, grossgeschrieben', find_op($ops, 'GARE CENTRALE', 1) !== null);
check('Bahnhof gemischt: Linie mit vier Zeichen, Zug-Gleis mit zwei', ($o = find_op($ops, 'ATHUS', 36)) !== null && $o['x'] === 28 && find_op($ops, '3A', 36) !== null);
$ops = transit_ops(ctx(['stops' => [array_replace($train, ['short' => 'Luxembourg Gare Centrale']), $tram, $bus]]), $boards, $now);
check('Kopf mit drei Quadraten: hoechstens 17 Zeichen', find_op($ops, 'LUXEMBOURG GARE C', 1) !== null);

// Leere Tafel, Stoerung, kein Schluessel, keine Haltestelle.
$next = dep(['realtime' => transit_iso($minute + 15 * 3600)]);
$ops = transit_ops(ctx(['stops' => [$bus]]), ok_board([], $next), $now);
check('Leer: NO DEPARTURES bei y 24, NEXT mit Uhrzeit bei y 38', find_op($ops, 'NO DEPARTURES', 24) !== null && find_op($ops, 'NEXT ' . local_time('Europe/Luxembourg', $minute + 15 * 3600), 38) !== null);
$ops = transit_ops(ctx(['stops' => [$bus]], 'de'), ok_board([]), $now);
check('Leer auf Deutsch ohne naechste Abfahrt', find_op($ops, 'KEINE ABFAHRTEN', 24) !== null && count(array_filter($ops, static fn($o) => ($o['y'] ?? 0) === 38)) === 0);
$stale = ['configured' => true, 'boards' => [['ok' => false, 'code' => 'NETWORK', 'stale' => ['departures' => [dep(['realtime' => $at(5)]), dep(['realtime' => $at(7), 'line' => '22']), dep(['realtime' => $at(9), 'line' => '23'])], 'fetched_at' => $at(-10)]]]];
$ops = transit_ops(ctx(['stops' => [$bus]]), $stale, $now);
check('Stoerung: zwei alte Zeilen gedimmt bei y 14 und 26', ($o = find_op($ops, '10', 14)) !== null && $o['c'] === TRANSIT_DIM_CODE && find_op($ops, 'BOURGAASS', 26)['c'] === TRANSIT_DIM_TEXT && find_op($ops, '23') === null);
$ops = transit_ops(ctx(['stops' => [$bus]], 'en', 50), $stale, $now);
check('Stoerung bei Helligkeit 50: hellere Toene, sonst verschwaenden sie', find_op($ops, '10', 14)['c'] === TRANSIT_DIM_CODE_NIGHT && find_op($ops, 'BOURGAASS', 26)['c'] === TRANSIT_DIM_TEXT_NIGHT);
check('Stoerung: NO DATA rot, AS OF 13:36, Balken unten', find_op($ops, 'NO DATA', 42)['c'] === C_RED && find_op($ops, 'AS OF 13:36', 52) !== null && in_array(op_rect(0, 61, 128, 2, TRANSIT_TROUBLE_BAR), $ops, true));
$ops = transit_ops(ctx(['stops' => [$bus]], 'de'), ['configured' => false, 'boards' => []], $now);
check('Kein Schluessel: KEIN SCHLUESSEL', find_op($ops, 'KEIN SCHLUESSEL', 42) !== null);
$ops = transit_ops(ctx(['stops' => []]), ['configured' => true, 'boards' => []], $now);
check('Keine Haltestelle: PICK A STOP mittig', ($o = find_op($ops, 'PICK A STOP')) !== null && $o['x'] === 'c');

// Seiten wechseln an der vollen Minute.
$pages = transit_build(ctx(['stops' => []]), $minute + 40.0, $minute + 66.0);
check('Seiten: Wechsel an der Minute', count($pages) === 2 && $pages[0]['to'] === ($minute + 60) * 1000 && $pages[1]['from'] === ($minute + 60) * 1000 && $pages[0]['id'] === 'transit');

// Einstellungen.
$in = ['stops' => [
    ['id' => '200405036', 'name' => "Gare\x07 routière", 'short' => str_repeat('A', 30), 'modes' => ['bus', 'plane'], 'lines' => [['code' => 'k01', 'mode' => 'bus'], ['code' => '10', 'mode' => 'ship']]],
    ['id' => '200405036', 'name' => 'doppelt'],
    ['id' => 'abc', 'name' => 'keine ID'],
    ['id' => '1001', 'name' => 'B'], ['id' => '1002', 'name' => 'C'], ['id' => '1003', 'name' => 'D'],
], 'modes' => ['tram', 'bus', 'x'], 'hide' => ['10', '10', ' t1 ', ['x']], 'rows' => 9, 'walk' => -3, 'fmt' => 'hours', 'notes' => 0, 'school' => 'ja'];
$out = transit_sanitize($in, device_defaults()['transit']);
check('Einstellungen: hoechstens drei Haltestellen, keine doppelt, keine ohne ID', array_column($out['stops'], 'id') === ['200405036', '1001', '1002']);
check('Einstellungen: Name ohne Steuerzeichen, Kurzname 18, Verkehrsmittel und Linien gefiltert', $out['stops'][0]['name'] === 'Gare routière' && strlen($out['stops'][0]['short']) === 18 && $out['stops'][0]['modes'] === ['bus'] && $out['stops'][0]['lines'] === [['code' => 'K01', 'mode' => 'bus']]);
check('Einstellungen: Verkehrsmittel in fester Reihenfolge, Linien einmal und gross', $out['modes'] === ['tram', 'bus'] && $out['hide'] === ['10', 'T1']);
check('Einstellungen: Zeilen 5, Fussweg 0, Format bleibt, Schalter', $out['rows'] === 5 && $out['walk'] === 0 && $out['fmt'] === 'min' && $out['notes'] === false && $out['school'] === true);

// Kurznamen und Linienkuerzel.
check('Kurzname: GARE CENTRALE BUS', transit_short_name(transit_split_name('Luxembourg, Gare Centrale routière')) === 'GARE CENTRALE BUS');
check('Kurzname: Busbahnhof mit Ort, ESCH/ALZETTE passt in 18', transit_short_name(transit_split_name('Esch-sur-Alzette, Gare routière')) === 'ESCH/ALZETTE BUS');
check('Kurzname: Tram ohne Zusatz', transit_short_name(transit_split_name('Luxembourg, Gare Centrale (Tram)')) === 'GARE CENTRALE');
check('Kurzname: Hotel de Ville ohne Akzent, 18 Zeichen', transit_short_name(transit_split_name("Esch-sur-Alzette, Place de l'Hôtel de Ville")) === "PLACE DE L'HOTEL D");
$codes = transit_stop_codes(['lines' => [['line' => '10', 'mode' => 'bus', 'category' => 'Bus'], ['line' => 'RE', 'mode' => 'train', 'category' => 'RE'], ['line' => 'T1', 'mode' => 'tram', 'category' => 'Tram'], ['line' => '10', 'mode' => 'bus', 'category' => 'Bus']]]);
check('Linienkuerzel: Zug, Tram, Bus, jede einmal', $codes === [['code' => 'RE', 'mode' => 'train'], ['code' => 'T1', 'mode' => 'tram'], ['code' => '10', 'mode' => 'bus']]);
check('Laufschrift als Befehl', op_ticker(2, 56, 'X', 'FFAA00') === ['t' => 'ticker', 'x' => 2, 'y' => 56, 's' => 'X', 'c' => 'FFAA00', 'v' => 14]);

// Einstieg in der Rotation (Befund A6): die Tafel faellt ein, frame_build() setzt fx aus
// enter, und jede Seite traegt die Oberkanten ihrer Zeilen, fuer das Einfallen und das Rollen.
check('Einstieg: Nahverkehr mit drop, andere Modi mit push', (mode_get('transit')['enter'] ?? '') === 'drop' && (mode_get('clock')['enter'] ?? '') === 'push');
check('Einstieg: Oberkanten der Zeilen bei 3, 4 und 5 Zeilen', transit_row_ys(3) === [13, 27, 41] && transit_row_ys(4) === [12, 24, 36, 48] && transit_row_ys(5) === [12, 22, 32, 42, 52]);

// Gleis mit geschuetztem Leerzeichen und Halbgeviertstrich: ASCII, und die Antwort laesst sich kodieren.
$odd = transit_panel_dep(['mode' => 'train', 'label' => 'RE 1', 'line' => 'RE', 'category' => 'RE', 'direction' => 'Troisvierges, Gare',
    'direction_split' => ['place' => 'Troisvierges', 'stop' => 'Gare'], 'realtime' => $at(4), 'planned' => $at(4),
    'platform' => "5\u{00A0}A\u{2013}D", 'platform_realtime' => "5\u{00A0}A\u{2013}D", 'platform_hidden' => false], 'Europe/Luxembourg');
check('Gleis mit Sonderzeichen wird 5AD', ($odd['plat'] ?? null) === '5AD');
$oddOps = transit_ops(ctx(['stops' => [$train]]), ok_board([dep(['mode' => 'train', 'realtime' => $at(4), 'platform' => "5\u{00A0}A\u{2013}D", 'platform_hidden' => false])]), $now);
check('Rahmen mit Sonderzeichen-Gleis laesst sich als JSON kodieren', json_encode($oddOps, JSON_THROW_ON_ERROR) !== '');

echo $fail === 0 ? "alles ok\n" : "$fail Fehler\n";
exit($fail === 0 ? 0 : 1);
