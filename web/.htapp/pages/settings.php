<?php
/*
 * Einstellungen: alles, was man selten anfasst. Geraet (Name, Zeitzone, WLAN,
 * Firmware, Zustand), Zugang (Schluessel, Freigaben), Konto und Daten, Loeschen.
 * Geraetekarten sieht nur der Besitzer, Konto und Schluessel jeder.
 */

declare(strict_types=1);

function page_settings(array $params = []): void
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
    } else {
        foreach ($devices as $d) {
            if ($d['role'] === 'owner') {
                redirect('/settings/' . (int) $d['id']);
            }
        }
    }
    $owner = $device !== null && $device['role'] === 'owner';
    $settingsNow = $device ? device_settings($device) : null;
    page_open(['title' => 'Settings', 'title_de' => 'Einstellungen', 'page' => 'settings', 'noindex' => true]);
    view('settings', [
        'user' => $user,
        'devices' => $devices,
        'device' => $device,
        'owner' => $owner,
        'settings' => $settingsNow,
        'shares' => $owner ? device_shares((int) $device['id']) : null,
        'haLinks' => $owner ? ha_links_public((int) $device['id']) : null,
        'firmware' => $owner ? firmware_available($device) : null,
        'mailReady' => smtp_configured(),
    ]);
    // Die Karte "So funktioniert das Geraet" steht im Abschnitt Geraet, den nur der Besitzer sieht.
    $scripts = $owner ? ['js/lib/ops.js', 'js/lib/anim.js', 'js/lib/tour.js', 'js/pages/settings.js'] : ['js/pages/settings.js'];
    page_close($scripts, [
        'id' => $device ? (int) $device['id'] : null,
        'owner' => $owner,
        'username' => $user['username'],
        'tour' => $owner ? ['auto' => false, 'seen' => ($user['tour_seen_at'] ?? null) !== null, 'name' => $device['name'], 'user' => $user['username'], 'reduce' => !empty($settingsNow['reduce']), 'device' => (int) $device['id'], 'test' => device_is_test($device)] : null,
    ]);
}

/** Alle Daten des Kontos als Datei. */
function download_export(array $params = []): void
{
    $user = require_user();
    $json = json_encode(account_export($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $name = 'thewall-' . $user['username'] . '-' . gmdate('Y-m-d') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: ' . NO_STORE);
    header('X-Content-Type-Options: nosniff');
    echo $json;
}
