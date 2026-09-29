<?php
/*
 * Firmware ueber die Luft. Eine neue Fassung ist eine Datei
 * .htdata/firmware/thewall-<version>.bin, der Server traegt sie beim naechsten
 * Blick in den Ordner selbst ein.
 *
 * Rollout: jedes Geraet hat einen festen Platz von 0 bis 99 (aus seiner ID).
 * Bei 25 Prozent bekommen die Geraete mit Platz unter 25 das Update angeboten.
 * Ohne "Automatisch" holt es sich ein Geraet erst, wenn der Besitzer in den
 * Einstellungen auf "Jetzt aktualisieren" drueckt.
 */

declare(strict_types=1);

function firmware_scan(): void
{
    // Eingetragene Fassungen, deren Datei geloescht oder ersetzt wurde, nachziehen.
    // Sonst bekaeme ein Geraet ein Update angeboten, das es nicht laden kann.
    foreach (qall('SELECT version, file, size FROM firmware') as $row) {
        $path = TW_DATA . '/firmware/' . basename((string) $row['file']);
        if (!is_file($path)) {
            q('DELETE FROM firmware WHERE version = ?', [$row['version']]);
        } elseif (filesize($path) !== (int) $row['size']) {
            db_update('firmware', ['sha256' => hash_file('sha256', $path), 'size' => filesize($path)], 'version = ?', [$row['version']]);
        }
    }
    foreach (glob(TW_DATA . '/firmware/thewall-*.bin') ?: [] as $file) {
        if (!preg_match('/thewall-(\d+\.\d+\.\d+)\.bin$/', $file, $m)) {
            continue;
        }
        if (q1('SELECT version FROM firmware WHERE version = ?', [$m[1]])) {
            continue;
        }
        db_insert('firmware', [
            'version' => $m[1],
            'file' => basename($file),
            'sha256' => hash_file('sha256', $file),
            'size' => filesize($file),
            'created_at' => time(),
        ]);
    }
}

function firmware_latest(): ?array
{
    static $latest = false;
    if ($latest !== false) {
        return $latest;
    }
    firmware_scan();
    $latest = null;
    foreach (qall('SELECT * FROM firmware') as $f) {
        if ($latest === null || version_compare($f['version'], $latest['version'], '>')) {
            $latest = $f;
        }
    }
    return $latest;
}

/* So gross ist eine App-Partition (firmware/partitions.csv), groesser passt nichts. */
const FIRMWARE_MAX_BYTES = 4 * 1024 * 1024;

/**
 * Eine hochgeladene Fassung pruefen und ablegen. Die Datei muss ein ESP32-App-Image
 * sein (erstes Byte 0xE9), zwischen 64 KB und 4 MB gross, und die Marke
 * "THEWALL-FW x.y.z" enthalten, die jede Firmware ab 0.1.1 traegt. Der Name der Datei
 * zaehlt nicht, die Version kommt aus der Marke. Gibt [Version, Fehler en, Fehler de].
 */
function firmware_store(string $bin): array
{
    $n = strlen($bin);
    if ($n < 65536 || $n > FIRMWARE_MAX_BYTES) {
        return [null, 'The file must be between 64 KB and 4 MB.', 'Die Datei muss zwischen 64 KB und 4 MB groß sein.'];
    }
    if (ord($bin[0]) !== 0xE9) {
        return [null, 'This is not an ESP32 app image. Take firmware.bin from the build folder.', 'Das ist kein App-Image für den ESP32. Nimm firmware.bin aus dem Build-Ordner.'];
    }
    if (!preg_match('/THEWALL-FW (\d+\.\d+\.\d+)\x00/', $bin, $m)) {
        return [null, 'No THE WALL version found in the file (firmware 0.1.1 or newer carries it).', 'In der Datei steht keine Version von THE WALL (ab Firmware 0.1.1 steht sie drin).'];
    }
    $version = $m[1];
    if (q1('SELECT version FROM firmware WHERE version = ?', [$version])) {
        return [null, 'Version ' . $version . ' is already on the server.', 'Version ' . $version . ' liegt schon auf dem Server.'];
    }
    ensure_data_dir();
    atomic_write(TW_DATA . '/firmware/thewall-' . $version . '.bin', $bin);
    firmware_scan();
    return [$version, '', ''];
}

function firmware_rollout(): int
{
    return clamp_int(setting('rollout', 100), 0, 100, 100);
}

function firmware_auto(): bool
{
    return (bool) setting('auto_update', false);
}

function firmware_bucket(string $uid): int
{
    return (int) (crc32($uid) % 100);
}

/** Ist fuer dieses Geraet eine neuere Fassung freigegeben? */
function firmware_available(array $device): ?array
{
    $latest = firmware_latest();
    if ($latest === null || empty($device['fw'])) {
        return null;
    }
    if (!version_compare($latest['version'], (string) $device['fw'], '>')) {
        return null;
    }
    if (firmware_bucket((string) $device['uid']) >= firmware_rollout()) {
        return null;
    }
    return $latest;
}

/**
 * Teil der Antwort an das Geraet, nur wenn es jetzt aktualisieren soll. $bad ist die Version
 * aus X-Wall-Fw-Bad: sie lief auf diesem Geraet nicht an und wurde zurueckgerollt (ab
 * Firmware 0.1.10). Die bekommt es nicht noch einmal angeboten.
 */
function firmware_offer(array $device, string $bad = ''): ?array
{
    $f = firmware_available($device);
    if ($f === null || ($bad !== '' && $bad === (string) $f['version'])) {
        return null;
    }
    $requested = !empty($device['update_requested_at']) && (int) $device['update_requested_at'] > time() - 86400;
    if (!firmware_auto() && !$requested) {
        return null;
    }
    return [
        'version' => $f['version'],
        'url' => app_url() . '/api/v1/firmware/' . $f['version'],
        'sha256' => $f['sha256'],
        'size' => (int) $f['size'],
    ];
}
