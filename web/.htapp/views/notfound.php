<?php
/** @var int $status @var string $message @var ?string $messageDe @var ?array $user */
$mono = "font-family:'IBM Plex Mono',monospace;";
$is404 = $status === 404;
$solid = $mono . 'font-size:12.5px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#08090A;background:#FFAA00;padding:15px 24px;border-radius:2px;transition:background 160ms ease,transform 160ms cubic-bezier(0.23,1,0.32,1)';
$ghost = $mono . 'font-size:12.5px;letter-spacing:0.1em;text-transform:uppercase;color:#E8EAEC;border:1px solid #2C353C;padding:15px 24px;border-radius:2px;transition:border-color 160ms ease,background 160ms ease,transform 160ms cubic-bezier(0.23,1,0.32,1)';
?>
<div style="min-height:100vh;display:flex;flex-direction:column">

  <?= skip_link('#main', 'Skip to content', 'Zum Inhalt springen') ?>

  <header style="border-bottom:1px solid #1B2126">
    <div style="max-width:760px;margin:0 auto;padding:14px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 20px">
      <?= brand_mark('/') ?>
      <div style="flex:1 0 0;min-width:0"></div>
      <?= lang_switch() ?>
    </div>
  </header>

  <main id="main" style="flex:1;display:flex;align-items:center;justify-content:center;padding:clamp(32px,6vw,72px) 20px">
    <div style="width:100%;max-width:560px">

      <div style="padding:9px;background:linear-gradient(#22282D,#15191C);border-radius:3px;box-shadow:0 24px 60px -20px rgba(0,0,0,0.9),inset 0 1px 0 rgba(255,255,255,0.06)">
        <div data-panel style="background:#050607;border-radius:1px;overflow:hidden;display:flex;justify-content:center">
          <canvas data-px="panel" role="img" aria-label="Panel showing <?= (int) $status ?>, <?= $is404 ? 'not found' : 'error' ?>" data-de-label="Panel zeigt <?= (int) $status ?>, <?= $is404 ? 'nicht gefunden' : 'Fehler' ?>"></canvas>
        </div>
      </div>

      <?php if ($is404): ?>
        <h1 style="margin:clamp(26px,4vw,36px) 0 12px;<?= $mono ?>font-size:clamp(20px,3.4vw,26px);font-weight:600;letter-spacing:-0.01em;line-height:1.25;color:#E8EAEC"<?= de('Diese Seite gibt es nicht') ?>>There is no page here</h1>
        <p style="margin:0 0 clamp(26px,4vw,34px);font-size:15px;line-height:1.65;color:#8B949C;max-width:46ch;text-wrap:pretty"<?= de('Vielleicht ein alter Link, vielleicht ein Tippfehler in der Adresse. Dein Gerät läuft davon unbeeindruckt weiter.') ?>>Maybe an old link, maybe a typo in the address. Your device carries on regardless.</p>
      <?php else: ?>
        <h1 style="margin:clamp(26px,4vw,36px) 0 12px;<?= $mono ?>font-size:clamp(20px,3.4vw,26px);font-weight:600;letter-spacing:-0.01em;line-height:1.25;color:#E8EAEC"<?= de($messageDe ?? ('Fehler ' . $status)) ?>><?= h($message !== '' ? $message : 'Error ' . $status) ?></h1>
        <p style="margin:0 0 clamp(26px,4vw,34px);font-size:15px;line-height:1.65;color:#8B949C;max-width:46ch;text-wrap:pretty"<?= de('Versuch es gleich noch einmal. Dein Gerät zeigt so lange seinen letzten Inhalt weiter.') ?>>Try again in a moment. Your device keeps showing its last content meanwhile.</p>
      <?php endif; ?>

      <div style="display:flex;flex-wrap:wrap;gap:10px">
        <a href="/device" class="h-solid a-press" style="<?= $solid ?>"<?= de('Zu meinen Geräten') ?>>To my devices</a>
        <a href="/" class="h-ghost a-press" style="<?= $ghost ?>"<?= de('Startseite') ?>>Home page</a>
      </div>

      <dl style="margin:clamp(30px,4vw,44px) 0 0;display:grid;grid-template-columns:auto 1fr;gap:0;<?= $mono ?>font-size:11.5px">
        <dt style="padding:10px 18px 10px 0;border-top:1px solid #1B2126;color:#8B949C"<?= de('Fehler') ?>>Error</dt>
        <dd style="margin:0;padding:10px 0;border-top:1px solid #1B2126;color:#C6CDD3;text-align:right"><?= (int) $status ?></dd>
        <dt style="padding:10px 18px 10px 0;border-top:1px solid #1B2126;border-bottom:1px solid #1B2126;color:#8B949C"<?= de('Geräte betroffen') ?>>Devices affected</dt>
        <?php if ((int) $status < 500): ?>
        <dd style="margin:0;padding:10px 0;border-top:1px solid #1B2126;border-bottom:1px solid #1B2126;color:#3DE07C;text-align:right"<?= de('keine') ?>>none</dd>
        <?php else: ?>
        <dd style="margin:0;padding:10px 0;border-top:1px solid #1B2126;border-bottom:1px solid #1B2126;color:#8B949C;text-align:right"<?= de('unbekannt') ?>>unknown</dd>
        <?php endif; ?>
      </dl>

      <button type="button" data-motion class="motion a-press" data-running="true" style="margin-top:20px" hidden><span></span><span data-motion-label>Pause motion</span></button>
    </div>
  </main>

  <footer style="border-top:1px solid #1B2126">
    <div style="max-width:760px;margin:0 auto;padding:22px 20px;display:flex;flex-wrap:wrap;gap:14px 26px;align-items:center;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em">
      <span style="color:#8B949C">THE WALL &middot; 2026</span>
      <a href="/legal#privacy" class="h-text hit" style="color:#8B949C"<?= de('Datenschutz') ?>>Privacy</a>
      <a href="/legal#imprint" class="h-text hit" style="color:#8B949C"<?= de('Impressum') ?>>Legal notice</a>
      <span style="margin-left:auto;color:#8B949C"><?= h(site_host()) ?></span>
    </div>
  </footer>
</div>
