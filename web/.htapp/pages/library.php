<?php
/*
 * Nachschlageseiten: die dreizehn Geraete-Animationen (Vorlage fuer die Firmware)
 * und die Bibliothek der Bedienelemente. Oeffentlich, ohne Daten.
 */

declare(strict_types=1);

function page_animations(array $params = []): void
{
    page_open(['title' => 'Device animations', 'title_de' => 'Geräte-Animationen', 'page' => 'animations']);
    view('animations');
    page_close(['js/lib/ops.js', 'js/lib/anim.js', 'js/pages/animations.js']);
}

function page_controls(array $params = []): void
{
    page_open(['title' => 'Input library', 'title_de' => 'Eingabe-Bibliothek', 'page' => 'controls']);
    view('controls');
    page_close(['js/pages/controls.js']);
}
