<?php
/*
 * Zeitzonen. Gespeichert werden nur IANA-Namen wie Europe/Luxembourg, nie ein
 * fester Versatz: ein Versatz ist die halbe Jahreshaelfte falsch.
 *
 * Die Firmware kann mit IANA-Namen nichts anfangen. Der ESP32 rechnet Ortszeit
 * nur mit einer POSIX-Zeichenkette wie CET-1CEST,M3.5.0,M10.5.0/3. Der Server
 * leitet sie aus den Umstellungen der Zone ab und schickt sie mit.
 */

declare(strict_types=1);

/** Die Auswahl auf Geraeteseite und Einstellungen. Beide Seiten nutzen dieselbe Liste. */
function tz_choices(): array
{
    return [
        'Europe/Luxembourg' => 'Luxembourg',
        'Europe/London'     => 'London',
        'Europe/Helsinki'   => 'Helsinki',
        'America/New_York'  => 'New York',
    ];
}

/** Gueltig ist jeder Name, den die Zeitzonendatenbank von PHP kennt. */
function tz_valid(string $iana): bool
{
    static $known = null;
    if ($known === null) {
        $known = array_flip(DateTimeZone::listIdentifiers());
    }
    return isset($known[$iana]);
}

/**
 * POSIX-TZ-Zeichenkette aus den Umstellungen des kommenden Jahres.
 * Beispiele: Europe/Luxembourg -> CET-1CEST,M3.5.0,M10.5.0/3
 *            America/New_York  -> EST5EDT,M3.2.0,M11.1.0
 *            UTC               -> UTC0
 */
function tz_posix(string $iana, ?int $year = null): string
{
    if (!tz_valid($iana)) {
        return 'UTC0';
    }
    $zone = new DateTimeZone($iana);
    $year = $year ?? ((int) gmdate('Y') + 1);
    $from = gmmktime(0, 0, 0, 1, 1, $year);
    $to = gmmktime(0, 0, 0, 1, 1, $year + 1);
    $trans = $zone->getTransitions($from, $to);

    // Erster Eintrag ist der Zustand am 1. Januar, danach die echten Wechsel.
    $start = $trans[0];
    $changes = array_slice($trans, 1);

    if (count($changes) < 2) {
        // Keine Sommerzeit in diesem Jahr: nur Name und Versatz.
        return tz_posix_name($start['abbr'], (int) $start['offset']) . tz_posix_offset((int) $start['offset']);
    }

    $std = null;
    $dst = null;
    $toDst = null;
    $toStd = null;
    foreach ($changes as $c) {
        if ($c['isdst'] && $toDst === null) {
            $toDst = $c;
            $dst = $c;
        } elseif (!$c['isdst'] && $toStd === null) {
            $toStd = $c;
            $std = $c;
        }
    }
    if ($std === null || $dst === null) {
        return tz_posix_name($start['abbr'], (int) $start['offset']) . tz_posix_offset((int) $start['offset']);
    }

    $out = tz_posix_name($std['abbr'], (int) $std['offset']) . tz_posix_offset((int) $std['offset']);
    $out .= tz_posix_name($dst['abbr'], (int) $dst['offset']);
    if ((int) $dst['offset'] - (int) $std['offset'] !== 3600) {
        $out .= tz_posix_offset((int) $dst['offset']);
    }
    // Die Umstellung nach Sommerzeit geschieht in Standardzeit, zurueck in Sommerzeit.
    $out .= ',' . tz_posix_rule((int) $toDst['ts'], (int) $std['offset']);
    $out .= ',' . tz_posix_rule((int) $toStd['ts'], (int) $dst['offset']);
    return $out;
}

/** POSIX kehrt das Vorzeichen um: UTC+1 heisst dort -1. */
function tz_posix_offset(int $seconds): string
{
    $s = -$seconds;
    $sign = $s < 0 ? '-' : '';
    $s = abs($s);
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    return $sign . $h . ($m ? ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
}

/** Buchstaben-Kuerzel bleiben, numerische wie +03 brauchen spitze Klammern. */
function tz_posix_name(string $abbr, int $offset): string
{
    if (preg_match('/^[A-Za-z]{3,}$/', $abbr)) {
        return $abbr;
    }
    $h = intdiv(abs($offset), 3600);
    $m = intdiv(abs($offset) % 3600, 60);
    $name = ($offset < 0 ? '-' : '+') . str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ($m ? str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
    return '<' . $name . '>';
}

/** Mm.w.d[/Uhrzeit], Woche 5 heisst "letzte im Monat". */
function tz_posix_rule(int $ts, int $offsetBefore): string
{
    $local = $ts + $offsetBefore;
    $month = (int) gmdate('n', $local);
    $day = (int) gmdate('j', $local);
    $weekday = (int) gmdate('w', $local);
    $daysInMonth = (int) gmdate('t', $local);
    $week = intdiv($day - 1, 7) + 1;
    if ($day + 7 > $daysInMonth) {
        $week = 5;
    }
    $secs = $local % 86400;
    $rule = 'M' . $month . '.' . $week . '.' . $weekday;
    if ($secs !== 7200) {
        $h = intdiv($secs, 3600);
        $m = intdiv($secs % 3600, 60);
        $rule .= '/' . $h . ($m ? ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
    }
    return $rule;
}

/** Datum in einer Zone: 26.09.2026 auf Deutsch, 26 Sep 2026 auf Englisch. */
function local_date_text(string $tz, int $ts, string $lang): string
{
    $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(tz_valid($tz) ? $tz : 'UTC'));
    return $lang === 'de' ? $dt->format('d.m.Y') : $dt->format('j M Y');
}
