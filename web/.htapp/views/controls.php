<?php
$mono = "font-family:'IBM Plex Mono',monospace;";
$card = 'background:#0B0D0F;padding:22px;display:flex;flex-direction:column;gap:16px';
$code = static fn(string $id, string $en, ?string $de = null): string => '<p style="margin:0;font-family:\'IBM Plex Mono\',monospace;font-size:11px;letter-spacing:0.12em;color:#E09A1A">' . h($id) . ' &middot; <span style="color:#8B949C"' . ($de !== null ? de($de) : '') . '>' . h($en) . '</span></p>';
$desc = static fn(string $en, string $de): string => '<p style="margin:0;font-size:12.5px;line-height:1.5;color:#8B949C"' . de($de) . '>' . h($en) . '</p>';
$label = 'display:block;margin-bottom:7px;' . $mono . 'font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3';
$sectionHead = static function (string $id, string $en, string $de, string $range) use ($mono): string {
    return '<div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:14px;padding-bottom:12px;border-bottom:1px solid #1B2126;margin-bottom:clamp(20px,3vw,28px)">'
        . '<h2 style="margin:0;' . $mono . 'font-size:13px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#E8EAEC"' . de($de) . '>' . h($en) . '</h2>'
        . '<span style="' . $mono . 'font-size:11px;color:#8B949C">' . $range . '</span></div>';
};
$grid = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,264px),1fr));gap:1px;background:#1B2126;border:1px solid #1B2126';
?>
<div style="min-height:100vh;display:flex;flex-direction:column">

  <?= skip_link('#lib', 'Skip to the library', 'Zur Bibliothek springen') ?>

  <header style="position:sticky;top:0;z-index:30;background:rgba(8,9,10,0.86);backdrop-filter:blur(12px);border-bottom:1px solid #1B2126">
    <div style="max-width:1240px;margin:0 auto;padding:14px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 20px">
      <?= brand_mark('/') ?>
      <nav style="display:flex;flex-wrap:wrap;gap:8px 18px;<?= $mono ?>font-size:12px;letter-spacing:0.08em;text-transform:uppercase" aria-label="Sections" data-de-label="Abschnitte">
        <a href="#fields" class="h-text hit" style="color:#8B949C"<?= de('Felder') ?>>Fields</a>
        <a href="#sliders" class="h-text hit" style="color:#8B949C"<?= de('Regler') ?>>Sliders</a>
        <a href="#switches" class="h-text hit" style="color:#8B949C"<?= de('Schalter') ?>>Switches</a>
        <a href="#choice" class="h-text hit" style="color:#8B949C"<?= de('Auswahl') ?>>Choice</a>
        <a href="#colour" class="h-text hit" style="color:#8B949C"<?= de('Farbe') ?>>Colour</a>
      </nav>
      <div style="flex:1 0 0;min-width:0"></div>
      <?= lang_switch() ?>
    </div>
  </header>

  <main id="lib" style="flex:1;width:100%;max-width:1240px;margin:0 auto;padding:clamp(32px,5vw,64px) 20px">

    <div data-pxwrap="1" style="margin:0 0 16px;max-width:720px"><canvas aria-hidden="true"></canvas></div>
    <h1 data-pxhead="1"<?= de('Eingabe-Bibliothek') ?>>Input library</h1>
    <p style="margin:0 0 clamp(40px,6vw,72px);max-width:58ch;font-size:16px;line-height:1.6;color:#8B949C;text-wrap:pretty"<?= de('Fünf Ansätze pro Eingabeart, alle bedienbar. Gewählt sind A1 und A5, B2 und B1, C1 und C5, D4 und D5, E5. Jede Karte nennt, wofür die Variante taugt.') ?>>Five approaches per input type, all of them working. The chosen ones are A1 and A5, B2 and B1, C1 and C5, D4 and D5, E5. Each card says what its variant is good for.</p>

    <section id="fields" style="margin-bottom:clamp(52px,7vw,88px);scroll-margin-top:80px">
      <?= $sectionHead('A', 'Text fields', 'Textfelder', 'A1 &ndash; A5') ?>
      <div style="<?= $grid ?>">
        <article style="<?= $card ?>">
          <?= $code('A1', 'FRAMED', 'RAHMEN') ?>
          <div><label for="a1" style="<?= $label ?>"<?= de('Gerätename') ?>>Device name</label><input id="a1" type="text" placeholder="Wohnzimmer" class="field" autocomplete="off"></div>
          <?= $desc('The default. Clear frame, focus glows. For forms with few fields.', 'Der Standard. Klarer Rahmen, Fokus leuchtet. Für Formulare mit wenigen Feldern.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('A2', 'UNDERLINE', 'UNTERSTRICH') ?>
          <div><label for="a2" style="<?= $label ?>"<?= de('Gerätename') ?>>Device name</label><input id="a2" type="text" placeholder="Wohnzimmer" class="field-underline" autocomplete="off"></div>
          <?= $desc('Just a line. For long lists where many frames get noisy.', 'Nur eine Linie. Für lange Listen, wo viele Rähmen unruhig wirken.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('A3', 'INSET', 'VERTIEFT') ?>
          <div><label for="a3" style="<?= $label ?>"<?= de('Gerätename') ?>>Device name</label><input id="a3" type="text" placeholder="Wohnzimmer" class="field-inset" autocomplete="off"></div>
          <?= $desc('Sunk in like a window in the device. When the field feeds the panel.', 'Eingesenkt wie ein Fenster im Gerät. Wenn das Feld auf dem Panel landet.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('A4', 'TERMINAL') ?>
          <div><label for="a4" style="<?= $label ?>"<?= de('Gerätename') ?>>Device name</label>
            <div class="term"><span aria-hidden="true" style="<?= $mono ?>font-size:15px;color:#3DE07C;flex:0 0 auto">&gt;</span><input id="a4" type="text" placeholder="wohnzimmer" autocomplete="off"></div>
          </div>
          <?= $desc('A console line. Fits network, IP and keys.', 'Konsolenzeile. Passt zu Netzwerk, IP und Schlüsseln.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('A5', 'COUNTED', 'ZAEHLER') ?>
          <div>
            <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:7px">
              <label for="a5" style="flex:1;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Panel-Zeile') ?>>Panel line</label>
              <span data-a5-count aria-live="polite" style="<?= $mono ?>font-size:11px;font-variant-numeric:tabular-nums;color:#8B949C"<?= de('21 frei') ?>>21 left</span>
            </div>
            <input id="a5" type="text" maxlength="21" placeholder="ALLES GUTE LENNY" class="field" aria-describedby="a5-hint" autocomplete="off">
            <p id="a5-hint" style="margin:7px 0 0;<?= $mono ?>font-size:11px;color:#8B949C"<?= de('21 Zeichen passen auf eine Panelzeile') ?>>21 characters fit one panel line</p>
          </div>
          <?= $desc('With a remaining count. Required for anything that lands on the panel.', 'Mit Restanzeige. Pflicht für alles, was auf dem Panel landet.') ?>
        </article>
      </div>
    </section>

    <section id="sliders" style="margin-bottom:clamp(52px,7vw,88px);scroll-margin-top:80px">
      <?= $sectionHead('B', 'Sliders', 'Regler', 'B1 &ndash; B5 &middot; 0&ndash;255') ?>
      <div style="<?= $grid ?>">
        <article style="<?= $card ?>">
          <?= $code('B1', 'WITH VALUE', 'MIT WERT') ?>
          <div>
            <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:6px">
              <label for="b1" style="flex:1;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Helligkeit') ?>>Brightness</label>
              <output for="b1" data-bright-out style="<?= $mono ?>font-size:15px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums">168</output>
            </div>
            <input id="b1" data-bright type="range" min="0" max="255" step="1" value="168">
          </div>
          <?= $desc('Number beside the track, tabular digits so nothing jumps.', 'Zahl neben dem Regler, tabellarische Ziffern damit nichts springt.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('B2', 'LED BAR', 'LED-BALKEN') ?>
          <div>
            <label for="b2" style="display:block;margin-bottom:8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Helligkeit') ?>>Brightness</label>
            <div data-b2-bar aria-hidden="true" style="margin-bottom:6px"><canvas aria-hidden="true"></canvas></div>
            <input id="b2" data-bright type="range" min="0" max="255" step="1" value="168" aria-describedby="b2-val">
            <p id="b2-val" style="margin:2px 0 0;<?= $mono ?>font-size:11px;color:#8B949C;font-variant-numeric:tabular-nums"><span data-bright-out>168</span> / 255</p>
          </div>
          <?= $desc('The value as a real LED row. Shows brightness instead of claiming it.', 'Der Wert als echte LED-Reihe. Zeigt die Helligkeit, statt sie zu behaupten.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('B3', 'STEPS', 'STUFEN') ?>
          <div>
            <p id="b3-label" style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Helligkeit') ?>>Brightness</p>
            <div role="group" aria-labelledby="b3-label" style="display:grid;grid-template-columns:repeat(5,1fr);gap:4px">
              <?php foreach ([0, 64, 128, 192, 255] as $v): ?><button type="button" class="step" data-step="<?= $v ?>" aria-pressed="false"><?= $v ?></button><?php endforeach; ?>
            </div>
          </div>
          <?= $desc('Fixed steps, one tap. Good on a phone, where dragging is imprecise.', 'Feste Stufen, ein Griff. Gut am Handy, wo Ziehen unpräzise ist.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('B4', 'UPRIGHT', 'STEHEND') ?>
          <div style="display:flex;align-items:flex-end;gap:16px">
            <input id="b4" data-bright class="vert" type="range" min="0" max="255" step="1" value="168" aria-label="Brightness" data-de-label="Helligkeit">
            <div>
              <p style="margin:0;<?= $mono ?>font-size:22px;font-weight:600;color:#FFAA00;font-variant-numeric:tabular-nums;line-height:1" data-bright-out>168</p>
              <p style="margin:4px 0 0;<?= $mono ?>font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:#8B949C"<?= de('von 255') ?>>of 255</p>
            </div>
          </div>
          <?= $desc('Upright like a mixing desk fader. Feels like hardware, needs room.', 'Stehend wie ein Mischpultfader. Wirkt wie Hardware, braucht aber Platz.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('B5', 'WITH ENTRY', 'MIT EINGABE') ?>
          <div>
            <label for="b5" style="display:block;margin-bottom:8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Helligkeit') ?>>Brightness</label>
            <div style="display:flex;align-items:center;gap:12px">
              <input id="b5" data-bright type="range" min="0" max="255" step="1" value="168" style="flex:1">
              <input type="number" data-bright-num min="0" max="255" value="168" aria-label="Brightness value" data-de-label="Helligkeitswert" style="width:74px;min-height:44px;padding:10px;background:#0E1215;border:1px solid #2C353C;border-radius:2px;color:#E8EAEC;font-size:14px;text-align:center;font-variant-numeric:tabular-nums">
            </div>
          </div>
          <?= $desc('Drag or type. For values you want to reproduce exactly.', 'Ziehen oder tippen. Für Werte, die man exakt wiederherstellen will.') ?>
        </article>
      </div>
    </section>

    <section id="switches" style="margin-bottom:clamp(52px,7vw,88px);scroll-margin-top:80px">
      <?= $sectionHead('C', 'Switches', 'Schalter', 'C1 &ndash; C5') ?>
      <div style="<?= $grid ?>">
        <article style="<?= $card ?>">
          <?= $code('C1', 'TOGGLE', 'KIPPSCHALTER') ?>
          <div style="display:flex;align-items:center;gap:14px">
            <button type="button" class="sw" role="switch" data-toggle aria-checked="true" aria-labelledby="c1-label"><span></span></button>
            <span id="c1-label" style="font-size:14px;color:#E8EAEC"<?= de('Nachtabsenkung') ?>>Night dimming</span>
          </div>
          <?= $desc('The familiar toggle. Takes effect at once, nothing to confirm.', 'Der bekannte Kippschalter. Wirkt sofort, kein Bestätigen.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('C2', 'CHECKBOX', 'KAESTCHEN') ?>
          <label style="display:flex;align-items:center;gap:14px;cursor:pointer;min-height:44px">
            <input type="checkbox">
            <span style="font-size:14px;color:#E8EAEC"<?= de('Sekunden anzeigen') ?>>Show seconds</span>
          </label>
          <?= $desc('Native, costs nothing, everyone knows it. For lists with many options.', 'Systemeigen, kostet nichts, jeder kennt es. Für Listen mit vielen Optionen.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('C3', 'TWO UP', 'ZWEI FELDER') ?>
          <div>
            <p id="c3-label" style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Zeitformat') ?>>Time format</p>
            <div role="group" aria-labelledby="c3-label" data-two-group style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
              <button type="button" class="two" aria-pressed="true">24 H</button>
              <button type="button" class="two" aria-pressed="false">12 H</button>
            </div>
          </div>
          <?= $desc('Both states visible. Better than a toggle when the wording matters.', 'Beide Zustände sichtbar. Besser als ein Schalter, wenn die Beschriftung zählt.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('C4', 'LED BUTTON', 'LED-TASTER') ?>
          <button type="button" class="ledbtn" role="switch" data-toggle aria-checked="false">
            <span aria-hidden="true" class="lamp"></span>
            <span style="flex:1;text-align:left"<?= de('Panel an') ?>>Panel on</span>
            <span class="state" data-led-state>OFF</span>
          </button>
          <?= $desc('An indicator lamp like on the device. Good for the one switch that matters.', 'Eine Kontrollleuchte wie am Gerät. Gut für den einen wichtigen Schalter.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('C5', 'WITH REASON', 'MIT ERKLAERUNG') ?>
          <div style="display:flex;align-items:flex-start;gap:14px">
            <button type="button" class="sw" role="switch" data-toggle aria-checked="true" aria-labelledby="c5-label"><span></span></button>
            <div style="flex:1;min-width:0">
              <p id="c5-label" style="margin:0 0 3px;font-size:14px;color:#E8EAEC"<?= de('Updates über WLAN') ?>>Updates over Wi-Fi</p>
              <p style="margin:0;font-size:12px;line-height:1.45;color:#8B949C"<?= de('Holt neue Firmware selbst. Danach nie wieder ans Kabel.') ?>>Fetches new firmware itself. Never needs the cable again.</p>
            </div>
          </div>
          <?= $desc('Explanation under the name. Required wherever the consequence is not obvious.', 'Erklärung unter dem Namen. Pflicht bei allem, dessen Folge nicht offensichtlich ist.') ?>
        </article>
      </div>
    </section>

    <section id="choice" style="margin-bottom:clamp(52px,7vw,88px);scroll-margin-top:80px">
      <?= $sectionHead('D', 'Choice', 'Auswahl', 'D1 &ndash; D5') ?>
      <div style="<?= $grid ?>">
        <article style="<?= $card ?>">
          <?= $code('D1', 'DROPDOWN', 'AUSKLAPPLISTE') ?>
          <div>
            <label for="d1" style="<?= $label ?>"<?= de('Modus') ?>>Mode</label>
            <select id="d1" class="field">
              <option value="flight"<?= de('Flugradar') ?>>Flight radar</option>
              <option value="clock" selected<?= de('Uhr') ?>>Clock</option>
              <option value="weather"<?= de('Wetter') ?>>Weather</option>
              <option value="notes"<?= de('Notizen') ?>>Notes</option>
              <option value="pixel"<?= de('Pixel-Editor') ?>>Pixel editor</option>
            </select>
          </div>
          <?= $desc('Saves room but shows one option. The right pick from six entries up.', 'Spart Platz, zeigt aber nur eine Option. Ab sechs Einträgen die richtige Wahl.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('D2', 'SEGMENTED', 'SEGMENTE') ?>
          <div>
            <p id="d2-label" style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Einheit') ?>>Unit</p>
            <div role="group" aria-labelledby="d2-label" data-seg-group style="display:flex;gap:0;border:1px solid #2C353C;border-radius:2px;overflow:hidden">
              <button type="button" class="segfull" aria-pressed="true">NM</button>
              <button type="button" class="segfull" aria-pressed="false">KM</button>
              <button type="button" class="segfull" aria-pressed="false">MI</button>
            </div>
          </div>
          <?= $desc('Two to four short options, all visible, one tap. Nothing beyond that.', 'Zwei bis vier kurze Optionen, alle sichtbar, ein Griff. Sonst nichts.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('D3', 'RADIO LIST', 'LISTE') ?>
          <div role="radiogroup" aria-labelledby="d3-label" data-radio-group>
            <p id="d3-label" style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Was bei Leerlauf') ?>>When idle</p>
            <div style="display:flex;flex-direction:column;gap:1px;background:#1B2126;border:1px solid #1B2126;border-radius:2px">
              <button type="button" class="rrow" role="radio" aria-checked="true" style="min-height:48px;padding:12px 14px;font-size:14px"><span class="rdot"></span><span<?= de('Uhr zeigen') ?>>Show the clock</span></button>
              <button type="button" class="rrow" role="radio" aria-checked="false" style="min-height:48px;padding:12px 14px;font-size:14px"><span class="rdot"></span><span<?= de('Letzte Notiz') ?>>Last note</span></button>
              <button type="button" class="rrow" role="radio" aria-checked="false" style="min-height:48px;padding:12px 14px;font-size:14px"><span class="rdot"></span><span<?= de('Ruhezustand') ?>>Resting screen</span></button>
            </div>
          </div>
          <?= $desc('One row per option, room for whole sentences. Three to six entries.', 'Eine Zeile je Option, Platz für ganze Sätze. Drei bis sechs Einträge.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('D4', 'TILES', 'KACHELN') ?>
          <div role="radiogroup" aria-labelledby="d4-label" data-radio-group>
            <p id="d4-label" style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Modus') ?>>Mode</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
              <button type="button" class="tile" role="radio" aria-checked="true"><span data-tile="t-flight" aria-hidden="true" style="display:block"><canvas aria-hidden="true"></canvas></span><span<?= de('Flug') ?>>Flight</span></button>
              <button type="button" class="tile" role="radio" aria-checked="false"><span data-tile="t-clock" aria-hidden="true" style="display:block"><canvas aria-hidden="true"></canvas></span><span<?= de('Uhr') ?>>Clock</span></button>
            </div>
          </div>
          <?= $desc('With a panel preview. The best pick for choosing the mode itself.', 'Mit Vorschau des Panels. Die beste Wahl für die Modusauswahl selbst.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('D5', 'MULTI', 'MEHRFACH') ?>
          <div>
            <p id="d5-label" style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('In der Rotation') ?>>In rotation</p>
            <div role="group" aria-labelledby="d5-label" style="display:flex;flex-wrap:wrap;gap:6px">
              <button type="button" class="chip-lib" data-multi aria-pressed="true"<?= de('Flug') ?>>Flight</button>
              <button type="button" class="chip-lib" data-multi aria-pressed="true"<?= de('Uhr') ?>>Clock</button>
              <button type="button" class="chip-lib" data-multi aria-pressed="false"<?= de('Wetter') ?>>Weather</button>
              <button type="button" class="chip-lib" data-multi aria-pressed="false"<?= de('Notizen') ?>>Notes</button>
            </div>
          </div>
          <?= $desc('Several at once. Exactly what the mode rotation needs.', 'Mehrere gleichzeitig. Genau das für die Modus-Rotation.') ?>
        </article>
      </div>
    </section>

    <section id="colour" style="margin-bottom:clamp(20px,3vw,32px);scroll-margin-top:80px">
      <?= $sectionHead('E', 'Colour', 'Farbe', 'E1 &ndash; E5') ?>
      <div style="<?= $grid ?>">
        <article style="<?= $card ?>">
          <?= $code('E1', 'SWATCHES', 'PALETTE') ?>
          <div role="radiogroup" aria-labelledby="e1-label" data-radio-group>
            <p id="e1-label" style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Ziffernfarbe') ?>>Digit colour</p>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
              <?php foreach ([['#FFAA00', 'Amber', 'Bernstein'], ['#35D6FF', 'Cyan', 'Cyan'], ['#3DE07C', 'Green', 'Grün'], ['#FF4A1C', 'Red', 'Rot'], ['#F2F4F5', 'White', 'Weiß']] as $i => [$hex, $cen, $cde]): ?>
                <button type="button" class="swatch" role="radio" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="<?= $cen ?>" data-de-label="<?= $cde ?>" style="--sw:<?= $hex ?>"></button>
              <?php endforeach; ?>
            </div>
          </div>
          <?= $desc('Colours that genuinely read well on LEDs. The only workable kind on a phone.', 'Farben, die auf LEDs wirklich gut aussehen. Am Handy die einzige brauchbare Art.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('E2', 'NATIVE PICKER', 'SYSTEMWAEHLER') ?>
          <div>
            <label for="e2" style="<?= $label ?>"<?= de('Freie Farbe') ?>>Any colour</label>
            <div style="display:flex;align-items:center;gap:12px">
              <input id="e2" type="color" value="#35d6ff" style="width:56px;height:48px;padding:2px;background:#0E1215;border:1px solid #2C353C;border-radius:2px;cursor:pointer">
              <span data-e2-out style="<?= $mono ?>font-size:14px;color:#E8EAEC;text-transform:uppercase">#35D6FF</span>
            </div>
          </div>
          <?= $desc('All sixteen million. Honestly the panel renders a lot of them identically.', 'Alle 16 Millionen. Ehrlich gesagt zeigt das Panel viele davon gleich an.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('E3', 'HEX') ?>
          <div>
            <label for="e3" style="<?= $label ?>"<?= de('Hex-Wert') ?>>Hex value</label>
            <div style="display:flex;align-items:stretch">
              <span aria-hidden="true" data-e3-chip style="flex:0 0 auto;width:48px;min-height:48px;border:1px solid #2C353C;border-right:0;border-radius:2px 0 0 2px;display:block;background:#3DE07C"></span>
              <input id="e3" type="text" value="#3DE07C" spellcheck="false" maxlength="7" placeholder="#FFAA00" class="hexfield" autocomplete="off">
            </div>
          </div>
          <?= $desc('Typed and pasted. For people who already know the value.', 'Tippbar und kopierbar. Für Leute, die den Wert schon kennen.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('E4', 'HUE', 'FARBTON') ?>
          <div>
            <label for="e4" style="display:block;margin-bottom:8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Farbton') ?>>Hue</label>
            <div data-hue aria-hidden="true" style="margin-bottom:6px"><canvas aria-hidden="true"></canvas></div>
            <input id="e4" type="range" min="0" max="359" step="1" value="38" aria-describedby="e4-val">
            <p id="e4-val" style="margin:2px 0 0;<?= $mono ?>font-size:11px;color:#8B949C;font-variant-numeric:tabular-nums" data-hue-out>38&deg;</p>
          </div>
          <?= $desc('One slider through the wheel, shown as an LED row. No aiming required.', 'Ein Regler durch den Farbkreis, gezeigt als LED-Reihe. Kein Zielen nötig.') ?>
        </article>
        <article style="<?= $card ?>">
          <?= $code('E5', 'SWATCH + FREE', 'PALETTE + FREI') ?>
          <div>
            <p id="e5-label" style="margin:0 0 8px;<?= $mono ?>font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#B4BCC3"<?= de('Ziffernfarbe') ?>>Digit colour</p>
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px">
              <div role="radiogroup" aria-labelledby="e5-label" style="display:flex;gap:6px">
              <?php foreach ([['#FFAA00', 'Amber', 'Bernstein'], ['#35D6FF', 'Cyan', 'Cyan'], ['#3DE07C', 'Green', 'Grün']] as $i => [$hex, $cen, $cde]): ?>
                <button type="button" class="swatch" role="radio" data-e5="<?= $hex ?>" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>" aria-label="<?= $cen ?>" data-de-label="<?= $cde ?>" style="--sw:<?= $hex ?>"></button>
              <?php endforeach; ?>
              </div>
              <label class="more" data-e5-more data-active="false"><input type="color" value="#ffaa00" aria-label="Any colour" data-de-label="Beliebige Farbe"><span<?= de('Mehr') ?>>More</span></label>
            </div>
          </div>
          <?= $desc('Three good colours up front, the rest one tap deeper. Chosen for the clock.', 'Drei gute Farben vorne, der Rest einen Griff weiter. Gewählt für die Uhr.') ?>
        </article>
      </div>
    </section>
  </main>

  <footer style="border-top:1px solid #1B2126">
    <div style="max-width:1240px;margin:0 auto;padding:24px 20px;display:flex;flex-wrap:wrap;gap:16px 28px;align-items:center;<?= $mono ?>font-size:11.5px;letter-spacing:0.06em">
      <span style="color:#8B949C">THE WALL &middot; 2026</span>
      <a href="/" class="h-text hit" style="color:#8B949C"<?= de('Startseite') ?>>Home</a>
      <a href="/account" class="h-text hit" style="color:#8B949C"<?= de('Anmelden') ?>>Sign in</a>
      <span style="margin-left:auto;color:#8B949C"<?= de('Alle Bedienelemente sind echt und tastaturbedienbar') ?>>Every control here is real and keyboard operable</span>
    </div>
  </footer>
</div>
