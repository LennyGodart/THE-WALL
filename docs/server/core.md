# core/

Werkzeuge ohne Fachwissen. Pfade relativ zu `web/.htapp/`.

## bootstrap.php

Einstieg für jeden Aufruf. Setzt `TW_ROOT` (Webroot), `TW_APP` (`.htapp`), `TW_DATA` (`.htdata`), `TW_VERSION`, schaltet `display_errors` aus und lädt `core`, `auth`, `services`, `device` in fester Reihenfolge, danach jede Datei in `modes/`.

Extern genutzt von: `index.php`

## core/config.php (5)

Feste Vorgaben (Umgebung, Adresse, Datenbank), überschrieben von `.htdata/config.php`. `TW_ENV=dev` schaltet auf SQLite. Erzeugt beim ersten Aufruf `.htdata/secret.php` mit `app_key` und `setup_token`. Enthält die Ausnahme `SetupMissing`.

- `config(?string $key = null, mixed $default = null): mixed` Wert mit Punktpfad, etwa `config('db.driver')`
- `is_dev(): bool`
- `app_url(): string` Basisadresse für Links in Mails, lokal der Host der Anfrage
- `site_host(): string` Name der Instanz aus `config('url')`, für die Fußzeilen, den SMTP-Gruß (`EHLO`) und den Rückfall im Mailer
- `secret(string $name): string` `app_key` oder `setup_token`, legt die Datei an, wenn sie fehlt
- `ensure_data_dir(): void` legt `.htdata` mit `logs/`, `firmware/`, `exports/` und `.htaccess` an

Extern genutzt von: `api/admin.php`, `api/web.php`, `core/crypto.php`, `core/db.php`, `core/http.php`, `core/ip.php`, `core/log.php`, `device/firmware.php`, `pages/account.php`, `services/mailer.php`, `services/upstream.php`

## core/db.php (12)

PDO für MariaDB oder SQLite, Verbindung beim ersten Gebrauch, Migrationen dabei. Wo sich die Dialekte unterscheiden (Upsert), entscheidet `db_driver()`.

- Verbindung: `db(): PDO`, `db_driver(): string`
- Abfragen: `q(string $sql, array $params = []): PDOStatement`, `q1(string $sql, array $params = []): ?array`, `qall(string $sql, array $params = []): array`, `qval(string $sql, array $params = []): mixed`
- Schreiben: `db_insert(string $table, array $row): int`, `db_update(string $table, array $set, string $where, array $params = []): int`, `db_upsert(string $table, array $row, array $keys, array $update): void`
- Sonstiges: `db_ident(string $name): string` (prüft Tabellen- und Spaltennamen), `db_tx(callable $fn): mixed`, `db_migrate(PDO $pdo): void`

Extern genutzt von: `api/device.php`, `api/web.php`, `auth/session.php`, `auth/tokens.php`, `auth/users.php`, `core/log.php`, `core/ratelimit.php`, `core/settings.php`, `device/devices.php`, `device/firmware.php`, `pages/account.php`, `pages/admin.php`, `pages/welcome.php`, `services/adsb.php`, `services/upstream.php`

## core/http.php (14)

Anfrage lesen, Antwort schreiben. Jede HTML- und JSON-Antwort ist `private, no-store`, weil Cloudflare auf dieser Domain auch HTML speichert. Enthält die Ausnahme `HttpError` (Status, englische und deutsche Meldung) und die Konstante `NO_STORE` (`private, no-store, max-age=0, no-transform`).

- Anfrage: `req_method(): string`, `req_path(): string`, `req_json(): array`, `input(string $key, mixed $default = null): mixed`, `input_str(string $key, int $max = 1000): string`, `wants_json(): bool`, `is_https(): bool`, `same_origin_request(): bool` (`Origin: null` aus Sandbox-iframes und `file://` gilt als fremd, außer `Sec-Fetch-Site` sagt `same-origin`, siehe [auth.md](auth.md))
- Antwort: `send_page_headers(string $variant = 'page', string $referrer = 'same-origin'): void` (CSP, Frame-Schutz, HSTS; `variant` `map` erlaubt Kacheln von OpenStreetMap und Einbettung von derselben Seite; `form-action` erlaubt dazu nur die Anmeldung bei Spotify, weil "Mit Spotify verbinden" ein Formular mit Weiterleitung ist), `send_json(mixed $data, int $status = 200): never`, `send_json_error(int $status, string $en, string $de, array $extra = []): never`, `redirect(string $to, int $status = 303): never`, `set_cookie(string $name, string $value, int $maxAge, string $sameSite = 'Lax'): void`, `header_is_set(string $name): bool` (`send_json()` behält ein vorher gesetztes Cache-Control, etwa für Logos)

Extern genutzt von: `api/admin.php`, `api/device.php`, `api/web.php`, `auth/csrf.php`, `auth/session.php`, `core/router.php`, `core/view.php`, `pages/account.php`, `pages/device.php`, `pages/settings.php`

## core/ip.php (5)

Adresse des Besuchers, nur für Rate-Limits. Hinter Cloudflare und nginx sieht PHP `127.0.0.1`. Vertraut wird dem letzten Eintrag in `X-Forwarded-For` (von nginx angehängt) und, wenn der eine Cloudflare-Adresse ist, `CF-Connecting-IP`.

- `client_ip(): string`
- `ip_is_loopback(string $ip): bool`
- `ip_is_cloudflare(string $ip): bool` (Liste der Cloudflare-Netze im Code)
- `ip_in_cidr(string $ip, string $cidr): bool`
- `ip_key(): string` Hash der Adresse als Teil von Rate-Limit-Schlüsseln, die Adresse selbst wird nie gespeichert

Extern genutzt von: `api/device.php`, `core/ratelimit.php`, `pages/account.php`

## core/log.php (4)

- `log_error(string $message, array $context = []): void` technische Fehler nach `.htdata/logs/app-<datum>.log`
- `event_add(string $source, string $en, string $de): void` kurze Meldung für "Zuletzt schiefgegangen"
- `events_recent(int $limit = 20): array`
- `housekeeping(): void` höchstens stündlich: Ereignisse und Logs nach 14 Tagen, abgelaufene Sitzungen, Tokens, Cache, Rate-Limits, Budget nach acht Tagen, unbestätigte Konten nach sieben Tagen, Exporte nach einer Stunde, leere Sperrdateien in `.htdata/locks` nach einem Tag. `app_run()` hängt sie als Shutdown-Funktion ein, damit sie auch nach `send_json()` läuft, das mit `exit` endet: vorher räumte nur eine HTML-Seite auf, der Geräteabruf nie

Extern genutzt von: `core/router.php`, `device/frame.php`, `pages/account.php`, `pages/admin.php`, `pages/welcome.php`, `services/adsb.php`, `services/geocode.php`, `services/mailer.php`, `services/upstream.php`, `services/weather.php`

## core/ratelimit.php (4)

Feste Zeitfenster in der Tabelle `rate_limits`.

- `rl_allow(string $key, int $max, int $window): bool` zählt mit, `false` ab dem Überschreiten. Eine einzige Anweisung (`INSERT ... ON DUPLICATE KEY UPDATE` bzw. `ON CONFLICT`), keine Transaktion: MariaDB ab 11.6 prüft mit `innodb_snapshot_isolation` (auf dem Server an, MariaDB 12.3), ob eine Zeile seit dem Lesen geändert wurde, und bricht dann mit 1020 ab. So endete bis zum 26. September 2026 ein Rahmen des Flugmodus mit FEHLER, wenn zwei Anfragen in derselben Sekunde den Nominatim-Zähler zählten (Gerät und Vorschau, oder zwei Geräte). Versuche über der Grenze zählen mit, das Fenster verlängern sie nicht; kommen zwei gleichzeitig an die Grenze, darf im Zweifel keiner
- `rl_blocked(string $key, int $max, int $window): bool` schaut nur nach
- `rl_reset(string $key): void`
- `rl_retry_after(string $key, int $window): int` Sekunden bis zum nächsten Fenster

Extern genutzt von: `api/admin.php`, `api/device.php`, `api/web.php`, `pages/account.php`, `services/geocode.php`

## core/router.php (4)

- `dispatch(array $routes, string $method, string $path): void` erste passende Route, `{id}` wird zu `[A-Za-z0-9_.-]+`, 405 mit `Allow` bei falscher Methode
- `app_run(): void` ganzer Ablauf eines Aufrufs, siehe [architektur.md](architektur.md)
- `app_discard(int $level): void` verwirft die Ausgabe einer abgebrochenen Seite
- `app_error(int $status, string $en = '', ?string $de = null): void` Fehlerseite oder JSON

Extern genutzt von: `index.php`

## core/settings.php (7)

Einstellungen, die der Admin im Browser ändert, als JSON in der Tabelle `settings`.

- `setting(string $key, mixed $default = null): mixed`, `settings_all(bool $reload = false): array`, `setting_set(string $key, mixed $value): void`
- `registration_mode(): string` `invite` (Vorgabe) oder `open`
- `imprint(): array` Name, Adresse (freiwillig), Kontakt-E-Mail, Repo-Adresse
- `smtp_settings(bool $withPassword = false): array` Host, Port, Sicherheit, Login, Absender; das Passwort nur entschlüsselt und nur mit `true`
- `smtp_configured(): bool`

Extern genutzt von: `api/admin.php`, `device/firmware.php`, `pages/account.php`, `pages/admin.php`, `pages/legal.php`, `pages/settings.php`, `pages/welcome.php`, `services/mailer.php`, `services/upstream.php`, `views/account.php`

## core/crypto.php (5)

- `seal_key(): string` 32-Byte-Schlüssel, abgeleitet aus `app_key`
- `seal(string $plain): string`, `unseal(string $sealed): ?string` libsodium secretbox, für den SMTP-Schlüssel
- `token_raw(): string` zufällige Kennung, `token_hash(string $raw): string` SHA-256 zum Speichern

Extern genutzt von: `api/admin.php`, `auth/session.php`, `auth/tokens.php`, `core/settings.php`

## core/validate.php (5)

Dieselben Regeln prüft der Browser vorab, der Server entscheidet.

- `valid_username(string $u): bool` 3 bis 20 Zeichen aus a bis z, Ziffern und Unterstrich
- `username_reserved(string $u): bool` admin, root, system, thewall, support
- `valid_email(string $e): bool`
- `password_problem(string $p): ?array` englische und deutsche Meldung oder `null`; mindestens 6 Zeichen, davon ein Buchstabe
- `valid_hex_colour(string $c): bool`

Extern genutzt von: `api/admin.php`, `api/web.php`, `modes/clock.php`, `pages/account.php`, `services/smtp.php`

## core/view.php (9)

Bausteine für die Views. Werte aus den Entwürfen stehen als Inline-Styles im Markup, der Rest in `assets/css/base.css`.

- `view(string $name, array $vars = []): void` bindet `views/<name>.php` mit den Variablen ein
- `asset(string $path): string` Adresse unter `/assets/` mit `?v=`, abgeleitet aus Änderungszeit und Größe (nginx liefert Assets mit `expires max`)
- `page_open(array $o): void` Kopf bis `<body>`: Titel mit `data-de`, CSRF-Meta, `lang-early.js`, Bunny Fonts, `base.css`, Favicon. Schlüssel: `title`, `title_de`, `page`, `fonts`, `variant`, `referrer`, `noindex`
- `page_close(array $scripts = [], array $data = []): void` Seitendaten als `<script type="application/json" id="tw-data">`, dann `pixelfont.js`, `ui.js` und die Seitenskripte
- `skip_link(string $href, string $en, string $de): string`
- `brand_mark(?string $href = '/'): string` Canvas für die Wortmarke
- `lang_switch(): string` EN/DE mit `aria-pressed`
- `t(string $en, string $de, string $tag = 'span', string $style = ''): string`
- `st(string $css): string`

Extern genutzt von: allen `pages/*.php` und `views/*.php`, dazu `device/devices.php`

## core/tz.php (6)

Zeitzonen als IANA-Namen. Für die Firmware leitet der Server die POSIX-Zeichenkette aus den Umstellungen der Zone ab (FEHLERLISTE 1.3).

- `tz_choices(): array` die Auswahl auf Geräteseite und Einstellungen
- `tz_valid(string $iana): bool`
- `tz_posix(string $iana, ?int $year = null): string` etwa `CET-1CEST,M3.5.0,M10.5.0/3`
- intern: `tz_posix_offset(int $seconds): string`, `tz_posix_name(string $abbr, int $offset): string`, `tz_posix_rule(int $ts, int $offsetBefore): string`

Geprüft für Luxemburg, London, Helsinki, New York, Los Angeles, Sydney, Tokio, UTC und Kolkata.

Extern genutzt von: `api/web.php`, `device/devices.php`, `device/frame.php`, `services/weather.php`, `views/device.php`, `views/settings.php`

## core/text.php (3)

Einzige Stelle, die Text fürs Panel umschreibt: ö wird o, é wird e, ß wird ss, Zeilen werden auf 21 Zeichen gekürzt. Die Vorschau im Browser zeigt die Zeichenbefehle des Servers, also genau das (FEHLERLISTE 4.4).

- `panel_text(string $s, bool $upper = true, int $max = PANEL_LINE_MAX): string`
- `panel_width(string $s, int $scale = 1): int` Breite in Spalten, 6 je Zeichen
- `panel_center_x(string $s, int $scale = 1): int`

Extern genutzt von: `api/device.php`, `device/frame.php`, `modes/clock.php`, `modes/flight.php`, `modes/notes.php`, `modes/weather.php`

## core/util.php (9)

- `h(mixed $s): string` HTML-Escape
- `de(string $german): string` Attribut ` data-de="..."`
- `random_hex(int $bytes): string`
- `clamp_int(mixed $v, int $min, int $max, int $default): int`
- `json_for_html(mixed $data): string` JSON, das in `<script>` nicht ausbrechen kann
- `atomic_write(string $path, string $content): void` in `.tmp` schreiben, dann `rename()`
- `str_cut(string $s, int $max): string`
- `ago_pair(?int $ts): array`, `uptime_pair(?int $secs): array` Zeitangaben in beiden Sprachen

Extern genutzt von: fast überall, alle Views, `api/admin.php`, `auth/*`, `core/*`, `device/devices.php`, `device/firmware.php`, `modes/flight.php`, `pages/device.php`, `pages/map.php`, `services/geocode.php`, `services/mailer.php`
