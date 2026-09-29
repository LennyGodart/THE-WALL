<?php
/*
 * Zwei Arten von Protokoll:
 *  - log_error(): technische Fehler in .htdata/logs, fuer die Fehlersuche.
 *  - event_add(): kurze Meldungen fuer "Zuletzt schiefgegangen" im Admin-Bereich.
 * Beides wird nach 14 Tagen geloescht, so steht es in der Datenschutzerklaerung.
 */

declare(strict_types=1);

function log_error(string $message, array $context = []): void
{
    ensure_data_dir();
    $line = gmdate('Y-m-d\TH:i:s\Z') . ' ' . $message;
    if ($context) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    @file_put_contents(TW_DATA . '/logs/app-' . gmdate('Y-m-d') . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

/** Meldung fuer den Admin-Bereich, englisch und deutsch. */
function event_add(string $source, string $en, string $de): void
{
    try {
        // Dieselbe Meldung nicht im Minutentakt wiederholen.
        $recent = qval(
            'SELECT COUNT(*) FROM events WHERE source = ? AND message_en = ? AND at > ?',
            [$source, $en, time() - 600]
        );
        if ((int) $recent > 0) {
            return;
        }
        db_insert('events', [
            'at' => time(),
            'source' => substr($source, 0, 16),
            'message_en' => str_cut($en, 250),
            'message_de' => str_cut($de, 250),
        ]);
    } catch (Throwable $e) {
        log_error('event_add fehlgeschlagen: ' . $e->getMessage());
    }
}

function events_recent(int $limit = 20): array
{
    return qall('SELECT * FROM events WHERE at > ? ORDER BY at DESC, id DESC LIMIT ' . (int) $limit, [time() - 14 * 86400]);
}

/** Aufraeumen, das nebenbei bei Aufrufen mitlaeuft, hoechstens einmal pro Stunde. */
function housekeeping(): void
{
    $flag = TW_DATA . '/logs/.housekeeping';
    if (is_file($flag) && filemtime($flag) > time() - 3600) {
        return;
    }
    @touch($flag);
    try {
        $cut = time() - 14 * 86400;
        q('DELETE FROM events WHERE at < ?', [$cut]);
        q('DELETE FROM sessions WHERE expires_at < ?', [time()]);
        q('DELETE FROM tokens WHERE expires_at < ?', [time() - 86400]);
        q('DELETE FROM cache WHERE expires_at < ?', [time()]);
        q('DELETE FROM rate_limits WHERE window_start < ?', [time() - 2 * 86400]);
        q('DELETE FROM budget WHERE hour < ?', [intdiv(time(), 3600) - 24 * 8]);
        // Unbestaetigte Konten nach sieben Tagen, so steht es in der Bestaetigungsmail.
        $stale = qall('SELECT id FROM users WHERE email_verified_at IS NULL AND created_at < ?', [time() - 7 * 86400]);
        foreach ($stale as $u) {
            user_delete((int) $u['id']);
        }
        foreach (glob(TW_DATA . '/logs/app-*.log') ?: [] as $f) {
            if (filemtime($f) < $cut) {
                @unlink($f);
            }
        }
        foreach (glob(TW_DATA . '/exports/*') ?: [] as $f) {
            if (filemtime($f) < time() - 3600) {
                @unlink($f);
            }
        }
        // Eine leere Sperrdatei je Zwischenspeicher-Schluessel (cache_remember), etwa je
        // Standort einer Vorschau. Nach einem Tag braucht sie niemand mehr.
        foreach (glob(TW_DATA . '/locks/*.lock') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    } catch (Throwable $e) {
        log_error('housekeeping: ' . $e->getMessage());
    }
}
