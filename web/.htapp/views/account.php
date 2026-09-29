<?php
/** @var array $s Zustand aus pages/account.php */
$mono = "font-family:'IBM Plex Mono',monospace;";
$mode = $s['mode'];
$err = $s['errors'];
$de = static fn(string $txt): string => de($txt);

$heads = [
    'login' => ['Welcome back', 'Willkommen zurück'],
    'register' => ['Create your account', 'Konto anlegen'],
    'reset' => ['Forgotten password', 'Passwort vergessen'],
    'newpass' => ['A new password', 'Neues Passwort'],
    'verified' => ['All set', 'Alles bereit'],
];
$leads = [
    'login' => ['Sign in to manage your devices.', 'Melde dich an, um deine Geräte zu verwalten.'],
    'register' => ['You get a key afterwards. Paste it into your device and the two know each other.', 'Danach bekommst du einen Schlüssel, den du in dein Gerät einsetzt. Das verbindet beide.'],
    'reset' => ['Enter your email address. We send you a link that lets you set a new password.', 'Gib deine E-Mail-Adresse ein. Wir schicken dir einen Link, mit dem du ein neues Passwort setzen kannst.'],
    'newpass' => ['The link was valid. Set a new password now.', 'Der Link war gültig. Setz jetzt ein neues Passwort.'],
    'verified' => ['Your address is confirmed and your account is open.', 'Deine Adresse ist bestätigt und dein Konto ist offen.'],
];
if ($mode === 'register' && !$s['can_register']) {
    $leads['register'] = $s['first_user']
        ? ['This server is waiting for its first account. That needs the setup link.', 'Dieser Server wartet auf sein erstes Konto. Dafür braucht es den Einrichtungslink.']
        : ['New accounts need an invitation link at the moment. Ask the person who runs this site.', 'Neue Konten gibt es gerade nur mit Einladungslink. Frag die Person, die diese Seite betreibt.'];
}
if ($mode === 'newpass' && $s['done']) {
    $leads['newpass'] = ['Your new password is saved.', 'Dein neues Passwort ist gespeichert.'];
}
$submits = [
    'login' => ['Sign in', 'Anmelden'],
    'register' => ['Create account', 'Konto anlegen'],
    'reset' => ['Send the link', 'Link schicken'],
    'newpass' => ['Save the password', 'Passwort speichern'],
    'verified' => ['Go to my devices', 'Zu meinen Geräten'],
];
$actions = [
    'login' => '/account/login',
    'register' => '/account/register',
    'reset' => '/account/reset',
    'newpass' => '/account/new-password',
];

$done = [
    'register' => ['Done. We sent you a confirmation email.', 'Fertig. Wir haben dir eine Bestätigung geschickt.'],
    'reset' => ['If an account with that address exists, the link is on its way.', 'Wenn es ein Konto mit dieser Adresse gibt, ist der Link unterwegs.'],
    'newpass' => ['Saved. You can sign in now.', 'Gespeichert. Du kannst dich jetzt anmelden.'],
];
$status = $s['status'];
$statusOk = false;
$statusInfo = is_array($status) && ($status[2] ?? '') === 'info';
if ($s['done'] && isset($done[$mode])) {
    $status = $done[$mode];
    $statusOk = true;
}

$showTabs = in_array($mode, ['login', 'register'], true);
$showBack = in_array($mode, ['reset', 'newpass'], true);
$formHidden = $mode === 'register' && !$s['can_register'];
$showEmail = in_array($mode, ['login', 'register', 'reset'], true) && !$formHidden;
$showPass = (in_array($mode, ['login', 'register'], true) && !$formHidden) || ($mode === 'newpass' && !$s['done']);
$showSubmit = !$formHidden && $mode !== 'verified' && !($mode === 'newpass' && $s['done']) && !($mode === 'reset' && $s['done']);
if ($mode === 'newpass' && $s['done']) {
    $showBack = true;
}

$label = 'display:block;margin-bottom:7px;' . $mono . 'font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3';
$hint = static function (bool $isError) use ($mono): string {
    return 'margin:7px 0 0;' . $mono . 'font-size:11.5px;line-height:1.45;letter-spacing:0.02em;color:' . ($isError ? '#FF7A54' : '#8B949C');
};
$hintText = static function (string $field, array $default) use ($err): array {
    return $err[$field] ?? $default;
};
$field = 'field field-account';
?>
<div style="min-height:100vh;display:flex;flex-direction:column">

  <?= skip_link('#form', 'Skip to the form', 'Zum Formular springen') ?>

  <header style="border-bottom:1px solid #1B2126">
    <div style="max-width:1100px;margin:0 auto;padding:14px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 20px">
      <?= brand_mark('/') ?>
      <div style="flex:1 0 0;min-width:0"></div>
      <?= lang_switch() ?>
    </div>
  </header>

  <main style="flex:1;display:flex;align-items:center;justify-content:center;padding:clamp(24px,5vw,64px) 20px">
    <div style="width:100%;max-width:1100px;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:clamp(28px,5vw,56px);align-items:center">

      <div>
        <div style="position:relative;padding:9px;background:linear-gradient(#22282D,#15191C);border-radius:3px;box-shadow:0 24px 60px -20px rgba(0,0,0,0.9),inset 0 1px 0 rgba(255,255,255,0.06)">
          <div data-panel style="background:#050607;border-radius:1px;overflow:hidden;display:flex;justify-content:center">
            <canvas data-px="panel" role="img" aria-label="Live preview of the panel" data-de-label="Live-Vorschau des Panels"></canvas>
          </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px 18px;margin-top:14px;<?= $mono ?>font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#8B949C">
          <span style="display:flex;align-items:center;gap:7px;color:#3DE07C"><span class="blink-18" style="width:6px;height:6px;background:#3DE07C;border-radius:1px"></span>PREVIEW</span>
          <span>128 &times; 64</span>
          <span style="margin-left:auto"<?= $de('Zeigt, was dein Gerät zeigen würde') ?>>Shows what your device would show</span>
        </div>
        <button type="button" data-motion class="motion a-press" data-running="true" style="margin-top:14px"><span></span><span data-motion-label>Pause motion</span></button>
      </div>

      <div id="form">
        <div data-tabs style="display:flex;gap:0;margin-bottom:clamp(24px,3vw,32px);border-bottom:1px solid #1B2126" role="group" aria-label="Account mode" data-de-label="Kontomodus"<?= $showTabs ? '' : ' hidden' ?>>
          <button type="button" class="tab" data-go="login" aria-pressed="<?= $mode === 'login' ? 'true' : 'false' ?>"<?= $de('Anmelden') ?>>Sign in</button>
          <button type="button" class="tab" data-go="register" aria-pressed="<?= $mode === 'register' ? 'true' : 'false' ?>"<?= $de('Konto anlegen') ?>>Create account</button>
        </div>

        <button type="button" data-go="login" data-back class="h-bright" style="background:none;border:0;padding:0 0 2px;margin:0 0 clamp(20px,3vw,28px);font:inherit;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#8B949C;cursor:pointer;transition:color 160ms ease;min-height:44px"<?= $showBack ? '' : ' hidden' ?>><span aria-hidden="true">&larr; </span><span<?= $de('Zurück zur Anmeldung') ?>>Back to sign in</span></button>

        <div data-pxwrap="1" style="margin:0 0 10px"><canvas aria-hidden="true"></canvas></div>
        <h1 data-pxhead="1" data-head<?= $de($heads[$mode][1]) ?>><?= h($heads[$mode][0]) ?></h1>

        <p data-lead style="margin:0 0 clamp(24px,3vw,30px);max-width:38ch;font-size:15px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= $de($leads[$mode][1]) ?>><?= h($leads[$mode][0]) ?></p>

        <form data-form method="post" action="<?= h($actions[$mode] ?? '/account') ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="invite" value="<?= h($s['invite']) ?>">
          <input type="hidden" name="setup" value="<?= h($s['setup']) ?>">
          <input type="hidden" name="token" value="<?= h($s['token']) ?>">
          <input type="hidden" name="next" value="<?= h($s['next']) ?>">
          <input type="hidden" name="lang" value="en" data-lang-field>

          <div data-when="register" style="margin-bottom:18px"<?= $mode === 'register' && !$formHidden ? '' : ' hidden' ?>>
            <label for="tw-user" style="<?= $label ?>"<?= $de('Benutzername') ?>>Username</label>
            <input id="tw-user" name="username" type="text" autocomplete="username" spellcheck="false" autocapitalize="none" placeholder="lenny" maxlength="20" value="<?= h($s['values']['username']) ?>" aria-invalid="<?= isset($err['username']) ? 'true' : 'false' ?>" aria-describedby="tw-user-hint" class="<?= $field ?>">
            <?php $ht = $hintText('username', ['3 to 20 characters: letters, numbers, underscore', '3 bis 20 Zeichen, Buchstaben, Zahlen, Unterstrich']); ?>
            <p id="tw-user-hint" aria-live="polite" aria-atomic="true" style="<?= $hint(isset($err['username'])) ?>"<?= $de($ht[1]) ?>><?= h($ht[0]) ?></p>
          </div>

          <div data-when="login register reset" style="margin-bottom:18px"<?= $showEmail ? '' : ' hidden' ?>>
            <label for="tw-mail" style="<?= $label ?>"<?= $de('E-Mail') ?>>Email</label>
            <input id="tw-mail" name="email" type="email" autocomplete="email" spellcheck="false" autocapitalize="none" placeholder="du@example.lu" maxlength="190" value="<?= h($s['values']['email']) ?>" aria-invalid="<?= isset($err['email']) ? 'true' : 'false' ?>" aria-describedby="tw-mail-hint" class="<?= $field ?>">
            <?php $ht = $hintText('email', ['For confirmation and password links', 'Für die Bestätigung und für Passwort-Links']); ?>
            <p id="tw-mail-hint" aria-live="polite" aria-atomic="true" style="<?= $hint(isset($err['email'])) ?>"<?= $de($ht[1]) ?>><?= h($ht[0]) ?></p>
          </div>

          <div data-when="login register newpass" style="margin-bottom:10px"<?= $showPass ? '' : ' hidden' ?>>
            <label for="tw-pass" style="<?= $label ?>" data-pass-label<?= $de($mode === 'newpass' ? 'Neues Passwort' : 'Passwort') ?>><?= $mode === 'newpass' ? 'New password' : 'Password' ?></label>
            <div style="position:relative;display:flex;align-items:stretch">
              <input id="tw-pass" name="password" type="password" autocomplete="<?= $mode === 'login' ? 'current-password' : 'new-password' ?>" placeholder="<?= $mode === 'register' ? '••••••' : '' ?>" aria-invalid="<?= isset($err['password']) ? 'true' : 'false' ?>" aria-describedby="tw-pass-hint" class="<?= $field ?> pw-field" style="border-top-right-radius:0;border-bottom-right-radius:0;border-right:0">
              <button type="button" data-pass-toggle class="pw-toggle" aria-pressed="false" aria-label="Show password" data-de-label="Passwort anzeigen"><span<?= $de('Anzeigen') ?>>Show</span></button>
            </div>

            <div data-meter data-when="register newpass" style="display:flex;gap:3px;margin-top:10px" aria-hidden="true"<?= in_array($mode, ['register', 'newpass'], true) ? '' : ' hidden' ?>>
              <span></span><span></span><span></span><span></span><span></span>
            </div>

            <?php $ht = $hintText('password', in_array($mode, ['register', 'newpass'], true) ? ['At least 6 characters, including at least one letter', 'Mindestens 6 Zeichen, davon mindestens ein Buchstabe'] : ['', '']); ?>
            <p id="tw-pass-hint" aria-live="polite" aria-atomic="true" style="<?= $hint(isset($err['password'])) ?>"<?= $de($ht[1]) ?>><?= h($ht[0]) ?></p>
          </div>

          <div data-when="newpass" style="margin-bottom:10px"<?= $mode === 'newpass' && !$s['done'] ? '' : ' hidden' ?>>
            <label for="tw-pass2" style="<?= $label ?>"<?= $de('Passwort wiederholen') ?>>Repeat the password</label>
            <input id="tw-pass2" name="password2" type="password" autocomplete="new-password" aria-invalid="<?= isset($err['password2']) ? 'true' : 'false' ?>" aria-describedby="tw-pass2-hint" class="<?= $field ?>">
            <?php $ht = $hintText('password2', ['Once more, to be sure', 'Zur Sicherheit noch einmal']); ?>
            <p id="tw-pass2-hint" aria-live="polite" aria-atomic="true" style="<?= $hint(isset($err['password2'])) ?>"<?= $de($ht[1]) ?>><?= h($ht[0]) ?></p>
          </div>

          <div data-when="login" style="display:flex;justify-content:flex-end;margin-bottom:22px"<?= $mode === 'login' ? '' : ' hidden' ?>>
            <button type="button" data-go="reset" class="hit" style="background:none;border:0;padding:0 0 1px;font:inherit;<?= $mono ?>font-size:12px;letter-spacing:0.06em;color:#FFAA00;border-bottom:1px solid #7A5200;cursor:pointer"<?= $de('Passwort vergessen?') ?>>Forgot your password?</button>
          </div>

          <p data-when="register" style="margin:18px 0 22px;font-size:12.5px;line-height:1.6;color:#8B949C"<?= $de('Wir speichern Benutzername, E-Mail und dein Passwort als Hash. Keine Tracker, keine Analytics. Server in Luxemburg, Verbindung über Cloudflare. Mehr in der Datenschutzerklärung.') ?><?= $mode === 'register' && !$formHidden ? '' : ' hidden' ?>>We store your username, your email and a hash of your password. No trackers, no analytics. Server in Luxembourg, connection through Cloudflare. Details in the privacy notice.</p>

          <?php if ($mode === 'verified'): ?>
            <div style="padding:18px;border:1px solid #1F4A33;border-radius:2px;background:rgba(61,224,124,0.06);margin-bottom:20px">
              <p style="margin:0 0 6px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#3DE07C"<?= $de('Bestätigt') ?>>Confirmed</p>
              <p style="margin:0;font-size:14px;line-height:1.6;color:#C6CDD3"<?= $de('Deine E-Mail-Adresse ist bestätigt. Der Schlüssel für dein Gerät liegt unter Einstellungen bereit.') ?>>Your email address is confirmed. The key for your device is waiting under Settings.</p>
            </div>
          <?php endif; ?>

          <button type="submit" data-submit style="width:100%;min-height:52px;margin-top:14px;border:0;border-radius:2px;cursor:pointer;<?= $mono ?>font-size:13px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;transition:background 160ms ease,transform 160ms cubic-bezier(0.23,1,0.32,1);background:#FFAA00;color:#08090A"<?= $de($submits[$mode][1]) ?><?= $showSubmit ? '' : ' hidden' ?>><?= h($submits[$mode][0]) ?></button>
          <?php if ($mode === 'verified'): ?>
            <?php /* Ein Link, kein Formular: /device gibt es nur als GET, ein POST dorthin endete mit 405. */ ?>
            <a href="/device" style="display:flex;align-items:center;justify-content:center;width:100%;min-height:52px;margin-top:14px;border-radius:2px;text-decoration:none;<?= $mono ?>font-size:13px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;background:#FFAA00;color:#08090A"<?= $de($submits['verified'][1]) ?>><?= h($submits['verified'][0]) ?></a>
          <?php endif; ?>

          <p data-status role="status" aria-live="polite" style="margin:14px 0 0;<?= $mono ?>font-size:12px;line-height:1.5;min-height:18px;color:<?= $statusOk ? '#3DE07C' : ($statusInfo ? '#B4BCC3' : '#FF7A54') ?>"<?= $status ? $de($status[1]) : '' ?>><?= $status ? h($status[0]) : '' ?></p>
        </form>

        <p data-switch style="margin:26px 0 0;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em;color:#8B949C"<?= $showTabs ? '' : ' hidden' ?>>
          <span data-switch-text<?= $de($mode === 'register' ? 'Schon ein Konto?' : 'Noch kein Konto?') ?>><?= $mode === 'register' ? 'Already have an account?' : 'No account yet?' ?></span>
          <button type="button" data-switch-go class="hit" style="background:none;border:0;padding:0 0 1px;margin-left:6px;font:inherit;color:#FFAA00;border-bottom:1px solid #7A5200;cursor:pointer"<?= $de($mode === 'register' ? 'Anmelden' : 'Konto anlegen') ?>><?= $mode === 'register' ? 'Sign in' : 'Create one' ?></button>
        </p>
      </div>
    </div>
  </main>

  <footer style="border-top:1px solid #1B2126">
    <div style="max-width:1100px;margin:0 auto;padding:24px 20px;display:flex;flex-wrap:wrap;gap:16px 28px;align-items:center;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em">
      <span style="color:#8B949C">THE WALL &middot; 2026</span>
      <a href="/legal#privacy" class="h-text hit" style="color:#8B949C"<?= $de('Datenschutz') ?>>Privacy</a>
      <a href="/legal#imprint" class="h-text hit" style="color:#8B949C"<?= $de('Impressum') ?>>Legal notice</a>
      <?php if (imprint()['repo'] !== ''): ?>
        <a href="<?= h(imprint()['repo']) ?>" rel="noreferrer" class="h-text hit" style="color:#8B949C">GitHub</a>
      <?php endif; ?>
      <span style="margin-left:auto;color:#8B949C"<?= $de('Keine Tracker, kein Cookie-Banner') ?>>No trackers, no cookie banner</span>
    </div>
  </footer>
</div>
