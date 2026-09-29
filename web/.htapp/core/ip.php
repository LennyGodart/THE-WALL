<?php
/*
 * Echte Adresse des Besuchers, nur fuer Rate-Limits. Gespeichert wird sie nicht,
 * die Schluessel der Rate-Limits enthalten nur einen Hash.
 *
 * Der Weg einer Anfrage: Besucher -> Cloudflare -> nginx (443) -> PHP. PHP sieht
 * als REMOTE_ADDR nur 127.0.0.1. nginx haengt die Adresse, von der es die
 * Verbindung bekam, hinten an X-Forwarded-For an. Nur diesem letzten Eintrag
 * ist zu trauen. Ist er eine Cloudflare-Adresse, gilt CF-Connecting-IP.
 */

declare(strict_types=1);

function client_ip(): string
{
    static $ip = null;
    if ($ip !== null) {
        return $ip;
    }
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $ip = $remote;
    if (!ip_is_loopback($remote)) {
        return $ip;
    }
    $chain = array_values(array_filter(array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')))));
    // Eintraege weiterer Proxys auf demselben Rechner (Varnish) ueberspringen.
    while ($chain && ip_is_loopback(end($chain))) {
        array_pop($chain);
    }
    if (!$chain) {
        return $ip;
    }
    $peer = end($chain);
    if (filter_var($peer, FILTER_VALIDATE_IP) === false) {
        return $ip;
    }
    $ip = $peer;
    if (ip_is_cloudflare($peer)) {
        $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) !== false) {
            $ip = $cf;
        }
    }
    return $ip;
}

function ip_is_loopback(string $ip): bool
{
    return $ip === '::1' || str_starts_with($ip, '127.');
}

/** Stand der Liste: cloudflare.com/ips. Aendert sich selten. */
function ip_is_cloudflare(string $ip): bool
{
    $ranges = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];
    foreach ($ranges as $cidr) {
        if (ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

function ip_in_cidr(string $ip, string $cidr): bool
{
    [$net, $bits] = explode('/', $cidr);
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($net);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $bits = (int) $bits;
    $bytes = intdiv($bits, 8);
    if (substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = chr((0xFF << (8 - $rest)) & 0xFF);
    return (($ipBin[$bytes] & $mask) === ($netBin[$bytes] & $mask));
}

/** Kurzer, nicht umkehrbarer Schluessel fuer Rate-Limits. */
function ip_key(): string
{
    return substr(hash_hmac('sha256', client_ip(), secret('app_key')), 0, 24);
}
