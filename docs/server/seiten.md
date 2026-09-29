# routes.php, pages/, views/, api/

Pfade relativ zu `web/.htapp/`.

## Adressen

| Methode | Pfad | Handler | Zugang |
| --- | --- | --- | --- |
| GET | `/` | `pages/welcome:page_welcome` | offen |
| GET | `/account` | `pages/account:page_account` | offen, `?mode=login\|register\|reset\|newpass\|verified`, `?setup=`, `?next=` |
| POST | `/account/login` | `pages/account:action_login` | Herkunft |
| POST | `/account/register` | `pages/account:action_register` | Herkunft, Einladung oder offene Registrierung |
| POST | `/account/reset` | `pages/account:action_reset` | Herkunft |
| POST | `/account/new-password` | `pages/account:action_new_password` | Herkunft, Token |
| POST | `/account/logout` | `pages/account:action_logout` | Sitzung, CSRF |
| GET | `/confirm?t=` | `pages/account:page_confirm` | Token, verschwindet aus der Adresszeile |
| GET | `/reset?t=` | `pages/account:page_reset` | Token, verschwindet aus der Adresszeile |
| GET | `/invite?t=` | `pages/account:page_invite` | Token, verschwindet aus der Adresszeile |
| GET | `/device`, `/device/{id}` | `pages/device:page_device` | Sitzung; ohne id weiter zum ersten Gerät, ohne Gerät die Seite "Noch kein Gerät" |
| GET | `/settings/export` | `pages/settings:download_export` | Sitzung |
| GET | `/settings`, `/settings/{id}` | `pages/settings:page_settings` | Sitzung; ohne id weiter zum ersten eigenen Gerät |
| GET | `/admin` | `pages/admin:page_admin` | Admin, sonst 404 |
| GET | `/legal` | `pages/legal:page_legal` | offen |
| GET | `/map` | `pages/map:page_map` | Sitzung, nur im iframe der Geräteseite |
| GET | `/map/stops` | `pages/map:page_map_stops` | Sitzung, nur im iframe der Geräteseite (Haltestellenkarte) |
| POST | `/spotify/connect` | `pages/spotify:action_spotify_connect` | Sitzung, CSRF, 10 in 10 min; leitet mit einem Einmal-Wert (`state`, 10 Minuten) zu Spotify weiter |
| GET | `/spotify/callback?code=&state=` | `pages/spotify:page_spotify_callback` | Sitzung, der Einmal-Wert muss zu diesem Konto gehören; zurück zur Geräteseite mit `?spotify=connected` und so weiter |
| GET | `/animations` | `pages/library:page_animations` | offen |
| GET | `/controls` | `pages/library:page_controls` | offen |
| POST | `/api/preview/{id}` | `api/web:api_preview` | Sitzung, CSRF, jede Rolle, 120/min; Leserecht: nur Notizen und Modus, 20/min |
| POST | `/api/device/{id}/apply` | `api/web:api_apply` | Sitzung, CSRF, jede Rolle (view: nur Notizen), 30/min |
| POST | `/api/device/{id}/note/hide` | `api/web:api_note_hide` | Sitzung, CSRF, jede Rolle, 30/min zusammen mit apply |
| POST | `/api/device/{id}/timer` | `api/web:api_timer_start` | Sitzung, CSRF, Besitz oder Bedienrecht, 30/min: `{seconds, label}` |
| POST | `/api/device/{id}/timer/cancel` | `api/web:api_timer_cancel` | Sitzung, CSRF, Besitz oder Bedienrecht: `{id}` oder `{}` für alle |
| POST | `/api/device/{id}/ring/stop` | `api/web:api_ring_stop` | Sitzung, CSRF, Besitz oder Bedienrecht |
| POST | `/api/device/{id}/alarm` | `api/web:api_alarm_save` | Sitzung, CSRF, Besitz oder Bedienrecht, 60/min: `{on, time, days}` |
| POST | `/api/device/{id}/ha/unlink` | `api/web:api_ha_unlink` | Besitzer: `{link}` |
| POST | `/api/device/{id}/settings` | `api/web:api_device_settings` | Besitzer: Name, Zeitzone |
| POST | `/api/device/{id}/update` | `api/web:api_device_update` | Besitzer: "Jetzt aktualisieren" |
| POST | `/api/device/{id}/remove` | `api/web:api_device_remove` | Besitzer |
| POST | `/api/device/{id}/invite` | `api/web:api_share_invite` | Besitzer, 10/h |
| POST | `/api/device/{id}/rights` | `api/web:api_share_rights` | Besitzer |
| POST | `/api/device/{id}/unshare` | `api/web:api_share_remove` | Besitzer |
| GET | `/api/device/{id}/status` | `api/web:api_device_status` | Sitzung, jede Rolle |
| GET | `/api/devices` | `api/web:api_devices` | Sitzung; die Seite "Noch kein Gerät" fragt alle 4 Sekunden |
| GET | `/api/logo/{code}` | `api/web:api_logo` | Sitzung |
| POST | `/api/geo` | `api/web:api_geo` | Sitzung, CSRF, 30/h |
| POST | `/api/map/aircraft` | `api/web:api_map_aircraft` | Sitzung, CSRF, 90 in 5 min |
| POST | `/api/transit/stops` | `api/web:api_transit_stops` | Sitzung, CSRF, 30 in 10 min |
| POST | `/api/transit/all` | `api/web:api_transit_all` | Sitzung, CSRF, 240 in 10 min; Arbeit am Raster nur mit Besitz- oder Bearbeitungsrecht an einem Gerät |
| POST | `/api/transit/stop` | `api/web:api_transit_stop` | Sitzung, CSRF, 120 in 10 min |
| POST | `/api/account/key` | `api/web:api_rotate_key` | Sitzung, CSRF, 5/h |
| POST | `/api/account/password` | `api/web:api_change_password` | Sitzung, CSRF, 5 Fehlversuche in 15 min |
| POST | `/api/account/export-mail` | `api/web:api_export_mail` | Sitzung, CSRF, 3/h |
| POST | `/api/account/delete` | `api/web:api_delete_account` | Sitzung, CSRF, Passwort, 5 Fehlversuche in 15 min |
| POST | `/api/account/tour` | `api/web:api_tour_seen` | Sitzung, CSRF; die Einführung ins Gerät lief |
| GET | `/api/device/{id}/spotify` | `api/web:api_spotify_status` | Sitzung, jede Rolle, 60/min |
| POST | `/api/spotify/disconnect` | `api/web:api_spotify_disconnect` | Sitzung, CSRF |
| POST | `/api/admin/registration` | `api/admin:api_registration` | Admin, CSRF |
| POST | `/api/admin/signup-link` | `api/admin:api_signup_link` | Admin, CSRF |
| POST | `/api/admin/firmware` | `api/admin:api_firmware` | Admin, CSRF |
| POST | `/api/admin/firmware-upload` | `api/admin:api_firmware_upload` | Admin, CSRF, 10/h, Rumpf `application/octet-stream` |
| POST | `/api/admin/smtp` | `api/admin:api_smtp` | Admin, CSRF |
| POST | `/api/admin/smtp-test` | `api/admin:api_smtp_test` | Admin, CSRF, 5 in 10 min |
| POST | `/api/admin/imprint` | `api/admin:api_imprint` | Admin, CSRF |
| POST | `/api/admin/test-device` | `api/admin:api_test_device` | Admin, CSRF |
| POST | `/api/admin/spotify` | `api/admin:api_spotify` | Admin, CSRF |
| POST | `/api/admin/spotify-test` | `api/admin:api_spotify_test` | Admin, CSRF, 10 in 10 min |
| POST | `/api/admin/transit` | `api/admin:api_transit` | Admin, CSRF |
| POST | `/api/admin/transit-test` | `api/admin:api_transit_test` | Admin, CSRF, 10 in 10 min |
| POST | `/api/admin/transit-sample` | `api/admin:api_transit_sample` | Admin, CSRF, 5 in 10 min |
| POST | `/api/admin/transit-raw` | `api/admin:api_transit_raw` | Admin, CSRF, 30 in 10 min |
| GET | `/api/v1/frame` | `api/device:api_frame` | API-Schlüssel, siehe [geraet.md](geraet.md) |
| GET | `/api/v1/rev` | `api/device:api_device_rev` | API-Schlüssel, siehe [geraet.md](geraet.md) |
| GET | `/api/v1/logo/{code}` | `api/device:api_device_logo` | API-Schlüssel |
| GET | `/api/v1/firmware/{version}` | `api/device:api_firmware_download` | API-Schlüssel, `X-Wall-Id` |
| POST | `/api/v1/ring/stop` | `api/device:api_device_ring_stop` | API-Schlüssel, `X-Wall-Id`, 10/min je Gerät, ab Firmware 0.2.1 |
| POST | `/api/ha/v1/pair` | `api/ha:ha_api_pair` | offen, 20/h je Adresse, 5 in 10 min je Gerät |
| POST | `/api/ha/v1/pair/confirm` | `api/ha:ha_api_pair_confirm` | Code vom Panel, 30 in 10 min je Adresse, 5 Versuche je Kopplung |
| GET, POST | `/api/ha/v1/state`, `/panel`, `/note`, `/note/hide`, `/timer`, `/timer/cancel`, `/alarm`, `/ring/stop`, `/firmware/update`, `/unlink` | `api/ha:ha_api_*` | Schlüssel `twha_` je Home Assistant, 120/min, siehe unten |

JSON-Antworten: `{"ok": true, ...}` oder `{"ok": false, "error": {"en": "...", "de": "..."}}` mit passendem Status.

## pages/

### pages/account.php (13)

Anmelden, Registrieren, Passwort vergessen, neues Passwort, bestätigt. Formulare gehen als normaler POST, bei Fehlern kommt die Seite mit Status 422, den Eingaben und den Meldungen am Feld zurück.

- Seiten: `page_account(array $params = []): void`, `page_confirm(array $params = []): void`, `page_reset(array $params = []): void`, `page_invite(array $params = []): void`
- Formulare: `action_login(array $params = []): void`, `action_register(array $params = []): void`, `action_reset(array $params = []): void`, `action_new_password(array $params = []): void`, `action_logout(array $params = []): void`
- Helfer: `account_next(string $next): string` (nur `/device`, `/settings`, `/admin`), `account_render(array $state, string $referrer = 'same-origin'): void`, `account_fail(array $state, array $errors, ?array $status = null): void`, `invite_accept(string $raw, array $user): ?string` (Freigabe anlegen, Ziel-Adresse)

Registrierung: das erste Konto nur mit `?setup=<setup_token>` aus `.htdata/secret.php`, es wird Admin. Danach mit Einladung (share oder signup) oder bei offener Registrierung. Mit SMTP geht eine Bestätigung raus; ohne SMTP, bei Einladung an genau diese Adresse oder mit Einrichtungs-Token ist das Konto sofort bestätigt. Scheitert der Versand, wird das Konto wieder gelöscht und ein Registrierungslink freigegeben.

### pages/device.php (2)

- `page_device(array $params = []): void` Gerät des Kontos oder 404; Seitendaten für `device.js`: id, Rolle, Einstellungen, Modi, `noteRev`, `noteLeft` (Sekunden, die eine neue Notiz noch vorn steht), `ring` (Timer, Wecker, Panel aus und die Serverzeit aus `ring_status_public()`), dazu `tour` für `tour.js` (`auto` für den Besitzer, solange `tour_seen_at` leer ist, seit dem 24. September 2026 auch bei einem Testgerät; `seen`, Geräte- und Benutzername, `reduce`, `test` für den Text der ersten Station). Ohne Gerät `waiting` für `nodevice.js`
- `device_status_pair(array $d): array` Zeile im Geräteumschalter, etwa "Online · Flugradar"

### pages/settings.php (2)

- `page_settings(array $params = []): void` Gerätekarten nur für den Besitzer, Konto und Schlüssel für jeden; für den Besitzer `tour` mit der Geräte-id, damit "Zur Geräteseite" am Ende der Einführung hinüberführt, und `haLinks`, die gekoppelten Home-Assistant-Instanzen
- `download_export(array $params = []): void` JSON-Datei `thewall-<name>-<datum>.json`

### pages/admin.php (2)

- `admin_stops_in_use(array $devices): int` verschiedene Haltestellen aller Geräte, die den Nahverkehr als Modus oder in der Rotation zeigen; ab vier steht die Zeile in Bernstein, weil jede eine Abfrage pro Minute kostet
- `page_admin(array $params = []): void` Abfragebudgets je Dienst, alle Geräte (Testgeräte markiert, bei Zählern und Firmware nicht mitgezählt), Konten mit Knopf für ein Testgerät, Firmware, Registrierung, SMTP, Schlüssel für mobiliteit.lu mit dem Stand der Haltestellenkarte (`stops_status()`), Stand der Airline-Logos, Impressum, letzte 20 Ereignisse

### pages/welcome.php (5)

- `page_welcome(array $params = []): void` Geräte online, Flugzeuge in der Luft, Beispielseiten fürs Demo-Panel als `demo` in `#tw-data`
- `welcome_aircraft_count(): ?int` um Luxemburg-Stadt, 40 NM, eine Minute gespeichert
- `welcome_demo(): array` die Beispielseiten je Sprache (`en`, `de`)
- `welcome_demo_pages(string $lang): array` acht Seiten `{key, ops}`, gerechnet mit `flight_ops()`, `transit_ops()`, `clock_ops()`, `weather_ops()` und dem Modus Notizen wie für ein Gerät: der Luxair-Flug aus `tools/flight-fixtures.php` in vier Ansichten (Ort Metz fest über `$ctx['geo']`), echte Fahrten der Datenprobe vom 14. September 2026 am Hauptbahnhof mit Minuten ab jetzt, die Uhr, das Wetter von Luxemburg-Stadt aus dem Zwischenspeicher (ohne Antwort ein fester Tag, über `$ctx['wx']`), eine Notiz. Ohne Nachtabsenkung, damit die Farben nicht von der Uhrzeit abhängen
- `welcome_logo_block(array $ops): array` ersetzt `logo` durch einen Farbblock mit Kürzel, die öffentliche Seite lädt keine Logos

### pages/spotify.php (2)

Spotify verbinden. `action_spotify_connect(array $params = []): void` legt einen Einmal-Wert der Art `spotify` an (Konto und Gerät, 10 Minuten) und leitet zur Anmeldung bei Spotify weiter, nur dorthin. Weil Browser `form-action` auch für das Ziel einer Weiterleitung prüfen, steht `https://accounts.spotify.com` in der Content-Security-Policy. `page_spotify_callback(array $params = []): void` prüft den Einmal-Wert gegen das angemeldete Konto, tauscht den Code über `spotify_connect_finish()` und geht zur Geräteseite zurück, `?spotify=` mit `connected`, `denied`, `failed`, `exchange`, `not_allowed`, `full`, `expired`, `setup`, `slow` oder `network`. Ein HEAD tut nichts.

### pages/legal.php, pages/map.php, pages/library.php, pages/errors.php

- `page_legal(array $params = []): void` Impressum aus dem Admin-Bereich, sonst die Platzhalter aus dem Entwurf
- `page_map(array $params = []): void` Leaflet-Karte, eigene CSP-Variante `map`
- `page_map_stops(array $params = []): void` Haltestellenkarte, ebenfalls Variante `map`, Skript `js/pages/stops.js`
- `page_animations(array $params = []): void`, `page_controls(array $params = []): void`
- `page_error(int $status, string $en = '', ?string $de = null): void` Gestalt von `NotFound.dc.html` für jeden Fehlerstatus

Extern genutzt von: `routes.php`; `page_error` von `core/router.php`; `device_status_pair` von `views/device.php`

## api/web.php (30)

JSON für den Browser. Jede Anfrage braucht eine Sitzung, jede verändernde CSRF.

- Helfer: `api_device_or_fail(array $params, array $user, array $roles = ['owner', 'edit', 'view']): array` (404, wenn das Gerät nicht zum Konto gehört; 403 bei fehlender Rolle), `api_limit(string $key, int $max, int $window): void` (429)
- Gerät: `api_preview(array $params = []): void` (Entwurf durch `device_sanitize()` und `frame_build(..., true)`; mit Leserecht nur Notizen und die Wahl des Modus, sonst der gespeicherte Stand, und höchstens 20 in der Minute: ein Gast soll über die Vorschau keine fremden Standorte und Haltestellen abfragen lassen), `api_apply(array $params = []): void` (auf dem Stand von gerade eben über `device_settings_update()`, Timer und Wecker bleiben unberührt; Notizregel aus `device_note_after_apply()`, Antwort mit `noteLeft`), `api_note_hide(array $params = []): void` ("Notiz jetzt ausblenden", jede Rolle), `api_timer_start(array $params = []): void`, `api_timer_cancel(array $params = []): void`, `api_ring_stop(array $params = []): void`, `api_alarm_save(array $params = []): void` (Timer und Wecker gelten sofort, ohne "Übernehmen", mit Besitz oder Bedienrecht; Antwort jeweils mit `timers`, `alarm`, `off`, `now`), `api_ha_unlink(array $params = []): void` (eine gekoppelte Home-Assistant-Instanz trennen, nur der Besitzer), `api_device_settings(array $params = []): void`, `api_device_update(array $params = []): void`, `api_device_remove(array $params = []): void` (merkt sich das Gerät, sein nächster Abruf bekommt 410), `api_device_status(array $params = []): void` (`online`, die Einträge aus `device_telemetry()`, `noteLeft`, Timer, Wecker und `off`, alle 30 Sekunden und kurz nach jedem Timerende von `device.js` abgefragt)
- Freigaben: `api_share_invite(array $params = []): void` (Link und Mail, Link auch zum Kopieren, wenn keine Mail rausging), `api_share_rights(array $params = []): void`, `api_share_remove(array $params = []): void`
- Sonstiges: `api_logo(array $params = []): void` (Logo als 3 264 Byte RGB888 für die Vorschau), `api_geo(array $params = []): void`, `api_map_aircraft(array $params = []): void` (`{lat, lon, nm}`: Flugzeuge in der Luft für die Karte, höchstens 120, nach Entfernung; aus demselben Zwischenspeicher wie das Panel, Strecke nur aus der Tabelle `callsigns`; 422 ohne gültige Position, 424 wenn adsb.lol nicht antwortet), `api_transit_stops(array $params = []): void` (`{lat, lon}`: Haltestellen im Umkreis von einem Kilometer für den Nahverkehrsmodus, höchstens 15, mit Vorschlag für den Namen auf dem Panel und Linienkürzeln; auf gut 100 Meter gerundet und einen Tag gespeichert; Fehler von mobiliteit.lu als 409, 424 oder 429), `api_transit_all(array $params = []): void` (alle Haltestellen für die Karte aus `stops_for_map()`, mit Fortschritt, solange der Server das Raster abfragt), `api_transit_stop(array $params = []): void` (`{id}`: eine Haltestelle aus dem Index mit Kurzname und Linien, 404, wenn sie fehlt)
- Spotify: `api_spotify_status(array $params = []): void` (ob das Konto des Besitzers verbunden ist, Name, Fehler, was läuft mit Link zu Spotify; Gäste sehen den Stand, verbinden kann nur der Besitzer), `api_spotify_disconnect(array $params = []): void` (die eigene Verbindung trennen, Token und Zwischenspeicher weg)
- Konto: `api_devices(array $params = []): void` (id und Rolle jedes Geräts, für die Seite "Noch kein Gerät"), `api_tour_seen(array $params = []): void` (setzt `tour_seen_at`, wenn es noch leer ist), `api_rotate_key(array $params = []): void`, `api_change_password(array $params = []): void` (beendet die anderen Sitzungen), `api_export_mail(array $params = []): void` (424, wenn die Mail nicht rausgeht), `api_delete_account(array $params = []): void` (409 für das einzige Admin-Konto)

## api/admin.php (14)

Nur Rolle admin, jede Änderung mit CSRF. Der SMTP-Schlüssel, der Schlüssel für mobiliteit.lu und das Spotify-Secret gehen nie zurück an den Browser, ein leeres Feld behält den gespeicherten.

- `api_spotify(array $params = []): void` `{client_id, secret}`: je 32 Zeichen 0 bis 9 und a bis f, das Secret verschlüsselt; setzt den Teststatus zurück
- `api_spotify_test(array $params = []): void` fragt ein Token nur für die App (Client Credentials); 409 ohne Zugang, 422, wenn Spotify ihn ablehnt

- `api_registration(array $params = []): void` `invite` oder `open`
- `api_signup_link(array $params = []): void` Registrierungslink, optional an eine Adresse gebunden, sieben Tage, einmal
- `api_firmware(array $params = []): void` Rollout in Fünferschritten, Automatisch
- `api_firmware_upload(array $params = []): void` neue Firmware als Rohdaten im Rumpf, geprüft und abgelegt von `firmware_store()`; 422 mit Grund, wenn die Datei nicht passt
- `api_smtp(array $params = []): void` Host, Port, Sicherheit, Login, Schlüssel (verschlüsselt), Absender; setzt den Teststatus zurück
- `api_smtp_test(array $params = []): void` Test-Mail an eine Adresse, Ergebnis mit Serverantwort; ein Fehlschlag kommt als 424
- `api_imprint(array $params = []): void` Name, Adresse, Kontakt-E-Mail, Repo-Adresse
- `api_test_device(array $params = []): void` `{user, on}`: Testgerät für ein Konto anlegen oder entfernen, antwortet mit der neuen Geräteanzahl; 404 für ein unbekanntes Konto, 409 bei 20 Geräten
- `api_transit(array $params = []): void` `{key}`: Schlüssel für mobiliteit.lu prüfen (8 bis 128 Zeichen, Buchstaben, Ziffern, Bindestriche) und verschlüsselt speichern, setzt den Teststatus zurück
- `api_transit_test(array $params = []): void` Haltestellen am Bahnhof Luxemburg und die nächsten Abfahrten der nächsten; antwortet mit Haltestellen, Abfahrten, Anzahl je Verkehrsmittel und Kontingent. 409 ohne Schlüssel, 403 wenn mobiliteit.lu ihn ablehnt
- `api_transit_sample(array $params = []): void` Datenprobe aus `transit_sample()` für Entwürfe, beim ersten Mal rund 25 Abfragen; die letzte Probe liegt zusätzlich in `.htdata/transit/sample-latest.json`
- `api_transit_raw(array $params = []): void` `{endpoint, params}`: Rohabfrage über `transit_raw()` für die Fehlersuche, Antwort unverändert ohne Schlüssel. Ohne Oberfläche, Aufruf per `fetch` aus dem angemeldeten Admin-Bereich

## api/device.php (6)

Siehe [geraet.md](geraet.md).

- `api_device_auth(string $bucket = 'devauth', int $perMinute = 120): array` Begrenzung je Adresse, beim Überschreiten 429 mit `Retry-After` (bis 23. September 2026 im Topf `devauth` 401, das hielt die Firmware für einen falschen Schlüssel). Falsche Schlüssel zählt `devauth-fail:<adresse>:<schlüssel-hash>`, 50 in der Stunde, danach 401 ohne Datenbank; andere Geräte hinter derselben Adresse zählen getrennt. Ein Schlüssel eines gelöschten Kontos bekommt 30 Tage lang 410 (`api_key_revoked()`). `api_device_header(string $name): string`
- `api_frame(array $params = []): void` (dazu `timers`, `alarm`, `ring` und im Zwischenspeicher `showing:<Gerät>`, was das Panel gerade zeigt, für Home Assistant), `api_device_rev(array $params = []): void`, `api_device_rev_value(array $device, ?array $settings = null, int $ownerId = 0): string` (ruft `device_rev_value()`), `api_device_logo(array $params = []): void`, `api_firmware_download(array $params = []): void`, `api_device_ring_stop(array $params = []): void` (Rad am Gerät, ab 0.2.1)

## api/ha.php (16)

Schnittstelle für Home Assistant, die Integration liegt in `homeassistant/` (Domain `thewall`). Home Assistant spricht nur mit dem Server, nie mit dem Gerät. Gekoppelt wird je Gerät: Home Assistant fragt mit der Geräte-ID einen Code an, der fünf Minuten groß auf dem Panel steht, wer ihn eintippt, steht davor. Danach gilt ein eigener Schlüssel `twha_` je Home Assistant, in `ha_links` nur als SHA-256. Nicht der API-Schlüssel des Kontos: der liegt im Flash des Geräts, und wer das Gerät in der Hand hat, könnte damit sonst auch Einstellungen ändern. Jede Kopplung gilt für ein Gerät, das Home Assistant beim Beschenkten sieht nur dessen Wand. Höchstens fünf je Gerät, die ältesten fallen weg. Konstante `HA_OFFLINE_SECONDS` 120: ein Gerät, das so lange nicht gefragt hat, kann keinen Code zeigen (409); Testgeräte zeigen ihn stattdessen in der Statuszeile.

Fehler wie überall, dazu `code`: `bad_request` (400), `invalid_token` (401, auch wenn das Gerät gelöscht wurde, Home Assistant bietet dann neu koppeln an), `unknown_device`, `unknown_pairing` (404), `device_offline`, `no_update` (409), `pairing_expired` (410), `invalid_code` mit `attempts_left`, `invalid_value`, `too_many_timers` (422), `rate_limited` (429 mit `Retry-After`).

- Helfer: `ha_fail(int $status, string $code, string $en, string $de, array $extra = []): never`, `ha_limit(string $key, int $max, int $window): void`, `ha_auth(): array` (Schlüssel prüfen, 30 falsche je Adresse und Stunde, danach sofort 401; zuletzt benutzt höchstens einmal pro Minute schreiben), `ha_device_fresh(int $id): array`, `ha_showing(array $device, array $settings, int $now): string` (was das Panel zeigt: aus dem letzten Abruf des Geräts, sonst geschätzt; `pairing`, `ring`, `off`, `hello` oder ein Modus), `ha_state(array $device): array` (liest nur, fragt keinen Dienst draußen: der Flug kommt aus `flightnow:<Gerät>`), `ha_send_state(int $deviceId, array $extra = []): never`, `ha_update(int $deviceId, callable $fn): void` (über `device_settings_update()`, setzt `applied`, Fehler als 422)
- Kopplung: `ha_api_pair(array $params = []): void`, `ha_api_pair_confirm(array $params = []): void`
- Mit Schlüssel: `ha_api_state`, `ha_api_panel` (`on`, `bright`, `mode`, `rotation`; ein anderer Modus beendet den Vorrang einer Notiz wie auf der Webseite, und ein Modus ohne `rotation` beendet eine laufende Rotation mit zwei oder mehr Modi, damit das Panel ihn zeigt), `ha_api_note`, `ha_api_note_hide`, `ha_api_timer`, `ha_api_timer_cancel`, `ha_api_alarm`, `ha_api_ring_stop`, `ha_api_firmware_update` (wie "Jetzt aktualisieren"), `ha_api_unlink` (Home Assistant entfernt die Integration), jeweils `(array $params = []): void`

Der Stand (`state`) hat `device` (uid, Name, Modell, Firmware, neuere Firmware, online, zuletzt gesehen, WLAN, Adresse der Geräteseite), `panel` (an, Helligkeit 0 bis 255, Modus, Rotation, was es zeigt, die verfügbaren Modi auf Englisch und Deutsch), `note` (Zeilen, Sekunden vorn, 21 Zeichen), `timers`, `alarm` (an, Uhrzeit, Tage, nächstes Klingeln, klingelt), `ringing`, `flight` (Rufzeichen, Kennzeichen, Airline, Typ, Höhe in Fuß, Tempo in Knoten, Entfernung in Seemeilen, Abflug und Ziel; `null` ohne Flug oder wenn der Flugmodus weder gewählt noch in der Rotation ist), `time` und `rev`.

## views/

HTML der Seiten. Jede bekommt fertige Variablen vom Controller, Werte aus den Entwürfen stehen wörtlich als Inline-Styles, deutsche Texte in `data-de`.

| View | Entwurf | Variablen |
| --- | --- | --- |
| `views/welcome.php` | `Welcome.dc.html` | `$online`, `$count`, `$repo` |
| `views/account.php` | `Account.dc.html` | `$s` (Zustand aus `pages/account.php`) |
| `views/device.php` | `Device.dc.html`, Nahverkehr aus `Transit.dc.html`; Timer und Wecker, Panel aus und der Satz zum Klingeln unter der Vorschau seit dem 26. September 2026 ohne Entwurf, aus den festgelegten Bedienelementen | `$user`, `$devices`, `$device`, `$settings`, `$modes`, `$role` |
| `views/settings.php` | `Settings.dc.html`, dazu die Karte Home Assistant (Geräte-ID, gekoppelte Instanzen) | `$user`, `$devices`, `$device`, `$owner`, `$settings`, `$shares`, `$firmware`, `$mailReady`, `$haLinks` |
| `views/admin.php` | `Admin.dc.html` | siehe `page_admin()` |
| `views/legal.php` | `Legal.dc.html` | `$imp` |
| `views/animations.php` | `Animations.dc.html` | keine |
| `views/controls.php` | `Inputs.dc.html` | keine |
| `views/notfound.php` | `NotFound.dc.html` | `$status`, `$message`, `$messageDe`, `$user` |
| `views/map.php` | `radius-map.html`, dazu Flugzeuge, Verkehrszeile und Live-Schalter | keine |
| `views/map_stops.php` | Haltestellenkarte ("Map to follow" in `Transit.dc.html`) | keine |

## mail/

| Datei | Vorlage |
| --- | --- |
| `confirm.html`, `reset.html`, `invite.html` | die Entwürfe `email-confirm.html`, `email-reset.html`, `email-invite.html`, unverändert |
| `test.html`, `export.html` | in derselben Gestalt gebaut |
| `*.txt` | Textfassung zu jeder Mail, `{{BASE}}` und `{{IMPRINT}}` werden ersetzt |
