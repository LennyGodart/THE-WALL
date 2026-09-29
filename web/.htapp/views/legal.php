<?php
/** @var array $imp name, address, email, repo. Die Adresse ist freiwillig: leer, dann fehlt die Zeile. */
$mono = "font-family:'IBM Plex Mono',monospace;";
$h2 = 'margin:0 0 12px;' . $mono . 'font-size:12px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#E8EAEC';
$name = $imp['name'] !== '' ? h($imp['name']) : '[NAME]';
$address = $imp['address'] !== '' ? nl2br(h($imp['address']), false) . '<br>' : '';
$email = $imp['email'] !== '' ? '<a href="mailto:' . h($imp['email']) . '">' . h($imp['email']) . '</a>' : '[E-MAIL]';
$filled = $imp['name'] !== '' && $imp['email'] !== '';
$store = static function (string $titleEn, string $titleDe, string $textEn, string $textDe, string $basisEn, string $basisDe): string {
    return '<div style="background:#0B0D0F;padding:16px 18px">'
        . '<p style="margin:0 0 4px;font-family:\'IBM Plex Mono\',monospace;font-size:12px;color:#FFAA00"' . de($titleDe) . '>' . h($titleEn) . '</p>'
        . '<p style="margin:0 0 6px;font-size:14px;color:#C6CDD3"' . de($textDe) . '>' . h($textEn) . '</p>'
        . '<p style="margin:0;font-family:\'IBM Plex Mono\',monospace;font-size:11.5px;color:#8B949C"' . de($basisDe) . '>' . h($basisEn) . '</p></div>';
};
?>
<div style="min-height:100vh;display:flex;flex-direction:column">

  <?= skip_link('#main', 'Skip to content', 'Zum Inhalt springen') ?>

  <header style="position:sticky;top:0;z-index:30;background:rgba(8,9,10,0.9);backdrop-filter:blur(12px);border-bottom:1px solid #1B2126">
    <div style="max-width:900px;margin:0 auto;padding:14px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 20px">
      <?= brand_mark('/') ?>
      <nav style="display:flex;flex-wrap:wrap;gap:8px 18px;<?= $mono ?>font-size:12px;letter-spacing:0.08em;text-transform:uppercase" aria-label="Sections" data-de-label="Abschnitte">
        <a href="#privacy" class="h-text hit" style="color:#8B949C"<?= de('Datenschutz') ?>>Privacy</a>
        <a href="#imprint" class="h-text hit" style="color:#8B949C"<?= de('Impressum') ?>>Legal notice</a>
      </nav>
      <div style="flex:1 0 0;min-width:0"></div>
      <?= lang_switch() ?>
    </div>
  </header>

  <main id="main" style="flex:1;width:100%;max-width:900px;margin:0 auto;padding:clamp(32px,5vw,56px) 20px">

    <?php if (!$filled): ?>
    <div style="padding:16px 18px;border:1px solid #1B2126;border-radius:2px;background:#0B0D0F;margin-bottom:clamp(32px,5vw,48px)">
      <p style="margin:0 0 6px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#FFAA00"<?= de('Entwurf') ?>>Draft</p>
      <p style="margin:0;font-size:13.5px;line-height:1.6;color:#C6CDD3"<?= de('Diese Texte sind ein Entwurf und keine Rechtsberatung. Platzhalter in eckigen Klammern musst du ausfüllen, und vor der Veröffentlichung sollte ein Anwalt drüberschauen.') ?>>These texts are a draft, not legal advice. Fill in the placeholders in square brackets, and have a lawyer review it before you publish.</p>
    </div>
    <?php endif; ?>

    <section id="privacy" style="margin-bottom:clamp(48px,7vw,80px);scroll-margin-top:90px">
      <div data-pxwrap="1" style="margin:0 0 14px;max-width:620px"><canvas aria-hidden="true"></canvas></div>
      <h1 data-pxhead="1"<?= de('Datenschutz') ?>>Privacy</h1>
      <p style="margin:0 0 clamp(28px,4vw,40px);<?= $mono ?>font-size:11.5px;letter-spacing:0.06em;color:#8B949C"<?= de('Stand September 2026') ?>>Last updated September 2026</p>

      <div style="display:flex;flex-direction:column;gap:clamp(28px,4vw,40px);font-size:15px;line-height:1.7">

        <div>
          <h2 style="<?= $h2 ?>"<?= de('Kurzfassung') ?>>The short version</h2>
          <p style="margin:0 0 14px;color:#C6CDD3;text-wrap:pretty"<?= de('THE WALL speichert so wenig wie möglich. Es gibt keine Tracker, keine Analytics, keine Werbung und keine Weitergabe an Dritte zu Werbezwecken. Deshalb gibt es auch kein Cookie-Banner: es werden nur technisch notwendige Cookies gesetzt, und für die braucht es keine Einwilligung.') ?>>THE WALL stores as little as possible. There are no trackers, no analytics, no advertising and nothing passed to third parties for marketing. That is also why there is no cookie banner: only strictly necessary cookies are set, and those need no consent.</p>
          <p style="margin:0;color:#C6CDD3;text-wrap:pretty"<?= de('Was es braucht, um zu funktionieren: einen Benutzernamen, eine E-Mail-Adresse, ein Passwort als Hash, und die Einstellungen deiner Geräte.') ?>>What it needs in order to work: a username, an email address, a password as a hash, and your devices' settings.</p>
        </div>

        <div>
          <h2 style="<?= $h2 ?>"<?= de('Verantwortlicher') ?>>Controller</h2>
          <p style="margin:0;color:#C6CDD3"><?= $name ?><br><?= $address ?>Luxembourg<br><?= $email ?></p>
        </div>

        <div>
          <h2 style="<?= $h2 ?>"<?= de('Was gespeichert wird und warum') ?>>What is stored and why</h2>
          <div style="display:grid;grid-template-columns:1fr;gap:1px;background:#1B2126;border:1px solid #1B2126">
            <?= $store('Account', 'Konto', 'Username, email address, password as a bcrypt hash, time of registration, and when you saw the introduction to the device.', 'Benutzername, E-Mail-Adresse, Passwort als bcrypt-Hash, Zeitpunkt der Registrierung, und wann du die Einführung ins Gerät gesehen hast.', 'Basis: performance of a contract, Art. 6(1)(b) GDPR. Retention: until you delete the account.', 'Grundlage: Vertragserfüllung, Art. 6 Abs. 1 lit. b DSGVO. Dauer: bis du das Konto löschst.') ?>
            <?= $store('Devices', 'Geräte', 'Device name, device ID, firmware version, the location you set with its coordinates, modes and all display settings. With every request the device also reports the name of its Wi-Fi network, signal strength, uptime, chip temperature, storage use and number of restarts; the device page shows them.', 'Gerätename, Geräte-ID, Firmware-Version, der eingestellte Standort mit Koordinaten, Modi und alle Anzeigeeinstellungen. Mit jeder Abfrage meldet das Gerät außerdem den Namen seines WLANs, die Signalstärke, die Laufzeit, die Chiptemperatur, den belegten Speicher und die Zahl der Neustarts; die Geräteseite zeigt sie an.', 'Basis: performance of a contract, Art. 6(1)(b) GDPR. Retention: until you remove the device.', 'Grundlage: Vertragserfüllung, Art. 6 Abs. 1 lit. b DSGVO. Dauer: bis du das Gerät entfernst.') ?>
            <?= $store('Content', 'Inhalte', 'Your notes. Anyone you share a device with can read the notes on it.', 'Deine Notizen. Wer ein Gerät mit dir teilt, kann die Notizen darauf lesen.', 'Basis: performance of a contract, Art. 6(1)(b) GDPR. Retention: until you delete them.', 'Grundlage: Vertragserfüllung, Art. 6 Abs. 1 lit. b DSGVO. Dauer: bis du sie löschst.') ?>
            <?= $store('Spotify, only if you connect it', 'Spotify, nur wenn du es verbindest', 'Your Spotify user ID and display name, and the access tokens, encrypted. Briefly held: what is playing (5 seconds), the queue (30 seconds), album details and covers already reduced to panel size (one day). Anyone you share a device with sees on the panel what is playing.', 'Deine Spotify-Kennung und dein Anzeigename, dazu die Zugangs-Token, verschlüsselt. Kurz gemerkt: was gerade läuft (5 Sekunden), die Warteschlange (30 Sekunden), Albumangaben und bereits auf Panelgröße gerechnete Cover (ein Tag). Wer ein Gerät mit dir teilt, sieht auf dem Panel, was läuft.', 'Basis: consent by connecting, Art. 6(1)(a) GDPR. Retention: until you disconnect or delete the account; withdrawing access in your Spotify account works too.', 'Grundlage: Einwilligung durch das Verbinden, Art. 6 Abs. 1 lit. a DSGVO. Dauer: bis du die Verbindung trennst oder das Konto löschst; den Zugang in deinem Spotify-Konto zu entziehen geht auch.') ?>
            <?= $store('Server logs', 'Server-Protokolle', 'IP address, timestamp and requested address, to fend off abuse and to find faults.', 'IP-Adresse, Zeitpunkt und aufgerufene Adresse, zur Abwehr von Missbrauch und zur Fehlersuche.', 'Basis: legitimate interest, Art. 6(1)(f) GDPR. Retention: 14 days, then deleted automatically.', 'Grundlage: berechtigtes Interesse, Art. 6 Abs. 1 lit. f DSGVO. Dauer: 14 Tage, dann automatisch gelöscht.') ?>
          </div>
        </div>

        <div>
          <h2 style="<?= $h2 ?>"<?= de('Dienste, die mitlesen') ?>>Services that see something</h2>
          <p style="margin:0 0 16px;color:#C6CDD3;text-wrap:pretty"<?= de('Diese Dienste werden gebraucht, damit das Panel Inhalte anzeigen kann. Wo eine IP-Adresse übertragen wird, steht es dabei.') ?>>These services are needed so the panel can show anything at all. Where an IP address is transmitted, it says so.</p>
          <dl style="margin:0;display:grid;grid-template-columns:1fr;gap:0;font-size:14px">
            <dt style="padding:12px 0 4px;border-top:1px solid #1B2126;<?= $mono ?>font-size:12.5px;color:#E8EAEC">Cloudflare</dt>
            <dd style="margin:0;padding:0 0 12px;color:#8B949C;line-height:1.6"<?= de('Auslieferung und Schutz der Seite, Cloudflare, Inc., USA. Jede Anfrage deines Browsers und deines Geräts läuft über Cloudflare. Weil Cloudflare die verschlüsselte Verbindung annimmt, sieht es deine IP-Adresse, die aufgerufene Adresse und den Inhalt. Die Übermittlung in die USA stützt sich auf das EU-US Data Privacy Framework, nach dem Cloudflare zertifiziert ist. Grundlage: berechtigtes Interesse an einer schnellen und geschützten Seite, Art. 6 Abs. 1 lit. f DSGVO.') ?>>Delivery and protection of the site, Cloudflare, Inc., USA. Every request from your browser and from your device passes through Cloudflare. Because Cloudflare accepts the encrypted connection, it sees your IP address, the requested address and the content. The transfer to the USA rests on the EU-US Data Privacy Framework, under which Cloudflare is certified. Basis: legitimate interest in a fast and protected site, Art. 6(1)(f) GDPR.</dd>

            <dt style="padding:12px 0 4px;border-top:1px solid #1B2126;<?= $mono ?>font-size:12.5px;color:#E8EAEC">Brevo</dt>
            <dd style="margin:0;padding:0 0 12px;color:#8B949C;line-height:1.6"<?= de('Versand der Systemmails (Adresse bestätigen, Passwort zurücksetzen, Einladungen, Datenexport) über Brevo, Sendinblue SAS, Paris. Brevo erhält deine E-Mail-Adresse und den Inhalt der Mail, beim Datenexport also alle deine Daten als Anhang. Grundlage: Vertragserfüllung, Art. 6 Abs. 1 lit. b DSGVO.') ?>>System emails (confirming your address, resetting a password, invitations, data export) are sent through Brevo, Sendinblue SAS, Paris. Brevo receives your email address and the content of the email, so for a data export all your data as an attachment. Basis: performance of a contract, Art. 6(1)(b) GDPR.</dd>

            <dt style="padding:12px 0 4px;border-top:1px solid #1B2126;<?= $mono ?>font-size:12.5px;color:#E8EAEC">adsb.lol, adsb.fi, adsbdb.com, GitHub Pages</dt>
            <dd style="margin:0;padding:0 0 12px;color:#8B949C;line-height:1.6"<?= de('Flugdaten. Die Abfrage stellt unser Server, nicht dein Browser und nicht dein Gerät. Übertragen wird der Standort, den du eingestellt hast oder gerade auf der Karte verschiebst, nie deine IP-Adresse. Beide Flugdatenquellen werden gefragt und ihre Listen zusammengelegt, weil jedes Netz eigene Empfänger hat. Für Airline und Strecke fragt der Server nur mit dem Rufzeichen des Flugzeugs, bei adsbdb.com und in den Standdaten von Virtual Radar Server, die adsb.lol über GitHub Pages (GitHub Inc., USA) bereitstellt.') ?>>Flight data. Our server makes the request, not your browser and not your device. What is sent is the location you set or are moving on the map, never your IP address. Both flight data sources are asked and their lists merged, because each network has its own receivers. For airline and route the server only sends the aircraft's callsign, to adsbdb.com and to the Virtual Radar Server standing data that adsb.lol serves through GitHub Pages (GitHub Inc., USA).</dd>

            <dt style="padding:12px 0 4px;border-top:1px solid #1B2126;<?= $mono ?>font-size:12.5px;color:#E8EAEC">Open-Meteo</dt>
            <dd style="margin:0;padding:0 0 12px;color:#8B949C;line-height:1.6"<?= de('Wetterdaten, ebenfalls nur vom Server abgefragt, mit den Koordinaten deines Geräts.') ?>>Weather data, also requested by the server only, with your device's coordinates.</dd>

            <dt style="padding:12px 0 4px;border-top:1px solid #1B2126;<?= $mono ?>font-size:12.5px;color:#E8EAEC">mobiliteit.lu</dt>
            <dd style="margin:0;padding:0 0 12px;color:#8B949C;line-height:1.6"<?= de('Abfahrten für den Nahverkehrsmodus und die Liste der Haltestellen, von der Administration des transports publics in Luxemburg. Die Abfrage stellt unser Server. Übertragen werden die Kennung der gewählten Haltestelle oder Koordinaten für die Suche, nie deine IP-Adresse.') ?>>Departures for the transit mode and the list of stops, from the Administration des transports publics in Luxembourg. Our server makes the request. What is sent is the ID of the chosen stop or coordinates for the search, never your IP address.</dd>

            <dt style="padding:12px 0 4px;border-top:1px solid #1B2126;<?= $mono ?>font-size:12.5px;color:#E8EAEC">Spotify</dt>
            <dd style="margin:0;padding:0 0 12px;color:#8B949C;line-height:1.6"<?= de('Nur wenn du Spotify auf der Geräteseite verbindest. Die Anmeldung läuft bei Spotify AB in Stockholm, dein Passwort sieht unser Server nie. Danach fragt unser Server im Namen deines Kontos, was gerade läuft und was als Nächstes kommt, solange ein Gerät Spotify zeigt, dazu Albumangaben und Cover. Spotify erfährt dabei, dass diese App dein Konto liest, nicht deine IP-Adresse. Gesteuert wird nichts.') ?>>Only if you connect Spotify on the device page. You sign in at Spotify AB in Stockholm, our server never sees your password. After that our server asks, on behalf of your account, what is playing and what comes next, as long as a device shows Spotify, plus album details and covers. Spotify learns that this app reads your account, not your IP address. Nothing is controlled.</dd>

            <dt style="padding:12px 0 4px;border-top:1px solid #1B2126;<?= $mono ?>font-size:12.5px;color:#E8EAEC">OpenStreetMap, Nominatim</dt>
            <dd style="margin:0;padding:0 0 12px;color:#8B949C;line-height:1.6"<?= de('Kartenbilder und Ortssuche auf der Geräteseite, dazu der Ort unter dem Flugzeug auf dem Panel. Ortssuche und Ort stellt unser Server, für den Ort nur mit den Koordinaten des Flugzeugs. Die Kartenbilder lädt dein Browser direkt, dabei wird deine IP-Adresse an die OpenStreetMap Foundation übertragen. Deshalb lädt die Karte erst, wenn du sie anforderst.') ?>>Map imagery and place search on the device page, plus the place under the aircraft on the panel. Our server makes the search and the place lookup, the latter only with the aircraft's coordinates. Your browser loads the map tiles directly, which transmits your IP address to the OpenStreetMap Foundation. That is why the map only loads once you ask for it.</dd>

            <dt style="padding:12px 0 4px;border-top:1px solid #1B2126;<?= $mono ?>font-size:12.5px;color:#E8EAEC">Bunny Fonts</dt>
            <dd style="margin:0;padding:0 0 12px;border-bottom:1px solid #1B2126;color:#8B949C;line-height:1.6"<?= de('Schriften, ausgeliefert von BunnyWay d.o.o. in Slowenien, also innerhalb der EU. Der Dienst protokolliert nach eigener Angabe nichts und setzt keine Cookies.') ?>>Typefaces, delivered by BunnyWay d.o.o. in Slovenia, so inside the EU. By its own account the service logs nothing and sets no cookies.</dd>
          </dl>
        </div>

        <div>
          <h2 style="<?= $h2 ?>"<?= de('Wo die Daten liegen') ?>>Where the data sits</h2>
          <p style="margin:0;color:#C6CDD3;text-wrap:pretty"<?= de('Auf einem Server in Luxemburg. Ausgeliefert wird die Seite über Cloudflare, das ist eine Übermittlung in die USA (siehe oben). Darüber hinaus verlässt nichts die EU, abgesehen von den Kartenkacheln, wenn du die Karte lädst.') ?>>On a server in Luxembourg. The site is delivered through Cloudflare, which is a transfer to the USA (see above). Beyond that nothing leaves the EU, apart from the map tiles when you load the map.</p>
        </div>

        <div>
          <h2 style="<?= $h2 ?>"<?= de('Deine Rechte') ?>>Your rights</h2>
          <p style="margin:0 0 14px;color:#C6CDD3;text-wrap:pretty"<?= de('Du hast das Recht auf Auskunft (Art. 15), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch (Art. 21).') ?>>You have the right of access (Art. 15), rectification (Art. 16), erasure (Art. 17), restriction (Art. 18), data portability (Art. 20) and objection (Art. 21).</p>
          <p style="margin:0 0 14px;color:#C6CDD3;text-wrap:pretty"<?= de('Zwei davon brauchen keine E-Mail: unter Einstellungen lädst du alle deine Daten als Datei herunter und löschst dein Konto samt Inhalten sofort und vollständig.') ?>>Two of those need no email: under Settings you can download all your data as a file and delete your account with its content at once and completely.</p>
          <p style="margin:0;color:#C6CDD3;text-wrap:pretty"<?= de('Beschweren kannst du dich bei der Commission nationale pour la protection des données (CNPD), 15 Boulevard du Jazz, L-4370 Belvaux.') ?>>You can lodge a complaint with the Commission nationale pour la protection des données (CNPD), 15 Boulevard du Jazz, L-4370 Belvaux.</p>
        </div>

        <div>
          <h2 style="<?= $h2 ?>"<?= de('Cookies') ?>>Cookies</h2>
          <p style="margin:0;color:#C6CDD3;text-wrap:pretty"<?= de('THE WALL setzt ein einziges: die Sitzungskennung, damit du angemeldet bleibst. Sie ist technisch notwendig, läuft nach 30 Tagen ab und wird beim Abmelden gelöscht. Cloudflare kann zum Schutz vor Bots ein eigenes, kurzlebiges Cookie setzen (__cf_bm, 30 Minuten), ebenfalls technisch notwendig und ohne Wiedererkennung über andere Seiten. Kein Tracking, keine Reichweitenmessung, kein Banner. Die gewählte Sprache merkt sich dein Browser selbst, ohne Cookie.') ?>>THE WALL sets a single one: the session identifier that keeps you signed in. It is strictly necessary, expires after 30 days and is deleted when you sign out. Cloudflare may set its own short-lived cookie to tell people from bots (__cf_bm, 30 minutes), also strictly necessary and without recognising you across other sites. No tracking, no audience measurement, no banner. Your chosen language is kept by your browser itself, without a cookie.</p>
        </div>
      </div>
    </section>

    <section id="imprint" style="scroll-margin-top:90px">
      <div data-pxwrap="1" style="margin:0 0 14px;max-width:620px"><canvas aria-hidden="true"></canvas></div>
      <h1 data-pxhead="1"<?= de('Impressum') ?>>Legal notice</h1>

      <div style="display:flex;flex-direction:column;gap:clamp(24px,4vw,36px);font-size:15px;line-height:1.7;margin-top:clamp(20px,3vw,28px)">
        <div>
          <h2 style="<?= $h2 ?>"<?= de('Anbieter') ?>>Provider</h2>
          <p style="margin:0;color:#C6CDD3"><?= $name ?><br><?= $address ?>Luxembourg</p>
        </div>
        <div>
          <h2 style="<?= $h2 ?>"<?= de('Kontakt') ?>>Contact</h2>
          <p style="margin:0;color:#C6CDD3"><?= $email ?><?php if ($imp['repo'] !== ''): ?><br><a href="<?= h($imp['repo']) ?>" rel="noreferrer"><?= h(preg_replace('#^https?://#', '', $imp['repo'])) ?></a><?php endif; ?></p>
        </div>
        <div>
          <h2 style="<?= $h2 ?>"<?= de('Art des Angebots') ?>>Nature of this service</h2>
          <p style="margin:0;color:#C6CDD3;text-wrap:pretty"<?= de('Ein privates, nicht kommerzielles Bastelprojekt. Es wird nichts verkauft und es gibt kein Abonnement. Der Quelltext von Firmware und Server ist öffentlich: nutzen und verändern erlaubt, verkaufen nicht.') ?>>A private, non-commercial hobby project. Nothing is sold and there is no subscription. The source code of the firmware and the server is public: use and change it, but do not sell it.</p>
        </div>
        <div>
          <h2 style="<?= $h2 ?>"<?= de('Fremde Inhalte') ?>>Third-party content</h2>
          <p style="margin:0 0 12px;color:#C6CDD3;text-wrap:pretty"<?= de('Kartendaten und Ortsnamen von OpenStreetMap, veröffentlicht unter der Open Database License. Die Karte auf dem Panel zeichnet der Server aus gemeinfreien Daten: Küsten, Grenzen, Gewässer, Stadtgebiete und Ortsnamen von Natural Earth, Flugplätze und Start- und Landebahnen von OurAirports. Diese Daten liegen fertig auf unserem Server, sie werden im Betrieb nirgends abgefragt. Flugdaten aus den offenen Projekten adsb.lol und adsb.fi, Strecken aus den Standdaten von Virtual Radar Server (gemeinfrei) und aus adsbdb.com. Wetterdaten von Open-Meteo, Abfahrten und Haltestellen von der Administration des transports publics (mobiliteit.lu), beide unter CC BY 4.0. Titel, Cover und Warteschlange im Spotify-Modus kommen von Spotify.') ?>>Map data and place names from OpenStreetMap, published under the Open Database License. The map on the panel is drawn by our server from public domain data: coastlines, borders, water, built-up areas and place names from Natural Earth, airports and runways from OurAirports. That data sits ready on our server and is never requested while the device runs. Flight data from the open projects adsb.lol and adsb.fi, routes from the Virtual Radar Server standing data (public domain) and from adsbdb.com. Weather data from Open-Meteo, departures and stops from the Administration des transports publics (mobiliteit.lu), both under CC BY 4.0. Titles, covers and the queue in the Spotify mode come from Spotify.</p>
          <p style="margin:0;color:#C6CDD3;text-wrap:pretty"<?= de('Airline-Logos sind eingetragene Marken der jeweiligen Fluggesellschaften. Sie werden hier nur zur Anzeige auf dem eigenen Gerät verwendet, nicht zum Verkauf und nicht als Hinweis auf eine Zusammenarbeit.') ?>>Airline logos are registered trademarks of the respective airlines. They are used here only for display on your own device, not for sale and not to imply any association.</p>
        </div>
        <div>
          <h2 style="<?= $h2 ?>"<?= de('Haftung') ?>>Liability</h2>
          <p style="margin:0;color:#C6CDD3;text-wrap:pretty"<?= de('Die angezeigten Flug- und Wetterdaten stammen aus fremden Quellen und können falsch, verzögert oder unvollständig sein. Sie sind nicht für die Navigation oder für Entscheidungen im Luftverkehr geeignet.') ?>>The flight and weather data shown comes from third-party sources and may be wrong, delayed or incomplete. It is not suitable for navigation or for any aviation decision.</p>
        </div>
      </div>
    </section>
  </main>

  <footer style="border-top:1px solid #1B2126">
    <div style="max-width:900px;margin:0 auto;padding:24px 20px;display:flex;flex-wrap:wrap;gap:16px 28px;align-items:center;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em">
      <span style="color:#8B949C">THE WALL &middot; 2026</span>
      <a href="/" class="h-text hit" style="color:#8B949C"<?= de('Startseite') ?>>Home</a>
      <a href="/account" class="h-text hit" style="color:#8B949C"<?= de('Anmelden') ?>>Sign in</a>
      <span style="margin-left:auto;color:#8B949C"><?= h(site_host()) ?></span>
    </div>
  </footer>
</div>
