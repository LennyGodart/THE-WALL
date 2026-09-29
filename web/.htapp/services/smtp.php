<?php
/*
 * Schlanker SMTP-Client fuer den Versand ueber Brevo oder jeden anderen
 * Anbieter: STARTTLS auf 587 oder SSL auf 465, Anmeldung mit PLAIN oder LOGIN.
 * Kein Composer-Paket, damit der Server ohne Build laeuft.
 */

declare(strict_types=1);

final class SmtpError extends RuntimeException
{
}

/**
 * $cfg: host, port, security (starttls|ssl), user, pass
 * $message: fertige Mail mit Kopfzeilen, Zeilenenden CRLF.
 */
function smtp_send(array $cfg, string $from, array $to, string $message): void
{
    $host = (string) $cfg['host'];
    $port = (int) $cfg['port'];
    $ssl = ($cfg['security'] ?? 'starttls') === 'ssl';
    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => $host,
        'SNI_enabled' => true,
    ]]);
    $remote = ($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new SmtpError('Connection to ' . $host . ':' . $port . ' failed: ' . $errstr);
    }
    stream_set_timeout($fp, 20);
    try {
        smtp_expect($fp, [220]);
        $ehlo = smtp_cmd($fp, 'EHLO ' . site_host(), [250]);
        if (!$ssl) {
            if (!preg_match('/^250[ -]STARTTLS/mi', $ehlo)) {
                throw new SmtpError('The server does not offer STARTTLS.');
            }
            smtp_cmd($fp, 'STARTTLS', [220]);
            $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                throw new SmtpError('TLS handshake failed.');
            }
            $ehlo = smtp_cmd($fp, 'EHLO ' . site_host(), [250]);
        }
        $user = (string) ($cfg['user'] ?? '');
        if ($user !== '') {
            $pass = (string) ($cfg['pass'] ?? '');
            if (preg_match('/^250[ -]AUTH[ =].*\bPLAIN\b/mi', $ehlo)) {
                smtp_cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235], true);
            } else {
                smtp_cmd($fp, 'AUTH LOGIN', [334]);
                smtp_cmd($fp, base64_encode($user), [334], true);
                smtp_cmd($fp, base64_encode($pass), [235], true);
            }
        }
        smtp_cmd($fp, 'MAIL FROM:<' . smtp_addr($from) . '>', [250]);
        foreach ($to as $rcpt) {
            smtp_cmd($fp, 'RCPT TO:<' . smtp_addr($rcpt) . '>', [250, 251]);
        }
        smtp_cmd($fp, 'DATA', [354]);
        $body = preg_replace('/(?<!\r)\n/', "\r\n", $message) ?? $message;
        $body = preg_replace('/^\./m', '..', $body) ?? $body;
        fwrite($fp, $body . "\r\n.\r\n");
        smtp_expect($fp, [250]);
        @fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

function smtp_addr(string $email): string
{
    if (!valid_email($email)) {
        throw new SmtpError('Invalid address.');
    }
    return $email;
}

function smtp_cmd($fp, string $line, array $codes, bool $secret = false): string
{
    fwrite($fp, $line . "\r\n");
    try {
        return smtp_expect($fp, $codes);
    } catch (SmtpError $e) {
        if ($secret) {
            throw new SmtpError('Authentication rejected: ' . $e->getMessage());
        }
        throw $e;
    }
}

function smtp_expect($fp, array $codes): string
{
    $all = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $all .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    if ($all === '') {
        $meta = stream_get_meta_data($fp);
        throw new SmtpError($meta['timed_out'] ? 'The server stopped answering.' : 'The connection closed.');
    }
    $code = (int) substr($all, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new SmtpError(trim(preg_replace('/\s+/', ' ', $all) ?? $all));
    }
    return $all;
}
