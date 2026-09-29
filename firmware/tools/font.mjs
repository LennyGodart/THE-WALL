// Rechnet die Glyphen aus web/assets/js/lib/pixelfont.js in eine C-Tabelle fuer die
// Firmware um, damit Panel und Webseite dieselbe Schrift zeigen.
// Aufruf aus dem Ordner firmware: node tools/font.mjs
import fs from 'fs';
import path from 'path';
import vm from 'vm';
import { fileURLToPath } from 'url';

const here = path.dirname(fileURLToPath(import.meta.url));
const src = path.resolve(here, '../../web/assets/js/lib/pixelfont.js');
const out = path.resolve(here, '../src/glyphs.h');

const sandbox = { window: {} };
vm.runInNewContext(fs.readFileSync(src, 'utf8'), sandbox);
const glyphs = sandbox.window.PX.glyphs;

const entries = Object.entries(glyphs)
  .map(([ch, rows]) => {
    if ([...ch].length !== 1) throw new Error('Glyphe mit mehr als einem Zeichen: ' + ch);
    if (rows.length !== 7 || rows.some((r) => r.length !== 5 || /[^.#]/.test(r))) {
      throw new Error('Glyphe nicht 5 x 7: ' + ch);
    }
    const bytes = rows.map((r) => [...r].reduce((v, c) => (v << 1) | (c === '#' ? 1 : 0), 0));
    return { cp: ch.codePointAt(0), bytes };
  })
  .sort((a, b) => a.cp - b.cp);

const hex = (n, w) => '0x' + n.toString(16).toUpperCase().padStart(w, '0');
const lines = entries.map(({ cp, bytes }) => `    {${hex(cp, 4)}, {${bytes.map((b) => hex(b, 2)).join(', ')}}},`);

fs.writeFileSync(
  out,
  `// Erzeugt von tools/font.mjs aus web/assets/js/lib/pixelfont.js, nicht von Hand aendern.
// Jede Glyphe hat 7 Zeilen zu je 5 Bits, Bit 4 ist die linke Spalte.
#pragma once
#include <cstdint>

struct Glyph {
  uint16_t cp;
  uint8_t rows[7];
};

// Nach Codepunkt sortiert, gesucht wird binaer.
static const Glyph GLYPHS[] = {
${lines.join('\n')}
};

static constexpr int GLYPH_COUNT = ${entries.length};
`
);
console.log(`${entries.length} Glyphen nach ${path.relative(process.cwd(), out)}`);
