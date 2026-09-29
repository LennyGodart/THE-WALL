<?php
/*
 * Verzeichnis der Anzeigemodi. Jeder Modus liegt als eigene Datei in modes/
 * und meldet sich hier mit mode_register() an. Ein neuer Modus (Spotify,
 * Nahverkehr, Boersenkurse) ist eine neue Datei dort, sonst nichts: die
 * Firmware kennt nur Zeichenbefehle und muss nie neu geflasht werden.
 *
 * Ein Modus bringt mit:
 *   order      Reihenfolge auf der Geraeteseite
 *   label      ['en' => ..., 'de' => ...]
 *   available  false, solange er erst nach dem 25. kommt
 *   rotatable  darf in die Rotation
 *   defaults   Einstellungen eines neuen Geraets
 *   sanitize   fn(array $eingabe, array $bisher): array  bereinigte Einstellungen
 *   build      fn(array $ctx, float $von, float $bis): array  Seiten mit Zeichenbefehlen
 *   enter      Uebergang, wenn der Modus in der Rotation drankommt: push (Vorgabe) oder drop
 *   active     fn(array $ctx): bool, optional. Hat der Modus gerade etwas zu zeigen? Ohne
 *              faellt er aus der Rotation, etwa Spotify ohne Musik
 *   corner     fn(array $ctx): array, optional. Wo die Ecke eines laufenden Timers sitzt
 *              (box, right, y wie CORNER_SPOT), ohne Angabe oben rechts
 *
 * Laeuft ein Timer, steht er in $ctx['corner']. Ein Modus, der oben rechts selbst etwas
 * zeigt, macht dann dort Platz (Flug, Wetter, Nahverkehr), sonst liegt die Ecke darueber.
 *
 * $ctx: settings, device, owner, lang, preview, now. Zeiten in Sekunden (float).
 */

declare(strict_types=1);

function mode_register(string $id, array $def): void
{
    $GLOBALS['TW_MODES'][$id] = $def + [
        'order' => 99,
        'label' => ['en' => $id, 'de' => $id],
        'available' => true,
        'rotatable' => false,
        'defaults' => [],
        'sanitize' => static fn(array $in, array $current): array => $current,
        'build' => static fn(array $ctx, float $from, float $to): array => [],
        'enter' => 'push',
    ];
    uasort($GLOBALS['TW_MODES'], static fn(array $a, array $b): int => $a['order'] <=> $b['order']);
}

function modes(): array
{
    return $GLOBALS['TW_MODES'] ?? [];
}

function mode_get(string $id): ?array
{
    return modes()[$id] ?? null;
}

/** Hat der Modus gerade etwas zu zeigen? Ohne active-Funktion immer. */
function mode_is_active(string $id, array $ctx): bool
{
    $m = mode_get($id);
    if ($m === null) {
        return false;
    }
    $fn = $m['active'] ?? null;
    return $fn === null ? true : (bool) $fn($ctx);
}
