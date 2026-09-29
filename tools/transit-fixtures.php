<?php
// Prueft das Auslesen der ATP-OpenAPI (mobiliteit.lu) gegen dokumentierte und echte
// Antwortformen, ohne Netz und ohne Schluessel. Aufruf aus dem Hauptverzeichnis:
//   TW_ENV=dev php tools/transit-fixtures.php
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

// Einzelne Abfahrt als Objekt, Product als Objekt, mit Echtzeit und Notiz.
$bus = json_decode('{
  "JourneyDetailRef": {"ref": "1|3078|7|82|29012023"},
  "JourneyStatus": "P",
  "Product": {"name": "Bus 812", "num": "3398", "line": "812", "catOut": "Bus", "catIn": "064", "catCode": "5", "cls": "32",
    "catOutS": "064", "catOutL": "Bus", "operatorCode": "RGT", "operator": "Régime Général des Transports Routiers", "admin": "RGTR--",
    "icon": {"res": "prod_bus_t", "foregroundColor": {"r": 255, "g": 255, "b": 255, "hex": "#FFFFFF"}, "backgroundColor": {"r": 117, "g": 40, "b": 100, "hex": "#752864"}}},
  "Notes": {"Note": [{"value": "RGTR", "key": "OPERATOR", "type": "A", "txtN": "RGTR"}, {"value": "Retard", "key": "text.realtime.journey", "type": "R", "txtN": "Retard suite à un incident"}]},
  "name": "Bus 812", "type": "ST", "stop": "Steinfort, Gemeng", "stopExtId": "191104004", "prognosisType": "PROGNOSED",
  "time": "15:33:00", "date": "2023-01-29", "rtTime": "15:35:00", "rtDate": "2023-01-29", "reachable": true,
  "direction": "Eischen, Denn Mairie", "trainNumber": "812", "trainCategory": "064"
}', true);
$d = transit_normalize_departure($bus);
check('Bus: mode bus', $d['mode'] === 'bus');
check('Bus: label', $d['label'] === 'Bus 812' && $d['line'] === '812');
check('Bus: operator RGT', $d['operator'] === 'RGT');
check('Bus: planned in Ortszeit', $d['planned'] === '2023-01-29T15:33:00+01:00');
check('Bus: realtime und 2 Minuten spaeter', $d['realtime'] === '2023-01-29T15:35:00+01:00' && $d['delay_min'] === 2);
check('Bus: Linienfarbe', $d['colour'] === '#752864' && $d['text_colour'] === '#FFFFFF');
check('Bus: nur R- und I-Notizen', count($d['notes']) === 1 && $d['notes'][0]['type'] === 'R');
check('Bus: Ziel geteilt', $d['direction_split'] === ['place' => 'Eischen', 'stop' => 'Denn Mairie']);
check('Bus: nicht ausgefallen', $d['cancelled'] === false);

// Zug ohne Echtzeit, Product als Liste, ausgefallen, mit Gleis; Sommerzeit.
$train = json_decode('{"Product": [{"name": "RE 3712", "num": "3712", "catOut": "RE", "cls": "4", "catOutL": "Regional-Express", "operatorCode": "CFL"}],
  "time": "07:12:00", "date": "2026-09-14", "direction": "Troisvierges, Gare", "track": "3", "rtTrack": "4", "cancelled": true, "JourneyStatus": "P"}', true);
$t = transit_normalize_departure($train);
check('Zug: mode train', $t['mode'] === 'train');
check('Zug: label aus Product-Liste', $t['label'] === 'RE 3712' && $t['category'] === 'RE');
check('Zug: Sommerzeit +02:00', $t['planned'] === '2026-09-14T07:12:00+02:00');
check('Zug: ohne Echtzeit keine Verspaetung', $t['realtime'] === null && $t['delay_min'] === null);
check('Zug: Gleis und Echtzeit-Gleis', $t['platform'] === '3' && $t['platform_realtime'] === '4');
check('Zug: ausgefallen', $t['cancelled'] === true);

// Tram ueber catOut ohne cls.
$tram = transit_normalize_departure(['Product' => ['name' => 'Tram 1', 'line' => '1', 'catOut' => 'Tram'], 'time' => '10:00:00', 'date' => '2026-09-14', 'direction' => 'Luxexpo']);
check('Tram: mode tram ueber catOut', $tram['mode'] === 'tram');
check('Tram: Ziel ohne Komma', $tram['direction_split'] === ['place' => '', 'stop' => 'Luxexpo']);

// Haltestelle aus nearbystops.
$stop = transit_normalize_stop(json_decode('{"id": "A=1@O=Luxembourg, Gare Centrale@X=6134239@Y=49599969@U=82@L=200405060@", "extId": "200405060",
  "name": "Luxembourg, Gare Centrale", "lon": 6.134239, "lat": 49.599969, "weight": 9000, "dist": 12, "products": 293,
  "productAtStop": [{"name": "Bus 16", "line": "16", "cls": "32", "catOut": "Bus", "catOutL": "Bus", "icon": {"backgroundColor": {"hex": "#e30613"}, "foregroundColor": {"hex": "#ffffff"}}},
    {"name": "Bus 16", "line": "16", "cls": "32"}, {"name": "RE", "line": "", "cls": "4", "catOut": "RE"}, {"name": "Tram 1", "line": "1", "cls": "256"}]}', true));
check('Halt: id ist extId', $stop['id'] === '200405060');
check('Halt: Ort und Haltestelle', $stop['place'] === 'Luxembourg' && $stop['stop'] === 'Gare Centrale');
check('Halt: Verkehrsmittel aus 293 = 1+4+32+256', $stop['modes'] === ['train', 'bus', 'tram']);
check('Halt: Linien ohne Doppelte, Zug ohne Linie ueber name', count($stop['lines']) === 3);
check('Halt: Farbe gross geschrieben', $stop['lines'][0]['colour'] === '#E30613');

// Echte Rohabfahrt vom 14. September 2026 (Steinfort, Bus 812): Steig ausgeblendet, Merkmal accessible.
$real = json_decode('{"JourneyStatus":"P","Product":[{"name":"Bus 812","num":"19354","line":"812","catOut":"Bus","cls":"32","catOutL":"Bus","operatorCode":"RGT","icon":{"foregroundColor":{"hex":"#FFFFFF"},"backgroundColor":{"hex":"#752864"}}}],
  "Notes":{"Note":[{"value":"RGTR","key":"OPERATOR","type":"A","txtN":"RGTR"},{"value":"accessible","key":"aa","type":"A","txtN":"accessible"}]},
  "platform":{"type":"ST","text":"2","hidden":true},"name":"Bus 812","time":"13:06:00","date":"2026-09-14","track":"2","trackHidden":true,
  "rtTime":"13:21:00","rtDate":"2026-09-14","direction":"Eischen, CIPA HPPA"}', true);
$rd = transit_normalize_departure($real);
check('Echt: Steig ausgeblendet', $rd['platform_hidden'] === true && $rd['platform'] === '2');
check('Echt: Merkmal accessible, ohne Betreiber', $rd['attributes'] === ['accessible']);
check('Echt: 15 Minuten spaeter', $rd['delay_min'] === 15);
check('Zug: Gleis nicht ausgeblendet', $t['platform_hidden'] === false);
check('Platform als Text statt Objekt wirft nicht', transit_normalize_departure(['platform' => '3', 'time' => '10:00:00', 'date' => '2026-09-14'])['platform_hidden'] === false);

// Fahrtstatus und Aenderungen, Formen vom 14. September 2026 13:40 (Bus 20 Zusatzfahrt, Bus 16 mit geaenderter Planzeit, Bus 18 geaendert).
$extra = transit_normalize_departure(['JourneyStatus' => 'A', 'Product' => [['name' => 'Bus 20', 'line' => '20', 'cls' => '32']], 'time' => '12:35:00', 'date' => '2026-09-14', 'rtTime' => '13:43:00', 'rtDate' => '2026-09-14', 'direction' => 'Merl, Marcel Cahen']);
check('Status A: Zusatzfahrt, 68 Minuten', $extra['additional'] === true && $extra['replacement'] === false && $extra['delay_min'] === 68);
check('Status R: Ersatzverkehr', transit_normalize_departure(['JourneyStatus' => 'R', 'time' => '10:00:00', 'date' => '2026-09-14'])['replacement'] === true);
$changed = transit_normalize_departure(['JourneyStatus' => 'P', 'redirected' => true, 'scheduledTimeChanged' => true, 'time' => '13:36:00', 'date' => '2026-09-14']);
check('redirected und scheduledTimeChanged', $changed['redirected'] === true && $changed['time_changed'] === true && $changed['additional'] === false);
check('Ohne Angaben alles false', $rd['redirected'] === false && $rd['time_changed'] === false && $rd['additional'] === false && $rd['replacement'] === false);
$flagStats = transit_stats([['name' => 'X', 'departures' => [$extra, $changed, $rd]]]);
check('Kennzahlen: Zusatzfahrt, geaendert, Zeit geaendert je 1', $flagStats['additional'] === 1 && $flagStats['redirected'] === 1 && $flagStats['time_changed'] === 1 && $flagStats['replacement'] === 0);

// Gewicht: der Bahnhof vor dem naeheren kleinen Halt.
$main = transit_main_stops([
    ['id' => '1', 'name' => 'Klein', 'weight' => 100, 'dist_m' => 10],
    ['id' => '2', 'name' => 'Bahnhof', 'weight' => 9000, 'dist_m' => 120],
    ['id' => '3', 'name' => 'Auch gross', 'weight' => 9000, 'dist_m' => 60],
]);
check('Wichtigste Haltestelle zuerst, bei Gleichstand die naechste', $main[0]['id'] === '3' && $main[1]['id'] === '2' && $main[2]['id'] === '1');
check('Halt: Gewicht uebernommen', $stop['weight'] === 9000);

// Gruppierung nach Hauptmast, Form wie am 14. September 2026 mit type=SE an Gare Centrale.
$grouped = transit_group_stops([
    ['StopLocation' => ['name' => 'Luxembourg, Gare Centrale', 'extId' => '300031019', 'dist' => 8, 'weight' => 32767, 'products' => 7, 'mainMastExtId' => '200405060']],
    ['StopLocation' => ['name' => 'Luxembourg, Gare Centrale (Tram)', 'extId' => '300030002', 'dist' => 51, 'weight' => 1068, 'products' => 256, 'mainMastExtId' => '200405059', 'productAtStop' => [['name' => 'T1', 'line' => 'T1', 'cls' => '256']]]],
    ['StopLocation' => ['name' => 'Luxembourg, Gare Centrale (Tram)', 'extId' => '300030001', 'dist' => 64, 'weight' => 1068, 'products' => 256, 'mainMastExtId' => '200405059', 'productAtStop' => [['name' => 'T1', 'line' => 'T1', 'cls' => '256']]]],
    ['StopLocation' => ['name' => 'Luxembourg, Gare Centrale routière', 'extId' => '300034001', 'dist' => 66, 'weight' => 2732, 'products' => 32, 'mainMastExtId' => '200405036']],
    ['StopLocation' => ['name' => 'Steinfort, Gemeng', 'extId' => '191104004', 'dist' => 3, 'weight' => 2732, 'products' => 32]],
    ['CoordLocation' => ['name' => 'irgendwas']],
]);
check('Gruppen: vier Haltestellen aus sechs Eintraegen', count($grouped) === 4);
check('Gruppen: nach Entfernung', array_column($grouped, 'id') === ['191104004', '200405060', '200405059', '200405036']);
check('Gruppen: Tram zusammengefasst, naechster Steig, eine Linie', $grouped[2]['dist_m'] === 51 && count($grouped[2]['lines']) === 1);
check('Gruppen: Hauptmast-ID fuer Abfahrten', $grouped[1]['id'] === '200405060' && $grouped[1]['modes'] === ['train']);
check('Gruppen: wichtigste ist der Bahnhof', transit_main_stops($grouped)[0]['id'] === '200405060');

// Listen-Helfer und Kennzahlen.
check('transit_list Objekt', count(transit_list(['a' => 1])) === 1);
check('transit_list Liste', count(transit_list([['a' => 1], ['a' => 2]])) === 2);
check('transit_list leer', transit_list(null) === [] && transit_list([]) === []);
$stats = transit_stats([['name' => 'Luxembourg, Gare Centrale', 'departures' => [$d, $t, $tram]]]);
check('Kennzahlen: 3 Abfahrten je Art', $stats['departures'] === 3 && $stats['by_mode'] === ['bus' => 1, 'train' => 1, 'tram' => 1]);
check('Kennzahlen: laengstes Ziel', $stats['longest']['direction']['text'] === 'Eischen, Denn Mairie');
check('Kennzahlen: 1 verspaetet, 1 ausgefallen', $stats['delayed_1min_or_more'] === 1 && $stats['cancelled'] === 1);

// Ohne Schluessel fragt der Server nicht. Liegt in der lokalen Datenbank einer, wuerde der Test wirklich fragen.
if (!transit_configured()) {
    $r = transit_call('departureBoard', ['id' => '200405060']);
    check('Ohne Schluessel: NO_KEY, keine Abfrage', $r['ok'] === false && $r['code'] === 'NO_KEY');
}
$r = transit_raw('location.search', []);
check('Rohabfrage: fremder Endpunkt abgelehnt', $r['ok'] === false && $r['code'] === 'BAD_ENDPOINT');
$r = transit_raw('journeyDetail', ['id' => 'x']);
check('Rohabfrage: journeyDetail gibt es bei der ATP nicht, abgelehnt', $r['ok'] === false && $r['code'] === 'BAD_ENDPOINT');
$r = transit_raw('departureBoard', ['accessId' => 'x']);
check('Rohabfrage: eigener Schluessel als Parameter abgelehnt', $r['ok'] === false && $r['code'] === 'BAD_PARAM');
$r = transit_departures('abc');
check('Ungueltige Haltestellen-ID', $r['ok'] === false && $r['code'] === 'BAD_STOP');

echo $fail === 0 ? "alles ok\n" : "$fail Fehler\n";
exit($fail === 0 ? 0 : 1);
