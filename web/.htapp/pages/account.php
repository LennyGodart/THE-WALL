<?php
/*
 * Kontoseite: anmelden, registrieren, Passwort vergessen, neues Passwort,
 * bestaetigt. Formulare gehen als normaler POST an den Server, der Browser prueft
 * vorab dieselben Regeln. Fehler kommen mit den eingegebenen Werten zurueck.
 *
 * Links aus Mails (/confirm, /reset, /invite) tragen einen Token in der Adresse.
 * Die Seite entfernt ihn gleich nach dem Laden aus der Adresszeile, und wie ueberall
 * gilt Referrer-Policy: same-origin, er geht also nie an eine fremde Adresse wie
 * fonts.bunny.net (FEHLERLISTE 5.4). Frueher stand hier no-referrer; damit schickt der
 * Browser beim Absenden des Formulars Origin: null, und die Herkunftspruefung lehnte
 * Registrierung, neues Passwort und Anmeldung aus einem Mail-Link ab (CHANGELOG 54).
 *
 * Registrierung: standardmaessig nur mit Einladung, der Admin schaltet um.
 * Das allererste Konto entsteht mit dem Einrichtungs-Token aus .htdata/secret.php
 * und wird Admin.
 */

declare(strict_types=1);

const LOGIN_MAX_PER_ACCOUNT = 5;
const LOGIN_MAX_PER_IP = 20;
const LOGIN_WINDOW = 900;

function page_account(array $params = []): void
{
    $user = session_user();
    $mode = (string) ($_GET['mode'] ?? 'login');
    if ($user && $user['email_verified_at'] !== null && !in_array($mode, ['verified', 'newpass'], true)) {
        redirect(account_next((string) ($_GET['next'] ?? '')) ?: '/device');
    }
    if (!in_array($mode, ['login', 'register', 'reset', 'newpass', 'verified'], true)) {
        $mode = 'login';
    }
    $state = [
        'mode' => $mode,
        'values' => ['username' => '', 'email' => ''],
        'errors' => [],
        'status' => null,
        'done' => isset($_GET['done']),
        'next' => account_next((string) ($_GET['next'] ?? '')),
        'invite' => '',
        'setup' => preg_match('/^[0-9a-f]{32}$/', (string) ($_GET['setup'] ?? '')) ? (string) $_GET['setup'] : '',
        'token' => '',
    ];
    if ($mode === 'verified' && !$user) {
        $state['mode'] = 'login';
    }
    if ($mode === 'newpass' && !$state['done']) {
        $state['mode'] = 'login';
    }
    account_render($state);
}

/** Nur Pfade dieser Seite, nie eine fremde Adresse. */
function account_next(string $next): string
{
    return preg_match('#^/(device|settings|admin)(/[A-Za-z0-9_\-]*)?$#', $next) ? $next : '';
}

/** $fromLink: die Seite kam ueber einen Link mit Token, der aus der Adresszeile soll. */
function account_render(array $state, bool $fromLink = false): void
{
    $regOpen = registration_mode() === 'open';
    $firstUser = users_count() === 0;
    if ($firstUser) {
        // Noch kein Konto: .htdata/secret.php anlegen, damit der Einrichtungslink aus der
        // Anleitung gleich im README auffindbar ist. Vorher entstand die Datei erst beim
        // ersten Anmelden, und wer der Anleitung folgte, fand keinen setup_token.
        secret('setup_token');
    }
    $setupOk = $state['setup'] !== '' && hash_equals(secret('setup_token'), $state['setup']);
    $state['can_register'] = $setupOk || ($firstUser ? false : ($regOpen || $state['invite'] !== ''));
    $state['setup_ok'] = $setupOk;
    $state['first_user'] = $firstUser;
    page_open([
        'title' => 'Account',
        'title_de' => 'Konto',
        'page' => 'account',
        'referrer' => 'same-origin',
        'noindex' => $state['mode'] !== 'login' && $state['mode'] !== 'register',
    ]);
    view('account', ['s' => $state]);
    page_close(['js/pages/account.js'], [
        'mode' => $state['mode'],
        'done' => (bool) $state['done'],
        'canRegister' => (bool) $state['can_register'],
        'errors' => $state['errors'],
        'strip' => $fromLink,
        'username' => session_user()['username'] ?? '',
    ]);
}

function account_fail(array $state, array $errors, ?array $status = null): void
{
    http_response_code(422);
    $state['errors'] = $errors;
    $state['status'] = $status ?? ['Please check the highlighted fields.', 'Bitte prüfe die markierten Felder.'];
    account_render($state);
}

function action_login(array $params = []): void
{
    csrf_check();
    $email = strtolower(input_str('email', 190));
    $password = (string) input('password', '');
    $state = [
        'mode' => 'login', 'values' => ['username' => '', 'email' => $email], 'errors' => [], 'status' => null, 'done' => false,
        'next' => account_next(input_str('next', 100)), 'invite' => preg_match('/^[0-9a-f]{48}$/', input_str('invite', 48)) ? input_str('invite', 48) : '',
        'setup' => '', 'token' => '',
    ];

    $errors = [];
    if (!valid_email($email)) {
        $errors['email'] = ['That email address looks incomplete', 'Diese E-Mail-Adresse sieht nicht vollständig aus'];
    }
    if ($password === '') {
        $errors['password'] = ['Please enter your password', 'Bitte dein Passwort eingeben'];
    }
    if ($errors) {
        account_fail($state, $errors);
        return;
    }

    $ipKey = 'login:ip:' . ip_key();
    $acctKey = 'login:acct:' . substr(hash('sha256', $email), 0, 32);
    if (rl_blocked($acctKey, LOGIN_MAX_PER_ACCOUNT, LOGIN_WINDOW) || rl_blocked($ipKey, LOGIN_MAX_PER_IP, LOGIN_WINDOW)) {
        $mins = (int) ceil(max(rl_retry_after($acctKey, LOGIN_WINDOW), 60) / 60);
        account_fail($state, [], ['Too many attempts. Try again in ' . $mins . ' minutes.', 'Zu viele Versuche. Versuch es in ' . $mins . ' Minuten wieder.']);
        return;
    }

    $user = user_by_email($email);
    $ok = false;
    if ($user) {
        $ok = password_check($password, (string) $user['pass_hash']);
    } else {
        password_dummy_check($password);
    }
    if (!$ok) {
        rl_allow($acctKey, LOGIN_MAX_PER_ACCOUNT, LOGIN_WINDOW);
        rl_allow($ipKey, LOGIN_MAX_PER_IP, LOGIN_WINDOW);
        account_fail($state, [], ['Email or password is not right.', 'E-Mail oder Passwort stimmt nicht.']);
        return;
    }
    if ($user['email_verified_at'] === null) {
        if (rl_allow('confirm-resend:' . $user['id'], 1, 600)) {
            $raw = token_create('confirm', ['user_id' => (int) $user['id']]);
            mail_confirm($user, $raw);
        }
        account_fail($state, [], ['Please confirm your email address first. We sent the link again.', 'Bitte bestätige zuerst deine E-Mail-Adresse. Der Link ist noch einmal unterwegs.']);
        return;
    }
    rl_reset($acctKey);
    session_begin((int) $user['id']);
    if ($state['invite'] !== '') {
        $target = invite_accept($state['invite'], $user);
        if ($target !== null) {
            redirect($target);
        }
    }
    redirect($state['next'] !== '' ? $state['next'] : '/device');
}

function action_register(array $params = []): void
{
    csrf_check();
    $username = input_str('username', 40);
    $email = strtolower(input_str('email', 190));
    $password = (string) input('password', '');
    $invite = preg_match('/^[0-9a-f]{48}$/', input_str('invite', 48)) ? input_str('invite', 48) : '';
    $setup = preg_match('/^[0-9a-f]{32}$/', input_str('setup', 32)) ? input_str('setup', 32) : '';
    $state = [
        'mode' => 'register', 'values' => ['username' => $username, 'email' => $email], 'errors' => [], 'status' => null, 'done' => false,
        'next' => '', 'invite' => $invite, 'setup' => $setup, 'token' => '',
    ];

    $setupOk = $setup !== '' && users_count() === 0 && hash_equals(secret('setup_token'), $setup);
    $token = null;
    if ($invite !== '') {
        $token = token_find('share', $invite) ?? token_find('signup', $invite);
    }
    if (!$setupOk && users_count() === 0) {
        account_fail($state, [], ['This server is waiting for its first account. That needs the setup link.', 'Dieser Server wartet auf sein erstes Konto. Dafür braucht es den Einrichtungslink.']);
        return;
    }
    if (!$setupOk && registration_mode() !== 'open' && $token === null) {
        account_fail($state, [], ['New accounts need an invitation link at the moment.', 'Neue Konten gibt es gerade nur mit Einladungslink.']);
        return;
    }
    if (!rl_allow('register:ip:' . ip_key(), 5, 3600)) {
        account_fail($state, [], ['Too many sign-ups from here. Try again in an hour.', 'Zu viele Registrierungen von hier. Versuch es in einer Stunde wieder.']);
        return;
    }

    $errors = [];
    if (!valid_username($username)) {
        $errors['username'] = ['Please use 3 to 20 allowed characters', 'Bitte einen Benutzernamen mit 3 bis 20 erlaubten Zeichen'];
    } elseif (username_reserved($username) || user_by_username($username)) {
        $errors['username'] = ['That name is taken', 'Dieser Name ist schon vergeben'];
    }
    if (!valid_email($email)) {
        $errors['email'] = ['That email address looks incomplete', 'Diese E-Mail-Adresse sieht nicht vollständig aus'];
    } elseif (user_by_email($email)) {
        $errors['email'] = ['There is already an account with this address', 'Mit dieser Adresse gibt es schon ein Konto'];
    }
    $pw = password_problem($password);
    if ($pw !== null) {
        $errors['password'] = $pw;
    }
    if ($errors) {
        account_fail($state, $errors);
        return;
    }

    $lang = input_str('lang', 2) === 'de' ? 'de' : 'en';
    $inviteMatches = $token !== null && $token['email'] !== null && $token['email'] === $email;
    $verified = $setupOk || $inviteMatches || !smtp_configured();
    // Ein Registrierungslink gilt einmal. Zuerst verbrauchen, dann anlegen: so
    // koennen zwei gleichzeitige Absendungen nicht beide ein Konto bekommen.
    $signup = $token !== null && $token['kind'] === 'signup';
    if ($signup && !token_consume((int) $token['id'])) {
        account_fail($state, [], ['This invitation is no longer valid.', 'Diese Einladung gilt nicht mehr.']);
        return;
    }
    $userId = user_create($username, $email, $password, $setupOk ? 'admin' : 'user', $verified, $lang);
    $user = user_by_id($userId);

    if (!$verified) {
        $raw = token_create('confirm', ['user_id' => $userId]);
        $sent = mail_confirm($user, $raw);
        if (!$sent['ok']) {
            user_delete($userId);
            if ($signup) {
                token_release((int) $token['id']);
            }
            account_fail($state, [], ['The confirmation email could not be sent. Please try again later.', 'Die Bestätigung konnte nicht verschickt werden. Bitte versuch es später noch einmal.']);
            return;
        }
        redirect('/account?mode=register&done=1');
    }

    if (!smtp_configured() && !$setupOk && !$inviteMatches) {
        event_add('MAIL', 'Account ' . $username . ' confirmed without email, SMTP is not set up', 'Konto ' . $username . ' ohne Mail bestätigt, SMTP ist nicht eingerichtet');
    }
    session_begin($userId);
    if ($token !== null && $token['kind'] === 'share') {
        $target = invite_accept($invite, $user);
        if ($target !== null) {
            redirect($target);
        }
    }
    redirect('/account?mode=verified');
}

function action_reset(array $params = []): void
{
    csrf_check();
    $email = strtolower(input_str('email', 190));
    $state = [
        'mode' => 'reset', 'values' => ['username' => '', 'email' => $email], 'errors' => [], 'status' => null, 'done' => false,
        'next' => '', 'invite' => '', 'setup' => '', 'token' => '',
    ];
    if (!valid_email($email)) {
        account_fail($state, ['email' => ['That email address looks incomplete', 'Diese E-Mail-Adresse sieht nicht vollständig aus']]);
        return;
    }
    $allowed = rl_allow('reset:ip:' . ip_key(), 5, 3600) && rl_allow('reset:mail:' . substr(hash('sha256', $email), 0, 32), 3, 3600);
    if ($allowed) {
        $user = user_by_email($email);
        if ($user && $user['email_verified_at'] !== null) {
            $raw = token_create('reset', ['user_id' => (int) $user['id']]);
            mail_reset($user, $raw);
        }
    }
    redirect('/account?mode=reset&done=1');
}

function page_reset(array $params = []): void
{
    $raw = (string) ($_GET['t'] ?? '');
    $token = token_find('reset', $raw);
    $state = [
        'mode' => 'newpass', 'values' => ['username' => '', 'email' => ''], 'errors' => [], 'status' => null, 'done' => false,
        'next' => '', 'invite' => '', 'setup' => '', 'token' => $token ? $raw : '',
    ];
    if (!$token) {
        $state['mode'] = 'reset';
        $state['status'] = ['This link is no longer valid. Request a new one below.', 'Dieser Link gilt nicht mehr. Fordere unten einen neuen an.'];
    }
    account_render($state, true);
}

function action_new_password(array $params = []): void
{
    csrf_check();
    $raw = input_str('token', 48);
    $password = (string) input('password', '');
    $password2 = (string) input('password2', '');
    $state = [
        'mode' => 'newpass', 'values' => ['username' => '', 'email' => ''], 'errors' => [], 'status' => null, 'done' => false,
        'next' => '', 'invite' => '', 'setup' => '', 'token' => $raw,
    ];
    header('Referrer-Policy: no-referrer');
    $token = token_find('reset', $raw);
    if (!$token) {
        $state['mode'] = 'reset';
        $state['token'] = '';
        account_fail($state, [], ['This link is no longer valid. Request a new one below.', 'Dieser Link gilt nicht mehr. Fordere unten einen neuen an.']);
        return;
    }
    $errors = [];
    $pw = password_problem($password);
    if ($pw !== null) {
        $errors['password'] = $pw;
    } elseif ($password !== $password2) {
        $errors['password2'] = ['The two passwords do not match', 'Die beiden Passwörter sind nicht gleich'];
    }
    if ($errors) {
        account_fail($state, $errors);
        return;
    }
    if (!token_consume((int) $token['id'])) {
        $state['mode'] = 'reset';
        account_fail($state, [], ['This link is no longer valid. Request a new one below.', 'Dieser Link gilt nicht mehr. Fordere unten einen neuen an.']);
        return;
    }
    user_set_password((int) $token['user_id'], $password);
    sessions_revoke((int) $token['user_id']);
    // Wer sein Passwort neu gesetzt hat, soll sich gleich anmelden koennen, auch nach Fehlversuchen.
    $owner = user_by_id((int) $token['user_id']);
    if ($owner) {
        rl_reset('login:acct:' . substr(hash('sha256', (string) $owner['email']), 0, 32));
    }
    redirect('/account?mode=newpass&done=1');
}

function page_confirm(array $params = []): void
{
    $raw = (string) ($_GET['t'] ?? '');
    $token = token_find('confirm', $raw);
    if (!$token || !token_consume((int) $token['id'])) {
        $state = [
            'mode' => 'login', 'values' => ['username' => '', 'email' => ''], 'errors' => [], 'done' => false,
            'status' => ['This confirmation link is no longer valid. Sign in and we send a new one.', 'Dieser Bestätigungslink gilt nicht mehr. Melde dich an, dann kommt ein neuer.'],
            'next' => '', 'invite' => '', 'setup' => '', 'token' => '',
        ];
        account_render($state, true);
        return;
    }
    db_update('users', ['email_verified_at' => time(), 'updated_at' => time()], 'id = ?', [(int) $token['user_id']]);
    session_begin((int) $token['user_id']);
    header('Referrer-Policy: no-referrer');
    redirect('/account?mode=verified');
}

/** Einladung zu einem Geraet oder zur Registrierung. */
function page_invite(array $params = []): void
{
    $raw = (string) ($_GET['t'] ?? '');
    $share = token_find('share', $raw);
    $signup = $share ? null : token_find('signup', $raw);
    $user = session_user();
    $base = ['values' => ['username' => '', 'email' => ''], 'errors' => [], 'done' => false, 'next' => '', 'setup' => '', 'token' => '', 'status' => null];

    if (!$share && !$signup) {
        account_render(['mode' => 'login', 'invite' => ''] + array_replace($base, ['status' => ['This invitation is no longer valid.', 'Diese Einladung gilt nicht mehr.']]), true);
        return;
    }
    if ($share) {
        if ($user && $user['email_verified_at'] !== null) {
            $target = invite_accept($raw, $user);
            header('Referrer-Policy: no-referrer');
            redirect($target ?? '/device');
        }
        $existing = $share['email'] ? user_by_email((string) $share['email']) : null;
        $state = ['mode' => $existing ? 'login' : 'register', 'invite' => $raw] + $base;
        $state['values']['email'] = (string) ($share['email'] ?? '');
        $state['status'] = $existing
            ? ['Sign in to accept the invitation.', 'Melde dich an, um die Einladung anzunehmen.', 'info']
            : ['Create an account to accept the invitation.', 'Leg ein Konto an, um die Einladung anzunehmen.', 'info'];
        account_render($state, true);
        return;
    }
    $state = ['mode' => 'register', 'invite' => $raw] + $base;
    $state['values']['email'] = (string) ($signup['email'] ?? '');
    account_render($state, true);
}

/** Freigabe annehmen. Liefert die Geraeteseite oder null. */
function invite_accept(string $raw, array $user): ?string
{
    $t = token_find('share', $raw);
    if (!$t || $t['device_id'] === null) {
        return null;
    }
    $device = q1('SELECT * FROM devices WHERE id = ?', [(int) $t['device_id']]);
    if (!$device) {
        return null;
    }
    if ((int) $device['owner_id'] !== (int) $user['id'] && token_consume((int) $t['id'])) {
        $exists = q1('SELECT 1 FROM shares WHERE device_id = ? AND user_id = ?', [(int) $device['id'], (int) $user['id']]);
        if (!$exists) {
            db_insert('shares', [
                'device_id' => (int) $device['id'],
                'user_id' => (int) $user['id'],
                'rights' => $t['rights'] === 'edit' ? 'edit' : 'view',
                'created_at' => time(),
            ]);
        }
    }
    return '/device/' . (int) $device['id'];
}

function action_logout(array $params = []): void
{
    csrf_check();
    session_end();
    redirect('/');
}
