// Vergleicht pixelfont.js mit der 5x7-Schrift der Firmware. Aufruf: node tools/fontcmp.mjs web/assets/js/lib/pixelfont.js tools/font95.hex
import {readFileSync} from 'fs';
import vm from 'vm';

const [,, pxPath, hexPath] = process.argv;

// pixelfont.js in einer Sandbox ausfuehren, so kommen die Glyphen exakt heraus
const sandbox = {window: {}};
vm.createContext(sandbox);
vm.runInContext(readFileSync(pxPath, 'utf8'), sandbox);
const G = sandbox.window.PX.glyphs;

const hex = readFileSync(hexPath, 'utf8').trim();
function gfx(ch) {
  const c = ch.charCodeAt(0);
  if (c < 32 || c > 126) return null;
  const o = (c - 32) * 10;
  const cols = [0, 1, 2, 3, 4].map(i => parseInt(hex.substr(o + i * 2, 2), 16));
  return [0, 1, 2, 3, 4, 5, 6].map(r => cols.map(b => (b >> r) & 1 ? '#' : '.').join(''));
}

const keys = Object.keys(G);
const same = [], diff = [];
for (const k of keys) {
  const b = gfx(k);
  if (!b) continue;
  (G[k].join('/') === b.join('/') ? same : diff).push(k);
}
const printable = [];
for (let c = 32; c < 127; c++) printable.push(String.fromCharCode(c));
const missing = printable.filter(ch => !G[ch]);

console.log('Glyphen in pixelfont.js:', keys.length);
console.log('identisch mit der Firmware-Schrift:', same.length, same.join(' '));
console.log('abweichend:', diff.length, diff.join(' '));
console.log('fehlen gegenueber ASCII 32-126:', missing.length, JSON.stringify(missing.join('')));
for (const ch of ['A', 'g', '7']) {
  const a = G[ch], b = gfx(ch);
  console.log(`--- ${ch}    pixelfont | firmware`);
  for (let r = 0; r < 7; r++) console.log(`    ${a ? a[r] : '(fehlt)'}   |   ${b[r]}`);
}
