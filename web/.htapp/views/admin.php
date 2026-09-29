<?php
/**
 * @var array $user @var array $devices @var int $online @var int $old @var int $onLatest @var ?array $latest
 * @var array $budgets @var array $smtp @var array $spotify @var array $transit @var array $stops @var int $stopsInUse @var array $imprint @var array $logos @var string $registration @var int $rollout
 * @var bool $auto @var array $events @var string $userAgent @var int $users @var array $accounts
 */
$mono = "font-family:'IBM Plex Mono',monospace;";
$h2 = 'margin:0 0 16px;' . $mono . 'font-size:12px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#E8EAEC;padding-bottom:10px;border-bottom:1px solid #1B2126';
$grid = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:1px;background:#1B2126;border:1px solid #1B2126';
$labelBlock = 'display:block;margin-bottom:8px;' . $mono . 'font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3';
$statusStyle = 'margin:12px 0 0;' . $mono . 'font-size:11px;color:#3DE07C;min-height:16px';
$tag = static function (string $kind): string {
    $map = ['ok' => ['rgba(61,224,124,0.14)', '#3DE07C'], 'warn' => ['rgba(255,170,0,0.16)', '#FFC44D'], 'off' => ['#1E252A', '#B4BCC3'], 'bad' => ['rgba(255,74,28,0.14)', '#FF7A54']];
    [$bg, $fg] = $map[$kind];
    return "font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;padding:3px 7px;border-radius:1px;flex:0 0 auto;background:$bg;color:$fg";
};
$nf = static fn(int $n): string => number_format($n, 0, '.', ',');
$nfDe = static fn(int $n): string => number_format($n, 0, ',', ' ');
$devCount = count(array_filter($devices, static fn(array $d): bool => !device_is_test($d)));

$b = $budgets;
$without = max(1, $devCount) * 360;
$cachedShare = $b['adsbdb']['cached_share'];
// Keiner der beiden Dienste nennt eine Grenze. Der Balken zeigt deshalb keinen Anteil an
// einem erfundenen Kontingent (Befund A20): adsb.lol gegen die Abfragen ohne Zwischenspeicher,
// adsbdb den Teil, der nicht aus dem Zwischenspeicher kam.
$adsbShare = min(1, $b['adsblol']['hour'] / $without);
$dbShare = $cachedShare === null ? 0.0 : min(1, (100 - $cachedShare) / 100);
$vrsCached = $b['vrs']['cached_share'];
$vrsShare = $vrsCached === null ? 0.0 : min(1, (100 - $vrsCached) / 100);
$meteoShare = min(1, $b['meteo']['day'] / 10000);
$nomShare = min(1, $b['nominatim']['hour'] / 3600);
$transitShare = min(1, max($b['transit']['hour'] / 500, $b['transit']['day'] / 5000));
$level = static fn(float $s): string => $s > 0.8 ? 'bad' : ($s > 0.5 ? 'warn' : 'ok');
$colour = static fn(float $s): string => $s > 0.8 ? '#FF4A1C' : ($s > 0.5 ? '#FFAA00' : '#3DE07C');
$tagText = static fn(string $k): array => $k === 'ok' ? ['Healthy', 'Gesund'] : ($k === 'warn' ? ['Watch', 'Beobachten'] : ['Act now', 'Handeln']);
$budgetCard = static function (string $name, float $share, string $valueHtml, string $unitEn, string $unitDe, string $textEn, string $textDe) use ($tag, $level, $colour, $tagText, $mono): string {
    $k = $level($share);
    [$ten, $tde] = $tagText($k);
    return '<article style="background:#0B0D0F;padding:22px">'
        . '<div style="display:flex;align-items:baseline;gap:10px;margin-bottom:12px">'
        . '<p style="margin:0;flex:1;' . $mono . 'font-size:12px;letter-spacing:0.06em;color:#E8EAEC">' . h($name) . '</p>'
        . '<span style="' . $tag($k) . '"' . de($tde) . '>' . h($ten) . '</span></div>'
        . '<div data-budget data-share="' . round($share, 4) . '" data-colour="' . $colour($share) . '" aria-hidden="true" style="margin-bottom:10px"><canvas aria-hidden="true"></canvas></div>'
        . '<p style="margin:0 0 4px;' . $mono . 'font-size:19px;font-weight:600;color:' . $colour($share) . ';font-variant-numeric:tabular-nums">' . $valueHtml
        . ' <span style="font-size:12px;font-weight:400;color:#8B949C"' . de($unitDe) . '>' . h($unitEn) . '</span></p>'
        . '<p style="margin:0;font-size:12.5px;line-height:1.55;color:#8B949C"' . de($textDe) . '>' . h($textEn) . '</p></article>';
};
$regOpen = $registration === 'open';
?>
<div style="min-height:100vh;display:flex;flex-direction:column">

  <?= skip_link('#main', 'Skip to content', 'Zum Inhalt springen') ?>

  <header style="position:sticky;top:0;z-index:30;background:rgba(8,9,10,0.9);backdrop-filter:blur(12px);border-bottom:1px solid #1B2126">
    <div style="max-width:1320px;margin:0 auto;padding:12px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 20px">
      <?= brand_mark('/') ?>
      <span style="padding:4px 9px;border:1px solid #6B2313;border-radius:2px;<?= $mono ?>font-size:10px;letter-spacing:0.14em;text-transform:uppercase;color:#FF7A54">Admin</span>
      <nav style="display:flex;gap:2px;padding-left:16px;border-left:1px solid #232A30" aria-label="Sections" data-de-label="Bereiche">
        <a href="/device" class="h-nav hit" style="padding:7px 12px;border-radius:2px;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#8B949C"<?= de('Gerät') ?>>Device</a>
        <a href="/settings" class="h-nav hit" style="padding:7px 12px;border-radius:2px;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#8B949C"<?= de('Einstellungen') ?>>Settings</a>
      </nav>
      <div style="flex:1 0 0;min-width:0"></div>
      <?= lang_switch() ?>
    </div>
  </header>

  <main id="main" style="flex:1;width:100%;max-width:1320px;margin:0 auto;padding:clamp(28px,4vw,44px) 20px">

    <div data-pxwrap="1" style="margin:0 0 14px;max-width:600px"><canvas aria-hidden="true"></canvas></div>
    <h1 data-pxhead="1"<?= de('Verwaltung') ?>>Operations</h1>
    <p style="margin:0 0 clamp(28px,4vw,40px);max-width:58ch;font-size:15.5px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Nur für dich. Das Wichtigste hier sind nicht die Nutzerzahlen, sondern die Abfragebudgets: die Dienste kosten nichts, aber sie sperren dich aus, wenn der Server zu oft fragt.') ?>>Only for you. The point of this page is not the user count but the request budgets: the services cost nothing, yet they will lock you out if the server asks too often.</p>

    <section style="margin-bottom:clamp(32px,4vw,48px)">
      <h2 style="<?= $h2 ?>"<?= de('Abfragebudgets') ?>>Request budgets</h2>
      <div style="<?= $grid ?>">
        <?= $budgetCard('adsb.lol', $adsbShare, $nf($b['adsblol']['hour']), '/ h, no published ceiling', '/ Std, keine veröffentlichte Grenze',
            'Without caching this would be ' . $nf($without) . ', ' . max(1, $devCount) . ' ' . (max(1, $devCount) === 1 ? 'device' : 'devices') . ' asking every ten seconds. Two in the same radius share one request.',
            'Ohne Zwischenspeicher wären es ' . $nfDe($without) . ', also ' . max(1, $devCount) . ' ' . (max(1, $devCount) === 1 ? 'Gerät' : 'Geräte') . ' im Zehn-Sekunden-Takt. Zwei im selben Umkreis teilen eine Abfrage.') ?>
        <?= $budgetCard('adsb.fi', min(1, $b['adsbfi']['hour'] / max(1, $without)), $nf($b['adsbfi']['hour']), '/ h, no published ceiling', '/ Std, keine veröffentlichte Grenze',
            'Second network with the same fields, asked together with adsb.lol and merged: each has receivers the other lacks. If one fails, the other is enough.',
            'Zweites Netz mit denselben Feldern, zusammen mit adsb.lol gefragt und zusammengelegt: jedes hat Empfänger, die dem anderen fehlen. Fällt eines aus, reicht das andere.') ?>
        <?= $budgetCard('VRS routes', $vrsShare, $nf($b['vrs']['hour']), '/ h, static files', '/ Std, feste Dateien',
            'Routes from the Virtual Radar Server standing data, one file per callsign, mirrored hourly by adsb.lol. Kept six hours' . ($vrsCached === null ? '.' : ', so ' . $vrsCached . ' percent come from cache.'),
            'Strecken aus den Standdaten von Virtual Radar Server, eine Datei je Rufzeichen, stündlich gespiegelt von adsb.lol. Sechs Stunden gemerkt' . ($vrsCached === null ? '.' : ', deshalb liegen ' . $vrsCached . ' % im Zwischenspeicher.')) ?>
        <?= $budgetCard('adsbdb.com', $dbShare, $nf($b['adsbdb']['hour']), '/ h, no published ceiling', '/ Std, keine veröffentlichte Grenze',
            'Airline names, and the route when VRS has none that fits the aircraft. A callsign is kept 30 days' . ($cachedShare === null ? '.' : ', so ' . $cachedShare . ' percent come from cache.'),
            'Airline-Namen, und die Strecke, wenn VRS keine kennt, die zum Flugzeug passt. Ein Rufzeichen bleibt 30 Tage gemerkt' . ($cachedShare === null ? '.' : ', deshalb liegen ' . $cachedShare . ' % im Zwischenspeicher.')) ?>
        <?= $budgetCard('Open-Meteo', $meteoShare, $nf($b['meteo']['day']), '/ 10,000 d', '/ 10 000 T',
            'At ' . (int) round($meteoShare * 100) . ' percent of the daily limit. Each location is cached for 15 minutes.',
            'Bei ' . (int) round($meteoShare * 100) . ' % des Tageslimits. Jeder Ort bleibt 15 Minuten im Zwischenspeicher.') ?>
        <?= $budgetCard('Nominatim', $nomShare, $nf($b['nominatim']['hour']), '/ 3,600 h', '/ 3 600 Std',
            'Place search only runs on submit, never while typing. That is the service\'s own rule.',
            'Ortssuche läuft nur auf Absenden, nicht beim Tippen. Das ist die Regel des Dienstes.') ?>
        <?= $budgetCard('mobiliteit.lu', $transitShare, $nf($b['transit']['hour']), '/ 500 h, ' . $nf($b['transit']['day']) . ' / 5,000 d', '/ 500 Std, ' . $nfDe($b['transit']['day']) . ' / 5 000 T',
            'Quota of the personal key. The server stops at 450 per hour and 4,500 per day, so the key never gets blocked.',
            'Kontingent des persönlichen Schlüssels. Der Server hört bei 450 pro Stunde und 4 500 pro Tag auf, damit der Schlüssel nie gesperrt wird.') ?>
      </div>
    </section>

    <section style="margin-bottom:clamp(32px,4vw,48px)">
      <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:14px;padding-bottom:10px;border-bottom:1px solid #1B2126;margin-bottom:16px">
        <h2 style="margin:0;<?= $mono ?>font-size:12px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#E8EAEC"<?= de('Geräte') ?>>Devices</h2>
        <span style="<?= $mono ?>font-size:11px;color:#8B949C"><?= $devCount ?> &middot; <?= $online ?> ONLINE</span>
        <?php if ($old > 0): ?>
          <span style="margin-left:auto;<?= $mono ?>font-size:11px;color:#FF7A54"<?= de($old . ' auf alter Firmware') ?>><?= $old ?> on old firmware</span>
        <?php endif; ?>
      </div>

      <div style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;overflow:hidden">
        <div style="display:flex;flex-wrap:wrap;gap:8px 16px;padding:10px 14px;background:#0E1215;<?= $mono ?>font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:#8B949C">
          <span style="flex:1 1 130px;min-width:0"<?= de('Gerät') ?>>Device</span>
          <span style="flex:1 1 110px;min-width:0"<?= de('Besitzer') ?>>Owner</span>
          <span style="flex:0 0 74px">Firmware</span>
          <span style="flex:0 0 86px"<?= de('Gesehen') ?>>Seen</span>
          <span style="flex:0 0 66px"<?= de('Modus') ?>>Mode</span>
        </div>
        <?php if (!$devices): ?>
          <div style="padding:13px 14px;background:#0B0D0F;<?= $mono ?>font-size:12px;color:#8B949C"<?= de('Noch hat sich kein Gerät gemeldet.') ?>>No device has checked in yet.</div>
        <?php endif; ?>
        <?php foreach ($devices as $d): $on = device_online($d); $s = device_settings($d); $m = mode_get((string) $s['mode']); [$aEn, $aDe] = ago_pair($d['last_seen_at'] ? (int) $d['last_seen_at'] : null); $stale = !$d['last_seen_at'] || (int) $d['last_seen_at'] < time() - 86400; $oldFw = $latest && $d['fw'] !== null && version_compare((string) $d['fw'], $latest['version'], '<'); ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px 16px;padding:13px 14px;background:#0B0D0F;<?= $mono ?>font-size:12px;align-items:center">
          <span style="flex:1 1 130px;min-width:0;display:flex;align-items:center;gap:9px;color:<?= $on ? '#E8EAEC' : '#8B949C' ?>"><span style="width:7px;height:7px;background:<?= $on ? '#3DE07C' : '#4A565F' ?>;border-radius:1px;flex:0 0 auto"></span><?= h($d['name']) ?><?php if (device_is_test($d)): ?> <span style="<?= $tag('off') ?>">Test</span><?php endif; ?></span>
          <span style="flex:1 1 110px;min-width:0;color:#B4BCC3"><?= h($d['owner_name']) ?></span>
          <span style="flex:0 0 74px;color:<?= $oldFw ? '#FF7A54' : ($d['fw'] !== null ? '#3DE07C' : '#8B949C') ?>"><?= $d['fw'] !== null ? h($d['fw']) : '?' ?></span>
          <span style="flex:0 0 86px;color:<?= $stale ? '#FF7A54' : '#B4BCC3' ?>"<?= de($aDe) ?>><?= h($aEn) ?></span>
          <?php if ($stale): ?>
            <span style="flex:0 0 66px;color:#8B949C"<?= de('aus') ?>>off</span>
          <?php else: ?>
            <span style="flex:0 0 66px;color:#8B949C"<?= de($m ? $m['label']['de'] : '') ?>><?= h($m ? $m['label']['en'] : '') ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section style="margin-bottom:clamp(32px,4vw,48px)">
      <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:14px;padding-bottom:10px;border-bottom:1px solid #1B2126;margin-bottom:16px">
        <h2 style="margin:0;<?= $mono ?>font-size:12px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#E8EAEC"<?= de('Konten') ?>>Accounts</h2>
        <span style="<?= $mono ?>font-size:11px;color:#8B949C"><?= count($accounts) ?></span>
      </div>
      <p style="margin:0 0 16px;max-width:60ch;font-size:14px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Ein Testgerät zeigt einem Konto die Geräteseite mit allen Modi und der Vorschau, ohne Hardware. Es fragt den Server nie selbst und zählt nicht bei Budgets und Firmware.') ?>>A test device shows an account the device page with every mode and the preview, without hardware. It never polls the server and does not count towards budgets or firmware.</p>

      <div style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;overflow:hidden">
        <div style="display:flex;flex-wrap:wrap;gap:8px 16px;padding:10px 14px;background:#0E1215;<?= $mono ?>font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:#8B949C">
          <span style="flex:1 1 180px;min-width:0"<?= de('Konto') ?>>Account</span>
          <span style="flex:0 0 66px"<?= de('Rolle') ?>>Role</span>
          <span style="flex:0 0 66px"<?= de('Geräte') ?>>Devices</span>
          <span style="flex:0 0 124px"<?= de('Testgerät') ?>>Test device</span>
        </div>
        <?php foreach ($accounts as $a): $hasTest = (int) $a['tests'] > 0; ?>
        <div data-account="<?= (int) $a['id'] ?>" style="display:flex;flex-wrap:wrap;gap:8px 16px;padding:10px 14px;background:#0B0D0F;<?= $mono ?>font-size:12px;align-items:center">
          <span style="flex:1 1 180px;min-width:0;display:flex;flex-direction:column;gap:2px">
            <span style="color:#E8EAEC"><?= h($a['username']) ?></span>
            <span style="font-size:11px;color:#8B949C;word-break:break-all"><?= h($a['email']) ?><?php if ($a['email_verified_at'] === null): ?> <span<?= de('· unbestätigt') ?>>· unconfirmed</span><?php endif; ?></span>
          </span>
          <span style="flex:0 0 66px;color:<?= $a['role'] === 'admin' ? '#FF7A54' : '#B4BCC3' ?>"><?= h($a['role']) ?></span>
          <span data-account-devices style="flex:0 0 66px;color:#B4BCC3"><?= (int) $a['devices'] ?></span>
          <span style="flex:0 0 124px">
            <button type="button" class="btn-ghost" style="width:100%;padding:0 10px;white-space:nowrap" data-test-toggle data-user="<?= (int) $a['id'] ?>" data-name="<?= h($a['username']) ?>" data-on="<?= $hasTest ? 'true' : 'false' ?>" aria-label="<?= h(($hasTest ? 'Remove test device from ' : 'Add test device for ') . $a['username']) ?>" data-de-label="<?= h('Testgerät ' . ($hasTest ? 'von ' . $a['username'] . ' entfernen' : 'für ' . $a['username'] . ' hinzufügen')) ?>"<?= de($hasTest ? 'Entfernen' : 'Hinzufügen') ?>><?= $hasTest ? 'Remove' : 'Add' ?></button>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <p data-accounts-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
    </section>

    <section style="margin-bottom:clamp(32px,4vw,48px)">
      <h2 style="<?= $h2 ?>"<?= de('Firmware ausrollen') ?>>Firmware rollout</h2>
      <div style="<?= $grid ?>">

        <article class="span-2" style="background:#0B0D0F;padding:22px;grid-column:span 2;min-width:0">
          <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:10px 14px;margin-bottom:16px">
            <?php if ($latest): ?>
              <p style="margin:0;<?= $mono ?>font-size:22px;font-weight:600;color:#E8EAEC"><?= h($latest['version']) ?></p>
              <span style="<?= $tag('ok') ?>"<?= de('Aktuell') ?>>Current</span>
              <span style="margin-left:auto;<?= $mono ?>font-size:11px;color:#8B949C"<?= de($onLatest . ' von ' . $devCount . ' Geräten') ?>><?= $onLatest ?> of <?= $devCount ?> devices</span>
            <?php else: ?>
              <p style="margin:0;<?= $mono ?>font-size:22px;font-weight:600;color:#8B949C"<?= de('Keine Datei') ?>>No file yet</p>
              <span style="<?= $tag('off') ?>"<?= de('Leer') ?>>Empty</span>
            <?php endif; ?>
          </div>

          <label for="rollout" style="<?= $labelBlock ?>"<?= de('Anteil, der es bekommt') ?>>Share that receives it</label>
          <div style="display:flex;align-items:center;gap:12px">
            <input id="rollout" data-rollout type="range" min="0" max="100" step="5" value="<?= $rollout ?>" style="flex:1;height:44px">
            <span style="<?= $mono ?>font-size:15px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums;flex:0 0 auto;width:48px;text-align:right"><span data-rollout-out><?= $rollout ?></span>%</span>
          </div>
          <p data-rollout-note style="margin:12px 0 0;<?= $mono ?>font-size:11px;line-height:1.55;color:#8B949C"></p>
          <p data-rollout-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>

          <div style="margin-top:18px;padding-top:16px;border-top:1px solid #1B2126">
            <label for="fw-file" style="<?= $labelBlock ?>"<?= de('Neue Fassung hochladen') ?>>Upload a new build</label>
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px">
              <input id="fw-file" data-fw-file type="file" accept=".bin,application/octet-stream" class="field" style="flex:1 1 220px;min-width:0;min-height:44px" aria-describedby="fw-file-note">
              <button type="button" data-fw-upload class="btn-ghost" style="color:#FFAA00"<?= de('Hochladen') ?>>Upload</button>
            </div>
            <p id="fw-file-note" style="margin:10px 0 0;<?= $mono ?>font-size:11px;line-height:1.55;color:#8B949C"<?= de('firmware.bin aus firmware/.pio/build/wall nach pio run. Der Server liest die Version aus der Datei (ab 0.1.1), prüft, ob es ein App-Image für den ESP32 bis 4 MB ist, und legt es als thewall-<version>.bin ab. Wer Zugriff auf den Server hat, kann die Datei auch so nach .htdata/firmware legen.') ?>>firmware.bin from firmware/.pio/build/wall after pio run. The server reads the version from the file (0.1.1 and newer), checks that it is an ESP32 app image up to 4 MB and stores it as thewall-&lt;version&gt;.bin. With access to the server you can also put the file into .htdata/firmware yourself.</p>
            <p data-fw-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
          </div>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <p id="adm-auto" style="margin:0 0 10px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Automatisch') ?>>Automatic</p>
          <div style="display:flex;align-items:flex-start;gap:14px">
            <button type="button" class="sw" role="switch" data-auto aria-checked="<?= $auto ? 'true' : 'false' ?>" aria-labelledby="adm-auto adm-auto-text" aria-describedby="adm-auto-note"><span></span></button>
            <div style="flex:1;min-width:0">
              <p id="adm-auto-text" style="margin:0 0 3px;font-size:14px;color:#E8EAEC"<?= de('Ohne Nachfrage aktualisieren') ?>>Update without asking</p>
              <p id="adm-auto-note" style="margin:0;font-size:12px;line-height:1.45;color:#8B949C"<?= de('Aus, weil ein fehlerhaftes Update sonst alle Geräte gleichzeitig trifft. Erst eins, dann der Rest.') ?>>Off, because a bad update would otherwise hit every device at once. One first, then the rest.</p>
            </div>
          </div>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <p id="adm-reg" style="margin:0 0 10px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Registrierung') ?>>Registration</p>
          <div style="display:flex;align-items:flex-start;gap:14px">
            <button type="button" class="sw" role="switch" data-registration aria-checked="<?= $regOpen ? 'true' : 'false' ?>" aria-labelledby="adm-reg adm-reg-text" aria-describedby="adm-reg-note"><span></span></button>
            <div style="flex:1;min-width:0">
              <p id="adm-reg-text" style="margin:0 0 3px;font-size:14px;color:#E8EAEC"<?= de('Jeder kann ein Konto anlegen') ?>>Anyone can create an account</p>
              <?php if ($regOpen): ?>
              <p id="adm-reg-note" data-reg-note style="margin:0;font-size:12px;line-height:1.45;color:#8B949C"<?= de('An. Jeder mit eigener Hardware kann sich registrieren, höchstens 5 Anmeldungen je Stunde und IP.') ?>>On. Anyone with their own hardware can sign up, at most 5 sign-ups per hour per IP.</p>
              <?php else: ?>
              <p id="adm-reg-note" data-reg-note style="margin:0;font-size:12px;line-height:1.45;color:#8B949C"<?= de('Aus. Neue Konten nur über einen Einladungslink, bestehende bleiben unberührt.') ?>>Off. New accounts only through an invitation link, existing ones are untouched.</p>
              <?php endif; ?>
            </div>
          </div>
          <form data-signup-form novalidate style="margin-top:18px;display:flex;flex-wrap:wrap;gap:8px">
            <input type="email" data-signup-email class="field" placeholder="name@example.lu" aria-label="Email for the invitation, optional" data-de-label="E-Mail für die Einladung, freiwillig" autocomplete="off" style="flex:1;min-width:160px;width:auto">
            <button type="submit" class="btn-ghost"<?= de('Einladungslink') ?>>Invitation link</button>
          </form>
          <div data-signup-link hidden style="display:flex;align-items:stretch;margin-top:8px">
            <input type="text" readonly aria-label="Invitation link" data-de-label="Einladungslink" class="field" style="flex:1;min-width:0;border-top-right-radius:0;border-bottom-right-radius:0;font-size:12px">
            <button type="button" data-copy-signup class="btn-ghost" style="border-radius:0 2px 2px 0;border-left:0"<?= de('Kopieren') ?>>Copy</button>
          </div>
          <p data-signup-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
        </article>
      </div>
    </section>

    <section style="margin-bottom:clamp(32px,4vw,48px)">
      <h2 style="<?= $h2 ?>"<?= de('Dienst-Zugänge') ?>>Service credentials</h2>
      <p style="margin:0 0 16px;max-width:56ch;font-size:14px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Diese gehören dem Projekt, nicht den Nutzern. Niemand tippt hier irgendwo einen Schlüssel ein, deshalb liegen sie alle an einer Stelle.') ?>>These belong to the project, not to users. Nobody types a key anywhere, which is why they all live in one place.</p>

      <div style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:15px;background:#0B0D0F">
          <span style="flex:1 1 160px;min-width:0;<?= $mono ?>font-size:12.5px;color:#E8EAEC">User-Agent</span>
          <span data-ua style="flex:2 1 260px;min-width:0;<?= $mono ?>font-size:11.5px;color:#B4BCC3;word-break:break-all"><?= h($userAgent) ?></span>
          <?php if ($imprint['email'] !== ''): ?>
            <span data-ua-tag style="<?= $tag('ok') ?>"<?= de('Gesetzt') ?>>Set</span>
          <?php else: ?>
            <span data-ua-tag style="<?= $tag('warn') ?>"<?= de('Kontakt fehlt') ?>>Contact missing</span>
          <?php endif; ?>
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:15px;background:#0B0D0F">
          <span style="flex:1 1 160px;min-width:0;<?= $mono ?>font-size:12.5px;color:#E8EAEC"<?= de('Airline-Logos') ?>>Airline logos</span>
          <?php if ($logos['count'] > 0): ?>
            <span style="flex:2 1 260px;min-width:0;<?= $mono ?>font-size:11.5px;color:#B4BCC3"<?= de(number_format($logos['count'], 0, ',', ' ') . ' Logos, Sammlung ' . $logos['ref'] . ($logos['built'] ? ', gerechnet am ' . gmdate('d.m.Y', $logos['built']) : '')) ?>><?= h(number_format($logos['count'], 0, '.', ',') . ' logos, collection ' . $logos['ref'] . ($logos['built'] ? ', built ' . gmdate('Y-m-d', $logos['built']) : '')) ?></span>
            <span style="<?= $tag('ok') ?>"<?= de('Vorhanden') ?>>Present</span>
          <?php else: ?>
            <span style="flex:2 1 260px;min-width:0;<?= $mono ?>font-size:11.5px;color:#B4BCC3"<?= de('Noch keine Sammlung auf dem Server. tools/logos-build.mjs rechnet sie, danach gehört der Ordner nach .htdata/logos.') ?>>No collection on the server yet. tools/logos-build.mjs builds it, then the folder goes into .htdata/logos.</span>
            <span style="<?= $tag('warn') ?>"<?= de('Fehlt') ?>>Missing</span>
          <?php endif; ?>
        </div>

        <div style="background:#0B0D0F">
          <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:15px">
            <span style="flex:1 1 160px;min-width:0;<?= $mono ?>font-size:12.5px;color:#E8EAEC">SMTP</span>
            <span data-smtp-summary style="flex:2 1 260px;min-width:0;<?= $mono ?>font-size:11.5px;color:#B4BCC3"><?= $smtp['host'] !== '' ? h($smtp['host'] . ':' . $smtp['port'] . ' · ' . strtoupper($smtp['security'])) : '' ?></span>
            <?php if ($smtp['host'] === ''): ?>
              <span data-smtp-tag style="<?= $tag('off') ?>"<?= de('Nicht eingerichtet') ?>>Not set up</span>
            <?php elseif ($smtp['tested_at'] && $smtp['test_ok']): ?>
              <span data-smtp-tag style="<?= $tag('ok') ?>"<?= de('Getestet') ?>>Tested</span>
            <?php elseif ($smtp['tested_at']): ?>
              <span data-smtp-tag style="<?= $tag('bad') ?>"<?= de('Test fehlgeschlagen') ?>>Test failed</span>
            <?php else: ?>
              <span data-smtp-tag style="<?= $tag('warn') ?>"<?= de('Ungetestet') ?>>Untested</span>
            <?php endif; ?>
            <button type="button" class="btn-subtle" data-smtp-toggle aria-expanded="<?= $smtp['host'] === '' ? 'true' : 'false' ?>" aria-controls="smtp-form"<?= de('Bearbeiten') ?>>Edit</button>
          </div>

          <form id="smtp-form" data-smtp-form novalidate<?= $smtp['host'] === '' ? '' : ' hidden' ?> style="border-top:1px solid #1B2126;padding:18px 15px 20px">
            <p style="margin:0 0 16px;max-width:64ch;font-size:13px;line-height:1.55;color:#8B949C"<?= de('Brevo: Server smtp-relay.brevo.com, Port 587, STARTTLS. Login und SMTP-Schlüssel stehen bei Brevo unter SMTP & API. Die Absenderadresse muss dort bestätigt sein, und ihre Domain braucht DKIM und DMARC im DNS.') ?>>Brevo: server smtp-relay.brevo.com, port 587, STARTTLS. Login and SMTP key are in Brevo under SMTP & API. The sender address has to be verified there, and its domain needs DKIM and DMARC in DNS.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:16px 20px">
              <div>
                <label for="smtp-host" style="<?= $labelBlock ?>"<?= de('Server') ?>>Server</label>
                <input id="smtp-host" name="host" type="text" class="field" value="<?= h($smtp['host']) ?>" placeholder="smtp-relay.brevo.com" autocomplete="off" spellcheck="false">
              </div>
              <div>
                <label for="smtp-port" style="<?= $labelBlock ?>">Port</label>
                <input id="smtp-port" name="port" type="number" min="1" max="65535" class="field" value="<?= (int) $smtp['port'] ?>" inputmode="numeric">
              </div>
              <div>
                <p id="smtp-sec-label" style="<?= $labelBlock ?>"<?= de('Verschlüsselung') ?>>Encryption</p>
                <div role="group" aria-labelledby="smtp-sec-label" style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
                  <button type="button" class="two" data-security="starttls" aria-pressed="<?= $smtp['security'] === 'starttls' ? 'true' : 'false' ?>">STARTTLS</button>
                  <button type="button" class="two" data-security="ssl" aria-pressed="<?= $smtp['security'] === 'ssl' ? 'true' : 'false' ?>">SSL</button>
                </div>
              </div>
              <div>
                <label for="smtp-user" style="<?= $labelBlock ?>">Login</label>
                <input id="smtp-user" name="user" type="text" class="field" value="<?= h($smtp['user']) ?>" placeholder="1234ab@smtp-brevo.com" autocomplete="off" spellcheck="false">
              </div>
              <div>
                <label for="smtp-pass" style="<?= $labelBlock ?>"<?= de('SMTP-Schlüssel') ?>>SMTP key</label>
                <input id="smtp-pass" name="pass" type="password" class="field" value="" autocomplete="new-password" placeholder="<?= $smtp['has_password'] ? '••••••••' : '' ?>" aria-describedby="smtp-pass-hint">
                <p id="smtp-pass-hint" style="margin:7px 0 0;<?= $mono ?>font-size:11px;line-height:1.45;color:#8B949C"<?= de($smtp['has_password'] ? 'Gespeichert und verschlüsselt. Leer lassen, um ihn zu behalten.' : 'Wird verschlüsselt gespeichert und nie wieder angezeigt.') ?>><?= $smtp['has_password'] ? 'Saved and encrypted. Leave empty to keep it.' : 'Stored encrypted and never shown again.' ?></p>
              </div>
              <div>
                <label for="smtp-from" style="<?= $labelBlock ?>"<?= de('Absenderadresse') ?>>Sender address</label>
                <input id="smtp-from" name="from_email" type="email" class="field" value="<?= h($smtp['from_email']) ?>" placeholder="wall@example.com" autocomplete="off" spellcheck="false">
              </div>
              <div>
                <label for="smtp-name" style="<?= $labelBlock ?>"<?= de('Absendername') ?>>Sender name</label>
                <input id="smtp-name" name="from_name" type="text" class="field" value="<?= h($smtp['from_name']) ?>" maxlength="60" autocomplete="off">
              </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:18px">
              <button type="submit" class="btn-solid"<?= de('Speichern') ?>>Save</button>
            </div>
            <p data-smtp-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>

            <div style="margin-top:18px;padding-top:18px;border-top:1px solid #1B2126">
              <label for="smtp-test-to" style="<?= $labelBlock ?>"<?= de('Test-Mail an') ?>>Test mail to</label>
              <div style="display:flex;flex-wrap:wrap;gap:8px">
                <input id="smtp-test-to" data-test-to type="email" class="field" value="<?= h($user['email']) ?>" autocomplete="off" style="flex:1;min-width:200px;width:auto">
                <button type="button" data-smtp-test class="btn-ghost"<?= de('Test-Mail senden') ?>>Send test mail</button>
              </div>
              <p data-test-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
            </div>
          </form>
        </div>

        <div style="background:#0B0D0F">
          <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:15px">
            <span style="flex:1 1 160px;min-width:0;<?= $mono ?>font-size:12.5px;color:#E8EAEC">Spotify</span>
            <span style="flex:2 1 260px;min-width:0;<?= $mono ?>font-size:11.5px;color:#B4BCC3"<?= de('App-Zugang des Projekts, Token pro Nutzer. Verbunden: ' . $spotify['users'] . ' von ' . $spotify['users_max']) ?>>Project app credentials, token per user. Connected: <?= (int) $spotify['users'] ?> of <?= (int) $spotify['users_max'] ?></span>
            <?php if ($spotify['client_id'] === '' || !$spotify['has_secret']): ?>
              <span data-spotify-tag style="<?= $tag('off') ?>"<?= de('Nicht eingerichtet') ?>>Not set up</span>
            <?php elseif ($spotify['tested_at'] && $spotify['test_ok']): ?>
              <span data-spotify-tag style="<?= $tag('ok') ?>"<?= de('Getestet') ?>>Tested</span>
            <?php elseif ($spotify['tested_at']): ?>
              <span data-spotify-tag style="<?= $tag('bad') ?>"<?= de('Test fehlgeschlagen') ?>>Test failed</span>
            <?php else: ?>
              <span data-spotify-tag style="<?= $tag('warn') ?>"<?= de('Ungetestet') ?>>Untested</span>
            <?php endif; ?>
            <?php $spSet = $spotify['client_id'] !== '' && $spotify['has_secret']; ?>
            <button type="button" class="btn-subtle" data-spotify-toggle aria-expanded="<?= $spSet ? 'false' : 'true' ?>" aria-controls="spotify-form"<?= de('Bearbeiten') ?>>Edit</button>
          </div>
          <form id="spotify-form" data-spotify-form data-has-secret="<?= $spotify['has_secret'] ? 'true' : 'false' ?>" novalidate<?= $spSet ? ' hidden' : '' ?> style="border-top:1px solid #1B2126;padding:18px 15px 20px">
            <p style="margin:0 0 12px;max-width:64ch;font-size:13px;line-height:1.55;color:#8B949C"<?= de('Eine App im Spotify-Dashboard (developer.spotify.com/dashboard), nur Web API. Unter Redirect URIs gehört genau diese Adresse hinein, Zeichen für Zeichen:') ?>>An app in the Spotify dashboard (developer.spotify.com/dashboard), Web API only. Under Redirect URIs it needs exactly this address, character for character:</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;max-width:620px">
              <input data-spotify-redirect type="text" class="field" value="<?= h($spotify['redirect']) ?>" readonly aria-label="Redirect URI" style="flex:1;min-width:220px;width:auto">
              <button type="button" data-spotify-copy class="btn-ghost"<?= de('Kopieren') ?>>Copy</button>
            </div>
            <p style="margin:0 0 16px;max-width:64ch;font-size:13px;line-height:1.55;color:#8B949C"<?= de('Entwicklungsmodus seit Februar 2026: der Besitzer der App braucht Premium, höchstens fünf Nutzer, jeder unter User Management mit Namen und der E-Mail seines Spotify-Kontos eingetragen. Das Secret sieht nur der Server, weder Browser noch Gerät.') ?>>Development mode since February 2026: the owner of the app needs Premium, five users at most, each entered under User Management with name and the email of their Spotify account. Only the server sees the secret, neither browsers nor devices do.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:16px 20px;max-width:620px">
              <div>
                <label for="spotify-id" style="<?= $labelBlock ?>">Client ID</label>
                <input id="spotify-id" name="client_id" type="text" class="field" value="<?= h($spotify['client_id']) ?>" autocomplete="off" spellcheck="false" maxlength="32" aria-describedby="spotify-id-hint">
                <p id="spotify-id-hint" style="margin:7px 0 0;<?= $mono ?>font-size:11px;line-height:1.45;color:#8B949C"<?= de('32 Zeichen, auf der Seite der App unter Basic Information.') ?>>32 characters, on the app page under Basic Information.</p>
              </div>
              <div>
                <label for="spotify-secret" style="<?= $labelBlock ?>">Client secret</label>
                <input id="spotify-secret" name="secret" type="password" class="field" value="" autocomplete="new-password" spellcheck="false" maxlength="32" placeholder="<?= $spotify['has_secret'] ? '••••••••' : '' ?>" aria-describedby="spotify-secret-hint">
                <p id="spotify-secret-hint" style="margin:7px 0 0;<?= $mono ?>font-size:11px;line-height:1.45;color:#8B949C"<?= de($spotify['has_secret'] ? 'Gespeichert und verschlüsselt. Leer lassen, um es zu behalten.' : 'Wird verschlüsselt gespeichert und nie wieder angezeigt.') ?>><?= $spotify['has_secret'] ? 'Saved and encrypted. Leave empty to keep it.' : 'Stored encrypted and never shown again.' ?></p>
              </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:18px">
              <button type="submit" class="btn-solid"<?= de('Speichern') ?>>Save</button>
              <button type="button" data-spotify-test class="btn-ghost"<?= de('Testen') ?>>Test</button>
            </div>
            <p data-spotify-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
          </form>
        </div>
        <div style="background:#0B0D0F">
          <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:15px">
            <span style="flex:1 1 160px;min-width:0;<?= $mono ?>font-size:12.5px;color:#E8EAEC">mobiliteit.lu</span>
            <span style="flex:2 1 260px;min-width:0;<?= $mono ?>font-size:11.5px;color:#B4BCC3"<?= de('Nahverkehr: AVL, CFL, Luxtram, RGTR, TICE') ?>>Public transport: AVL, CFL, Luxtram, RGTR, TICE</span>
            <?php if (!$transit['has_key']): ?>
              <span data-transit-tag style="<?= $tag('off') ?>"<?= de('Nicht eingerichtet') ?>>Not set up</span>
            <?php elseif ($transit['tested_at'] && $transit['test_ok']): ?>
              <span data-transit-tag style="<?= $tag('ok') ?>"<?= de('Getestet') ?>>Tested</span>
            <?php elseif ($transit['tested_at']): ?>
              <span data-transit-tag style="<?= $tag('bad') ?>"<?= de('Test fehlgeschlagen') ?>>Test failed</span>
            <?php else: ?>
              <span data-transit-tag style="<?= $tag('warn') ?>"<?= de('Ungetestet') ?>>Untested</span>
            <?php endif; ?>
            <button type="button" class="btn-subtle" data-transit-toggle aria-expanded="<?= $transit['has_key'] ? 'false' : 'true' ?>" aria-controls="transit-form"<?= de('Bearbeiten') ?>>Edit</button>
          </div>
          <?php
            $day = static fn(?int $ts): string => $ts === null ? '' : (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('Europe/Luxembourg'))->format('d.m.Y');
            if ($stops['building']) {
                $stopsEn = 'Stop map: collecting every stop, ' . (int) $stops['progress'] . ' %' . ($stops['reason'] === 'quota' ? ', paused to spare the quota' : '') . '.';
                $stopsDe = 'Haltestellenkarte: alle Haltestellen werden gesammelt, ' . (int) $stops['progress'] . ' %' . ($stops['reason'] === 'quota' ? ', pausiert wegen des Kontingents' : '') . '.';
            } elseif ($stops['built_at'] !== null) {
                $stopsEn = 'Stop map: ' . $nf((int) $stops['count']) . ' stops from ' . (int) $stops['queries'] . ' requests, as of ' . $day($stops['built_at']) . '. Refreshed monthly, next around ' . $day($stops['next_at']) . '.';
                $stopsDe = 'Haltestellenkarte: ' . $nfDe((int) $stops['count']) . ' Haltestellen aus ' . (int) $stops['queries'] . ' Abfragen, Stand ' . $day($stops['built_at']) . '. Monatlich neu, als Nächstes um den ' . $day($stops['next_at']) . '.';
            } else {
                $stopsEn = 'Stop map: not collected yet. It starts when someone opens the map on a device page.';
                $stopsDe = 'Haltestellenkarte: noch nicht gesammelt. Das beginnt, sobald jemand die Karte auf einer Geräteseite öffnet.';
            }
          ?>
          <p style="margin:0;padding:0 15px 14px;<?= $mono ?>font-size:11px;line-height:1.55;color:#8B949C"<?= de($stopsDe) ?>><?= h($stopsEn) ?></p>
          <?php
            // Jede Haltestelle eine Abfrage pro Minute, 1 440 am Tag: mehr als drei verschiedene sprengen 4 500.
            $useEn = 'In use on devices: ' . $stopsInUse . ' different ' . ($stopsInUse === 1 ? 'stop' : 'stops') . '. Each costs one request a minute, more than three hit the daily brake.';
            $useDe = 'In Benutzung auf Geräten: ' . $stopsInUse . ' verschiedene ' . ($stopsInUse === 1 ? 'Haltestelle' : 'Haltestellen') . '. Jede kostet eine Abfrage pro Minute, mehr als drei laufen in die Tagesbremse.';
          ?>
          <p data-stops-in-use style="margin:0;padding:0 15px 14px;<?= $mono ?>font-size:11px;line-height:1.55;color:<?= $stopsInUse > 3 ? '#FFAA00' : '#8B949C' ?>"<?= de($useDe) ?>><?= h($useEn) ?></p>

          <form id="transit-form" data-transit-form data-has-key="<?= $transit['has_key'] ? 'true' : 'false' ?>" novalidate<?= $transit['has_key'] ? ' hidden' : '' ?> style="border-top:1px solid #1B2126;padding:18px 15px 20px">
            <p style="margin:0 0 16px;max-width:64ch;font-size:13px;line-height:1.55;color:#8B949C"<?= de('Persönlicher Schlüssel der Administration des transports publics, zu beantragen bei opendata-api@atp.etat.lu. Kontingent 500 Abfragen pro Stunde und 5 000 pro Tag. Nur der Server benutzt ihn, weder Browser noch Gerät bekommen ihn zu sehen.') ?>>Personal key of the Administration des transports publics, requested at opendata-api@atp.etat.lu. Quota 500 requests per hour and 5,000 per day. Only the server uses it, neither browsers nor devices ever see it.</p>
            <div style="max-width:420px">
              <label for="transit-key" style="<?= $labelBlock ?>"<?= de('Schlüssel') ?>>Key</label>
              <input id="transit-key" name="key" type="password" class="field" value="" autocomplete="new-password" spellcheck="false" placeholder="<?= $transit['has_key'] ? '••••••••' : '' ?>" aria-describedby="transit-key-hint">
              <p id="transit-key-hint" style="margin:7px 0 0;<?= $mono ?>font-size:11px;line-height:1.45;color:#8B949C"<?= de($transit['has_key'] ? 'Gespeichert und verschlüsselt. Ein neuer ersetzt ihn.' : 'Wird verschlüsselt gespeichert und nie wieder angezeigt.') ?>><?= $transit['has_key'] ? 'Saved and encrypted. A new one replaces it.' : 'Stored encrypted and never shown again.' ?></p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:18px">
              <button type="submit" class="btn-solid"<?= de('Speichern') ?>>Save</button>
              <button type="button" data-transit-test class="btn-ghost"<?= de('Testen') ?>>Test</button>
              <button type="button" data-transit-sample class="btn-ghost"<?= de('Datenprobe laden') ?>>Load data sample</button>
            </div>
            <p data-transit-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>

            <div data-transit-result hidden style="margin-top:14px;padding-top:14px;border-top:1px solid #1B2126">
              <p data-transit-result-head style="margin:0 0 10px;<?= $mono ?>font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#B4BCC3"></p>
              <ul data-transit-list style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126"></ul>
            </div>

            <div data-transit-sample-box hidden style="margin-top:14px;padding-top:14px;border-top:1px solid #1B2126">
              <p data-transit-sample-head style="margin:0 0 12px;font-size:13px;line-height:1.55;color:#C6CDD3"></p>
              <div style="display:flex;flex-wrap:wrap;gap:8px">
                <button type="button" data-transit-download class="btn-ghost"<?= de('JSON herunterladen') ?>>Download JSON</button>
                <button type="button" data-transit-copy class="btn-ghost"<?= de('JSON kopieren') ?>>Copy JSON</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </section>

    <section style="margin-bottom:clamp(32px,4vw,48px)">
      <h2 style="<?= $h2 ?>"<?= de('Impressum und Kontakt') ?>>Legal notice and contact</h2>
      <p style="margin:0 0 16px;max-width:60ch;font-size:14px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Steht im Impressum, in der Datenschutzerklärung, im Fußtext jeder Mail und als Kontakt im User-Agent für adsb.lol, adsb.fi und adsbdb. Solange Name oder Kontakt-E-Mail fehlen, zeigt die Seite den Platzhalter in eckigen Klammern. Die Adresse ist freiwillig, ohne sie fehlt die Zeile.') ?>>Shown in the legal notice, the privacy notice, the footer of every mail, and as the contact in the User-Agent for adsb.lol, adsb.fi and adsbdb. While the name or the contact email is missing, the page shows the placeholder in square brackets. The address is optional, without it the line is left out.</p>
      <form data-imprint-form novalidate style="background:#0B0D0F;border:1px solid #1B2126;padding:22px">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:16px 20px">
          <div>
            <label for="imp-name" style="<?= $labelBlock ?>"<?= de('Name') ?>>Name</label>
            <input id="imp-name" name="name" type="text" class="field" value="<?= h($imprint['name']) ?>" maxlength="120" placeholder="[NAME]" autocomplete="off">
          </div>
          <div>
            <label for="imp-address" style="<?= $labelBlock ?>"<?= de('Adresse, freiwillig') ?>>Address, optional</label>
            <input id="imp-address" name="address" type="text" class="field" value="<?= h($imprint['address']) ?>" maxlength="240" autocomplete="off">
          </div>
          <div>
            <label for="imp-email" style="<?= $labelBlock ?>"<?= de('Kontakt-E-Mail') ?>>Contact email</label>
            <input id="imp-email" name="email" type="email" class="field" value="<?= h($imprint['email']) ?>" maxlength="190" placeholder="[E-MAIL]" autocomplete="off">
          </div>
          <div>
            <label for="imp-repo" style="<?= $labelBlock ?>"<?= de('Öffentliches Repository') ?>>Public repository</label>
            <input id="imp-repo" name="repo" type="url" class="field" value="<?= h($imprint['repo']) ?>" maxlength="190" placeholder="https://github.com/..." autocomplete="off" aria-describedby="imp-repo-hint">
            <p id="imp-repo-hint" style="margin:7px 0 0;<?= $mono ?>font-size:11px;line-height:1.45;color:#8B949C"<?= de('Leer, solange das Repo privat ist. Dann zeigt die Seite keine GitHub-Links.') ?>>Empty while the repo is private. The site then shows no GitHub links.</p>
          </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:18px">
          <button type="submit" class="btn-solid"<?= de('Speichern') ?>>Save</button>
        </div>
        <p data-imprint-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
      </form>
    </section>

    <section style="margin-bottom:clamp(20px,3vw,32px)">
      <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:14px;padding-bottom:10px;border-bottom:1px solid #1B2126;margin-bottom:16px">
        <h2 style="margin:0;<?= $mono ?>font-size:12px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#E8EAEC"<?= de('Zuletzt schiefgegangen') ?>>Recently went wrong</h2>
        <span style="margin-left:auto;<?= $mono ?>font-size:11px;color:#8B949C"<?= de('14 Tage, dann automatisch gelöscht') ?>>14 days, then deleted automatically</span>
      </div>

      <div style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;<?= $mono ?>font-size:12px">
        <?php if (!$events): ?>
          <div style="padding:13px 15px;background:#0B0D0F;color:#8B949C"<?= de('Nichts in den letzten 14 Tagen.') ?>>Nothing in the last 14 days.</div>
        <?php endif; ?>
        <?php foreach ($events as $e): $today = gmdate('Y-m-d', (int) $e['at']) === gmdate('Y-m-d'); $time = local_time('Europe/Luxembourg', (int) $e['at']); ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px 14px;padding:13px 15px;background:#0B0D0F">
          <?php if ($today): ?>
            <span style="flex:0 0 82px;color:#8B949C"><?= h($time) ?></span>
          <?php else: ?>
            <span style="flex:0 0 82px;color:#8B949C"><?= h((new DateTimeImmutable('@' . (int) $e['at']))->setTimezone(new DateTimeZone('Europe/Luxembourg'))->format('d.m. H:i')) ?></span>
          <?php endif; ?>
          <span style="flex:0 0 64px;color:<?= in_array($e['source'], ['METEO', 'NOMINATIM'], true) ? '#E09A1A' : '#FF7A54' ?>"><?= h($e['source']) ?></span>
          <span style="flex:1 1 220px;min-width:0;color:#B4BCC3"<?= de((string) $e['message_de']) ?>><?= h($e['message_en']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <footer style="border-top:1px solid #1B2126">
    <div style="max-width:1320px;margin:0 auto;padding:22px 20px;display:flex;flex-wrap:wrap;gap:14px 26px;align-items:center;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em">
      <span style="color:#8B949C">THE WALL &middot; 2026</span>
      <a href="/animations" class="h-text hit" style="color:#8B949C"<?= de('Animationen') ?>>Animations</a>
      <a href="/controls" class="h-text hit" style="color:#8B949C"<?= de('Bedienelemente') ?>>Controls</a>
      <span style="margin-left:auto;color:#8B949C"><?= h(site_host()) ?></span>
    </div>
  </footer>
</div>
