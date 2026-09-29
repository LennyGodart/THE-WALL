// Rechnet eine Antwort des Servers als PNG, mit denselben Dateien, die auch die
// Vorschau auf der Webseite zeichnen (pixelfont.js, ops.js, anim.js).
// Gedacht als Vergleich fuer die Firmware: so soll das Panel die Seite zeigen.
// Die Bilder in docs/images/ sind damit entstanden.
//
// Eingabe ist JSON aus GET /api/v1/frame, aus POST /api/preview/{id} oder eine
// einzelne Seite {ops: [...]}. "-" liest von stdin.
//
// Aufruf aus dem Hauptverzeichnis, Node 18 oder neuer, keine Pakete:
//   node tools/panel-png.mjs antwort.json bild.png              laufende oder erste Seite
//   node tools/panel-png.mjs antwort.json bild.png --page 2     dritte Seite
//   node tools/panel-png.mjs antwort.json ordner/ --all         jede Seite als page-<n>.png
//   node tools/panel-png.mjs antwort.json bild.png --now 1790354520000 --iana Europe/Luxembourg
//   node tools/panel-png.mjs antwort.json bild.png --logos web/.htdata/logos
//
// Weitere Schalter: --px 7 (LED-Groesse), --gap 1, --glow 0.6, --bezel 9, --margin 0,
// --lang en|de, --t 1.5 (Sekunden fuer anim), --scale 1.
// Ohne --logos zeichnet es fuer ein Logo den grauen Block mit Kuerzel, wie das
// Geraet bei 404. Airline-Logos sind Marken, Bilder mit Logos gehoeren nicht ins Repo.

import fs from 'fs';
import path from 'path';
import vm from 'vm';
import zlib from 'zlib';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const args = process.argv.slice(2);
const SWITCHES = new Set(['--all']);
const opt = (name, fallback) => {
  const i = args.indexOf('--' + name);
  if (i === -1) return fallback;
  return SWITCHES.has('--' + name) ? true : args[i + 1];
};
const positional = args.filter((a, i) => !a.startsWith('--') && !(i > 0 && args[i - 1].startsWith('--') && !SWITCHES.has(args[i - 1])));

if (positional.length < 2) {
  console.error('Aufruf: node tools/panel-png.mjs <antwort.json|-> <bild.png|ordner/> [--page n] [--all] [--now ms] [--logos ordner]');
  process.exit(2);
}

const PAL_OFF = '#151A1E';
const PANEL_BG = '#050607';
const BEZEL = '#1A1F23';
const OUTER = '#08090A';

/* ---------- Renderer der Webseite laden ---------- */
function loadRenderer(logoDir) {
  const window = { devicePixelRatio: 1 };
  if (logoDir) {
    window.fetch = (url) => {
      const code = decodeURIComponent(String(url).split('/').pop()).toUpperCase().replace(/[^A-Z0-9]/g, '');
      const file = path.join(logoDir, code + '.rgb');
      if (!fs.existsSync(file)) return Promise.resolve({ ok: false });
      const buf = fs.readFileSync(file);
      return Promise.resolve({ ok: true, arrayBuffer: () => Promise.resolve(buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.length)) });
    };
  }
  const context = vm.createContext({ window, Intl, Date, Math, String, Number, Array, Uint8Array, Promise, console, encodeURIComponent, parseInt });
  context.fetch = window.fetch;
  for (const file of ['pixelfont.js', 'ops.js', 'anim.js']) {
    vm.runInContext(fs.readFileSync(path.join(root, 'web/assets/js/lib', file), 'utf8'), context, { filename: file });
  }
  return window;
}

/* ---------- Farben und Zeichnen ---------- */
function rgbOf(c) {
  const s = String(c).trim();
  let m = s.match(/^#([0-9a-f]{6})$/i);
  if (m) return [parseInt(m[1].slice(0, 2), 16), parseInt(m[1].slice(2, 4), 16), parseInt(m[1].slice(4, 6), 16)];
  m = s.match(/^#([0-9a-f]{3})$/i);
  if (m) return [...m[1]].map((h) => parseInt(h + h, 16));
  m = s.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
  if (m) return [Number(m[1]), Number(m[2]), Number(m[3])];
  return [255, 0, 255];
}

class Image {
  constructor(w, h, colour) {
    this.w = w;
    this.h = h;
    this.px = new Float32Array(w * h * 3);
    this.fill(0, 0, w, h, rgbOf(colour), 1);
  }

  fill(x, y, w, h, rgb, alpha) {
    const x0 = Math.max(0, x), y0 = Math.max(0, y);
    const x1 = Math.min(this.w, x + w), y1 = Math.min(this.h, y + h);
    for (let yy = y0; yy < y1; yy++) {
      for (let xx = x0; xx < x1; xx++) {
        const i = (yy * this.w + xx) * 3;
        for (let k = 0; k < 3; k++) this.px[i + k] = this.px[i + k] * (1 - alpha) + rgb[k] * alpha;
      }
    }
  }
}

/* Wie PX.paint(): unbeleuchtete LEDs, Schein mit 22 Prozent je LED, dann die LEDs. */
function paint(grid, o) {
  const step = o.px + o.gap;
  const pw = grid.w * step - o.gap;
  const ph = grid.h * step - o.gap;
  const full = o.margin * 2 + o.bezel * 2;
  const img = new Image(pw + full, ph + full, OUTER);
  const ox = o.margin + o.bezel;
  const oy = o.margin + o.bezel;
  if (o.bezel > 0) img.fill(o.margin, o.margin, pw + o.bezel * 2, ph + o.bezel * 2, rgbOf(BEZEL), 1);
  img.fill(ox, oy, pw, ph, rgbOf(PANEL_BG), 1);
  const off = rgbOf(PAL_OFF);
  for (let y = 0; y < grid.h; y++) for (let x = 0; x < grid.w; x++) img.fill(ox + x * step, oy + y * step, o.px, o.px, off, 1);

  // Der Schein bleibt auf der Panelflaeche, wie auf dem Canvas.
  if (o.glow > 0) {
    for (let y = 0; y < grid.h; y++) {
      for (let x = 0; x < grid.w; x++) {
        const c = grid.data[y * grid.w + x];
        if (!c) continue;
        const gx = Math.max(ox, ox + x * step - step);
        const gy = Math.max(oy, oy + y * step - step);
        const gx1 = Math.min(ox + pw, ox + x * step - step + o.px + step * 2);
        const gy1 = Math.min(oy + ph, oy + y * step - step + o.px + step * 2);
        img.fill(gx, gy, gx1 - gx, gy1 - gy, rgbOf(c), 0.22 * o.glow);
      }
    }
  }
  for (let y = 0; y < grid.h; y++) {
    for (let x = 0; x < grid.w; x++) {
      const c = grid.data[y * grid.w + x];
      if (c) img.fill(ox + x * step, oy + y * step, o.px, o.px, rgbOf(c), 1);
    }
  }
  return img;
}

/* ---------- PNG schreiben, ohne Paket ---------- */
const CRC = new Int32Array(256).map((_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  return c;
});

function crc32(buf) {
  let c = -1;
  for (let i = 0; i < buf.length; i++) c = CRC[(c ^ buf[i]) & 255] ^ (c >>> 8);
  return (c ^ -1) >>> 0;
}

function chunk(type, data) {
  const head = Buffer.alloc(4);
  head.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const tail = Buffer.alloc(4);
  tail.writeUInt32BE(crc32(body));
  return Buffer.concat([head, body, tail]);
}

/* Filter je Zeile nach kleinster Summe, das LED-Raster wiederholt sich und packt dann gut. */
function encodePng(img, scale) {
  const w = img.w * scale;
  const h = img.h * scale;
  const rows = Buffer.alloc((w * 3 + 1) * h);
  let prev = Buffer.alloc(w * 3);
  const line = Buffer.alloc(w * 3);
  const cand = [0, 1, 2, 3, 4].map(() => Buffer.alloc(w * 3));
  for (let y = 0; y < h; y++) {
    const sy = Math.floor(y / scale);
    for (let x = 0; x < w; x++) {
      const i = (sy * img.w + Math.floor(x / scale)) * 3;
      for (let k = 0; k < 3; k++) line[x * 3 + k] = Math.max(0, Math.min(255, Math.round(img.px[i + k])));
    }
    let best = 0;
    let bestSum = Infinity;
    for (let f = 0; f < 5; f++) {
      const out = cand[f];
      let sum = 0;
      for (let i = 0; i < line.length; i++) {
        const a = i >= 3 ? line[i - 3] : 0;
        const b = prev[i];
        const c = i >= 3 ? prev[i - 3] : 0;
        let p = 0;
        if (f === 1) p = a;
        else if (f === 2) p = b;
        else if (f === 3) p = (a + b) >> 1;
        else if (f === 4) {
          const pa = Math.abs(b - c), pb = Math.abs(a - c), pc = Math.abs(a + b - 2 * c);
          p = pa <= pb && pa <= pc ? a : pb <= pc ? b : c;
        }
        const v = (line[i] - p) & 255;
        out[i] = v;
        sum += v < 128 ? v : 256 - v;
      }
      if (sum < bestSum) { bestSum = sum; best = f; }
    }
    const at = y * (w * 3 + 1);
    rows[at] = best;
    cand[best].copy(rows, at + 1);
    prev = Buffer.from(line);
  }
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(w, 0);
  ihdr.writeUInt32BE(h, 4);
  ihdr[8] = 8;
  ihdr[9] = 2;
  return Buffer.concat([
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
    chunk('IHDR', ihdr),
    chunk('IDAT', zlib.deflateSync(rows, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

/* ---------- Ablauf ---------- */
async function main() {
  const [input, output] = positional;
  const text = input === '-' ? fs.readFileSync(0, 'utf8') : fs.readFileSync(input, 'utf8');
  const json = JSON.parse(text);
  const frame = json.frame || json;
  const pages = Array.isArray(frame.pages) ? frame.pages : [frame];
  const logoDir = opt('logos', '') ? path.resolve(process.cwd(), String(opt('logos', ''))) : '';
  const window = loadRenderer(logoDir);
  const o = {
    px: Number(opt('px', 7)),
    gap: Number(opt('gap', 1)),
    glow: Number(opt('glow', 0.6)),
    bezel: Number(opt('bezel', 9)),
    margin: Number(opt('margin', 0)),
  };
  const scale = Math.max(1, Math.round(Number(opt('scale', 1))));
  const now = Number(opt('now', 0)) || Number(frame.now) || Date.now();
  const ctx = {
    now,
    iana: String(opt('iana', frame.iana || 'Europe/Luxembourg')),
    lang: String(opt('lang', frame.lang || 'en')),
    logoUrl: logoDir ? '/logo/{code}' : '',
    flash: false,
    t: Number(opt('t', 1.5)),
    reduce: false,
  };

  const draw = async (page) => {
    let g = window.PX.grid(128, 64);
    window.TWOps.render(g, page, ctx);
    if (logoDir && (page.ops || []).some((op) => op.t === 'logo')) {
      // Logos laden asynchron wie im Browser, beim zweiten Zeichnen sind sie da.
      await new Promise((r) => setTimeout(r, 20));
      g = window.PX.grid(128, 64);
      window.TWOps.render(g, page, ctx);
    }
    return encodePng(paint(g, o), scale);
  };

  if (opt('all', false)) {
    fs.mkdirSync(output, { recursive: true });
    for (let i = 0; i < pages.length; i++) {
      const file = path.join(output, 'page-' + i + '.png');
      fs.writeFileSync(file, await draw(pages[i]));
      console.log(file, pages[i].id || '');
    }
    return;
  }
  const idx = opt('page', null);
  const page = idx !== null ? pages[Number(idx)] : window.TWOps.pageAt({ pages }, now);
  if (!page) {
    console.error('Keine Seite gefunden.');
    process.exit(1);
  }
  fs.mkdirSync(path.dirname(path.resolve(output)), { recursive: true });
  fs.writeFileSync(output, await draw(page));
  console.log(output, page.id || '');
}

main().catch((e) => {
  console.error(e.stack || String(e));
  process.exit(1);
});
