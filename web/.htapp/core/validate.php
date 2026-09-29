<?php
/*
 * Pruefregeln fuer Eingaben. Dieselben Regeln prueft der Browser vorab, der
 * Server entscheidet. Passwort: mindestens 6 Zeichen, davon ein Buchstabe, so
 * am 13. September entschieden.
 */

declare(strict_types=1);

const RESERVED_USERNAMES = ['admin', 'root', 'system', 'thewall', 'support'];

function valid_username(string $u): bool
{
    return (bool) preg_match('/^[a-z0-9_]{3,20}$/i', $u);
}

function username_reserved(string $u): bool
{
    return in_array(strtolower($u), RESERVED_USERNAMES, true);
}

function valid_email(string $e): bool
{
    return strlen($e) <= 190
        && (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $e)
        && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

/** Leer, wenn gut; sonst der Fehler als [en, de]. */
function password_problem(string $p): ?array
{
    if (mb_strlen($p) < 6) {
        return ['At least 6 characters', 'Mindestens 6 Zeichen'];
    }
    if (!preg_match('/\p{L}/u', $p)) {
        return ['At least one letter', 'Mindestens ein Buchstabe'];
    }
    if (strlen($p) > 1000) {
        return ['At most 1000 characters', 'Hoechstens 1000 Zeichen'];
    }
    return null;
}

function valid_hex_colour(string $c): bool
{
    return (bool) preg_match('/^#[0-9A-F]{6}$/i', $c);
}
