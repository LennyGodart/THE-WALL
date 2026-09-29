<?php
/**
 * @var array $user @var array $devices @var ?array $device @var bool $owner @var ?array $settings
 * @var ?array $shares @var ?array $firmware @var bool $mailReady @var ?array $haLinks
 */
$mono = "font-family:'IBM Plex Mono',monospace;";
$h2 = 'margin:0 0 16px;' . $mono . 'font-size:12px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#E8EAEC;padding-bottom:10px;border-bottom:1px solid #1B2126';
$label = 'margin:0 0 9px;' . $mono . 'font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3';
$labelBlock = 'display:block;margin-bottom:9px;' . $mono . 'font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3';
$grid = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:1px;background:#1B2126;border:1px solid #1B2126';
/* Fuer Reihen, die nicht voll werden: die Linie zeichnet jede Karte selbst, leere Zellen
   bleiben Seitengrund statt einer grauen Flaeche. */
$gridCards = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:1px';
$statusStyle = 'margin:12px 0 0;' . $mono . 'font-size:11px;color:#3DE07C;min-height:16px';
$note = 'margin:10px 0 0;' . $mono . 'font-size:11px;line-height:1.5;color:#8B949C';
$text = 'margin:0 0 14px;font-size:13px;line-height:1.55;color:#8B949C';
$dt = 'padding:7px 16px 7px 0;color:#8B949C';
$dd = 'margin:0;padding:7px 0;color:#C6CDD3;text-align:right';
$dialog = 'margin-top:16px;padding:18px;border:1px solid #FF4A1C;border-radius:2px;background:rgba(255,74,28,0.06)';
$dialogTitle = 'margin:0 0 8px;' . $mono . 'font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#FF7A54';
?>
<div style="min-height:100vh;display:flex;flex-direction:column">

  <?= skip_link('#main', 'Skip to content', 'Zum Inhalt springen') ?>

  <header style="position:sticky;top:0;z-index:30;background:rgba(8,9,10,0.9);backdrop-filter:blur(12px);border-bottom:1px solid #1B2126">
    <div style="max-width:1240px;margin:0 auto;padding:12px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:12px 20px">
      <?= brand_mark('/') ?>

      <?php if ($device): ?>
      <div style="display:flex;align-items:center;gap:10px;padding-left:20px;border-left:1px solid #232A30">
        <span class="<?= device_online($device) ? 'blink-24' : '' ?>" style="width:7px;height:7px;background:<?= device_online($device) ? '#3DE07C' : '#4A565F' ?>;border-radius:1px;flex:0 0 auto"></span>
        <span data-device-name style="<?= $mono ?>font-size:13px;color:#E8EAEC"><?= h($device['name']) ?></span>
      </div>
      <?php endif; ?>

      <nav style="display:flex;gap:2px;padding-left:20px;border-left:1px solid #232A30" aria-label="Device sections" data-de-label="Gerätebereiche">
        <a href="/device<?= $device ? '/' . (int) $device['id'] : '' ?>" class="h-nav hit" style="padding:7px 12px;border-radius:2px;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#8B949C"<?= de('Gerät') ?>>Device</a>
        <span aria-current="page" style="padding:7px 12px;border-radius:2px;background:#171E23;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#E8EAEC"<?= de('Einstellungen') ?>>Settings</span>
        <?php if (is_admin($user)): ?>
          <a href="/admin" class="h-nav hit" style="padding:7px 12px;border-radius:2px;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#8B949C"<?= de('Verwaltung') ?>>Admin</a>
        <?php endif; ?>
      </nav>

      <div style="flex:1 0 0;min-width:0"></div>
      <?= lang_switch() ?>
    </div>
  </header>

  <main id="main" style="flex:1;width:100%;max-width:1240px;margin:0 auto;padding:clamp(28px,4vw,48px) 20px">

    <div data-pxwrap="1" style="margin:0 0 14px;max-width:640px"><canvas aria-hidden="true"></canvas></div>
    <h1 data-pxhead="1"<?= de('Einstellungen') ?>>Settings</h1>
    <p style="margin:0 0 clamp(32px,5vw,56px);max-width:56ch;font-size:15.5px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Alles, was man selten anfasst. Was das Panel zeigt, stellst du auf der Geräteseite ein.') ?>>Everything you touch rarely. What the panel shows is set on the device page.</p>

    <?php if ($owner): ?>
    <section style="margin-bottom:clamp(36px,5vw,56px)">
      <h2 style="<?= $h2 ?>"<?= de('Gerät') ?>>Device</h2>
      <?php if (device_is_test($device)): ?>
      <p style="margin:0 0 16px;max-width:60ch;font-size:14px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Das ist ein Testgerät aus dem Admin-Bereich. Name, Zeitzone und alles auf der Geräteseite lassen sich ausprobieren, WLAN, Firmware und Zustand gibt es erst mit echter Hardware.') ?>>This is a test device from the admin area. Name, time zone and everything on the device page can be tried out, Wi-Fi, firmware and health only come with real hardware.</p>
      <?php endif; ?>
      <div style="<?= $gridCards ?>">

        <article style="background:#0B0D0F;padding:22px;box-shadow:0 0 0 1px #1B2126">
          <label for="s-name" style="<?= $labelBlock ?>"<?= de('Name') ?>>Name</label>
          <input id="s-name" data-name type="text" value="<?= h($device['name']) ?>" maxlength="40" placeholder="Wohnzimmer" autocomplete="off" class="field">
          <p style="<?= $note ?>"<?= de('Steht in der Geräteliste und beim Einrichten auf dem Panel.') ?>>Shown in the device list and on the panel during setup.</p>
          <p data-name-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
        </article>

        <article style="background:#0B0D0F;padding:22px;box-shadow:0 0 0 1px #1B2126">
          <label for="s-tz" style="<?= $labelBlock ?>"<?= de('Zeitzone') ?>>Time zone</label>
          <select id="s-tz" data-tz class="field">
            <?php $choices = tz_choices(); if (!isset($choices[$settings['tz']])) { $choices = [$settings['tz'] => $settings['tz']] + $choices; } foreach ($choices as $zone => $zlabel): ?>
              <option value="<?= h($zone) ?>"<?= $zone === $settings['tz'] ? ' selected' : '' ?>><?= h($zlabel) ?></option>
            <?php endforeach; ?>
          </select>
          <p style="margin:12px 0 0;<?= $mono ?>font-size:11px;line-height:1.5;color:#8B949C"<?= de('Die Echtzeituhr auf dem Board hält die Zeit auch ohne WLAN. Sommerzeit macht die Zeitzone selbst.') ?>>The real-time clock on the board keeps time without Wi-Fi. The zone handles daylight saving itself.</p>
          <p data-tz-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
        </article>

        <article style="background:#0B0D0F;padding:22px;box-shadow:0 0 0 1px #1B2126">
          <p style="<?= $label ?>">WLAN</p>
          <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:6px">
            <?php if (!empty($device['ssid'])): ?>
              <span style="<?= $mono ?>font-size:15px;color:#E8EAEC"><?= h($device['ssid']) ?></span>
            <?php else: ?>
              <span style="<?= $mono ?>font-size:15px;color:#E8EAEC"<?= de('Noch nicht gemeldet') ?>>Not reported yet</span>
            <?php endif; ?>
            <?php if ($device['rssi'] !== null): ?>
              <span style="<?= $mono ?>font-size:11px;color:<?= (int) $device['rssi'] >= -70 ? '#3DE07C' : '#FFAA00' ?>">&minus;<?= abs((int) $device['rssi']) ?> dBm</span>
            <?php endif; ?>
          </div>
          <p style="<?= $text ?>"<?= de('2,4 GHz. Zum Wechseln EN am Board zweimal drücken oder das Rad fünf Sekunden halten, dann öffnet es sein Einrichtungsnetz.') ?>>2.4 GHz. To change it, press EN on the board twice or hold the wheel for five seconds, and it opens its setup network again.</p>
          <a href="/#ablauf" class="hit" style="<?= $mono ?>font-size:11.5px;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1px solid #7A5200;padding-bottom:2px"<?= de('Einrichtung ansehen') ?>>See the setup page</a>
        </article>

        <?php /* Vierte Karte neben Name, Zeitzone und WLAN, wie in Intro.dc.html vorgeschlagen. */ ?>
        <article style="background:#0B0D0F;padding:22px;box-shadow:0 0 0 1px #1B2126">
          <p style="<?= $label ?>"<?= de('So funktioniert das Gerät') ?>>How the device works</p>
          <p style="<?= $text ?>"<?= de('Eine Runde um das Steuerboard: welche Buchse den Strom bekommt, was die Tasten und das Rad tun und was das Panel sagt, wenn etwas fehlt. Eine Minute.') ?>>A round trip around the control board: which socket takes the power, what the buttons and the wheel do, and what the panel says when something is missing. One minute.</p>
          <button type="button" data-tour-open class="btn-ghost"<?= de('Einführung öffnen') ?>>Open the introduction</button>
        </article>

        <article style="background:#0B0D0F;padding:22px;box-shadow:0 0 0 1px #1B2126">
          <p style="<?= $label ?>"<?= de('Firmware') ?>>Firmware</p>
          <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:4px">
            <span style="<?= $mono ?>font-size:22px;font-weight:600;color:#E8EAEC"><?= $device['fw'] !== null ? h($device['fw']) : '?' ?></span>
            <?php if ($firmware): ?>
              <span style="<?= $mono ?>font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#3DE07C"<?= de($firmware['version'] . ' verfügbar') ?>><?= h($firmware['version']) ?> available</span>
            <?php else: ?>
              <span style="<?= $mono ?>font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C"<?= de('Aktuell') ?>>Up to date</span>
            <?php endif; ?>
          </div>
          <p style="<?= $text ?>"<?= de('Das Gerät holt sich das Update selbst über WLAN. Es hängt nie wieder am Kabel.') ?>>The device fetches the update over Wi-Fi. It never needs the cable again.</p>
          <button type="button" data-update class="btn-ghost"<?= $firmware ? '' : ' disabled' ?><?= de('Jetzt aktualisieren') ?>>Update now</button>
          <p data-fw-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
        </article>

        <article style="background:#0B0D0F;padding:22px;box-shadow:0 0 0 1px #1B2126">
          <p style="<?= $label ?>"<?= de('Zustand') ?>>Health</p>
          <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:0;<?= $mono ?>font-size:12px">
            <?php [$upEn, $upDe] = uptime_pair($device['uptime'] !== null ? (int) $device['uptime'] : null); ?>
            <dt style="<?= $dt ?>"<?= de('Laufzeit') ?>>Uptime</dt>
            <dd style="<?= $dd ?>"<?= $upEn !== '' ? de($upDe) : '' ?>><?= $upEn !== '' ? h($upEn) : '?' ?></dd>
            <dt style="<?= $dt ?>"<?= de('Temperatur') ?>>Temperature</dt>
            <dd style="<?= $dd ?>"><?= $device['temp'] !== null ? (int) $device['temp'] . ' &deg;C' : '?' ?></dd>
            <dt style="<?= $dt ?>"<?= de('Speicher') ?>>Flash</dt>
            <dd style="<?= $dd ?>"><?= $device['flash'] !== null ? (int) $device['flash'] . ' %' : '?' ?></dd>
            <dt style="<?= $dt ?>"<?= de('Neustarts') ?>>Restarts</dt>
            <dd style="<?= $dd ?>"><?= $device['restarts'] !== null ? (int) $device['restarts'] : '?' ?></dd>
          </dl>
        </article>

        <article style="background:#0B0D0F;padding:22px;box-shadow:0 0 0 1px #1B2126">
          <p style="<?= $label ?>"<?= de('Ohne Internet') ?>>Without internet</p>
          <p style="<?= $text ?>"<?= de('Die Uhr läuft weiter, sie kommt aus dem Uhrchip auf dem Board. Der letzte Inhalt bleibt etwa eine halbe Minute stehen, dann zeigt das Panel, dass der Server fehlt. Ohne WLAN sagt es das sofort.') ?>>The clock keeps running, it comes from the clock chip on the board. The last content stays for about half a minute, then the panel shows that the server is missing. Without Wi-Fi it says so straight away.</p>
          <div style="display:flex;flex-direction:column;gap:7px;<?= $mono ?>font-size:11.5px">
            <div style="display:flex;gap:10px;align-items:center"><span style="width:7px;height:7px;background:#3DE07C;border-radius:1px;flex:0 0 auto"></span><span style="color:#B4BCC3"<?= de('Uhr') ?>>Clock</span></div>
            <div style="display:flex;gap:10px;align-items:center"><span style="width:7px;height:7px;background:#FF4A1C;border-radius:1px;flex:0 0 auto"></span><span style="color:#B4BCC3"<?= de('Flug, Wetter, Nahverkehr, Notizen') ?>>Flight, weather, departures, notes</span></div>
          </div>
        </article>
      </div>
    </section>
    <?php endif; ?>

    <section style="margin-bottom:clamp(36px,5vw,56px)">
      <h2 style="<?= $h2 ?>"<?= de('Zugang') ?>>Access</h2>
      <div style="<?= $grid ?>">

        <article id="key" style="background:#0B0D0F;padding:22px;grid-column:1 / -1;min-width:0;scroll-margin-top:90px">
          <p style="<?= $label ?>"<?= de('API-Schlüssel') ?>>API key</p>
          <div style="display:flex;align-items:stretch;margin-bottom:12px">
            <span aria-hidden="true" style="flex:0 0 auto;display:flex;align-items:center;padding:0 12px;background:#050607;border:1px solid #1E2A24;border-right:0;border-radius:2px 0 0 2px;<?= $mono ?>font-size:15px;color:#3DE07C">&gt;</span>
            <input type="text" data-key readonly value="<?= h($user['api_key']) ?>" aria-label="API key" data-de-label="API-Schlüssel" style="flex:1;min-width:0;min-height:48px;padding:13px 14px;background:#050607;border:1px solid #1E2A24;border-radius:0 2px 2px 0;color:#E8EAEC;font-size:14px">
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <button type="button" data-copy-key class="btn-ghost"<?= de('Kopieren') ?>>Copy</button>
            <button type="button" data-ask-rotate class="btn-ghost"<?= de('Neu erzeugen') ?>>Rotate</button>
          </div>
          <p data-key-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>

          <div data-rotate-dialog role="alertdialog" aria-labelledby="roll-title" aria-describedby="roll-text" tabindex="-1" hidden style="<?= $dialog ?>">
            <p id="roll-title" style="<?= $dialogTitle ?>"<?= de('Sicher?') ?>>Are you sure?</p>
            <p id="roll-text" style="margin:0 0 16px;font-size:13.5px;line-height:1.55;color:#C6CDD3"<?= de('Jedes Gerät verliert sofort die Verbindung, zeigt SCHLUESSEL ABGELEHNT und öffnet sein Einrichtungsnetz, bis du dort den neuen Schlüssel einsetzt.') ?>>Every device loses the connection at once, shows KEY REJECTED and opens its setup network until you paste the new key there.</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              <button type="button" data-do-rotate class="btn-danger"<?= de('Neu erzeugen') ?>>Rotate the key</button>
              <button type="button" data-cancel-rotate class="btn-ghost"<?= de('Abbrechen') ?>>Cancel</button>
            </div>
          </div>

          <p style="margin:12px 0 0;<?= $mono ?>font-size:11px;line-height:1.5;color:#8B949C"<?= de('Diesen Schlüssel setzt du auf der Geräteseite unter seiner IP ein. Ein Schlüssel gilt für alle deine Geräte.') ?>>Paste this on the device page at its IP address. One key covers all your devices.</p>
        </article>

        <?php if ($owner): ?>
        <article style="background:#0B0D0F;padding:22px;grid-column:1 / -1;min-width:0">
          <p style="margin:0 0 4px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Freigaben') ?>>Sharing</p>
          <p style="margin:0 0 16px;font-size:13px;line-height:1.55;color:#8B949C"<?= de('Wer mitlesen oder mitbedienen darf. Notizen schreiben darf jeder mit Zugang, das ist der Spaß daran.') ?>>Who may watch or operate it. Anyone with access can write notes, that is the fun of it.</p>

          <div data-share-list style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;border-radius:2px">
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:14px;background:#0B0D0F">
              <div style="flex:1;min-width:140px">
                <p style="margin:0 0 2px;font-size:14px;color:#E8EAEC"><?= h($user['username']) ?></p>
                <p style="margin:0;<?= $mono ?>font-size:11px;color:#8B949C"><?= h($user['email']) ?></p>
              </div>
              <span style="<?= $mono ?>font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#08090A;background:#FFAA00;padding:3px 8px;border-radius:1px"<?= de('Besitzer') ?>>Owner</span>
            </div>
            <?php foreach ($shares['users'] as $sh): ?>
            <div data-share-row="<?= (int) $sh['user_id'] ?>" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:14px;background:#0B0D0F">
              <div style="flex:1;min-width:140px">
                <p style="margin:0 0 2px;font-size:14px;color:#E8EAEC"><?= h($sh['username']) ?></p>
                <p style="margin:0;<?= $mono ?>font-size:11px;color:#8B949C"><?= h($sh['email']) ?></p>
              </div>
              <div role="group" aria-label="Rights for <?= h($sh['username']) ?>" data-de-label="Rechte für <?= h($sh['username']) ?>" style="display:flex;gap:0;border:1px solid #2C353C;border-radius:2px;overflow:hidden">
                <button type="button" class="seg" data-rights="view" data-user="<?= (int) $sh['user_id'] ?>" aria-pressed="<?= $sh['rights'] === 'view' ? 'true' : 'false' ?>"<?= de('Ansehen') ?>>View</button>
                <button type="button" class="seg" data-rights="edit" data-user="<?= (int) $sh['user_id'] ?>" aria-pressed="<?= $sh['rights'] === 'edit' ? 'true' : 'false' ?>"<?= de('Bedienen') ?>>Operate</button>
              </div>
              <button type="button" class="btn-subtle" data-unshare-user="<?= (int) $sh['user_id'] ?>"<?= de('Entfernen') ?>>Remove</button>
            </div>
            <?php endforeach; ?>
            <?php foreach ($shares['pending'] as $inv): ?>
            <div data-invite-row="<?= (int) $inv['id'] ?>" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:14px;background:#0B0D0F">
              <div style="flex:1;min-width:140px">
                <p style="margin:0 0 2px;font-size:14px;color:#E8EAEC"><?= h($inv['email']) ?></p>
                <p style="margin:0;<?= $mono ?>font-size:11px;color:#8B949C"<?= de('Einladung offen') ?>>Invitation pending</p>
              </div>
              <span style="<?= $mono ?>font-size:10px;letter-spacing:0.1em;text-transform:uppercase;padding:3px 7px;border-radius:1px;background:#1E252A;color:#B4BCC3"<?= de('Eingeladen') ?>>Invited</span>
              <button type="button" class="btn-subtle" data-unshare-invite="<?= (int) $inv['id'] ?>"<?= de('Zurückziehen') ?>>Withdraw</button>
            </div>
            <?php endforeach; ?>
          </div>

          <form data-invite-form style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px" novalidate>
            <input type="email" data-invite-email placeholder="name@example.lu" aria-label="Invite by email" data-de-label="Per E-Mail einladen" autocomplete="off" class="field" style="flex:1;min-width:180px;width:auto">
            <button type="submit" class="btn-solid"<?= de('Einladen') ?>>Invite</button>
          </form>
          <p data-invite-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
          <div data-invite-link hidden style="display:flex;align-items:stretch;margin-top:8px">
            <input type="text" readonly aria-label="Invitation link" data-de-label="Einladungslink" class="field" style="flex:1;min-width:0;border-top-right-radius:0;border-bottom-right-radius:0;font-size:12px">
            <button type="button" data-copy-invite class="btn-ghost" style="border-radius:0 2px 2px 0;border-left:0"<?= de('Kopieren') ?>>Copy</button>
          </div>
        </article>

        <?php /* Home Assistant koppelt sich je Geraet mit einem Code vom Panel (api/ha.php). Hier
                 steht die Geraete-ID fuer die Einrichtung von Hand und wer gekoppelt ist. */ ?>
        <article id="homeassistant" style="background:#0B0D0F;padding:22px;grid-column:1 / -1;min-width:0;scroll-margin-top:90px">
          <p style="margin:0 0 4px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3">Home Assistant</p>
          <p style="margin:0 0 16px;max-width:70ch;font-size:13px;line-height:1.55;color:#8B949C"<?= de('Mit der Integration THE WALL steuert Home Assistant dieses Gerät: Modus, Helligkeit, Notizen, Timer und Wecker, dazu der Flug auf dem Panel als Sensoren. Ab Firmware 0.2.1 findet Home Assistant das Gerät im Heimnetz von selbst, sonst beim Hinzufügen die Geräte-ID eingeben. Auf dem Panel erscheint dann ein Code, den tippst du in Home Assistant ein.') ?>>With the THE WALL integration, Home Assistant controls this device: mode, brightness, notes, timers and the alarm, plus the flight on the panel as sensors. From firmware 0.2.1 Home Assistant finds the device on your home network by itself, otherwise enter the device ID when you add it. The panel then shows a code, which you type into Home Assistant.</p>
          <p style="<?= $label ?>"<?= de('Geräte-ID') ?>>Device ID</p>
          <div style="display:flex;align-items:stretch;max-width:420px;margin-bottom:18px">
            <input type="text" data-ha-uid readonly value="<?= h($device['uid']) ?>" aria-label="Device ID" data-de-label="Geräte-ID" class="field" style="flex:1;min-width:0;border-top-right-radius:0;border-bottom-right-radius:0;<?= $mono ?>">
            <button type="button" data-copy-uid class="btn-ghost" style="border-radius:0 2px 2px 0;border-left:0"<?= de('Kopieren') ?>>Copy</button>
          </div>
          <p style="<?= $label ?>"<?= de('Gekoppelt') ?>>Paired</p>
          <div data-ha-list style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;border-radius:2px">
            <?php foreach ($haLinks ?? [] as $l): [$usedEn, $usedDe] = ago_pair($l['used']); ?>
            <div data-ha-row="<?= (int) $l['id'] ?>" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:14px;background:#0B0D0F">
              <div style="flex:1;min-width:160px">
                <p style="margin:0 0 2px;font-size:14px;color:#E8EAEC"><?= h($l['name']) ?></p>
                <p style="margin:0;<?= $mono ?>font-size:11px;color:#8B949C"<?= de('Gekoppelt am ' . local_date_text($settings['tz'], $l['created'], 'de') . ', zuletzt vor ' . $usedDe) ?>>Paired on <?= h(local_date_text($settings['tz'], $l['created'], 'en')) ?>, last used <?= h($usedEn) ?> ago</p>
              </div>
              <button type="button" class="btn-subtle" data-ha-unlink="<?= (int) $l['id'] ?>" aria-label="Unpair <?= h($l['name']) ?>" data-de-label="<?= h($l['name']) ?> trennen"<?= de('Trennen') ?>>Unpair</button>
            </div>
            <?php endforeach; ?>
            <p data-ha-empty style="margin:0;padding:14px;background:#0B0D0F;font-size:13px;color:#8B949C"<?= $haLinks ? ' hidden' : '' ?><?= de('Noch kein Home Assistant gekoppelt.') ?>>No Home Assistant paired yet.</p>
          </div>
          <p data-ha-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
        </article>
        <?php endif; ?>
      </div>
    </section>

    <section style="margin-bottom:clamp(36px,5vw,56px)">
      <h2 style="<?= $h2 ?>"<?= de('Konto und Daten') ?>>Account and data</h2>
      <div style="<?= $grid ?>">

        <article style="background:#0B0D0F;padding:22px">
          <p style="<?= $label ?>"<?= de('Anmeldedaten') ?>>Credentials</p>
          <dl style="margin:0 0 16px;display:grid;grid-template-columns:auto 1fr;gap:0;<?= $mono ?>font-size:12px">
            <dt style="<?= $dt ?>"<?= de('Benutzername') ?>>Username</dt>
            <dd style="<?= $dd ?>"><?= h($user['username']) ?></dd>
            <dt style="<?= $dt ?>">E-Mail</dt>
            <dd style="<?= $dd ?>;word-break:break-all"><?= h($user['email']) ?></dd>
          </dl>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <button type="button" data-open-password class="btn-ghost" aria-expanded="false" aria-controls="pw-form"<?= de('Passwort ändern') ?>>Change password</button>
            <form method="post" action="/account/logout" style="margin:0">
              <?= csrf_field() ?>
              <button type="submit" class="btn-ghost"<?= de('Abmelden') ?>>Sign out</button>
            </form>
          </div>
          <form id="pw-form" data-password-form hidden novalidate style="margin-top:18px">
            <label for="pw-current" style="<?= $labelBlock ?>"<?= de('Bisheriges Passwort') ?>>Current password</label>
            <input id="pw-current" type="password" autocomplete="current-password" class="field" aria-describedby="pw-current-hint">
            <p id="pw-current-hint" aria-live="polite" style="margin:7px 0 14px;<?= $mono ?>font-size:11.5px;color:#8B949C"></p>
            <label for="pw-new" style="<?= $labelBlock ?>"<?= de('Neues Passwort') ?>>New password</label>
            <input id="pw-new" type="password" autocomplete="new-password" class="field" aria-describedby="pw-new-hint">
            <p id="pw-new-hint" aria-live="polite" style="margin:7px 0 14px;<?= $mono ?>font-size:11.5px;color:#8B949C"<?= de('Mindestens 6 Zeichen, davon mindestens ein Buchstabe') ?>>At least 6 characters, including at least one letter</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              <button type="submit" class="btn-solid"<?= de('Speichern') ?>>Save</button>
              <button type="button" data-close-password class="btn-ghost"<?= de('Abbrechen') ?>>Cancel</button>
            </div>
          </form>
          <p data-password-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <p style="<?= $label ?>"<?= de('Was gespeichert wird') ?>>What is stored</p>
          <ul style="margin:0 0 14px;padding:0;list-style:none;display:flex;flex-direction:column;gap:7px;font-size:13px;line-height:1.5;color:#8B949C">
            <li<?= de('Benutzername, E-Mail, Passwort als Hash') ?>>Username, email, password as a hash</li>
            <li<?= de('Gerätename, Standort und Einstellungen') ?>>Device name, location and settings</li>
            <li<?= de('Deine Notizen') ?>>Your notes</li>
          </ul>
          <p style="margin:0;<?= $mono ?>font-size:11px;line-height:1.5;color:#8B949C"><span<?= de('Keine Tracker, keine Analytics, keine Weitergabe zu Werbezwecken. Wer sonst Daten verarbeitet, steht in der') ?>>No trackers, no analytics, nothing passed on for advertising. Who else handles data is listed in the</span> <a href="/legal#privacy" style="color:#B4BCC3"<?= de('Datenschutzerklärung') ?>>privacy policy</a>.</p>
        </article>

        <article style="background:#0B0D0F;padding:22px">
          <p style="<?= $label ?>"<?= de('Deine Daten') ?>>Your data</p>
          <p style="<?= $text ?>"<?= de('Du bekommst alles als JSON-Datei: Konto, Geräte, Einstellungen, Notizen.') ?>>You get everything as a JSON file: account, devices, settings, notes.</p>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <a href="/settings/export" download data-export class="btn-ghost" style="display:inline-flex;align-items:center"<?= de('Herunterladen') ?>>Download</a>
            <button type="button" data-export-mail class="btn-ghost"<?= $mailReady ? '' : ' disabled' ?><?= de('Per Mail schicken') ?>>Send by email</button>
          </div>
          <p data-export-status role="status" aria-live="polite" style="<?= $statusStyle ?>"<?= $mailReady ? '' : de('Mailversand ist auf diesem Server noch nicht eingerichtet.') ?>><?= $mailReady ? '' : 'Sending mail is not set up on this server yet.' ?></p>
        </article>
      </div>
    </section>

    <section style="margin-bottom:clamp(20px,3vw,32px)">
      <h2 style="margin:0 0 16px;<?= $mono ?>font-size:12px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#FF7A54;padding-bottom:10px;border-bottom:1px solid #3A1D14"<?= de('Endgültig') ?>>Permanent</h2>
      <div style="<?= $gridCards ?>">

        <?php if ($owner): ?>
        <article style="background:#0B0D0F;padding:22px;box-shadow:0 0 0 1px #1B2126">
          <p style="<?= $label ?>"<?= de('Gerät entfernen') ?>>Remove the device</p>
          <p style="<?= $text ?>"<?= de('Beim nächsten Abruf, spätestens nach zehn Sekunden, vergisst das Gerät den Schlüssel und öffnet wieder sein Einrichtungsnetz, das WLAN behält es. Ab Firmware 0.1.1. Ganz zurücksetzen: EN am Board drücken und, solange EN NOCHMAL = RESET auf dem Panel steht, ein zweites Mal (ab Firmware 0.1.5). Das Rad fünf Sekunden halten tut dasselbe.') ?>>On its next request, within ten seconds, the device forgets the key and opens its setup network again, keeping the Wi-Fi. From firmware 0.1.1. For a full reset press EN on the board, and while EN AGAIN = RESET shows on the panel, press it a second time (from firmware 0.1.5). Holding the wheel for five seconds does the same.</p>
          <button type="button" data-ask-remove class="btn-danger-ghost"<?= de('Gerät entfernen') ?>>Remove the device</button>
          <div data-remove-dialog role="alertdialog" aria-labelledby="rm-title" aria-describedby="rm-text" tabindex="-1" hidden style="<?= $dialog ?>">
            <p id="rm-title" style="<?= $dialogTitle ?>"<?= de('Sicher?') ?>>Are you sure?</p>
            <p id="rm-text" style="margin:0 0 16px;font-size:13.5px;line-height:1.55;color:#C6CDD3"<?= de('Das Gerät verschwindet aus deinem Konto, samt Einstellungen und Freigaben, und vergisst den Schlüssel. Setzt du ihn dort wieder ein, taucht es als neues Gerät auf.') ?>>The device leaves your account with its settings and shares, and forgets the key. If you paste the key there again, it shows up as a new device.</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              <button type="button" data-do-remove class="btn-danger"<?= de('Entfernen') ?>>Remove</button>
              <button type="button" data-cancel-remove class="btn-ghost"<?= de('Abbrechen') ?>>Cancel</button>
            </div>
          </div>
          <p data-remove-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
        </article>
        <?php endif; ?>

        <article style="background:#0B0D0F;padding:22px;box-shadow:0 0 0 1px #1B2126">
          <p style="<?= $label ?>"<?= de('Konto löschen') ?>>Delete the account</p>
          <p style="<?= $text ?>"<?= de('Löscht Konto, Geräte und Notizen sofort und vollständig. Nicht umkehrbar.') ?>>Deletes account, devices and notes at once and completely. This cannot be undone.</p>
          <button type="button" data-ask-delete class="btn-danger-ghost"<?= de('Konto löschen') ?>>Delete the account</button>
          <form data-delete-dialog role="alertdialog" aria-labelledby="del-title" aria-describedby="del-text" tabindex="-1" hidden novalidate style="<?= $dialog ?>">
            <p id="del-title" style="<?= $dialogTitle ?>"<?= de('Wirklich löschen?') ?>>Really delete?</p>
            <p id="del-text" style="margin:0 0 14px;font-size:13.5px;line-height:1.55;color:#C6CDD3"<?= de('Zur Sicherheit noch einmal dein Passwort. Danach ist alles weg.') ?>>Your password once more, to be sure. After that everything is gone.</p>
            <label for="del-pass" style="<?= $labelBlock ?>"<?= de('Passwort') ?>>Password</label>
            <input id="del-pass" type="password" autocomplete="current-password" class="field" style="margin-bottom:14px">
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              <button type="submit" class="btn-danger"<?= de('Konto löschen') ?>>Delete the account</button>
              <button type="button" data-cancel-delete class="btn-ghost"<?= de('Abbrechen') ?>>Cancel</button>
            </div>
          </form>
          <p data-delete-status role="status" aria-live="polite" style="<?= $statusStyle ?>"></p>
        </article>
      </div>
    </section>
  </main>

  <footer style="border-top:1px solid #1B2126">
    <div style="max-width:1240px;margin:0 auto;padding:24px 20px;display:flex;flex-wrap:wrap;gap:16px 28px;align-items:center;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em">
      <span style="color:#8B949C">THE WALL &middot; 2026</span>
      <a href="/" class="h-text hit" style="color:#8B949C"<?= de('Startseite') ?>>Home</a>
      <a href="/legal#privacy" class="h-text hit" style="color:#8B949C"<?= de('Datenschutz') ?>>Privacy</a>
      <a href="/legal#imprint" class="h-text hit" style="color:#8B949C"<?= de('Impressum') ?>>Legal notice</a>
      <span style="margin-left:auto;color:#8B949C"><?= h(site_host()) ?></span>
    </div>
  </footer>
</div>
