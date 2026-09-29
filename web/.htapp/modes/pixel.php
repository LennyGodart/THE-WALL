<?php
/*
 * Pixel-Editor. Kommt nach dem 25. September, bis dahin nur eine Vorschau auf
 * der Geraeteseite, uebernehmen laesst er sich nicht.
 */

declare(strict_types=1);

mode_register('pixel', [
    'order' => 50,
    'label' => ['en' => 'Pixel editor', 'de' => 'Pixel-Editor'],
    'available' => false,
    'rotatable' => false,
    'defaults' => [],
    'build' => static fn(array $ctx, float $from, float $to): array => [frame_page($from, $to, [op_anim('pixeldemo')], ['id' => 'pixel'])],
]);
