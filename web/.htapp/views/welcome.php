<?php
/** @var int $online @var ?int $count @var string $repo */
$mono = "font-family:'IBM Plex Mono',monospace;";
$tile = static function (string $key, string $en, string $de, string $textEn, string $textDe, bool $v1): string {
    $tag = $v1
        ? '<span style="font-family:\'IBM Plex Mono\',monospace;font-size:9px;letter-spacing:0.1em;color:#08090A;background:#FFAA00;padding:2px 5px;border-radius:1px">V1</span>'
        : '<span style="font-family:\'IBM Plex Mono\',monospace;font-size:9px;letter-spacing:0.1em;color:#8B949C;border:1px solid #2C353C;padding:1px 5px;border-radius:1px"' . de('Später') . '>Later</span>';
    return '<article style="background:#0B0D0F;padding:20px;display:flex;flex-direction:column;gap:14px;box-shadow:0 0 0 1px #1B2126">'
        . '<div style="height:69px;display:flex;align-items:center"><canvas data-px="' . h($key) . '" aria-hidden="true"></canvas></div>'
        . '<div><div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">'
        . '<h3 style="margin:0;font-size:15px;font-weight:600;color:#E8EAEC"' . de($de) . '>' . h($en) . '</h3>' . $tag
        . '</div><p style="margin:0;font-size:13px;line-height:1.5;color:#8B949C"' . de($textDe) . '>' . h($textEn) . '</p></div></article>';
};
$stat = static function (string $value, string $en, string $de, bool $last = false): string {
    return '<div style="padding:18px 0' . ($last ? '' : ';border-right:1px solid #14191D') . '">'
        . '<p style="margin:0;font-family:\'IBM Plex Mono\',monospace;font-weight:700;letter-spacing:-0.02em;font-size:22px;color:#E8EAEC">' . $value . '</p>'
        . '<p style="margin:4px 0 0;font-family:\'IBM Plex Mono\',monospace;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C"' . de($de) . '>' . h($en) . '</p></div>';
};
// Die Bildschirme der Demo in der Reihenfolge von welcome_demo_pages(): Name und ein Hinweis rechts.
$screens = [
    ['ROUTE', 'ROUTE', 'LUX→LIS', 'LUX→LIS'],
    ['DEPARTED, ARRIVING', 'ABFLUG, ANKUNFT', '~ESTIMATE', '~SCHÄTZUNG'],
    ['FLYING OVER', 'FLIEGT ÜBER', 'NOMINATIM', 'NOMINATIM'],
    ['METRICS', 'MESSWERTE', 'YOUR UNITS', 'DEINE EINHEITEN'],
    ['DEPARTURES', 'ABFAHRTEN', 'MOBILITEIT.LU', 'MOBILITEIT.LU'],
    ['CLOCK', 'UHR', 'RTC', 'RTC'],
    ['WEATHER', 'WETTER', 'OPEN-METEO', 'OPEN-METEO'],
    ['NOTE', 'NOTIZ', '2 × 21', '2 × 21'],
];
$dtdd = static function (string $dtEn, ?string $dtDe, string $ddEn, ?string $ddDe, bool $last = false): string {
    $b = 'border-top:1px solid #1B2126;' . ($last ? 'border-bottom:1px solid #1B2126;' : '');
    return '<dt style="padding:11px 24px 11px 0;' . $b . 'color:#8B949C;letter-spacing:0.06em"' . ($dtDe !== null ? de($dtDe) : '') . '>' . h($dtEn) . '</dt>'
        . '<dd style="margin:0;padding:11px 0;' . $b . 'color:#C6CDD3;text-align:right"' . ($ddDe !== null ? de($ddDe) : '') . '>' . h($ddEn) . '</dd>';
};
?>
<div style="min-height:100vh;display:flex;flex-direction:column">

  <?= skip_link('#main', 'Skip to content', 'Zum Inhalt springen') ?>

  <header style="position:sticky;top:0;z-index:30;background:rgba(8,9,10,0.82);backdrop-filter:blur(12px);border-bottom:1px solid #1B2126">
    <div style="max-width:1240px;margin:0 auto;padding:12px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 20px">
      <?= brand_mark(null) ?>
      <nav style="display:flex;flex-wrap:wrap;gap:8px 20px;<?= $mono ?>font-size:12px;letter-spacing:0.08em;text-transform:uppercase" aria-label="Page sections" data-de-label="Abschnitte">
        <a href="#modi" class="h-text hit" style="color:#8B949C"<?= de('Modi') ?>>Modes</a>
        <a href="#technik" class="h-text hit" style="color:#8B949C"<?= de('Technik') ?>>Hardware</a>
        <a href="#ablauf" class="h-text hit" style="color:#8B949C"<?= de('Einrichtung') ?>>Setup</a>
        <a href="/animations" class="h-text hit" style="color:#8B949C"<?= de('Animationen') ?>>Animations</a>
      </nav>
      <div style="flex:1 0 0;min-width:0"></div>
      <?= lang_switch() ?>
      <a href="/account" class="h-text hit" style="<?= $mono ?>font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#8B949C;padding:9px 4px;white-space:nowrap"<?= de('Anmelden') ?>>Sign in</a>
      <a href="/account?mode=register" class="h-solid a-press hit" style="<?= $mono ?>font-size:12px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#08090A;background:#FFAA00;padding:10px 16px;border-radius:2px;white-space:nowrap;transition:transform 160ms cubic-bezier(0.23,1,0.32,1),background 160ms ease"<?= de('Konto anlegen') ?>>Create account</a>
    </div>
  </header>

  <main id="main" style="flex:1">

    <section style="position:relative;overflow:hidden;border-bottom:1px solid #1B2126">
      <div style="max-width:1240px;margin:0 auto;padding:0 20px">

        <div style="display:flex;flex-wrap:wrap;gap:8px 28px;padding:14px 0;border-bottom:1px solid #14191D;<?= $mono ?>font-size:11px;letter-spacing:0.06em;color:#8B949C">
          <span style="display:flex;align-items:center;gap:7px;color:#3DE07C"><span class="blink-18" style="width:6px;height:6px;background:#3DE07C;border-radius:1px"></span>LIVE</span>
          <span>49.6116&deg;N 6.1319&deg;E</span>
          <span>R 40 NM</span>
          <?php if ($count !== null): ?>
            <span<?= de($count . ' FLUGZEUGE') ?>><?= (int) $count ?> AIRCRAFT</span>
          <?php endif; ?>
          <span style="margin-left:auto"<?= de($online . ' ' . ($online === 1 ? 'GERAET' : 'GERAETE') . ' ONLINE') ?>><?= (int) $online ?> <?= $online === 1 ? 'device' : 'devices' ?> online</span>
        </div>

        <div style="padding:clamp(48px,9vw,104px) 0 clamp(40px,6vw,72px);display:flex;flex-direction:column;align-items:center;text-align:center">
          <p style="margin:0 0 clamp(28px,4vw,44px);<?= $mono ?>font-size:11px;letter-spacing:0.28em;text-transform:uppercase;color:#E09A1A"<?= de('128 × 64 Pixel · Quelltext offen') ?>>128 × 64 pixels · source available</p>

          <div data-hero style="width:100%;display:flex;justify-content:center;min-height:clamp(120px,19vw,200px);align-items:center">
            <canvas data-px="hero" role="img" aria-label="THE WALL, rendered on an LED pixel grid" data-de-label="THE WALL, auf einem LED-Pixelraster gezeichnet"></canvas>
          </div>

          <h1 style="position:absolute;left:-9999px"<?= de('THE WALL, ein LED-Matrix-Display mit 128 mal 64 Pixeln') ?>>THE WALL, a 128 by 64 LED matrix display</h1>

          <p style="margin:clamp(28px,4vw,44px) 0 0;max-width:30ch;font-size:clamp(17px,2.1vw,21px);line-height:1.5;color:#B4BCC3;text-wrap:pretty"<?= de('Ein LED-Panel im Regal, das die Flugzeuge über dir zeigt. Dazu die Uhrzeit, das Wetter, deine Notizen, was du ihm schickst.') ?>>An LED panel on your shelf that shows the aircraft overhead. Plus the time, the weather, your notes, whatever you send it.</p>

          <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-top:clamp(28px,4vw,40px)">
            <a href="/account?mode=register" class="h-solid a-press" style="<?= $mono ?>font-size:13px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#08090A;background:#FFAA00;padding:15px 28px;border-radius:2px;transition:transform 160ms cubic-bezier(0.23,1,0.32,1),background 160ms ease"<?= de('Konto anlegen') ?>>Create account</a>
            <a href="#demo" class="h-ghost a-press" style="<?= $mono ?>font-size:13px;font-weight:500;letter-spacing:0.1em;text-transform:uppercase;color:#E8EAEC;border:1px solid #2C353C;padding:15px 28px;border-radius:2px;transition:transform 160ms cubic-bezier(0.23,1,0.32,1),border-color 160ms ease,background 160ms ease"<?= de('Display ansehen') ?>>See the display</a>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));border-top:1px solid #14191D">
          <?= $stat('P2.5', 'Pixel pitch', 'Pixelabstand') ?>
          <?= $stat('320&times;160', 'Millimetres', 'Millimeter') ?>
          <?= $stat('~2 S', 'From click to panel', 'Vom Klick aufs Panel') ?>
          <?= $stat('USB-C', 'One cable, 5 V', 'Ein Kabel, 5 V', true) ?>
        </div>
      </div>
    </section>

    <section id="demo" style="border-bottom:1px solid #1B2126;background:#0A0C0D">
      <div style="max-width:1240px;margin:0 auto;padding:clamp(48px,7vw,88px) 20px">
        <div class="demo-grid">

          <div class="demo-intro">
            <p style="margin:0 0 14px;<?= $mono ?>font-size:11px;letter-spacing:0.24em;text-transform:uppercase;color:#E09A1A"<?= de('Gerechnet wie fürs Gerät') ?>>Rendered like on the device</p>
            <div data-pxwrap="1" style="margin:0 0 18px"><canvas aria-hidden="true"></canvas></div>
            <h2 data-pxhead="1"<?= de('Was das Panel zeigt') ?>>What the panel shows</h2>
            <p style="margin:0 0 28px;max-width:46ch;font-size:16px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Jedes Bild ist eine echte Antwort des Servers, gezeichnet mit demselben Code wie die Vorschau auf der Geräteseite. Ein Flug in seinen vier Ansichten, Abfahrten am Hauptbahnhof, die Uhr in Luxemburger Zeit, das Wetter von heute in Luxemburg-Stadt, eine Notiz. Nur das Logo ist hier ein Farbblock.') ?>>Every picture is a real answer from the server, drawn with the same code as the preview on the device page. A flight in its four views, departures at Luxembourg's main station, the clock in Luxembourg time, today's weather in Luxembourg City, a note. Only the logo is a colour block here.</p>
          </div>

          <div class="demo-tabs">
            <div data-views style="display:flex;flex-direction:column;gap:0;border-top:1px solid #1B2126;<?= $mono ?>font-size:12px" role="group" aria-label="Panel screen" data-de-label="Panel-Bildschirm">
              <?php foreach ($screens as $i => [$sEn, $sDe, $hEn, $hDe]): ?>
                <button type="button" class="vtab" data-view="<?= $i ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><span style="color:#8B949C;width:22px;display:inline-block"><?= sprintf('%02d', $i + 1) ?></span><span style="flex:1;text-align:left"<?= $sDe !== $sEn ? de($sDe) : '' ?>><?= h($sEn) ?></span><span style="color:#8B949C"<?= $hDe !== $hEn ? de($hDe) : '' ?>><?= h($hEn) ?></span></button>
              <?php endforeach; ?>
            </div>

            <button type="button" data-cycle class="motion a-press" data-running="true" style="margin-top:16px"><span></span><span data-cycle-label>Pause cycling</span></button>
          </div>

          <div class="demo-side">
            <div style="position:relative;padding:9px;background:linear-gradient(#22282D,#15191C);border-radius:3px;box-shadow:0 24px 60px -20px rgba(0,0,0,0.9),inset 0 1px 0 rgba(255,255,255,0.06)">
              <div data-panel style="background:#050607;padding:0;border-radius:1px;overflow:hidden;display:flex;justify-content:center">
                <canvas data-px="panel" role="img" aria-label="Panel simulation, 128 by 64 pixels"></canvas>
              </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px 18px;margin-top:14px;<?= $mono ?>font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C">
              <span>HUB75E &middot; 1/32 SCAN</span>
              <span>FM6126A</span>
              <span>128 &times; 64 &middot; P2.5</span>
              <span style="margin-left:auto;color:#E09A1A"<?= de('Logo: 32×34, echte Logos nur auf dem eigenen Gerät') ?>>Logo: 32×34, real artwork only on your own device</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="modi" style="border-bottom:1px solid #1B2126">
      <div style="max-width:1240px;margin:0 auto;padding:clamp(48px,7vw,88px) 20px">
        <div style="margin-bottom:clamp(28px,4vw,44px)">
          <div data-pxwrap="1" style="margin:0 0 14px"><canvas aria-hidden="true"></canvas></div>
          <h2 data-pxhead="1"<?= de('Elf Modi') ?>>Eleven modes</h2>
          <p style="margin:0;<?= $mono ?>font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#8B949C"<?= de('Jederzeit von der Webseite umschaltbar, kein Neu-Flashen') ?>>Switch any time from the web, no reflashing</p>
        </div>

        <?php /* Die Linien zeichnet jede Kachel selbst, leere Zellen der letzten Reihe bleiben Seitengrund. */ ?>
        <div data-modes style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,236px),1fr));gap:1px">
          <?= $tile('m-flight', 'Flight radar', 'Flugradar', 'The nearest aircraft in your radius, four views: route, departure and arrival, what it is flying over, the numbers. In the units you pick.', 'Das nächste Flugzeug im Umkreis, vier Ansichten: Route, Abflug und Ankunft, worüber es fliegt, die Messwerte. In den Einheiten deiner Wahl.', true) ?>
          <?= $tile('m-track', 'Track one flight', 'Einzelflug', 'Flight number, callsign or registration, anywhere in the world: what it is flying over, when it left, when it lands. Shows up once a receiver can see it.', 'Flugnummer, Rufzeichen oder Kennzeichen, egal wo auf der Welt: worüber er fliegt, wann er gestartet ist, wann er landet. Erscheint, sobald ein Empfänger ihn sieht.', true) ?>
          <?= $tile('m-transit', 'Departures', 'Abfahrten', 'CFL, RGTR, AVL, Luxtram, TICE. Up to three stops, picked on a map of the whole country. The number is the real time, a delay turns it amber.', 'CFL, RGTR, AVL, Luxtram, TICE. Bis zu drei Haltestellen, gewählt auf einer Karte des ganzen Landes. Die Zahl ist die Echtzeit, eine Verspätung färbt sie bernstein.', true) ?>
          <?= $tile('m-clock', 'Clock and date', 'Uhr und Datum', 'Fonts, colours, seconds, 12 or 24 hour, dimmer after sunset. Runs from the clock chip on the board, even without Wi-Fi.', 'Schrift, Farben, Sekunden, 12 oder 24 Stunden, nach Sonnenuntergang dunkler. Läuft aus dem Uhrchip auf dem Board, auch ohne WLAN.', true) ?>
          <?= $tile('m-weather', 'Weather', 'Wetter', 'As a strip under the clock, or full screen with three days ahead. Celsius or Fahrenheit, wind if you like.', 'Als Streifen unter der Uhr, oder ganzflächig mit drei Tagen Vorhersage. Celsius oder Fahrenheit, Wind nach Wunsch.', true) ?>
          <?= $tile('m-notes', 'Notes', 'Notizen', 'Type it, it shows up on the wall. Two lines of 21 characters, a new note flashes three times. People you share the device with may write too.', 'Tippen, und es steht an der Wand. Zwei Zeilen mit je 21 Zeichen, eine neue Notiz blinkt dreimal. Wem du das Gerät freigibst, der darf auch schreiben.', true) ?>
          <?= $tile('m-pixel', 'Pixel editor', 'Pixel-Editor', 'All 8192 pixels, one by one, in the browser. Save as a scene.', 'Alle 8192 Pixel, einzeln, im Browser. Als Szene speichern.', false) ?>
          <?= $tile('m-ticker', 'Ticker', 'Laufband', 'Longer text scrolling across, adjustable speed.', 'Längerer Text, der durchläuft, Geschwindigkeit einstellbar.', false) ?>
          <?= $tile('m-timer', 'Countdown', 'Countdown', 'To a date or as a timer. Flashes when it hits zero.', 'Auf ein Datum oder als Timer. Blinkt, wenn er abgelaufen ist.', false) ?>
          <?= $tile('m-music', 'Now playing', 'Läuft gerade', 'Title, artist and a level meter. Needs a Spotify login.', 'Titel, Interpret und ein Pegel. Braucht eine Spotify-Anmeldung.', false) ?>
          <?= $tile('m-image', 'Images and GIFs', 'Bilder und GIFs', 'Drop a file in. The browser reduces it to 128 by 64 and shows you the result before it goes out.', 'Datei reinziehen. Der Browser rechnet auf 128 mal 64 runter und zeigt das Ergebnis vorher.', false) ?>

          <article style="background:#0B0D0F;padding:20px;display:flex;flex-direction:column;justify-content:center;gap:10px;border:1px dashed #2C353C;grid-column:1 / -1">
            <p style="margin:0;<?= $mono ?>font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#E09A1A"<?= de('Deine Idee') ?>>Your idea</p>
            <p style="margin:0;font-size:13px;line-height:1.5;color:#8B949C"<?= de('Modi entstehen auf dem Server, nicht in der Firmware. Neue kosten deshalb keinen Flash, und weil der Quelltext offen ist, kannst du eigene bauen.') ?>>Modes are built on the server, not in the firmware. New ones cost no flash, and with the source open you can add your own.</p>
          </article>
        </div>
      </div>
    </section>

    <section id="ablauf" style="border-bottom:1px solid #1B2126;background:#0A0C0D;scroll-margin-top:70px">
      <div style="max-width:1240px;margin:0 auto;padding:clamp(48px,7vw,88px) 20px">
        <div data-pxwrap="1" style="margin:0 0 clamp(28px,4vw,48px)"><canvas aria-hidden="true"></canvas></div>
        <h2 data-pxhead="1"<?= de('Drei Schritte, dann läuft es') ?>>Three steps, then it runs</h2>

        <ol style="list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:1px;background:#1B2126;border:1px solid #1B2126">
          <li style="background:#08090A;padding:clamp(22px,3vw,32px)">
            <p style="margin:0 0 16px;<?= $mono ?>font-weight:700;letter-spacing:-0.03em;font-size:42px;line-height:1;color:#FFAA00">01</p>
            <h3 style="margin:0 0 10px;font-size:16px;font-weight:600;color:#E8EAEC"<?= de('Einstecken, ins WLAN des Geräts') ?>>Plug it in, join its Wi-Fi</h3>
            <p style="margin:0;font-size:14px;line-height:1.6;color:#8B949C"<?= de('Das Panel zeigt den Namen seines eigenen Netzes, THE WALL SETUP, und das Passwort dazu. Mit dem Handy verbinden, die Einrichtungsseite öffnet sich meist von selbst. Dort dein Heim-WLAN wählen, 2,4 GHz.') ?>>The panel shows the name of its own network, THE WALL SETUP, and the password for it. Join it with your phone and the setup page usually opens by itself. Pick your home Wi-Fi there, 2.4 GHz.</p>
          </li>
          <li style="background:#08090A;padding:clamp(22px,3vw,32px)">
            <p style="margin:0 0 16px;<?= $mono ?>font-weight:700;letter-spacing:-0.03em;font-size:42px;line-height:1;color:#FFAA00">02</p>
            <h3 style="margin:0 0 10px;font-size:16px;font-weight:600;color:#E8EAEC"<?= de('Schlüssel einsetzen') ?>>Paste the key</h3>
            <p style="margin:0;font-size:14px;line-height:1.6;color:#8B949C"<?= de('Dein Konto gibt dir einen API-Schlüssel. Den auf derselben Einrichtungsseite einsetzen, damit kennen sich beide. Das Panel sagt HALLO mit deinem Namen.') ?>>Your account hands you an API key. Paste it into the same setup page, that is what pairs the two. The panel says HELLO with your name.</p>
          </li>
          <li style="background:#08090A;padding:clamp(22px,3vw,32px)">
            <p style="margin:0 0 16px;<?= $mono ?>font-weight:700;letter-spacing:-0.03em;font-size:42px;line-height:1;color:#FFAA00">03</p>
            <h3 style="margin:0 0 10px;font-size:16px;font-weight:600;color:#E8EAEC"<?= de('Von überall bedienen') ?>>Run it from anywhere</h3>
            <p style="margin:0;font-size:14px;line-height:1.6;color:#8B949C"<?= de('Modi, Rotation, Helligkeit, Nachtabsenkung, Firmware-Updates über die Luft. Eine Änderung ist in gut zwei Sekunden auf dem Panel. Es braucht nur Strom und WLAN.') ?>>Modes, rotation, brightness, night dimming, firmware updates over the air. A change reaches the panel in about two seconds. It only needs power and Wi-Fi.</p>
          </li>
        </ol>
      </div>
    </section>

    <section id="technik" style="border-bottom:1px solid #1B2126">
      <div style="max-width:1240px;margin:0 auto;padding:clamp(48px,7vw,88px) 20px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:clamp(32px,5vw,64px)">
          <div>
            <div data-pxwrap="1" style="margin:0 0 18px"><canvas aria-hidden="true"></canvas></div>
            <h2 data-pxhead="1"<?= de('Nichts gelötet') ?>>Nothing soldered</h2>
            <?php if ($repo !== ''): ?>
              <p style="margin:0 0 24px;max-width:44ch;font-size:16px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Zwei Teile, beide steckbar, dazu zwei gedruckte Füße, auf die das Board aufschnappt. Ein Handyladegerät versorgt das Ganze. Firmware und Server liegen auf GitHub.') ?>>Two parts that plug together, plus two printed feet the board snaps onto. A phone charger runs the whole thing. Firmware and server are on GitHub.</p>
              <a href="<?= h($repo) ?>" rel="noreferrer" class="hit" style="<?= $mono ?>font-size:12px;letter-spacing:0.1em;text-transform:uppercase;border-bottom:1px solid #E09A1A;padding-bottom:2px">Repository &rarr;</a>
            <?php else: ?>
              <p style="margin:0 0 24px;max-width:44ch;font-size:16px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Zwei Teile, beide steckbar, dazu zwei gedruckte Füße, auf die das Board aufschnappt. Ein Handyladegerät versorgt das Ganze.') ?>>Two parts that plug together, plus two printed feet the board snaps onto. A phone charger runs the whole thing.</p>
            <?php endif; ?>
          </div>
          <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:0;<?= $mono ?>font-size:12.5px;align-content:start">
            <?= $dtdd('PANEL', null, '128×64, P2.5, indoor', null) ?>
            <?= $dtdd('BOARD', null, 'ESP32-S3, HUB75, RTC, microSD', null) ?>
            <?= $dtdd('DRIVER', 'TREIBER', 'FM6126A, 1/32 scan', 'FM6126A, 1/32 Scan') ?>
            <?= $dtdd('WI-FI', 'WLAN', '2.4 GHz', '2,4 GHz') ?>
            <?= $dtdd('POWER', 'STROM', 'USB-C, 5 V, power supply with 3 A', 'USB-C, 5 V, Netzteil mit 3 A') ?>
            <?= $dtdd('STAND', 'STANDFUSS', '2 printed feet, no supports', '2 Druckteile, ohne Stützen') ?>
            <?= $dtdd('FLIGHT DATA', 'FLUGDATEN', 'adsb.lol, adsb.fi, VRS, adsbdb', null) ?>
            <?= $dtdd('DEPARTURES', 'ABFAHRTEN', 'mobiliteit.lu, CC BY 4.0', null) ?>
            <?= $dtdd('WEATHER', 'WETTER', 'Open-Meteo', null) ?>
            <?= $dtdd('MAPS', 'KARTEN', 'OpenStreetMap', null, true) ?>
          </dl>
        </div>
      </div>
    </section>

    <section style="background:#0A0C0D;border-bottom:1px solid #1B2126">
      <div style="max-width:1240px;margin:0 auto;padding:clamp(44px,6vw,72px) 20px;display:flex;flex-wrap:wrap;align-items:center;gap:24px;justify-content:space-between">
        <div>
          <div data-pxwrap="1" style="margin:0 0 8px"><canvas aria-hidden="true"></canvas></div>
          <h2 data-pxhead="1"<?= de('Hardware schon da?') ?>>Got the hardware?</h2>
          <p style="margin:0;font-size:14.5px;color:#8B949C"<?= de('Konto, Schlüssel, verbunden. Zwei Minuten.') ?>>Account, key, paired. Two minutes.</p>
        </div>
        <a href="/account?mode=register" class="h-solid a-press" style="<?= $mono ?>font-size:13px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#08090A;background:#FFAA00;padding:15px 28px;border-radius:2px;transition:transform 160ms cubic-bezier(0.23,1,0.32,1),background 160ms ease"<?= de('Konto anlegen') ?>>Create account</a>
      </div>
    </section>
  </main>

  <footer>
    <div style="max-width:1240px;margin:0 auto;padding:32px 20px;display:flex;flex-wrap:wrap;gap:20px 32px;align-items:center;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em">
      <span style="color:#8B949C">THE WALL &middot; 2026</span>
      <a href="/legal#privacy" class="h-text hit" style="color:#8B949C"<?= de('Datenschutz') ?>>Privacy</a>
      <a href="/legal#imprint" class="h-text hit" style="color:#8B949C"<?= de('Impressum') ?>>Legal notice</a>
      <?php if ($repo !== ''): ?>
        <a href="<?= h($repo) ?>" rel="noreferrer" class="h-text hit" style="color:#8B949C">GitHub</a>
      <?php endif; ?>
      <span style="margin-left:auto;color:#8B949C"<?= de('Keine Tracker, keine Analytics, kein Cookie-Banner') ?>>No trackers, no analytics, no cookie banner</span>
    </div>
  </footer>
</div>
