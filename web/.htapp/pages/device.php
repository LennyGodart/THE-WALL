<?php
/*
 * Geraeteseite. Modi links, Vorschau rechts, Einstellungen des gewaehlten Modus
 * darunter. Aenderungen bleiben ein Entwurf im Browser, bis "Uebernehmen"
 * gedrueckt wird. Die Vorschau fragt dafuer /api/preview mit dem Entwurf.
 */

declare(strict_types=1);

function page_device(array $params = []): void
{
    $user = require_user();
    $devices = devices_for_user((int) $user['id']);
    $device = null;
    if (isset($params['id'])) {
        foreach ($devices as $d) {
            if ((string) $d['id'] === (string) $params['id']) {
                $device = $d;
            }
        }
        if ($device === null) {
            throw new HttpError(404);
        }
    } elseif ($devices) {
        redirect('/device/' . (int) $devices[0]['id']);
    }

    $settings = $device ? device_settings($device) : null;
    // Einfuehrung ins Geraet: von selbst fuer den Besitzer, bis das Konto sie einmal gesehen
    // oder uebersprungen hat. Auch bei einem Testgeraet: bis zum 24. September 2026 kam sie
    // dort nie, und ein Testkonto sah das Geraet nie erklaert. Gaeste oeffnen sie ueber den
    // Knopf unten auf der Seite.
    $tourAuto = $device !== null && $device['role'] === 'owner' && ($user['tour_seen_at'] ?? null) === null;
    $modesMeta = [];
    foreach (modes() as $id => $m) {
        $modesMeta[$id] = ['label' => $m['label'], 'available' => $m['available'], 'rotatable' => $m['rotatable']];
    }

    page_open(['title' => $device ? $device['name'] : 'Devices', 'title_de' => $device ? $device['name'] : 'Geräte', 'page' => 'device', 'noindex' => true]);
    view('device', [
        'user' => $user,
        'devices' => $devices,
        'device' => $device,
        'settings' => $settings,
        'modes' => modes(),
        'role' => $device['role'] ?? null,
    ]);
    page_close(
        $device ? ['js/lib/ops.js', 'js/lib/anim.js', 'js/lib/tour.js', 'js/pages/device.js'] : ['js/pages/nodevice.js'],
        $device ? [
            'id' => (int) $device['id'],
            'role' => $device['role'],
            'settings' => $settings,
            'applied' => !empty($settings['applied']),
            'modes' => $modesMeta,
            'noteRev' => (int) $device['note_rev'],
            'noteLeft' => note_front_left($settings),
            'ring' => ring_status_public($settings),
            'fw' => (string) ($device['fw'] ?? ''),
            'mapFw' => FLIGHT_MAP_FW,
            'spotifyDemo' => spotify_demo(),
            'tour' => ['auto' => $tourAuto, 'seen' => ($user['tour_seen_at'] ?? null) !== null, 'name' => $device['name'], 'user' => $user['username'], 'reduce' => !empty($settings['reduce']), 'test' => device_is_test($device)],
        ] : ['waiting' => true]
    );
}

/** Zeile unter dem Namen im Geraeteumschalter: [en, de]. */
function device_status_pair(array $d): array
{
    $s = device_settings($d);
    $mode = mode_get((string) $s['mode']);
    $label = $mode ? $mode['label'] : ['en' => '', 'de' => ''];
    if (device_online($d)) {
        $en = 'Online · ' . mb_strtolower($label['en']);
        $de = 'Online · ' . $label['de'];
        if (($d['role'] ?? '') === 'view') {
            $en .= ' · view only';
            $de .= ' · nur ansehen';
        }
        return [$en, $de];
    }
    if (device_is_test($d) && empty($d['last_seen_at'])) {
        return ['Test device · ' . mb_strtolower($label['en']), 'Testgerät · ' . $label['de']];
    }
    if (empty($d['last_seen_at'])) {
        return ['Not seen yet', 'Noch nicht gesehen'];
    }
    [$en, $de] = ago_pair((int) $d['last_seen_at']);
    return ['Last seen ' . $en . ' ago', 'Zuletzt vor ' . $de];
}
