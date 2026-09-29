<?php
/*
 * Karte fuer den Standort, als eigene Seite in einem iframe der Geraeteseite.
 * Kartenbibliotheken brauchen einen Container, der schon im Dokument steht.
 * Leaflet liegt lokal (FEHLERLISTE 5.1), die Kacheln kommen von OpenStreetMap,
 * die Ortssuche geht ueber den Server.
 */

declare(strict_types=1);

function page_map(array $params = []): void
{
    require_user();
    page_open([
        'title' => 'Coverage radius',
        'title_de' => 'Abdeckung',
        'page' => 'map',
        'variant' => 'map',
        'referrer' => 'strict-origin-when-cross-origin',
        'fonts' => 'ibm-plex-mono:400,500,600',
        'noindex' => true,
    ]);
    view('map');
    echo '<script src="' . h(asset('vendor/leaflet/leaflet.js')) . '" defer></script>' . "\n";
    echo '<script src="' . h(asset('js/pages/map.js')) . '" defer></script>' . "\n";
    echo "</body>\n</html>\n";
}

/**
 * Haltestellenkarte fuer den Nahverkehr, ebenfalls im iframe der Geraeteseite. Alle
 * Haltestellen des Landes als Punkte, Suche nach Ort oder Haltestelle in der Liste
 * selbst (keine Abfrage nach aussen), Auswahl per Nachricht an die Geraeteseite.
 */
function page_map_stops(array $params = []): void
{
    require_user();
    page_open([
        'title' => 'Stops',
        'title_de' => 'Haltestellen',
        'page' => 'stops',
        'variant' => 'map',
        'referrer' => 'strict-origin-when-cross-origin',
        'fonts' => 'ibm-plex-mono:400,500,600',
        'noindex' => true,
    ]);
    view('map_stops');
    echo '<script src="' . h(asset('vendor/leaflet/leaflet.js')) . '" defer></script>' . "\n";
    echo '<script src="' . h(asset('js/pages/stops.js')) . '" defer></script>' . "\n";
    echo "</body>\n</html>\n";
}
