<?php
/*
 * Mails. Die HTML-Vorlagen in mail/ sind die sendefertigen Entwuerfe der Gestaltung,
 * unveraendert. Ersetzt werden beim Versand nur Token, Benutzername, Name des
 * Einladenden, Geraetename und die Platzhalter im Fusstext. Zu jeder Mail gibt
 * es eine Textfassung (.txt), fuer Textclients und Zustellbarkeit.
 *
 * Versand ueber den SMTP-Zugang aus dem Admin-Bereich (Brevo: smtp-relay.brevo.com,
 * Port 587, STARTTLS). Ohne Zugang geht keine Mail raus, der Aufrufer bekommt
 * ok=false und entscheidet selbst.
 */

declare(strict_types=1);

function mail_send(string $to, string $subject, string $html, string $text, array $attachments = []): array
{
    if (!smtp_configured()) {
        return ['ok' => false, 'error' => 'not-configured'];
    }
    $cfg = smtp_settings(true);
    try {
        $message = mail_build($cfg['from_email'], $cfg['from_name'], $to, $subject, $html, $text, $attachments);
        smtp_send($cfg, $cfg['from_email'], [$to], $message);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        $msg = str_cut($e->getMessage(), 200);
        log_error('Mailversand fehlgeschlagen: ' . $msg);
        event_add('MAIL', 'Mail could not be sent: ' . $msg, 'Mail nicht versendet: ' . $msg);
        return ['ok' => false, 'error' => $msg];
    }
}

function mail_build(string $fromEmail, string $fromName, string $to, string $subject, string $html, string $text, array $attachments): string
{
    $eol = "\r\n";
    $alt = 'alt-' . random_hex(12);
    $mixed = 'mix-' . random_hex(12);
    $domain = substr(strrchr($fromEmail, '@') ?: '@' . site_host(), 1);
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . mail_encode_header($fromName) . ' <' . $fromEmail . '>',
        'To: <' . $to . '>',
        'Subject: ' . mail_encode_header($subject),
        'Message-ID: <' . random_hex(16) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Auto-Submitted: auto-generated',
        'X-Auto-Response-Suppress: All',
    ];
    $altPart = '--' . $alt . $eol
        . 'Content-Type: text/plain; charset=utf-8' . $eol
        . 'Content-Transfer-Encoding: quoted-printable' . $eol . $eol
        . quoted_printable_encode($text) . $eol
        . '--' . $alt . $eol
        . 'Content-Type: text/html; charset=utf-8' . $eol
        . 'Content-Transfer-Encoding: quoted-printable' . $eol . $eol
        . quoted_printable_encode($html) . $eol
        . '--' . $alt . '--' . $eol;

    if (!$attachments) {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
        return implode($eol, $headers) . $eol . $eol . $altPart;
    }
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixed . '"';
    $body = '--' . $mixed . $eol . 'Content-Type: multipart/alternative; boundary="' . $alt . '"' . $eol . $eol . $altPart;
    foreach ($attachments as $a) {
        $name = preg_replace('/[^A-Za-z0-9._\-]/', '', (string) $a['name']) ?: 'attachment.bin';
        $body .= '--' . $mixed . $eol
            . 'Content-Type: ' . $a['type'] . '; name="' . $name . '"' . $eol
            . 'Content-Transfer-Encoding: base64' . $eol
            . 'Content-Disposition: attachment; filename="' . $name . '"' . $eol . $eol
            . chunk_split(base64_encode((string) $a['data']), 76, $eol);
    }
    $body .= '--' . $mixed . '--' . $eol;
    return implode($eol, $headers) . $eol . $eol . $body;
}

function mail_encode_header(string $s): string
{
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** Vorlage laden und die gemeinsamen Stellen ersetzen: Adresse der Seite und Fusstext. */
function mail_template(string $name): array
{
    $html = (string) file_get_contents(TW_APP . '/mail/' . $name . '.html');
    $text = (string) file_get_contents(TW_APP . '/mail/' . $name . '.txt');
    $base = app_url();
    $imp = imprint();
    $parts = array_filter([$imp['name'], $imp['address']]);
    $footer = $parts ? h(implode(', ', $parts)) . ', ' : '';
    $html = str_replace('THE WALL &middot; [NAME], [ADRESSE], Luxembourg', 'THE WALL &middot; ' . $footer . 'Luxembourg', $html);
    // Die Vorlagen nennen die Instanz des Projekts. Eine andere Instanz setzt ihre eigene Adresse ein.
    $host = (string) (parse_url($base, PHP_URL_HOST) ?: site_host());
    $html = str_replace(['https://thewall.godart.lu', 'thewall.godart.lu'], [$base, h($host)], $html);
    $text = strtr($text, [
        '{{BASE}}' => $base,
        '{{IMPRINT}}' => ($parts ? implode(', ', $parts) . ', ' : '') . 'Luxembourg',
        'thewall.godart.lu' => $host,
    ]);
    return [$html, $text];
}

/** Namen im HTML ersetzen: nur ganze Woerter, damit nichts anderes getroffen wird. */
function mail_replace_word(string $html, string $word, string $value): string
{
    return preg_replace('/\b' . preg_quote($word, '/') . '\b/', $value, $html) ?? $html;
}

function mail_confirm(array $user, string $token): array
{
    [$html, $text] = mail_template('confirm');
    $url = app_url() . '/confirm?t=' . $token;
    $html = str_replace(app_url() . '/confirm?t=REPLACE_WITH_TOKEN', h($url), $html);
    $html = mail_replace_word($html, 'lenny', h($user['username']));
    $text = strtr($text, ['{{USER}}' => $user['username'], '{{URL}}' => $url]);
    return mail_send($user['email'], 'Confirm your email address', $html, $text);
}

function mail_reset(array $user, string $token): array
{
    [$html, $text] = mail_template('reset');
    $url = app_url() . '/reset?t=' . $token;
    $html = str_replace(app_url() . '/reset?t=REPLACE_WITH_TOKEN', h($url), $html);
    $html = mail_replace_word($html, 'lenny', h($user['username']));
    $text = strtr($text, ['{{USER}}' => $user['username'], '{{URL}}' => $url]);
    return mail_send($user['email'], 'Set a new password', $html, $text);
}

function mail_invite(string $email, array $inviter, array $device, string $token): array
{
    [$html, $text] = mail_template('invite');
    $url = app_url() . '/invite?t=' . $token;
    $html = str_replace(app_url() . '/invite?t=REPLACE_WITH_TOKEN', h($url), $html);
    $html = mail_replace_word($html, 'lenny', h($inviter['username']));
    $html = mail_replace_word($html, 'Lenny', h($inviter['username']));
    $html = mail_replace_word($html, 'Wohnzimmer', h($device['name']));
    $text = strtr($text, ['{{INVITER}}' => $inviter['username'], '{{DEVICE}}' => $device['name'], '{{URL}}' => $url]);
    return mail_send($email, $inviter['username'] . ' shared a panel with you', $html, $text);
}

function mail_test(string $to): array
{
    [$html, $text] = mail_template('test');
    $cfg = smtp_settings();
    $sent = gmdate('Y-m-d H:i') . ' UTC';
    $html = strtr($html, ['{{SENT_AT}}' => h($sent), '{{SMTP_HOST}}' => h($cfg['host'])]);
    $text = strtr($text, ['{{SENT_AT}}' => $sent, '{{SMTP_HOST}}' => $cfg['host']]);
    return mail_send($to, 'Test mail from THE WALL', $html, $text);
}

function mail_export(array $user, string $fileName, string $json): array
{
    [$html, $text] = mail_template('export');
    $html = mail_replace_word($html, 'lenny', h($user['username']));
    $html = str_replace('{{FILE_NAME}}', h($fileName), $html);
    $text = strtr($text, ['{{USER}}' => $user['username'], '{{FILE_NAME}}' => $fileName]);
    return mail_send($user['email'], 'Your data from THE WALL', $html, $text, [
        ['name' => $fileName, 'type' => 'application/json', 'data' => $json],
    ]);
}
