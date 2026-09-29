<?php
/*
 * Modus Notizen: zwei Zeilen mit je 21 Zeichen. Gross- und Kleinschreibung
 * bleibt, wie getippt, Umlaute schreibt panel_text() um. Gaeste mit Leserecht
 * duerfen genau das hier aendern und sonst nichts.
 *
 * Bei einer neuen Notiz blinkt das Panel dreimal, zwei Wechsel pro Sekunde,
 * also unter der Grenze von drei Blitzen pro Sekunde.
 */

declare(strict_types=1);

mode_register('notes', [
    'order' => 40,
    'label' => ['en' => 'Notes', 'de' => 'Notizen'],
    'rotatable' => false,
    'defaults' => ['line1' => '', 'line2' => '', 'flash' => true],
    'sanitize' => static function (array $in, array $cur): array {
        $out = $cur;
        if (array_key_exists('flash', $in)) {
            $out['flash'] = (bool) $in['flash'];
        }
        return $out;
    },
    'build' => static function (array $ctx, float $from, float $to): array {
        $n = $ctx['settings']['notes'];
        $l1 = panel_text((string) $n['line1'], false);
        $l2 = panel_text((string) $n['line2'], false);
        $ops = [];
        if ($l1 !== '') {
            $ops[] = op_text(max(2, panel_center_x($l1)), 18, $l1, C_ACCENT);
        }
        if ($l2 !== '') {
            $ops[] = op_text(max(2, panel_center_x($l2)), 34, $l2, C_WHITE);
        }
        $ops[] = op_rect(0, 60, 128, 1, C_LINE);
        $extra = ['id' => 'notes'];
        if (!empty($n['flash'])) {
            $extra['flash'] = (int) ($ctx['device']['note_rev'] ?? 0);
        }
        return [frame_page($from, $to, $ops, $extra)];
    },
]);
