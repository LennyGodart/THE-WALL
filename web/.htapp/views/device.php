<?php
/**
 * @var array $user @var array $devices @var ?array $device @var ?array $settings
 * @var array $modes @var ?string $role
 */
$mono = "font-family:'IBM Plex Mono',monospace;";
$cardLabel = 'margin:0 0 10px;' . $mono . 'font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3';
$labelBlock = 'display:block;margin-bottom:10px;' . $mono . 'font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3';
$note = 'margin:10px 0 0;' . $mono . 'font-size:11px;line-height:1.5;color:#8B949C';
$grid = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:1px;background:#1B2126;border:1px solid #1B2126';
/* Nahverkehr wie Transit.dc.html, dazu der Flugmodus: die Linien liegen auf den Karten, sonst bleibt bei freien Rasterzellen ein grauer Block stehen. */
$gridCards = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:1px';
$s = $settings;
$ro = $role === 'view';
$dis = $ro ? ' disabled' : '';
$on = static fn(bool $b): string => $b ? 'true' : 'false';

$sw = static function (string $key, bool $checked, string $labelId, string $titleEn, string $titleDe, string $textEn, string $textDe) use ($on, $dis): string {
    return '<div style="display:flex;align-items:flex-start;gap:14px">'
        . '<button type="button" class="sw" role="switch" data-set="' . h($key) . '" aria-checked="' . $on($checked) . '" aria-labelledby="' . h($labelId) . '"' . $dis . '><span></span></button>'
        . '<div style="flex:1;min-width:0">'
        . '<p style="margin:0 0 3px;font-size:14px;color:#E8EAEC"' . de($titleDe) . '>' . h($titleEn) . '</p>'
        . '<p style="margin:0;font-size:12px;line-height:1.45;color:#8B949C"' . de($textDe) . '>' . h($textEn) . '</p>'
        . '</div></div>';
};
$row = static function (string $key, string $value, bool $checked, string $en, string $de) use ($on, $dis): string {
    return '<button type="button" class="rrow" role="radio" data-set="' . h($key) . '" data-value="' . h($value) . '" aria-checked="' . $on($checked) . '"' . $dis . '><span class="rdot"></span><span' . de($de) . '>' . h($en) . '</span></button>';
};
/* Ort des Geraets mit Suchfeld, bei Uhr und Wetter. Ein Punkt je Geraet, derselbe wie auf der
   Karte beim Flugradar. Gesucht wird ueber /api/geo nur auf Absenden, wie dort (Nominatim).
   $p macht die ids eindeutig. */
$placeCard = static function (string $p) use ($s, $cardLabel, $mono, $dis): string {
    return '<article style="background:#0B0D0F;padding:22px">'
        . '<p style="' . $cardLabel . '"' . de('Ort') . '>Location</p>'
        . '<p data-out="place" style="margin:0 0 6px;font-size:15px;color:#E8EAEC">' . h((string) $s['location']['place']) . '</p>'
        . '<p data-out="coords" style="margin:0 0 16px;' . $mono . 'font-size:11px;color:#8B949C"></p>'
        . '<form data-place-form novalidate style="margin:0">'
        . '<label for="' . h($p) . '-place" style="display:block;margin-bottom:8px;' . $mono . 'font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"' . de('Ort oder Adresse suchen') . '>Find a place or address</label>'
        . '<div style="display:flex;gap:6px">'
        . '<input id="' . h($p) . '-place" data-place-q type="search" enterkeyhint="search" maxlength="120" autocomplete="off" placeholder="Esch-sur-Alzette" class="field" style="flex:1;min-width:0"' . $dis . '>'
        . '<button type="submit" class="btn-ghost" style="flex:none;color:#FFAA00"' . $dis . de('Suchen') . '>Find</button>'
        . '</div></form>'
        . '<p data-place-status role="status" aria-live="polite" style="margin:10px 0 0;font-size:12.5px;line-height:1.5;color:#8B949C"' . de('Weltweit. Gilt für das ganze Gerät, auch für den Flugradar, Übernehmen bringt ihn aufs Panel. Im Ausland auch die Zeitzone bei der Uhr umstellen.') . '>Anywhere in the world. Applies to the whole device, the flight radar too, and Apply puts it on the panel. Abroad, also change the time zone under clock.</p>'
        . '</article>';
};
?>
<div style="min-height:100vh;display:flex;flex-direction:column">

  <?= skip_link($device ? '#settings' : '#main', $device ? 'Skip to settings' : 'Skip to content', $device ? 'Zu den Einstellungen springen' : 'Zum Inhalt springen') ?>

  <header style="position:sticky;top:0;z-index:30;background:rgba(8,9,10,0.9);backdrop-filter:blur(12px);border-bottom:1px solid #1B2126">
    <div style="max-width:1400px;margin:0 auto;padding:12px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:12px 24px">
      <?= brand_mark('/') ?>

      <div data-switcher style="position:relative;padding-left:20px;border-left:1px solid #232A30">
        <button type="button" data-switcher-toggle aria-expanded="false" style="display:flex;align-items:center;gap:10px;min-height:44px;padding:6px 10px 6px 0;border:0;background:transparent;cursor:pointer;font-family:inherit;transition:opacity 160ms ease">
          <?php if ($device): ?>
            <span data-active-dot class="<?= device_online($device) ? 'blink-24' : '' ?>" style="width:7px;height:7px;border-radius:1px;flex:0 0 auto;background:<?= device_online($device) ? '#3DE07C' : '#4A565F' ?>"></span>
            <span style="<?= $mono ?>font-size:13px;color:#E8EAEC"><?= h($device['name']) ?></span>
          <?php else: ?>
            <span style="width:7px;height:7px;border-radius:1px;flex:0 0 auto;background:#4A565F"></span>
            <span style="<?= $mono ?>font-size:13px;color:#E8EAEC"<?= de('Kein Gerät') ?>>No device</span>
          <?php endif; ?>
          <span aria-hidden="true" data-caret style="font-size:9px;color:#8B949C;transition:transform 200ms cubic-bezier(0.23,1,0.32,1)">&#9662;</span>
        </button>

        <div data-switcher-menu role="group" aria-label="Your devices" data-de-label="Deine Geräte" hidden style="position:absolute;top:calc(100% + 8px);left:20px;z-index:50;min-width:268px;background:#0B0D0F;border:1px solid #2C353C;border-radius:3px;box-shadow:0 20px 44px -12px rgba(0,0,0,0.85);transform-origin:top left;overflow:hidden">
          <p style="margin:0;padding:12px 14px 8px;<?= $mono ?>font-size:10px;letter-spacing:0.16em;text-transform:uppercase;color:#8B949C"<?= de('Deine Geräte') ?>>Your devices</p>
          <?php foreach ($devices as $d): $pair = device_status_pair($d); $active = $device && (int) $d['id'] === (int) $device['id']; ?>
            <a href="/device/<?= (int) $d['id'] ?>" class="h-row" aria-current="<?= $active ? 'page' : 'false' ?>" style="display:flex;align-items:center;gap:11px;width:100%;padding:11px 14px;font-family:inherit;transition:background 160ms ease;background:<?= $active ? '#141A1E' : 'transparent' ?>">
              <span style="width:7px;height:7px;border-radius:1px;flex:0 0 auto;background:<?= device_online($d) ? '#3DE07C' : '#4A565F' ?>"></span>
              <span style="flex:1;min-width:0;text-align:left">
                <span style="display:block;font-size:13.5px;color:#E8EAEC"><?= h($d['name']) ?></span>
                <span style="display:block;<?= $mono ?>font-size:10.5px;color:#8B949C"<?= de($pair[1]) ?>><?= h($pair[0]) ?></span>
              </span>
              <span style="flex:0 0 auto;font-size:12px;color:#FFAA00;opacity:<?= $active ? '1' : '0' ?>">&#10003;</span>
            </a>
          <?php endforeach; ?>
          <a href="/settings#key" class="h-row" style="display:flex;align-items:center;gap:10px;padding:13px 14px;border-top:1px solid #1B2126;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em;text-transform:uppercase;color:#FFAA00">
            <span aria-hidden="true" style="width:12px;height:12px;border:1px dashed #7A5200;border-radius:1px;flex:0 0 auto"></span>
            <span<?= de('Gerät hinzufügen') ?>>Add a device</span>
          </a>
        </div>
      </div>

      <nav style="display:flex;gap:2px;padding-left:20px;border-left:1px solid #232A30" aria-label="Device sections" data-de-label="Gerätebereiche">
        <span aria-current="page" style="padding:7px 12px;border-radius:2px;background:#171E23;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#E8EAEC"<?= de('Gerät') ?>>Device</span>
        <a href="/settings<?= $device ? '/' . (int) $device['id'] : '' ?>" class="h-nav hit" style="padding:7px 12px;border-radius:2px;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#8B949C"<?= de('Einstellungen') ?>>Settings</a>
        <?php if (is_admin($user)): ?>
          <a href="/admin" class="h-nav hit" style="padding:7px 12px;border-radius:2px;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#8B949C"<?= de('Verwaltung') ?>>Admin</a>
        <?php endif; ?>
      </nav>

      <div style="flex:1 0 0;min-width:0"></div>

      <?php if ($device): ?>
      <div style="display:flex;align-items:center;gap:14px;flex:1 1 260px;min-width:200px;max-width:380px">
        <label for="dev-bright" style="<?= $mono ?>font-size:10px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3;flex:0 0 auto"<?= de('Helligkeit') ?>>Bright</label>
        <div style="flex:1;min-width:80px">
          <div data-bar aria-hidden="true" style="margin-bottom:1px"><canvas data-px="bar" aria-hidden="true"></canvas></div>
          <input id="dev-bright" data-set="bright" type="range" min="0" max="255" step="1" value="<?= (int) $s['bright'] ?>"<?= $dis ?>>
        </div>
        <output for="dev-bright" data-out="bright" style="<?= $mono ?>font-size:14px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums;flex:0 0 auto;width:32px;text-align:right"><?= (int) $s['bright'] ?></output>
      </div>
      <?php endif; ?>

      <?= lang_switch() ?>
    </div>

    <?php if ($device): ?>
    <div data-telemetry style="max-width:1400px;margin:0 auto;padding:0 20px 12px;display:flex;flex-wrap:wrap;gap:6px 24px;<?= $mono ?>font-size:10.5px;letter-spacing:0.08em;color:#8B949C">
      <?php foreach (device_telemetry($device) as [$tEn, $tDe]): ?>
        <span data-telemetry-item<?= $tDe !== $tEn ? de($tDe) : '' ?>><?= h($tEn) ?></span>
      <?php endforeach; ?>
      <span data-radius-tag style="margin-left:auto;color:#E09A1A">ADSB.LOL &middot; <?= (int) $s['flight']['radius'] ?> NM</span>
    </div>
    <?php endif; ?>
  </header>

  <main id="main" style="flex:1;width:100%;max-width:1400px;margin:0 auto;padding:clamp(20px,3vw,32px) 20px">
  <?php if (!$device): ?>
    <div data-pxwrap="1" style="margin:0 0 12px;max-width:560px"><canvas aria-hidden="true"></canvas></div>
    <h1 data-pxhead="1"<?= de('Noch kein Gerät') ?>>No device yet</h1>
    <div style="background:#0B0D0F;border:1px dashed #2C353C;padding:clamp(24px,4vw,40px);text-align:center">
      <p style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#E09A1A"<?= de('Schlüssel einsetzen') ?>>Paste the key</p>
      <p style="margin:0 auto 20px;font-size:14.5px;line-height:1.6;color:#8B949C;max-width:52ch"<?= de('Setz deinen API-Schlüssel auf der Geräteseite unter seiner IP ein. Nach der ersten Abfrage erscheint das Gerät hier, meist nach zehn Sekunden.') ?>>Paste your API key into the device page at its IP address. The device shows up here after its first request, usually within ten seconds.</p>
      <a href="/settings#key" class="btn-ghost" style="display:inline-flex;align-items:center"<?= de('Schlüssel anzeigen') ?>>Show my key</a>
    </div>
  <?php else: ?>
    <div class="stage-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:1px;background:#1B2126;border:1px solid #1B2126">

      <nav aria-label="Modes" data-de-label="Modi" style="background:#0B0D0F;padding:16px;grid-column:span 1;min-width:0">
        <p style="margin:0 0 12px;<?= $mono ?>font-size:10px;letter-spacing:0.16em;text-transform:uppercase;color:#8B949C"<?= de('Modi') ?>>Modes</p>
        <div role="radiogroup" aria-label="Active mode" data-de-label="Aktiver Modus" style="display:flex;flex-direction:column;gap:2px">
          <?php foreach ($modes as $id => $m): ?>
            <button type="button" class="navrow" role="radio" data-set="mode" data-value="<?= h($id) ?>" aria-checked="<?= $on($s['mode'] === $id) ?>"><span class="navdot"></span><span style="flex:1;text-align:left"<?= de($m['label']['de']) ?>><?= h($m['label']['en']) ?></span><?php if ($id === 'flight'): ?><span class="livetag">LIVE</span><?php endif; ?></button>
          <?php endforeach; ?>
        </div>

        <?php if (!$ro): ?>
        <div style="display:flex;flex-direction:column;gap:6px;margin-top:12px">
          <?php /* Ausgeblendet auf Wunsch des Projektinhabers am 20. September 2026: das Fenster
               mit allen Modi wird noch nicht gebraucht. Der Code bleibt, hidden entfernen genuegt. */ ?>
          <button type="button" data-modes-open class="btn-ghost" style="width:100%" hidden<?= de('Alle Modi ansehen') ?>>See all modes</button>
          <button type="button" data-views-open class="btn-ghost" style="width:100%"<?= de('Ansichten des Flugs wählen') ?>>Choose flight views</button>
        </div>
        <?php endif; ?>

        <p style="margin:22px 0 12px;<?= $mono ?>font-size:10px;letter-spacing:0.16em;text-transform:uppercase;color:#8B949C"<?= de('Rotation') ?>>Rotation</p>
        <div role="group" aria-label="Modes in rotation" data-de-label="Modi in der Rotation" style="display:flex;flex-wrap:wrap;gap:5px">
          <?php foreach ([['flight', 'Flight', 'Flug'], ['clock', 'Clock', 'Uhr'], ['weather', 'Weather', 'Wetter'], ['transit', 'Departures', 'Nahverkehr'], ['spotify', 'Spotify', 'Spotify']] as [$rid, $ren, $rde]): ?>
            <button type="button" class="chip" data-rotation="<?= $rid ?>" aria-pressed="<?= $on(in_array($rid, $s['rotation'], true)) ?>"<?= de($rde) ?><?= $dis ?>><?= $ren ?></button>
          <?php endforeach; ?>
        </div>
        <p data-rot-summary style="margin:10px 0 0;<?= $mono ?>font-size:11px;line-height:1.5;color:#8B949C"></p>

        <p id="d-reduce-label" style="margin:22px 0 12px;<?= $mono ?>font-size:10px;letter-spacing:0.16em;text-transform:uppercase;color:#8B949C"<?= de('Bewegung') ?>>Motion</p>
        <?= $sw('reduce', !empty($s['reduce']), 'd-reduce-label', 'Reduce motion', 'Bewegung reduzieren', 'No sliding between modes, no blinking, notes stand still. Animations stop at their last frame.', 'Kein Schieben zwischen den Modi, kein Blinken, Laufschrift steht. Animationen bleiben im letzten Bild stehen.') ?>

        <p id="d-off-label" style="margin:22px 0 12px;<?= $mono ?>font-size:10px;letter-spacing:0.16em;text-transform:uppercase;color:#8B949C"<?= de('Panel') ?>>Panel</p>
        <?= $sw('off', !empty($s['off']), 'd-off-label', 'Turn the panel off', 'Panel ausschalten', 'Dark until you turn it on again. A timer or the alarm still lights it up to ring.', 'Dunkel, bis du es wieder einschaltest. Ein Timer oder der Wecker schaltet es zum Klingeln trotzdem an.') ?>
      </nav>

      <div class="stage-main" style="background:#0B0D0F;padding:clamp(16px,2.5vw,24px);grid-column:span 2;min-width:0">
        <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:10px 16px;margin-bottom:16px">
          <p style="margin:0;<?= $mono ?>font-size:10px;letter-spacing:0.16em;text-transform:uppercase;color:#8B949C"<?= de('Vorschau') ?>>Preview</p>
          <span data-dirty style="<?= $mono ?>font-size:10px;letter-spacing:0.1em;text-transform:uppercase;padding:3px 7px;border-radius:1px;background:rgba(61,224,124,0.14);color:#3DE07C"<?= de('Auf dem Gerät') ?>>On the device</span>
          <span style="margin-left:auto;<?= $mono ?>font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C"<?= de('Im Browser gerechnet') ?>>Rendered in the browser</span>
        </div>

        <div style="padding:9px;background:linear-gradient(#22282D,#15191C);border-radius:3px;box-shadow:0 20px 48px -18px rgba(0,0,0,0.9),inset 0 1px 0 rgba(255,255,255,0.06)">
          <div data-panel style="background:#050607;border-radius:1px;overflow:hidden;display:flex;justify-content:center">
            <canvas data-px="panel" role="img" aria-label="Preview"></canvas>
          </div>
        </div>

        <?php /* Steht eine neue Notiz vorn, zeigt das Panel sie statt des gewaehlten Modus, die
                 Vorschau aber den Modus. Der Satz sagt das, der Knopf blendet sie aus. Der
                 Status steht von Anfang an im Dokument, damit er vorgelesen wird. */ ?>
        <div data-note-front style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px">
          <p data-note-front-text role="status" aria-live="polite" style="margin:0;flex:1 1 260px;<?= $mono ?>font-size:11.5px;line-height:1.55;color:#FFAA00"></p>
          <button type="button" data-note-hide hidden class="h-ghost-text" style="min-height:44px;padding:0 16px;border:1px solid #2C353C;border-radius:2px;background:transparent;cursor:pointer;<?= $mono ?>font-size:11.5px;letter-spacing:0.08em;text-transform:uppercase;color:#B4BCC3;transition:border-color 160ms ease,color 160ms ease"<?= de('Notiz jetzt ausblenden') ?>>Hide the note now</button>
        </div>

        <?php /* Klingelt ein Timer oder der Wecker, gehoert ihm das ganze Panel. Der Satz sagt
                 das, der Knopf stoppt es. Wie bei der Notiz steht der Status von Anfang an da. */ ?>
        <div data-ring-front style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px">
          <p data-ring-front-text role="status" aria-live="assertive" style="margin:0;flex:1 1 260px;<?= $mono ?>font-size:11.5px;line-height:1.55;color:#3DE07C"></p>
          <button type="button" data-ring-stop hidden style="min-height:44px;padding:0 18px;border:1px solid #3DE07C;border-radius:2px;background:transparent;cursor:pointer;<?= $mono ?>font-size:11.5px;letter-spacing:0.08em;text-transform:uppercase;color:#3DE07C"<?= de('Stoppen') ?>>Stop</button>
        </div>

        <?php if ($ro): ?>
          <p style="margin:16px 0 0;padding:12px 14px;border:1px solid #2C353C;border-radius:2px;<?= $mono ?>font-size:11.5px;line-height:1.55;color:#B4BCC3"<?= de('Dieses Gerät ist für dich freigegeben, aber nur zum Ansehen. Notizen darfst du schreiben.') ?>>This device is shared with you for viewing only. You may still write notes.</p>
        <?php endif; ?>

        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:18px;align-items:center">
          <button type="button" data-apply style="min-height:48px;padding:0 26px;border:0;border-radius:2px;cursor:default;<?= $mono ?>font-size:12.5px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;transition:background 160ms ease,transform 160ms cubic-bezier(0.23,1,0.32,1);background:#1E252A;color:#8B949C"<?= de('Übernehmen') ?>>Apply</button>
          <button type="button" data-revert class="h-ghost-text" style="min-height:48px;padding:0 20px;border:1px solid #2C353C;border-radius:2px;background:transparent;cursor:pointer;<?= $mono ?>font-size:12.5px;letter-spacing:0.1em;text-transform:uppercase;color:#B4BCC3;transition:border-color 160ms ease,color 160ms ease"<?= de('Zurücksetzen') ?>>Discard</button>
          <p data-apply-status role="status" aria-live="polite" style="margin:0;<?= $mono ?>font-size:11.5px;color:#3DE07C;min-height:16px"></p>
          <button type="button" data-motion class="motion a-press" data-running="true" style="margin-left:auto"><span></span><span data-motion-label>Pause preview</span></button>
        </div>
      </div>
    </div>

    <?php if (!$ro): ?>
    <?php /* Timer und Wecker gelten sofort, ohne "Uebernehmen": sie haengen an keinem Modus.
             Ein laufender Timer steht in jedem Modus in einer Ecke des Panels. */ ?>
    <section id="timers" aria-labelledby="tw-title" style="margin-top:clamp(20px,3vw,32px)">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:1px">
        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px;min-width:0">
          <p id="tw-title" style="<?= $cardLabel ?>">Timer</p>
          <div role="group" aria-label="Start a timer" data-de-label="Timer starten" style="display:flex;flex-wrap:wrap;gap:5px">
            <?php foreach ([60, 180, 300, 600, 900, 1800] as $sec): ?>
              <button type="button" class="chip" data-timer-start="<?= $sec ?>" aria-label="Start a <?= $sec / 60 ?> minute timer" data-de-label="Timer über <?= $sec / 60 ?> <?= $sec === 60 ? 'Minute' : 'Minuten' ?> starten"><?= $sec / 60 ?> MIN</button>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;align-items:flex-end">
            <div style="flex:0 0 92px">
              <label for="tw-min" style="<?= $labelBlock ?>"<?= de('Minuten') ?>>Minutes</label>
              <input id="tw-min" type="number" min="1" max="1440" step="1" value="20" inputmode="numeric" class="field" aria-describedby="tw-error">
            </div>
            <div style="flex:1 1 130px;min-width:0">
              <label for="tw-label" style="<?= $labelBlock ?>"<?= de('Name, freiwillig') ?>>Name, optional</label>
              <input id="tw-label" type="text" maxlength="<?= TIMER_LABEL_MAX ?>" placeholder="Pasta" autocomplete="off" class="field">
            </div>
            <button type="button" data-timer-go class="btn-ghost" style="flex:0 0 auto"<?= de('Starten') ?>>Start</button>
          </div>
          <p id="tw-error" data-timer-error role="alert" style="margin:10px 0 0;<?= $mono ?>font-size:11.5px;line-height:1.5;color:#FF7A54"></p>
          <ul data-timer-list aria-label="Running timers" data-de-label="Laufende Timer" style="list-style:none;margin:6px 0 0;padding:0;display:flex;flex-direction:column;gap:1px;background:#1B2126"></ul>
          <p style="<?= $note ?>"<?= de('Solange er läuft, steht er in jedem Modus in einer Ecke des Panels. Danach sagt es das ganze Panel, bis du stoppst, höchstens 15 Minuten.') ?>>While it runs it sits in a corner of the panel in every mode. When it ends the whole panel says so until you stop it, at most 15 minutes.</p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px;min-width:0">
          <p id="al-title" style="<?= $cardLabel ?>"<?= de('Wecker') ?>>Alarm</p>
          <?php $al = alarm_get($s); ?>
          <div style="display:flex;align-items:flex-start;gap:14px">
            <button type="button" class="sw" role="switch" data-alarm-on aria-checked="<?= $on($al['on']) ?>" aria-labelledby="al-on-label" aria-describedby="al-on-text"><span></span></button>
            <div style="flex:1;min-width:0">
              <p id="al-on-label" style="margin:0 0 3px;font-size:14px;color:#E8EAEC"<?= de('Wecker an') ?>>Alarm on</p>
              <p id="al-on-text" style="margin:0;font-size:12px;line-height:1.45;color:#8B949C"<?= de('Klingelt in jedem Modus, auch bei ausgeschaltetem Panel. Ab Firmware 0.2.1 piept das Gerät dazu, klingelt auch ohne Internet, und ein Druck aufs Rad stoppt es.') ?>>Rings in every mode, even with the panel off. From firmware 0.2.1 the device also beeps, rings without internet, and a press on the wheel stops it.</p>
            </div>
          </div>
          <label for="al-time" style="<?= $labelBlock ?>;margin-top:18px"<?= de('Uhrzeit') ?>>Time</label>
          <input id="al-time" type="time" value="<?= h($al['time']) ?>" class="field" style="max-width:160px" aria-describedby="al-error">
          <p id="al-days-label" style="<?= $labelBlock ?>;margin-top:18px"<?= de('Tage') ?>>Days</p>
          <div role="group" aria-labelledby="al-days-label" style="display:flex;flex-wrap:wrap;gap:4px">
            <?php foreach ([[1, 'Mon', 'Mo'], [2, 'Tue', 'Di'], [3, 'Wed', 'Mi'], [4, 'Thu', 'Do'], [5, 'Fri', 'Fr'], [6, 'Sat', 'Sa'], [7, 'Sun', 'So']] as [$dn, $den, $dde]): ?>
              <button type="button" class="chip" data-alarm-day="<?= $dn ?>" aria-pressed="<?= $on(in_array($dn, $al['days'], true)) ?>"<?= de($dde) ?>><?= $den ?></button>
            <?php endforeach; ?>
          </div>
          <p data-alarm-next role="status" aria-live="polite" style="margin:14px 0 0;<?= $mono ?>font-size:11.5px;line-height:1.5;color:#FFAA00"></p>
          <p id="al-error" data-alarm-error role="alert" style="margin:6px 0 0;<?= $mono ?>font-size:11.5px;line-height:1.5;color:#FF7A54"></p>
        </article>
      </div>
    </section>
    <?php endif; ?>

    <section id="settings" style="margin-top:clamp(20px,3vw,32px)">
      <div data-pxwrap="1" style="margin:0 0 12px;max-width:560px"><canvas aria-hidden="true"></canvas></div>
      <h1 data-pxhead="1" data-settings-title>Flight mode</h1>

      <div data-panel-for="flight" style="<?= $gridCards ?>"<?= $s['mode'] === 'flight' ? '' : ' hidden' ?>>
        <article class="span-2" style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:0;grid-column:span 2;min-width:0">
          <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:10px 16px;padding:18px 22px 12px">
            <p style="margin:0;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Standort und Abdeckung') ?>>Location and coverage</p>
            <span data-out="place" style="<?= $mono ?>font-size:11px;color:#FFAA00"><?= h($s['location']['place']) ?></span>
            <span data-out="coords" style="<?= $mono ?>font-size:11px;color:#8B949C"></span>
            <span style="margin-left:auto;<?= $mono ?>font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C"<?= de('Suchen, klicken oder Nadel ziehen') ?>>Search, click or drag the pin</span>
          </div>
          <div data-map-slot></div>
          <div data-map-pending style="border-top:1px solid #1B2126;padding:clamp(24px,4vw,36px) 22px;display:flex;flex-wrap:wrap;align-items:center;gap:16px 24px;background:#080A0B">
            <div style="flex:1;min-width:200px">
              <p style="margin:0 0 6px;font-size:14px;color:#E8EAEC"<?= de('Karte auf Anforderung') ?>>Map on request</p>
              <p style="margin:0;font-size:12.5px;line-height:1.55;color:#8B949C;max-width:52ch"<?= de('Die Kartenbilder kommen von OpenStreetMap. Dabei erfährt OpenStreetMap deine IP-Adresse, weil dein Browser sie direkt lädt. Deshalb erst nach deinem Klick.') ?>>The map imagery comes from OpenStreetMap. Loading it tells OpenStreetMap your IP address, because your browser fetches it directly. So it waits for your click.</p>
            </div>
            <button type="button" data-load-map style="min-height:46px;padding:0 20px;border:1px solid #2C353C;border-radius:2px;background:transparent;cursor:pointer;<?= $mono ?>font-size:11.5px;letter-spacing:0.08em;text-transform:uppercase;color:#FFAA00;transition:border-color 160ms ease,background 160ms ease,transform 160ms cubic-bezier(0.23,1,0.32,1)"<?= de('Karte laden') ?><?= $dis ?>>Load the map</button>
          </div>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <label for="f-radius" style="<?= $labelBlock ?>"<?= de('Umkreis') ?>>Radius</label>
          <div style="display:flex;align-items:center;gap:12px">
            <input id="f-radius" data-set="flight.radius" type="range" min="5" max="150" step="5" value="<?= (int) $s['flight']['radius'] ?>" style="flex:1"<?= $dis ?>>
            <span style="<?= $mono ?>font-size:15px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums;flex:0 0 auto"><span data-out="radius"><?= (int) $s['flight']['radius'] ?></span> NM</span>
          </div>
          <p data-radius-note style="<?= $note ?>"></p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="f-alt-label" style="<?= $cardLabel ?>"<?= de('Höhenfilter') ?>>Altitude filter</p>
          <?= $sw('flight.alt', (bool) $s['flight']['alt'], 'f-alt-label', 'Hide high overflights', 'Hohe Überflieger ausblenden', 'Anything above 30,000 feet is ignored.', 'Alles über 30 000 Fuß wird ignoriert.') ?>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="f-mil-label" style="<?= $cardLabel ?>"<?= de('Militär') ?>>Military</p>
          <?= $sw('flight.mil', (bool) $s['flight']['mil'], 'f-mil-label', 'Show in a different colour', 'In anderer Farbe zeigen', 'adsb.lol supplies the flag.', 'adsb.lol liefert die Kennzeichnung mit.') ?>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <label for="f-dwell" style="<?= $labelBlock ?>"<?= de('Standzeit je Flug') ?>>Seconds per flight</label>
          <div style="display:flex;align-items:center;gap:12px">
            <input id="f-dwell" data-set="flight.dwell" type="range" min="<?= FLIGHT_DWELL_MIN ?>" max="<?= FLIGHT_DWELL_MAX ?>" step="1" value="<?= (int) $s['flight']['dwell'] ?>" style="flex:1"<?= $dis ?>>
            <span style="<?= $mono ?>font-size:15px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums;flex:0 0 auto"><span data-out="dwell"><?= (int) $s['flight']['dwell'] ?></span> S</span>
          </div>
          <p style="<?= $note ?>"<?= de('Danach schaut der Server neu. Ist ein anderes Flugzeug näher, wechselt das Panel dorthin, sonst bleibt der Flug stehen.') ?>>Then the server looks again. If another aircraft is closer, the panel switches to it, otherwise the flight stays.</p>

          <label for="f-view" style="<?= $labelBlock ?>;margin-top:22px"<?= de('Ansicht wechseln alle') ?>>Change view every</label>
          <div style="display:flex;align-items:center;gap:12px">
            <input id="f-view" data-set="flight.view" type="range" min="<?= FLIGHT_VIEW_MIN ?>" max="<?= FLIGHT_VIEW_MAX ?>" step="1" value="<?= (int) $s['flight']['view'] ?>" style="flex:1"<?= $dis ?>>
            <span style="<?= $mono ?>font-size:15px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums;flex:0 0 auto"><span data-out="view"><?= (int) $s['flight']['view'] ?></span> S</span>
          </div>
          <?php $round = 4 * (int) $s['flight']['view']; ?>
          <p data-view-note style="<?= $note ?>"<?= de('Route, Abflug und Ankunft, Position, Messwerte: eine Runde dauert ' . $round . ' s. Ein neuer Flug beginnt mit der Route.') ?>>Route, departure and arrival, position, metrics: one round takes <?= $round ?> s. A new flight starts with the route.</p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="f-views-label" style="<?= $cardLabel ?>"<?= de('Ansichten') ?>>Views</p>
          <p data-views-summary style="margin:0 0 14px;font-size:13.5px;line-height:1.5;color:#E8EAEC"></p>
          <button type="button" data-views-open class="btn-ghost"<?= $dis ?><?= de('Ansichten wählen') ?>>Choose views</button>
          <p style="<?= $note ?>"<?= de('Im Fenster stehen alle vier nebeneinander, gerechnet mit dem Flug, der gerade da ist.') ?>>The window shows all four side by side, drawn with the flight that is up right now.</p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="f-hold-label" style="<?= $cardLabel ?>"<?= de('Start und Landung') ?>>Takeoff and landing</p>
          <?= $sw('flight.hold', (bool) $s['flight']['hold'], 'f-hold-label', 'Stay with the aircraft', 'Beim Flugzeug bleiben', 'If an aircraft climbs or sinks below 10 000 feet at more than 300 feet per minute, the panel stays with it until it is through. Otherwise the dwell time decides.', 'Steigt oder sinkt ein Flugzeug unter 10 000 Fuß mit mehr als 300 Fuß pro Minute, bleibt das Panel bei ihm, bis es durch ist. Sonst zählt die Standzeit.') ?>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <label for="f-pin" style="<?= $labelBlock ?>"<?= de('Einzelflug anheften') ?>>Pin a flight</label>
          <input id="f-pin" data-set="flight.pin" type="text" value="<?= h($s['flight']['pin']) ?>" spellcheck="false" autocapitalize="characters" autocomplete="off" maxlength="12" placeholder="LGL9561" class="field" aria-describedby="f-pin-hint"<?= $dis ?>>
          <p id="f-pin-hint" style="<?= $note ?>"<?= de('Flugnummer, Rufzeichen oder Kennzeichen, egal wo auf der Welt. Das Panel zeigt dann nur diesen Flug: worüber er gerade fliegt, wann er gestartet ist und wann er landet. Er erscheint, sobald ein Empfänger ihn sieht. Über dem Atlantik gibt es kaum Empfänger, dort verschwindet er.') ?>>Flight number, callsign or registration, anywhere in the world. The panel then shows only this flight: what it is flying over, when it left and when it lands. It appears once a receiver sees it. There are few receivers over the Atlantic, so it drops out there.</p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="f-units-label" style="<?= $cardLabel ?>"<?= de('Einheiten') ?>>Units</p>
          <?php foreach ([
              ['ualt', 'Altitude', 'Höhe', [['ft', 'KFT'], ['m', 'M']]],
              ['uspd', 'Speed', 'Tempo', [['kmh', 'KM/H'], ['kt', 'KT'], ['mph', 'MPH']]],
              ['uvr', 'Climb', 'Steigen', [['ms', 'M/S'], ['fpm', 'FT/MIN']]],
              ['udist', 'Distance', 'Abstand', [['nm', 'NM'], ['km', 'KM'], ['mi', 'MI']]],
          ] as [$uk, $uen, $ude, $uopts]): ?>
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 12px;margin-top:8px">
              <span id="f-<?= $uk ?>-label" style="flex:0 0 84px;<?= $mono ?>font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#B4BCC3"<?= de($ude) ?>><?= $uen ?></span>
              <div role="radiogroup" aria-labelledby="f-units-label f-<?= $uk ?>-label" style="flex:1 1 150px;display:grid;grid-template-columns:repeat(<?= count($uopts) ?>,1fr);gap:4px">
                <?php foreach ($uopts as [$uval, $ulab]): ?>
                  <button type="button" class="two" role="radio" data-set="flight.<?= $uk ?>" data-value="<?= $uval ?>" aria-checked="<?= $on(($s['flight'][$uk] ?? '') === $uval) ?>"<?= $dis ?>><?= $ulab ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <p style="<?= $note ?>"<?= de('Für die Messwerte auf dem Panel und die Karte. Den Umkreis misst adsb.lol in Seemeilen, dabei bleibt es.') ?>>For the metrics on the panel and the map. adsb.lol measures the radius in nautical miles, so that stays.</p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="f-empty-label" style="<?= $cardLabel ?>"<?= de('Wenn nichts fliegt') ?>>When nothing is flying</p>
          <p style="margin:0 0 10px;font-size:12.5px;line-height:1.55;color:#8B949C"<?= de('Der Bildschirm geht nie ganz aus. Der Ruhezustand ist die leise Variante: Uhr schwach, etwas Staub, ab und zu ein Nachtflug.') ?>>The screen never goes fully off. The resting screen is the quiet version: a faint clock, some drifting dust, and now and then a night flight.</p>
          <div role="radiogroup" aria-labelledby="f-empty-label" style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;border-radius:2px">
            <?= $row('flight.empty', 'clock', $s['flight']['empty'] === 'clock', 'Show the clock', 'Uhr zeigen') ?>
            <?= $row('flight.empty', 'wait', $s['flight']['empty'] === 'wait', 'Say it is waiting', 'Warten anzeigen') ?>
            <?= $row('flight.empty', 'off', $s['flight']['empty'] === 'off', 'Resting screen', 'Ruhezustand') ?>
          </div>
        </article>
      </div>

      <div data-panel-for="clock" style="<?= $grid ?>"<?= $s['mode'] === 'clock' ? '' : ' hidden' ?>>
        <article style="background:#0B0D0F;padding:22px">
          <p id="c-face-label" style="<?= $cardLabel ?>"<?= de('Ziffernschrift') ?>>Digit face</p>
          <div role="radiogroup" aria-labelledby="c-face-label" style="display:flex;flex-direction:column;gap:4px">
            <?php foreach ([['small', 'Small', 'Schmal'], ['big', 'Bold', 'Fett'], ['seg', 'Segment', 'Segment']] as [$fid, $fen, $fde]): ?>
              <button type="button" class="face" role="radio" data-set="clock.face" data-value="<?= $fid ?>" aria-checked="<?= $on($s['clock']['face'] === $fid) ?>"<?= $dis ?>><span data-face="<?= $fid ?>" aria-hidden="true" style="display:block;flex:1;min-width:0"><canvas aria-hidden="true"></canvas></span><span class="facelabel"<?= de($fde) ?>><?= $fen ?></span></button>
            <?php endforeach; ?>
          </div>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <p id="c-col-label" style="<?= $cardLabel ?>"<?= de('Ziffernfarbe') ?>>Digit colour</p>
          <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px">
            <div role="radiogroup" aria-labelledby="c-col-label" style="display:flex;gap:6px">
            <?php foreach ([['#FFAA00', 'Amber', 'Bernstein'], ['#35D6FF', 'Cyan', 'Cyan'], ['#3DE07C', 'Green', 'Grün']] as [$hex, $cen, $cde]): ?>
              <button type="button" class="swatch" role="radio" data-colour="<?= $hex ?>" aria-checked="<?= $on(strtoupper($s['clock']['color']) === $hex) ?>" aria-label="<?= $cen ?>" data-de-label="<?= $cde ?>" style="--sw:<?= $hex ?>"<?= $dis ?>></button>
            <?php endforeach; ?>
            </div>
            <label class="more" data-more data-active="<?= $on(!in_array(strtoupper($s['clock']['color']), ['#FFAA00', '#35D6FF', '#3DE07C'], true)) ?>"><input type="color" data-set="clock.color" value="<?= h(strtolower($s['clock']['color'])) ?>" aria-label="Any colour" data-de-label="Beliebige Farbe"<?= $dis ?>><span<?= de('Mehr') ?>>More</span></label>
          </div>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <p id="c-fmt-label" style="<?= $cardLabel ?>"<?= de('Zeitformat') ?>>Time format</p>
          <div role="group" aria-labelledby="c-fmt-label" style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:16px">
            <button type="button" class="two" data-set="clock.h24" data-value="1" aria-pressed="<?= $on((bool) $s['clock']['h24']) ?>"<?= $dis ?>>24 H</button>
            <button type="button" class="two" data-set="clock.h24" data-value="0" aria-pressed="<?= $on(!$s['clock']['h24']) ?>"<?= $dis ?>>12 H</button>
          </div>
          <div style="display:flex;align-items:center;gap:14px">
            <button type="button" class="sw" role="switch" data-set="clock.sec" aria-checked="<?= $on((bool) $s['clock']['sec']) ?>" aria-labelledby="c-sec-label"<?= $dis ?>><span></span></button>
            <span id="c-sec-label" style="font-size:14px;color:#E8EAEC"<?= de('Sekunden anzeigen') ?>>Show seconds</span>
          </div>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <label for="c-tz" style="<?= $labelBlock ?>"<?= de('Zeitzone') ?>>Time zone</label>
          <select id="c-tz" data-set="tz" class="field" style="background:#0E1215"<?= $dis ?>>
            <?php $choices = tz_choices(); if (!isset($choices[$s['tz']])) { $choices = [$s['tz'] => $s['tz']] + $choices; } foreach ($choices as $zone => $zlabel): ?>
              <option value="<?= h($zone) ?>"<?= $zone === $s['tz'] ? ' selected' : '' ?>><?= h($zlabel) ?></option>
            <?php endforeach; ?>
          </select>
          <p style="<?= $note ?>"<?= de('Die Echtzeituhr auf dem Board hält die Zeit auch ohne WLAN. Sommerzeit macht die Zeitzone selbst.') ?>>The real-time clock on the board keeps time without Wi-Fi. The zone handles daylight saving itself.</p>
        </article>

        <?= $placeCard('c') ?>

        <article style="background:#0B0D0F;padding:22px">
          <p id="c-wx-label" style="<?= $cardLabel ?>"<?= de('Wetterstreifen') ?>>Weather strip</p>
          <?= $sw('clock.wx', (bool) $s['clock']['wx'], 'c-wx-label', 'Show under the clock', 'Unter der Uhr anzeigen', 'Twelve pixels tall: temperature and one word like CLEAR.', 'Zwölf Pixel hoch: Temperatur und ein Wort wie SONNIG.') ?>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <p id="c-night-label" style="<?= $cardLabel ?>"<?= de('Nachtabsenkung') ?>>Night dimming</p>
          <?= $sw('clock.night', (bool) $s['clock']['night'], 'c-night-label', 'Dimmer after sunset', 'Ab Sonnenuntergang dunkler', 'The panel drops to about a third of its brightness until sunrise.', 'Das Panel geht bis Sonnenaufgang auf etwa ein Drittel seiner Helligkeit.') ?>
        </article>
      </div>

      <div data-panel-for="notes" style="<?= $grid ?>"<?= $s['mode'] === 'notes' ? '' : ' hidden' ?>>
        <article class="span-2" style="background:#0B0D0F;padding:22px;grid-column:span 2;min-width:0">
          <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:8px">
            <label for="n-line1" style="flex:1;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Zeile 1') ?>>Line 1</label>
            <span data-count="line1" aria-live="polite" style="<?= $mono ?>font-size:11px;font-variant-numeric:tabular-nums;color:#8B949C"></span>
          </div>
          <input id="n-line1" data-set="notes.line1" type="text" value="<?= h($s['notes']['line1']) ?>" maxlength="21" placeholder="Alles Gute" autocomplete="off" class="field">

          <div style="display:flex;align-items:baseline;gap:10px;margin:18px 0 8px">
            <label for="n-line2" style="flex:1;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Zeile 2') ?>>Line 2</label>
            <span data-count="line2" aria-live="polite" style="<?= $mono ?>font-size:11px;font-variant-numeric:tabular-nums;color:#8B949C"></span>
          </div>
          <input id="n-line2" data-set="notes.line2" type="text" value="<?= h($s['notes']['line2']) ?>" maxlength="21" placeholder="zum Geburtstag" autocomplete="off" class="field">

          <p style="margin:16px 0 0;<?= $mono ?>font-size:11px;line-height:1.5;color:#8B949C"<?= de('Groß- und Kleinschreibung bleibt, wie du sie tippst. Umlaute schreibt der Server um: ö wird o, é wird e.') ?>>Capitalisation stays as you type it. The server rewrites accents: ö becomes o, é becomes e.</p>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <p id="n-flash-label" style="<?= $cardLabel ?>"<?= de('Bei neuer Notiz') ?>>On a new note</p>
          <?= $sw('notes.flash', (bool) $s['notes']['flash'], 'n-flash-label', 'Flash three times', 'Dreimal aufblinken', 'So you notice somebody wrote something.', 'Damit man merkt, dass jemand etwas geschrieben hat.') ?>
        </article>
      </div>

      <div data-panel-for="weather" style="<?= $grid ?>"<?= $s['mode'] === 'weather' ? '' : ' hidden' ?>>
        <?= $placeCard('w') ?>

        <article style="background:#0B0D0F;padding:22px">
          <p id="w-unit-label" style="<?= $cardLabel ?>"<?= de('Einheit') ?>>Unit</p>
          <div role="group" aria-labelledby="w-unit-label" style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:16px">
            <button type="button" class="two" data-set="weather.unit" data-value="C" aria-pressed="<?= $on($s['weather']['unit'] === 'C') ?>"<?= $dis ?>>&deg;C</button>
            <button type="button" class="two" data-set="weather.unit" data-value="F" aria-pressed="<?= $on($s['weather']['unit'] === 'F') ?>"<?= $dis ?>>&deg;F</button>
          </div>
          <div style="display:flex;align-items:center;gap:14px">
            <button type="button" class="sw" role="switch" data-set="weather.wind" aria-checked="<?= $on((bool) $s['weather']['wind']) ?>" aria-labelledby="w-wind-label"<?= $dis ?>><span></span></button>
            <span id="w-wind-label" style="font-size:14px;color:#E8EAEC"<?= de('Wind mit anzeigen') ?>>Show wind too</span>
          </div>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <p id="w-view-label" style="<?= $cardLabel ?>"<?= de('Ansicht') ?>>View</p>
          <div role="radiogroup" aria-labelledby="w-view-label" style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;border-radius:2px">
            <?= $row('weather.view', 'now', $s['weather']['view'] === 'now', 'Right now, large', 'Nur jetzt, groß') ?>
            <?= $row('weather.view', 'forecast', $s['weather']['view'] === 'forecast', 'Now and three days', 'Jetzt und drei Tage') ?>
          </div>
          <p style="margin:12px 0 0;<?= $mono ?>font-size:11px;line-height:1.55;color:#8B949C"<?= de('Open-Meteo, kostenlos und ohne Schlüssel. Der Server fragt alle 15 Minuten.') ?>>Open-Meteo, free and without a key. The server asks every 15 minutes.</p>
        </article>
      </div>

      <?php $tr = $s['transit']; ?>
      <div data-panel-for="transit" style="<?= $gridCards ?>"<?= $s['mode'] === 'transit' ? '' : ' hidden' ?>>
        <?php if (!transit_configured()): ?>
        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:18px 22px;grid-column:1 / -1;min-width:0">
          <p style="margin:0;font-size:13.5px;line-height:1.55;color:#FFC44D"<?= de('Im Admin-Bereich ist noch kein Schlüssel für mobiliteit.lu gespeichert. Ohne ihn gibt es keine Abfahrten.') ?>>No key for mobiliteit.lu has been saved in the admin area yet. Without it there are no departures.</p>
        </article>
        <?php endif; ?>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px;grid-column:1 / -1;min-width:0">
          <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:10px 14px;margin-bottom:14px">
            <p style="margin:0;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Haltestellen') ?>>Stops</p>
            <span data-t-count style="<?= $mono ?>font-size:11px;color:#8B949C"></span>
            <span style="margin-left:auto;<?= $mono ?>font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C"<?= de('Suchen oder Punkt anklicken') ?>>Search or click a dot</span>
          </div>

          <div data-t-map-slot></div>
          <div data-t-map-pending style="display:flex;flex-wrap:wrap;align-items:center;gap:14px 20px;padding:18px;margin-bottom:16px;border:1px solid #1B2126;border-radius:2px;background:#080A0B">
            <div style="flex:1;min-width:200px">
              <p style="margin:0 0 6px;font-size:14px;color:#E8EAEC"<?= de('Alle Haltestellen auf einer Karte') ?>>Every stop on a map</p>
              <p style="margin:0;font-size:12.5px;line-height:1.55;color:#8B949C;max-width:60ch"<?= de('Jede Haltestelle im Land als Punkt, mit Suche nach Ort oder Haltestelle. Die Kartenbilder kommen von OpenStreetMap, das dabei deine IP-Adresse erfährt. Deshalb erst nach deinem Klick.') ?>>Every stop in the country as a dot, with search by village or stop. The map imagery comes from OpenStreetMap, which then learns your IP address. So it waits for your click.</p>
            </div>
            <button type="button" data-t-load-map style="min-height:46px;padding:0 20px;border:1px solid #2C353C;border-radius:2px;background:transparent;cursor:pointer;<?= $mono ?>font-size:11.5px;letter-spacing:0.08em;text-transform:uppercase;color:#FFAA00;transition:border-color 160ms ease,background 160ms ease"<?= de('Karte laden') ?>>Load the map</button>
          </div>

          <div data-t-stops style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;border-radius:2px;margin-bottom:16px"></div>
          <p style="margin:0;<?= $mono ?>font-size:11px;line-height:1.55;color:#8B949C"<?= de('Höchstens drei je Gerät. Der Schlüssel erlaubt 500 Abfragen pro Stunde für alle Geräte zusammen, und eine Haltestelle braucht eine pro Minute.') ?>>At most three per device. The key allows 500 requests an hour across all devices, and one stop needs one a minute.</p>

          <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;margin-top:20px;padding-top:18px;border-top:1px solid #1B2126">
            <button type="button" data-t-find class="btn-ghost" style="color:#FFAA00"<?= $dis ?>></button>
            <p data-t-find-status role="status" aria-live="polite" style="margin:0;flex:1;min-width:200px;font-size:12.5px;line-height:1.5;color:#8B949C"<?= de('Im Umkreis von einem Kilometer um den Standort des Geräts. Den Standort setzt du bei Uhr oder Wetter, oder auf der Karte beim Flugradar.') ?>>Within a kilometre of the device location. You set the location under clock or weather, or on the map of the flight radar.</p>
          </div>
          <div data-t-near hidden style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;border-radius:2px;margin-top:14px"></div>

          <p style="margin:16px 0 0;<?= $mono ?>font-size:11px;line-height:1.6;color:#8B949C">Administration des transports publics, mobiliteit.lu &middot; CC BY 4.0</p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="t-modes" style="<?= $cardLabel ?>"<?= de('Verkehrsmittel') ?>>Modes</p>
          <div role="group" aria-labelledby="t-modes" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">
            <?php foreach ([['bus', 'Bus', 'Bus', '#FFAA00'], ['train', 'Train', 'Zug', '#35D6FF'], ['tram', 'Tram', 'Tram', '#3DE07C']] as [$mid, $men, $mde, $tint]): ?>
              <button type="button" class="chip tint" data-t-mode="<?= $mid ?>" aria-pressed="<?= $on(in_array($mid, $tr['modes'], true)) ?>" style="--tint:<?= $tint ?>"<?= de($mde) ?><?= $dis ?>><?= $men ?></button>
            <?php endforeach; ?>
          </div>
          <p style="margin:0;font-size:12.5px;line-height:1.55;color:#8B949C"<?= de('Einzelne Linien ausblenden geht darunter, sobald eine Haltestelle gewählt ist: ihre Linien kommen aus derselben Abfrage.') ?>>Hiding single lines appears below once a stop is chosen: its lines come from the same request.</p>
          <div data-t-lines-wrap hidden style="margin-top:16px;padding-top:14px;border-top:1px solid #1B2126">
            <button type="button" data-t-lines-toggle class="btn-ghost" aria-expanded="false" aria-controls="t-lines"></button>
            <div id="t-lines" data-t-lines role="group" aria-label="Lines shown" data-de-label="Angezeigte Linien" hidden style="display:flex;flex-wrap:wrap;gap:5px;margin-top:12px"></div>
          </div>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <label for="t-rows" style="<?= $labelBlock ?>"<?= de('Zeilen') ?>>Rows</label>
          <div style="display:flex;align-items:center;gap:12px">
            <input id="t-rows" data-set="transit.rows" type="range" min="3" max="5" step="1" value="<?= (int) $tr['rows'] ?>" style="flex:1"<?= $dis ?>>
            <span data-out="t-rows" style="<?= $mono ?>font-size:15px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums;flex:0 0 auto;width:16px;text-align:right"><?= (int) $tr['rows'] ?></span>
          </div>
          <p data-t-rows-note style="<?= $note ?>"></p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <label for="t-walk" style="<?= $labelBlock ?>"<?= de('Fußweg') ?>>Walking time</label>
          <div style="display:flex;align-items:center;gap:12px">
            <input id="t-walk" data-set="transit.walk" type="range" min="0" max="15" step="1" value="<?= (int) $tr['walk'] ?>" style="flex:1"<?= $dis ?>>
            <span style="<?= $mono ?>font-size:15px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums;flex:0 0 auto;width:38px;text-align:right"><span data-out="t-walk"><?= (int) $tr['walk'] ?></span> M</span>
          </div>
          <p style="<?= $note ?>"<?= de('Abfahrten, die du nicht mehr erreichst, fallen raus. Bei 0 steht alles da, auch was gerade wegfährt.') ?>>Departures you can no longer reach drop out. At 0 everything shows, including what is leaving now.</p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="t-fmt-label" style="<?= $cardLabel ?>"<?= de('Zeitangabe') ?>>Time shown as</p>
          <div role="group" aria-labelledby="t-fmt-label" style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
            <button type="button" class="two" data-set="transit.fmt" data-value="min" aria-pressed="<?= $on($tr['fmt'] === 'min') ?>"<?= de('Minuten') ?><?= $dis ?>>Minutes</button>
            <button type="button" class="two" data-set="transit.fmt" data-value="clock" aria-pressed="<?= $on($tr['fmt'] === 'clock') ?>"<?= $dis ?>>13:50</button>
          </div>
          <p style="<?= $note ?>"<?= de('Minuten sind der Standard: vier Zeichen mehr für den Ortsnamen.') ?>>Minutes are the default: four more characters for the place name.</p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="t-notes-label" style="<?= $cardLabel ?>"<?= de('Meldungen') ?>>Notes</p>
          <?= $sw('transit.notes', (bool) $tr['notes'], 't-notes-label', 'Scroll them along the bottom', 'Unten durchlaufen lassen', 'Notes run to 120 characters, which only fits as a ticker. Off leaves just the marker in the row.', 'Meldungen sind bis zu 120 Zeichen lang, das passt nur als Laufschrift. Aus bleibt nur das Zeichen in der Zeile.') ?>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="t-school-label" style="<?= $cardLabel ?>"<?= de('Schulbusse') ?>>School buses</p>
          <?= $sw('transit.school', (bool) $tr['school'], 't-school-label', 'Include them', 'Mit anzeigen', 'Lines with a letter and two digits such as K01 or L34. At a bus station in the morning they fill every row.', 'Linien mit Buchstabe und zwei Ziffern wie K01 oder L34. An einem Busbahnhof morgens füllen die alle Zeilen.') ?>
        </article>
      </div>

      <?php $sp = $s['spotify']; $spFwOld = !empty($device['fw']) && version_compare((string) $device['fw'], SPOTIFY_FW, '<'); ?>
      <div data-panel-for="spotify" style="<?= $gridCards ?>"<?= $s['mode'] === 'spotify' ? '' : ' hidden' ?>>
        <article id="spotify" style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px;grid-column:1 / -1;min-width:0">
          <p style="<?= $cardLabel ?>"<?= de('Spotify-Konto') ?>>Spotify account</p>
          <p data-sp-status role="status" aria-live="polite" style="margin:0 0 12px;font-size:14px;line-height:1.55;color:#E8EAEC;max-width:64ch"<?= de('Lädt …') ?>>Loading …</p>
          <p data-sp-now hidden style="margin:0 0 14px;font-size:13.5px;line-height:1.5;color:#B4BCC3;max-width:64ch"><span data-sp-now-text></span> <a data-sp-open href="https://open.spotify.com/" target="_blank" rel="noopener noreferrer" class="h-text" style="color:#1ED760;white-space:nowrap" hidden<?= de('Auf Spotify öffnen') ?>>Open on Spotify</a></p>
          <?php if ($role === 'owner'): ?>
          <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
            <form method="post" action="/spotify/connect" data-sp-connect hidden style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="device" value="<?= (int) $device['id'] ?>">
              <button type="submit" class="btn-solid"<?= de('Mit Spotify verbinden') ?>>Connect Spotify</button>
            </form>
            <button type="button" class="btn-ghost" data-sp-disconnect hidden<?= de('Verbindung trennen') ?>>Disconnect</button>
          </div>
          <p data-sp-dirty hidden style="<?= $note ?>;color:#FFC44D"<?= de('Erst übernehmen: der Weg zu Spotify lädt die Seite neu, der Entwurf ginge verloren.') ?>>Apply first: the trip to Spotify reloads the page, and the draft would be lost.</p>
          <?php endif; ?>
          <p style="<?= $note ?>"<?= de('THE WALL liest nur, was gerade läuft und was als Nächstes kommt, und steuert nichts. Im Entwicklungsmodus von Spotify muss jedes Konto vorher an der App dieses Servers freigeschaltet sein, höchstens fünf.') ?>>THE WALL only reads what is playing and what comes next, and controls nothing. In Spotify's development mode each account has to be enabled on this server's app first, five at most.</p>
          <p style="margin:12px 0 0;<?= $mono ?>font-size:11px;line-height:1.6;color:#8B949C"<?= de('Titel, Cover und Warteschlange kommen von Spotify.') ?>>Titles, covers and the queue come from Spotify.</p>
        </article>

        <?php if ($spFwOld): ?>
        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:18px 22px;grid-column:1 / -1;min-width:0">
          <p style="margin:0;font-size:13.5px;line-height:1.55;color:#FFC44D"<?= de('Zeitleiste, Platte und Übergänge brauchen Firmware ' . SPOTIFY_FW . '. Auf dem Gerät läuft ' . $device['fw'] . ': bis zum Update stehen Balken und Zeit und werden mit jedem Abruf neu gezeichnet, alle zehn Sekunden.') ?>>The time bar, the record and the transitions need firmware <?= SPOTIFY_FW ?>. The device runs <?= h((string) $device['fw']) ?>: until the update, bar and time stand still and are redrawn with every request, every ten seconds.</p>
        </article>
        <?php endif; ?>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px;grid-column:1 / -1;min-width:0">
          <p id="sp-layout-label" style="<?= $cardLabel ?>"<?= de('Layout') ?>>Layout</p>
          <div role="radiogroup" aria-labelledby="sp-layout-label" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,272px),1fr));gap:6px">
            <?php foreach ([
                ['A1', 'Classic', 'Klassisch', 'Cover left, then title, artist and album.', 'Cover links, daneben Titel, Künstler und Album.'],
                ['A2', 'Large', 'Groß', 'The cover fills the height, the text gets ten characters.', 'Das Cover füllt die Höhe, der Text bekommt zehn Zeichen.'],
                ['A3', 'Cover colour', 'Farbe aus dem Cover', 'Like Classic, artist and bar in the strongest colour of the cover.', 'Wie Klassisch, Künstler und Balken in der kräftigsten Farbe des Covers.'],
                ['A4', 'Record', 'Platte', 'A record turns behind the sleeve and slides back in on pause.', 'Hinter der Hülle dreht sich eine Platte, bei Pause gleitet sie zurück.'],
            ] as [$lid, $len, $lde, $lten, $ltde]): ?>
              <button type="button" class="face" role="radio" data-set="spotify.layout" data-value="<?= $lid ?>" aria-checked="<?= $on($sp['layout'] === $lid) ?>" style="flex-direction:column;align-items:stretch;gap:8px;padding:10px"<?= $dis ?>>
                <span data-sp-layout="<?= $lid ?>" aria-hidden="true" style="display:block;min-width:0;background:#050607;padding:4px 0"><canvas aria-hidden="true" style="display:block;margin:0 auto"></canvas></span>
                <span class="facelabel" style="text-align:left"<?= de($lde) ?>><?= $len ?></span>
                <span style="font-size:12px;line-height:1.45;color:#8B949C;text-align:left"<?= de($ltde) ?>><?= $lten ?></span>
              </button>
            <?php endforeach; ?>
          </div>
          <p style="<?= $note ?>"<?= de('Zehn Sekunden vor dem Ende sagt das Panel den nächsten Titel an. Läuft ein Titel aus, rollt die Walze, springst du am Handy weiter, dreht sich das Karussell.') ?>>Ten seconds before the end the panel announces the next track. When a track runs out the reel turns, when you skip on your phone the carousel spins.</p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="sp-views-label" style="<?= $cardLabel ?>"<?= de('Ansichten') ?>>Views</p>
          <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:#8B949C"<?= de('„Läuft“ ist immer dabei. Was du hier wählst, kommt im Wechsel dazu.') ?>>"Now playing" is always there. What you pick here joins in turn.</p>
          <div role="group" aria-labelledby="sp-views-label" style="display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach ([['album', 'Album', 'Album'], ['queue', 'Up next', 'Als Nächstes'], ['cover', 'Cover only', 'Nur Cover']] as [$vid, $ven, $vde]): ?>
              <button type="button" class="chip" data-sp-view="<?= $vid ?>" aria-pressed="<?= $on(in_array($vid, $sp['views'], true)) ?>"<?= de($vde) ?><?= $dis ?>><?= $ven ?></button>
            <?php endforeach; ?>
          </div>
          <label for="sp-view" style="<?= $labelBlock ?>;margin-top:22px"<?= de('Ansicht wechseln alle') ?>>Change view every</label>
          <div style="display:flex;align-items:center;gap:12px">
            <input id="sp-view" data-set="spotify.view" type="range" min="<?= SPOTIFY_VIEW_MIN ?>" max="<?= SPOTIFY_VIEW_MAX ?>" step="1" value="<?= (int) $sp['view'] ?>" style="flex:1"<?= $dis ?>>
            <span style="<?= $mono ?>font-size:15px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums;flex:0 0 auto"><span data-out="sp-view"><?= (int) $sp['view'] ?></span> S</span>
          </div>
          <p data-sp-views-note style="<?= $note ?>"></p>
        </article>

        <article style="background:#0B0D0F;box-shadow:0 0 0 1px #1B2126;padding:22px">
          <p id="sp-first-label" style="<?= $cardLabel ?>"<?= de('Vorrang') ?>>Priority</p>
          <?= $sw('spotify.first', !empty($sp['first']), 'sp-first-label', 'Music comes first', 'Musik geht vor', 'While music plays, the panel shows it instead of the chosen mode or the rotation. A new note still comes first. Without music the panel goes back.', 'Solange Musik läuft, zeigt das Panel sie statt des gewählten Modus oder der Rotation. Eine neue Notiz geht trotzdem vor. Ohne Musik geht es zurück.') ?>
          <p style="<?= $note ?>"<?= de('Im Wechsel fällt Spotify heraus, solange nichts läuft, auch nach zehn Minuten Pause.') ?>>In the rotation Spotify drops out while nothing plays, also after ten minutes of pause.</p>
        </article>
      </div>

      <div data-panel-for="pixel" style="background:#0B0D0F;border:1px dashed #2C353C;padding:clamp(24px,4vw,40px);text-align:center"<?= $s['mode'] === 'pixel' ? '' : ' hidden' ?>>
        <p style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#E09A1A"<?= de('Kommt nach dem 25.') ?>>After the 25th</p>
        <p style="margin:0;font-size:14.5px;line-height:1.6;color:#8B949C;max-width:46ch;margin-left:auto;margin-right:auto"<?= de('Alle 8192 Pixel einzeln setzen, mit Frames für kurze Animationen. Großes Stück Arbeit, deshalb nach dem 25.') ?>>Setting all 8192 pixels one by one, with frames for short animations. A big piece of work, so after the 25th.</p>
      </div>
    </section>

    <?php /* Die Leiste ganz unten, wie in Intro.dc.html vorgeschlagen: die Einfuehrung ins Geraet neben dem Weg zu den Einstellungen. Gaeste sehen nur die Einfuehrung. */ ?>
    <section style="margin-top:clamp(20px,3vw,32px);display:flex;flex-wrap:wrap;align-items:center;gap:16px 24px;padding:20px 22px;background:#0B0D0F;border:1px solid #1B2126">
      <div style="flex:1;min-width:200px">
        <?php if ($role === 'owner'): ?>
        <p style="margin:0 0 4px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Schlüssel, Firmware, Freigaben') ?>>Key, firmware, sharing</p>
        <p style="margin:0;font-size:13.5px;line-height:1.55;color:#8B949C"<?= de('Alles, was man selten anfasst, liegt auf der Einstellungsseite.') ?>>Everything you touch rarely lives on the settings page.</p>
        <?php else: ?>
        <p style="margin:0 0 4px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Das Gerät') ?>>The device</p>
        <p style="margin:0;font-size:13.5px;line-height:1.55;color:#8B949C"<?= de('Was die Tasten und das Rad tun und was das Panel sagt, wenn etwas fehlt.') ?>>What the buttons and the wheel do, and what the panel says when something is missing.</p>
        <?php endif; ?>
      </div>
      <button type="button" data-tour-open class="h-ghost-text" style="min-height:44px;<?= $mono ?>font-size:11.5px;letter-spacing:0.08em;text-transform:uppercase;padding:13px 18px;border:1px solid #2C353C;border-radius:2px;background:transparent;color:#B4BCC3;cursor:pointer"<?= de('So funktioniert das Gerät') ?>>How the device works</button>
      <?php if ($role === 'owner'): ?>
      <a href="/settings/<?= (int) $device['id'] ?>" class="h-ghost-text" style="<?= $mono ?>font-size:11.5px;letter-spacing:0.08em;text-transform:uppercase;padding:13px 18px;border:1px solid #2C353C;border-radius:2px;color:#B4BCC3"<?= de('Einstellungen öffnen') ?>>Open settings</a>
      <?php endif; ?>
    </section>
  <?php endif; ?>
  </main>


  <footer style="border-top:1px solid #1B2126">
    <div style="max-width:1400px;margin:0 auto;padding:24px 20px;display:flex;flex-wrap:wrap;gap:16px 28px;align-items:center;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em">
      <span style="color:#8B949C">THE WALL &middot; 2026</span>
      <a href="/" class="h-text hit" style="color:#8B949C"<?= de('Startseite') ?>>Home</a>
      <a href="/controls" class="h-text hit" style="color:#8B949C"<?= de('Bedienelemente') ?>>Controls</a>
      <span style="margin-left:auto;color:#8B949C"><?= h(site_host()) ?></span>
    </div>
  </footer>
</div>

  <?php if ($device && !$ro): ?>
  <?php /* Ansichten waehlen. Die vier Vorschauen rechnet der Server mit den echten Daten von
       jetzt, je eine Anfrage an /api/preview mit genau dieser einen Ansicht. */ ?>
  <div class="vp" data-viewpicker hidden>
    <div class="vp-box" role="dialog" aria-modal="true" aria-labelledby="vp-title" aria-describedby="vp-sub">
      <div class="vp-head">
        <div style="flex:1;min-width:200px">
          <p id="vp-title" style="margin:0 0 3px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#FFAA00"<?= de('Ansichten wählen') ?>>Choose views</p>
          <p id="vp-sub" style="margin:0;font-size:13px;line-height:1.5;color:#8B949C"<?= de('Jede Kachel zeigt, was das Panel gerade zeigen würde. Abgeschaltete Ansichten fallen aus dem Wechsel.') ?>>Each tile shows what the panel would show right now. Views you switch off drop out of the rotation.</p>
        </div>
        <p data-vp-state style="margin:0;<?= $mono ?>font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#8B949C"></p>
        <button type="button" data-vp-close class="btn-ghost"<?= de('Schließen') ?>>Close</button>
      </div>
      <div class="vp-body">
        <?php foreach ([
            ['route', 'Route', 'Route', 'Airline, where from and where to, aircraft type.', 'Airline, von wo nach wo, Flugzeugtyp.'],
            ['progress', 'Departure and arrival', 'Abflug und Ankunft', 'How long it has been flying and how long it still needs, with a bar.', 'Wie lange er schon fliegt und wie lange er noch braucht, mit Balken.'],
            ['position', 'Position', 'Position', 'What the aircraft is flying over right now.', 'Worüber das Flugzeug gerade fliegt.'],
            ['metrics', 'Metrics', 'Messwerte', 'Altitude, speed, climb rate, distance.', 'Höhe, Tempo, Steigrate, Abstand.'],
            ['map', 'Map', 'Karte', 'Where the flight is: the whole route, or the airfield up close at takeoff and landing. Stays twice as long as a text view.', 'Wo der Flug ist: die ganze Strecke, bei Start und Landung der Flugplatz aus der Nähe. Sie steht doppelt so lange wie eine Textansicht.'],
        ] as [$vk, $ven, $vde, $vten, $vtde]): ?>
        <article class="vp-tile">
          <div class="vp-screen">
            <div><canvas data-vp-canvas="<?= $vk ?>" role="img" aria-label="Preview: <?= $ven ?>" data-de-label="Vorschau: <?= $vde ?>"></canvas></div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:14px">
            <button type="button" class="sw" role="switch" data-vp-view="<?= $vk ?>" aria-checked="true" aria-labelledby="vp-<?= $vk ?>-label"><span></span></button>
            <div style="flex:1;min-width:0">
              <p id="vp-<?= $vk ?>-label" style="margin:0 0 3px;font-size:14px;color:#E8EAEC"<?= de($vde) ?>><?= $ven ?></p>
              <p style="margin:0;font-size:12px;line-height:1.45;color:#8B949C"<?= de($vtde) ?>><?= $vten ?></p>
              <p data-vp-note="<?= $vk ?>" role="status" style="margin:6px 0 0;font-size:11.5px;line-height:1.45;color:#FFAA00"></p>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <div class="vp-foot">
        <p style="flex:1;min-width:200px;margin:0;font-size:12.5px;line-height:1.5;color:#8B949C"<?= de('Die Wahl gilt, sobald du auf der Seite „Übernehmen“ drückst.') ?>>Your choice takes effect once you press Apply on the page.</p>
        <button type="button" data-vp-done class="btn-solid"<?= de('Fertig') ?>>Done</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($device && !$ro): ?>
  <?php /* Alle Modi nebeneinander, jeder mit dem Bild, das er gerade zeigen wuerde.
       Die Knoepfe sind dieselben wie in der Spalte (data-set, data-rotation), damit
       Auswahl und Rotation an einer Stelle gepflegt werden. */ ?>
  <div class="vp" data-modepicker hidden>
    <div class="vp-box" role="dialog" aria-modal="true" aria-labelledby="mp-title" aria-describedby="mp-sub">
      <div class="vp-head">
        <div style="flex:1;min-width:200px">
          <p id="mp-title" style="margin:0 0 3px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#FFAA00"<?= de('Alle Modi') ?>>All modes</p>
          <p id="mp-sub" style="margin:0;font-size:13px;line-height:1.5;color:#8B949C"<?= de('Jede Kachel zeigt, was das Panel in diesem Modus gerade zeigen würde. Oben wählst du den Modus, darunter, ob er im Wechsel mitläuft.') ?>>Each tile shows what the panel would show in that mode right now. Pick the mode above, and below whether it joins the rotation.</p>
        </div>
        <p data-mp-state style="margin:0;<?= $mono ?>font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#8B949C"></p>
        <button type="button" data-mp-close class="btn-ghost"<?= de('Schließen') ?>>Close</button>
      </div>
      <div class="vp-body">
        <?php foreach ($modes as $mid => $m): ?>
        <article class="vp-tile">
          <div class="vp-screen">
            <div><canvas data-mp-canvas="<?= h($mid) ?>" role="img" aria-label="Preview: <?= h($m['label']['en']) ?>" data-de-label="Vorschau: <?= h($m['label']['de']) ?>"></canvas></div>
          </div>
          <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px">
            <button type="button" class="rrow" role="radio" data-set="mode" data-value="<?= h($mid) ?>" aria-checked="<?= $on($s['mode'] === $mid) ?>" style="flex:1 1 150px"><span class="rdot"></span><span style="flex:1;text-align:left"<?= de($m['label']['de']) ?>><?= h($m['label']['en']) ?></span></button>
            <?php if (!empty($m['rotatable'])): ?>
            <button type="button" class="chip" data-rotation="<?= h($mid) ?>" aria-pressed="<?= $on(in_array($mid, $s['rotation'], true)) ?>"<?= de('Im Wechsel') ?>>In rotation</button>
            <?php endif; ?>
          </div>
          <p data-mp-note="<?= h($mid) ?>" role="status" style="margin:0;font-size:11.5px;line-height:1.45;color:#FFAA00"></p>
        </article>
        <?php endforeach; ?>
      </div>
      <div class="vp-foot">
        <p style="flex:1;min-width:200px;margin:0;font-size:12.5px;line-height:1.5;color:#8B949C"<?= de('Die Wahl gilt, sobald du auf der Seite „Übernehmen“ drückst.') ?>>Your choice takes effect once you press Apply on the page.</p>
        <button type="button" data-mp-done class="btn-solid"<?= de('Fertig') ?>>Done</button>
      </div>
    </div>
  </div>
  <?php endif; ?>
