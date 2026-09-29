<?php
/* Fehlerseiten. 404 nach dem Entwurf NotFound, andere Codes in derselben Gestalt. */

declare(strict_types=1);

function page_error(int $status, string $en = '', ?string $de = null): void
{
    $user = null;
    try {
        $user = session_user();
    } catch (Throwable $e) {
        $user = null;
    }
    page_open(['title' => $status === 404 ? 'Not found' : 'Error ' . $status, 'title_de' => $status === 404 ? 'Nicht gefunden' : 'Fehler ' . $status, 'page' => 'notfound', 'fonts' => 'ibm-plex-mono:400,500,600|ibm-plex-sans:400,500', 'noindex' => true]);
    view('notfound', ['status' => $status, 'message' => $en, 'messageDe' => $de, 'user' => $user]);
    page_close(['js/pages/notfound.js'], ['status' => $status]);
}
