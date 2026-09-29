<?php
/*
 * Schutz gegen fremde Formulare. Jede veraendernde Anfrage muss von dieser Seite
 * kommen (Origin und Sec-Fetch-Site). Mit Sitzung muss zusaetzlich der Token der
 * Sitzung mitkommen, als Formularfeld _csrf oder Kopf X-CSRF-Token.
 */

declare(strict_types=1);

function csrf_token(): string
{
    $s = session_row();
    return $s ? (string) $s['csrf'] : '';
}

function csrf_field(): string
{
    $t = csrf_token();
    return $t === '' ? '' : '<input type="hidden" name="_csrf" value="' . h($t) . '">';
}

function csrf_check(): void
{
    if (!same_origin_request()) {
        throw new HttpError(403, 'This request did not come from this site.', 'Diese Anfrage kam nicht von dieser Seite.');
    }
    $s = session_row();
    if ($s === null) {
        return;
    }
    $sent = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($sent === '') {
        $j = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') ? req_json() : [];
        $sent = (string) ($j['_csrf'] ?? '');
    }
    if (!hash_equals((string) $s['csrf'], $sent)) {
        throw new HttpError(403, 'The page was open too long. Please reload it.', 'Die Seite war zu lange offen. Bitte neu laden.');
    }
}
