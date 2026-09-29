<?php
/* Datenschutz und Impressum. Name, Adresse und Kontakt traegt der Admin ein,
   bis dahin stehen die Platzhalter aus dem Entwurf da. */

declare(strict_types=1);

function page_legal(array $params = []): void
{
    page_open(['title' => 'Privacy and legal notice', 'title_de' => 'Datenschutz und Impressum', 'page' => 'legal']);
    view('legal', ['imp' => imprint()]);
    page_close();
}
