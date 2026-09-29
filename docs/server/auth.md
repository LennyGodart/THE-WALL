# auth/ und Sicherheit

Pfade relativ zu `web/.htapp/`.

## Sicherheitsmodell

| Thema | Umsetzung | Datei |
| --- | --- | --- |
| Sitzung | ein Cookie `__Host-tw_session`, 64 Hex-Zeichen, HttpOnly, Secure, SameSite=Lax, 30 Tage; in der Datenbank nur SHA-256 | `auth/session.php` |
| CSRF | jede verändernde Anfrage: `Sec-Fetch-Site` muss `same-origin` oder `none` sein, `Origin` muss der eigene Host sein. `Origin: null` gilt als fremd, außer der Browser meldet selbst `Sec-Fetch-Site: same-origin`: so schickt jede Seite mit `no-referrer` ein normales Formular an sich selbst. Fehlt `Sec-Fetch-Site` (Safari vor 16.4), bleibt `null` fremd (Befund S3). Mit Sitzung zusätzlich `_csrf` (Formular) oder `X-CSRF-Token` (fetch). Geprüft von `tools/auth-fixtures.php` | `auth/csrf.php`, `core/http.php` |
| Passwörter | bcrypt (Kosten 11) über SHA-256 des Passworts, damit bcrypt nie bei 72 Byte abschneidet; bei unbekannter Adresse trotzdem ein bcrypt, gleiche Laufzeit | `auth/users.php` |
| Anmeldung | 5 Fehlversuche je Konto und 20 je Adresse in 15 Minuten, danach "Zu viele Versuche"; neues Passwort hebt die Sperre auf | `pages/account.php` |
| Registrierung | Vorgabe nur mit Einladung; erstes Konto nur mit Einrichtungs-Token und wird Admin; 5 je Adresse pro Stunde | `pages/account.php` |
| Passwort-Link | 5 je Adresse und 3 je E-Mail pro Stunde, Antwort verrät nicht, ob es das Konto gibt; Link eine Stunde, einmal; danach alle Sitzungen beendet | `pages/account.php` |
| Links mit Token | `/confirm`, `/reset`, `/invite` laufen wie jede Seite mit `Referrer-Policy: same-origin`, der Token geht also nie an eine fremde Adresse wie fonts.bunny.net; die Seite nimmt ihn gleich nach dem Laden aus der Adresszeile. Bis zum 22. September 2026 stand hier `no-referrer`: damit schickte der Browser beim Absenden `Origin: null`, und Registrieren, neues Passwort und Anmelden aus einem Mail-Link endeten mit 403 (CHANGELOG 54) | `pages/account.php`, `assets/js/pages/account.js` |
| Rollen | Konto: `user` oder `admin`. Gerät: `owner`, `edit`, `view`. Gäste mit `view` ändern nur Notizen, geprüft in `device_sanitize()` | `device/devices.php`, `api/web.php` |
| Admin-Bereich | Nicht-Admins bekommen 404, nicht 403 | `auth/session.php` |
| Geräteschnittstelle | Bearer-Schlüssel, 120 Anfragen pro Minute je Adresse, ein Abruf pro Sekunde je Gerät | `api/device.php` |
| Kopfzeilen | CSP `default-src 'self'; script-src 'self'`, Styles inline erlaubt (Entwürfe), Fonts nur Bunny, `frame-ancestors 'none'` (Karte: `'self'`), `X-Content-Type-Options`, `Permissions-Policy`, COOP, HSTS | `core/http.php` |
| Cache | jede dynamische Antwort `private, no-store, max-age=0, no-transform`: sonst gäbe Cloudflare eine Geräteseite an Fremde weiter, und ohne `no-transform` fügt Cloudflare Web Analytics und JavaScript Detections in jede Seite ein | `core/http.php` |
| SMTP-Schlüssel | mit libsodium verschlüsselt in `settings`, Schlüssel in `.htdata/secret.php`; geht nie zurück an den Browser | `core/crypto.php`, `api/admin.php` |
| Airline-Logos | nur mit Sitzung oder gültigem Geräteschlüssel, öffentliche Seiten zeigen den Farbblock | `services/logos.php` |
| Fremde Werte | kein `innerHTML` für Ortsnamen, Suchbegriffe, Notizen; Views escapen mit `h()`; `postMessage` nur mit Herkunftsprüfung an `location.origin` | Views, `assets/js/` |
| Dateien | `.htapp` und `.htdata` von nginx gesperrt (403), zusätzlich `.htaccess` für Apache | `web/.htaccess` |

Bewusst so, laut `CLAUDE.md` entschieden: ein API-Schlüssel pro Konto, dauerhaft sichtbar; Passwort ab 6 Zeichen. Der Schlüssel hat 64 Bit (`tw_live_` und 16 Hex-Zeichen), passend zum Eingabefeld der Einrichtungsseite; Durchprobieren bremst das Rate-Limit.

## auth/users.php (16)

Konten. Benutzername und E-Mail klein gespeichert.

- Passwort: `password_make(string $plain): string`, `password_check(string $plain, string $hash): bool`, `password_dummy_check(string $plain): void`
- Suchen: `user_by_id(int $id): ?array`, `user_by_email(string $email): ?array`, `user_by_username(string $username): ?array`, `user_by_api_key(string $key): ?array`
- Gelöschte Schlüssel: `api_key_revoke(string $key): void` merkt den SHA-256 eines Schlüssels 30 Tage (`API_KEY_REVOKED_DAYS`) in der Einstellung `revoked_keys`, `api_key_revoked(string $key): bool`. So bekommt ein Gerät eines gelöschten Kontos 410 und vergisst den Schlüssel, statt ewig mit 401 zu fragen
- Schlüssel: `api_key_generate(): string`, `user_rotate_key(int $id): string`
- Ändern: `user_create(string $username, string $email, string $password, string $role = 'user', bool $verified = false, string $lang = 'en'): int`, `user_set_password(int $id, string $password): void`, `user_delete(int $id): void` (samt Geräten, Freigaben, Sitzungen, Links und Spotify-Verbindung, in einer Transaktion; der Schlüssel geht vorher an `api_key_revoke()`)
- `users_count(): int`, `is_admin(?array $user): bool`

Extern genutzt von: `api/device.php`, `api/web.php`, `auth/session.php`, `core/log.php`, `pages/account.php`, `pages/admin.php`, `views/device.php`, `views/settings.php`

## auth/session.php (7)

- `session_row(): ?array` Zeile zur Kennung aus dem Cookie, löscht ein ungültiges Cookie, frischt `last_seen_at` höchstens alle fünf Minuten auf
- `session_user(): ?array`
- `session_begin(int $userId): void` neue Kennung und neuer CSRF-Token
- `session_end(): void`
- `sessions_revoke(int $userId, ?string $keepSessionId = null): void` nach neuem Passwort, optional ohne die eigene
- `require_user(): array` ohne bestätigtes Konto: JSON 401 oder Weiterleitung auf `/account?next=...`
- `require_admin(): array` sonst 404

Extern genutzt von: `api/admin.php`, `api/web.php`, `auth/csrf.php`, `pages/account.php`, `pages/admin.php`, `pages/device.php`, `pages/errors.php`, `pages/map.php`, `pages/settings.php`

## auth/csrf.php (3)

Kein eigenes Cookie: der Token hängt an der Sitzung. Ohne Sitzung (Anmelden, Registrieren, Passwort vergessen) zählt nur die Herkunft.

- `csrf_token(): string` leer ohne Sitzung
- `csrf_field(): string` verstecktes Feld `_csrf`
- `csrf_check(): void` 403 "Diese Anfrage kam nicht von dieser Seite." oder "Die Seite war zu lange offen."

Extern genutzt von: `api/admin.php`, `api/web.php`, `core/view.php`, `pages/account.php`, `views/account.php`, `views/settings.php`

## auth/tokens.php (4)

Einmal-Links, gespeichert als SHA-256.

| Art | gültig | Zweck |
| --- | --- | --- |
| `confirm` | 24 Stunden | Adresse bestätigen |
| `reset` | 1 Stunde | neues Passwort |
| `share` | 7 Tage | Einladung zu einem Gerät, optional an eine Adresse gebunden |
| `signup` | 7 Tage | Einladung zur Registrierung ohne Gerät, vom Admin |
| `spotify` | 10 Minuten | Rückweg von der Anmeldung bei Spotify (`state`), an Konto und Gerät gebunden |

- `token_create(string $kind, array $data = []): string` gibt die Kennung für den Link zurück
- `token_find(string $kind, string $raw): ?array` nur unbenutzt und nicht abgelaufen
- `token_consume(int $id): bool` `false`, wenn ein anderer Aufruf schneller war; ein Registrierungslink wird vor dem Anlegen des Kontos verbraucht
- `token_release(int $id): void` gibt einen verbrauchten Link wieder frei, wenn der Vorgang danach scheitert (Bestätigungsmail ging nicht raus)

Extern genutzt von: `api/admin.php`, `api/web.php`, `pages/account.php`
