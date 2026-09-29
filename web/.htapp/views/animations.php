<?php
$mono = "font-family:'IBM Plex Mono',monospace;";
$groupHead = 'margin:0 0 10px;' . $mono . 'font-size:10px;letter-spacing:0.16em;text-transform:uppercase;color:#8B949C';
$row = static function (int $i, string $en, string $de): string {
    return '<button type="button" class="arow" role="radio" data-anim="' . $i . '" aria-checked="' . ($i === 0 ? 'true' : 'false') . '" tabindex="' . ($i === 0 ? '0' : '-1') . '">'
        . '<span class="anum">' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . '</span>'
        . '<span style="flex:1;text-align:left"' . de($de) . '>' . h($en) . '</span></button>';
};
?>
<div style="min-height:100vh;display:flex;flex-direction:column">

  <?= skip_link('#stage', 'Skip to the preview', 'Zur Vorschau springen') ?>

  <header style="position:sticky;top:0;z-index:30;background:rgba(8,9,10,0.9);backdrop-filter:blur(12px);border-bottom:1px solid #1B2126">
    <div style="max-width:1320px;margin:0 auto;padding:13px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 20px">
      <?= brand_mark('/') ?>
      <span style="padding-left:20px;border-left:1px solid #232A30;<?= $mono ?>font-size:12px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C"<?= de('Geräte-Animationen') ?>>Device animations</span>
      <div style="flex:1 0 0;min-width:0"></div>
      <?= lang_switch() ?>
    </div>
  </header>

  <main style="flex:1;width:100%;max-width:1320px;margin:0 auto;padding:clamp(24px,4vw,40px) 20px">

    <div data-pxwrap="1" style="margin:0 0 14px;max-width:660px"><canvas aria-hidden="true"></canvas></div>
    <h1 data-pxhead="1"<?= de('Dreizehn Zustände') ?>>Thirteen states</h1>
    <p style="margin:0 0 clamp(28px,4vw,40px);max-width:60ch;font-size:15.5px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Jede läuft mit zwölf Bildern pro Sekunde. Das ist eine Stilentscheidung: das Panel soll sichtbar springen, die Bibliothek schafft deutlich mehr. Alle werden gerechnet, nicht gespeichert: ein Vollbild wäre 24 KB, eine Formel sind zweihundert Byte.') ?>>Each runs at twelve frames a second. That is a style decision: the panel is meant to step visibly, the driver library could do far more. All of them are computed, not stored: one full frame would be 24 KB, a formula is two hundred bytes.</p>

    <div id="stage" class="stage-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:1px;background:#1B2126;border:1px solid #1B2126;align-items:stretch;scroll-margin-top:80px">

      <nav aria-label="Animations" data-de-label="Animationen" style="background:#0B0D0F;padding:14px;min-width:0">
        <div role="radiogroup" aria-label="Animation" data-de-label="Animation" data-radiogroup>
          <div role="group" aria-labelledby="grp-start" style="margin:0">
            <p id="grp-start" style="<?= $groupHead ?>"<?= de('Beim Start') ?>>At startup</p>
            <div style="display:flex;flex-direction:column;gap:2px">
              <?= $row(0, 'Power on', 'Einschalten') ?>
              <?= $row(1, 'No Wi-Fi yet', 'Kein WLAN') ?>
              <?= $row(2, 'Connecting', 'Verbindet') ?>
              <?= $row(3, 'Showing its address', 'Adresse zeigen') ?>
              <?= $row(4, 'Paired', 'Gekoppelt') ?>
            </div>
          </div>
          <div role="group" aria-labelledby="grp-use" style="margin-top:18px">
            <p id="grp-use" style="<?= $groupHead ?>"<?= de('Im Betrieb') ?>>In use</p>
            <div style="display:flex;flex-direction:column;gap:2px">
              <?= $row(5, 'Mode change', 'Moduswechsel') ?>
              <?= $row(6, 'Nothing flying', 'Nichts fliegt') ?>
              <?= $row(7, 'New note', 'Neue Notiz') ?>
              <?= $row(8, 'Resting', 'Ruhezustand') ?>
            </div>
          </div>
          <div role="group" aria-labelledby="grp-missing" style="margin-top:18px">
            <p id="grp-missing" style="<?= $groupHead ?>"<?= de('Wenn etwas fehlt') ?>>When something is missing</p>
            <div style="display:flex;flex-direction:column;gap:2px">
              <?= $row(9, 'Server gone', 'Server weg') ?>
              <?= $row(10, 'Wi-Fi gone', 'WLAN weg') ?>
              <?= $row(11, 'Updating', 'Update läuft') ?>
              <?= $row(12, 'Power off', 'Ausschalten') ?>
            </div>
          </div>
        </div>
      </nav>

      <div class="stage-main" style="background:#0B0D0F;padding:clamp(16px,2.5vw,24px);grid-column:span 2;min-width:0">
        <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:10px 16px;margin-bottom:16px">
          <p data-title style="margin:0;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#FFAA00"></p>
          <span data-timing style="<?= $mono ?>font-size:11px;color:#8B949C"></span>
          <span data-clock style="margin-left:auto;<?= $mono ?>font-size:11px;color:#8B949C;font-variant-numeric:tabular-nums"></span>
        </div>

        <div style="padding:9px;background:linear-gradient(#22282D,#15191C);border-radius:3px;box-shadow:0 20px 48px -18px rgba(0,0,0,0.9),inset 0 1px 0 rgba(255,255,255,0.06)">
          <div data-panel style="background:#050607;border-radius:1px;overflow:hidden;display:flex;justify-content:center">
            <canvas data-px="panel" role="img" aria-label="Panel preview"></canvas>
          </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:16px">
          <button type="button" data-play class="motion play" data-running="true"><span></span><span data-play-label>Pause</span></button>
          <button type="button" data-restart class="btn-ghost"<?= de('Von vorn') ?>>Restart</button>
          <div style="display:flex;align-items:center;gap:10px;flex:1 1 180px;min-width:150px">
            <label for="speed" style="<?= $mono ?>font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:#B4BCC3;flex:0 0 auto"<?= de('Tempo') ?>>Speed</label>
            <input id="speed" data-speed type="range" min="25" max="200" step="5" value="100" style="flex:1">
            <span data-speed-out style="<?= $mono ?>font-size:12px;color:#FFAA00;font-variant-numeric:tabular-nums;flex:0 0 auto;width:42px;text-align:right">1.00x</span>
          </div>
        </div>

        <p data-note style="margin:18px 0 0;font-size:14px;line-height:1.6;color:#8B949C;max-width:60ch;text-wrap:pretty"></p>
      </div>
    </div>

    <section style="margin-top:clamp(24px,4vw,36px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:1px;background:#1B2126;border:1px solid #1B2126">
      <article style="background:#0B0D0F;padding:22px">
        <p style="margin:0 0 9px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Was das kostet') ?>>What this costs</p>
        <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:0;<?= $mono ?>font-size:12px">
          <dt style="padding:7px 16px 7px 0;color:#8B949C"<?= de('Ein Vollbild') ?>>One full frame</dt>
          <dd style="margin:0;padding:7px 0;color:#C6CDD3;text-align:right">24 KB</dd>
          <dt style="padding:7px 16px 7px 0;color:#8B949C"<?= de('Eine Sekunde gespeichert') ?>>One second stored</dt>
          <dd style="margin:0;padding:7px 0;color:#FF7A54;text-align:right">288 KB</dd>
          <dt style="padding:7px 16px 7px 0;color:#8B949C"<?= de('Dieselbe Sekunde gerechnet') ?>>The same second computed</dt>
          <dd style="margin:0;padding:7px 0;color:#3DE07C;text-align:right">~200 B</dd>
        </dl>
        <p style="margin:14px 0 0;font-size:12.5px;line-height:1.55;color:#8B949C"<?= de('Deshalb liegt keine einzige Bildfolge im Flash. Jede Animation ist eine Funktion, die aus der Laufzeit ein Bild rechnet.') ?>>That is why no frame sequence sits in flash. Each animation is a function that computes a frame from elapsed time.</p>
      </article>

      <article style="background:#0B0D0F;padding:22px">
        <p style="margin:0 0 9px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Abbrechbar') ?>>Interruptible</p>
        <p style="margin:0 0 14px;font-size:13px;line-height:1.6;color:#8B949C"<?= de('Jede Animation muss jederzeit abbrechen können. Sonst warten Flugdaten hinter einer Begrüßung, und das Panel wirkt langsam statt lebendig.') ?>>Every animation has to be able to stop at any moment. Otherwise flight data waits behind a greeting, and the panel feels slow instead of alive.</p>
        <p style="margin:0;<?= $mono ?>font-size:11.5px;line-height:1.6;color:#B4BCC3">if (frameReady) anim.cancel();</p>
      </article>

      <article style="background:#0B0D0F;padding:22px">
        <p style="margin:0 0 9px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Bewegung reduzieren') ?>>Reduced motion</p>
        <p style="margin:0 0 14px;font-size:13px;line-height:1.6;color:#8B949C"<?= de('Auf dem Gerät gibt es kein Betriebssystem, das eine Vorliebe meldet. Deshalb braucht es einen eigenen Schalter in den Einstellungen, der alle Bewegung auf den Endzustand kappt.') ?>>There is no operating system on the device to report a preference. So it needs its own switch in the settings that cuts every animation down to its final state.</p>
        <div style="display:flex;align-items:center;gap:14px">
          <button type="button" class="sw" role="switch" data-reduce aria-checked="false" aria-labelledby="reduce-label"><span></span></button>
          <span id="reduce-label" style="font-size:13.5px;color:#E8EAEC"<?= de('Hier ausprobieren') ?>>Try it here</span>
        </div>
      </article>
    </section>
  </main>

  <footer style="border-top:1px solid #1B2126">
    <div style="max-width:1320px;margin:0 auto;padding:22px 20px;display:flex;flex-wrap:wrap;gap:14px 26px;align-items:center;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em">
      <span style="color:#8B949C">THE WALL &middot; 2026</span>
      <a href="/device" class="h-text hit" style="color:#8B949C"<?= de('Geräteseite') ?>>Device page</a>
      <a href="/#ablauf" class="h-text hit" style="color:#8B949C"<?= de('Einrichtung') ?>>Setup</a>
      <span style="margin-left:auto;color:#8B949C"><?= h(site_host()) ?></span>
    </div>
  </footer>
</div>
