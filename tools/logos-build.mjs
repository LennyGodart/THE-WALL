// Airline-Logos fuer das Panel: laedt die Sammlung github.com/Jxck-S/airline-logos
// (FlightAware-Symbole, Luecken aus den RadarBox-Kacheln), rechnet jedes Logo auf
// 32 x 34 LED-Pixel in Vollfarbe herunter und schreibt je Airline eine Datei
// <ICAO>.rgb mit 3 264 Byte (RGB888, zeilenweise). Schwarz heisst: LED aus.
//
// Die Logos sind eingetragene Marken. Sie gehoeren nur auf den Server
// (.htdata/logos), nie ins Repo. Ausgabe und Zwischenspeicher sind in .gitignore.
//
// Drei weitere Quellen, alle nur lokal in tools/.logo-cache/ (gitignored):
//   nachgeladen/  Logos, die FlightAware erst nach dem Stand der Sammlung bekam (ICAO.png).
//                 Sie laufen durch dieselbe Pruefung wie die Sammlung und gehen ihr vor.
//   eigen/        von Hand freigestellte echte Logos (ICAO.png), unveraendert uebernommen.
//                 Sie gehen allem vor. Herkunft je Datei in eigen/quellen.json. Betreiber
//                 ohne ICAO-Kuerzel bekommen zwei Buchstaben (PL: Polizei Luxemburg), die
//                 kann keine Airline haben; zugeordnet in FLIGHT_CALLSIGN_OWNERS (flight.php).
//   ALIASES       unten: eine Marke, die unter einem zweiten Kuerzel fliegt.
//
// Aufruf aus dem Hauptverzeichnis, Node 18 oder neuer, keine Pakete:
//   node tools/logos-build.mjs                       alles, nach web/.htdata/logos
//   node tools/logos-build.mjs --only LGL,DLH,RYR    nur diese Kuerzel
//   node tools/logos-build.mjs --extra eigene/       eigene PNGs statt tools/.logo-cache/eigen/
//   node tools/logos-build.mjs --more ordner/        nachgeladene statt tools/.logo-cache/nachgeladen/
//   node tools/logos-build.mjs --ref main            neuesten Stand der Sammlung statt des geprueften
//   node tools/logos-build.mjs --preview             zusaetzlich tools/.logo-cache/preview.html
// Danach web/.htdata/logos/ nach .htdata/logos/ auf den Server kopieren, siehe docs/server/betrieb.md.

import fs from 'fs';
import path from 'path';
import zlib from 'zlib';

const REPO = 'Jxck-S/airline-logos';
const TESTED_REF = 'e0b218991caef4832e0db911136173e8eead942a'; // 22. Juli 2026, am 13. September 2026 geprueft
const SOURCES = [['flightaware', 'flightaware_logos'], ['radarbox', 'radarbox_logos']];
const W = 32;
const H = 34;

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const args = process.argv.slice(2);
const opt = (name, fallback) => {
  const i = args.indexOf('--' + name);
  return i === -1 ? fallback : (args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : true);
};
const ref = String(opt('ref', TESTED_REF));
const out = path.resolve(root, String(opt('out', 'web/.htdata/logos')));
const cacheDir = path.resolve(root, 'tools/.logo-cache', ref.slice(0, 12));
const only = opt('only', '') ? new Set(String(opt('only', '')).toUpperCase().split(',').filter(Boolean)) : null;
const extra = opt('extra', '') ? path.resolve(process.cwd(), String(opt('extra', ''))) : path.resolve(root, 'tools/.logo-cache/eigen');
const more = opt('more', '') ? path.resolve(process.cwd(), String(opt('more', ''))) : path.resolve(root, 'tools/.logo-cache/nachgeladen');

// Eine Marke unter einem zweiten Kuerzel: das Logo des zweiten, solange das erste keins hat.
// Die JUP-Fluege zwischen Frankfurt und Birmingham fliegt Zimex (IMX) mit eigenen ATR fuer
// Jump Air. Fly 7 Finland (FSF) heisst seit Mai 2026 Jetfly Management und gehoert zu Jetfly
// (JFA). Circadian Aviation (CYC) gehoert wie Sterling Aviation (NSH) zu Airshare.
const ALIASES = { JUP: 'IMX', FSF: 'JFA', CYC: 'NSH' };

// Kuerzel, die nie ein Logo bekommen: Rufzeichen fuer anonyme Privatfluege aus dem Programm
// PIA der FAA. Wer fliegt, ist absichtlich verborgen; das Logo des Anbieters (ForeFlight,
// FlightAware, ARINC) saehe aus, als fliege der Anbieter selbst.
const NEVER = new Set(['FFL', 'FWR', 'XAA']);
const wantPreview = Boolean(opt('preview', false));

/* ---------- PNG lesen, ohne Paket ---------- */

function decodePng(buf) {
  const sig = [137, 80, 78, 71, 13, 10, 26, 10];
  if (buf.length < 33 || sig.some((v, i) => buf[i] !== v)) throw new Error('keine PNG-Datei');
  let pos = 8, width = 0, height = 0, depth = 0, ctype = 0, interlace = 0, palette = null, trns = null;
  const idat = [];
  while (pos + 8 <= buf.length) {
    const len = buf.readUInt32BE(pos);
    const type = buf.toString('latin1', pos + 4, pos + 8);
    const data = buf.subarray(pos + 8, pos + 8 + len);
    pos += 12 + len;
    if (type === 'IHDR') { width = data.readUInt32BE(0); height = data.readUInt32BE(4); depth = data[8]; ctype = data[9]; interlace = data[12]; }
    else if (type === 'PLTE') palette = data;
    else if (type === 'tRNS') trns = data;
    else if (type === 'IDAT') idat.push(data);
    else if (type === 'IEND') break;
  }
  const channels = { 0: 1, 2: 3, 3: 1, 4: 2, 6: 4 }[ctype];
  if (!channels || !width || !height || width > 4096 || height > 4096) throw new Error('Format nicht unterstuetzt');
  const raw = zlib.inflateSync(Buffer.concat(idat));
  const bitsPP = depth * channels;
  const bpp = Math.max(1, Math.ceil(bitsPP / 8));
  const max = (1 << Math.min(depth, 8)) - 1;
  const rgba = new Uint8Array(width * height * 4);

  const sample = (line, idx) => {
    if (depth === 8) return line[idx];
    if (depth === 16) return line[idx * 2];
    const bit = idx * depth;
    return (line[bit >> 3] >> (8 - depth - (bit & 7))) & max;
  };
  const scale = (v) => (depth < 8 ? Math.round(v * 255 / max) : v);

  const passes = interlace
    ? [[0, 0, 8, 8], [4, 0, 8, 8], [0, 4, 4, 8], [2, 0, 4, 4], [0, 2, 2, 4], [1, 0, 2, 2], [0, 1, 1, 2]]
    : [[0, 0, 1, 1]];
  let offset = 0;
  for (const [x0, y0, dx, dy] of passes) {
    const pw = Math.ceil((width - x0) / dx);
    const ph = Math.ceil((height - y0) / dy);
    if (pw <= 0 || ph <= 0) continue;
    const stride = Math.ceil((pw * bitsPP) / 8);
    let prev = new Uint8Array(stride);
    for (let y = 0; y < ph; y++) {
      const filter = raw[offset];
      const line = new Uint8Array(raw.subarray(offset + 1, offset + 1 + stride));
      offset += 1 + stride;
      for (let i = 0; i < stride; i++) {
        const a = i >= bpp ? line[i - bpp] : 0, b = prev[i], c = i >= bpp ? prev[i - bpp] : 0;
        let v = line[i];
        if (filter === 1) v += a;
        else if (filter === 2) v += b;
        else if (filter === 3) v += (a + b) >> 1;
        else if (filter === 4) {
          const p = a + b - c, pa = Math.abs(p - a), pb = Math.abs(p - b), pc = Math.abs(p - c);
          v += pa <= pb && pa <= pc ? a : pb <= pc ? b : c;
        } else if (filter !== 0) throw new Error('Filter ' + filter);
        line[i] = v & 255;
      }
      for (let x = 0; x < pw; x++) {
        let r, g, bl, al = 255;
        if (ctype === 0 || ctype === 4) {
          const v = sample(line, x * channels);
          r = g = bl = scale(v);
          if (ctype === 4) al = sample(line, x * 2 + 1);
          else if (trns && trns.length >= 2 && (depth === 16 ? trns[0] : trns.readUInt16BE(0)) === v) al = 0;
        } else if (ctype === 2 || ctype === 6) {
          r = sample(line, x * channels); g = sample(line, x * channels + 1); bl = sample(line, x * channels + 2);
          if (ctype === 6) al = sample(line, x * 4 + 3);
          else if (trns && trns.length >= 6 && trns.readUInt16BE(0) >> (depth === 16 ? 8 : 0) === r
            && trns.readUInt16BE(2) >> (depth === 16 ? 8 : 0) === g && trns.readUInt16BE(4) >> (depth === 16 ? 8 : 0) === bl) al = 0;
        } else {
          const idx = sample(line, x);
          if (!palette || idx * 3 + 2 >= palette.length) { r = g = bl = 0; al = 0; }
          else { r = palette[idx * 3]; g = palette[idx * 3 + 1]; bl = palette[idx * 3 + 2]; al = trns && idx < trns.length ? trns[idx] : 255; }
        }
        const o = ((y0 + y * dy) * width + (x0 + x * dx)) * 4;
        rgba[o] = r; rgba[o + 1] = g; rgba[o + 2] = bl; rgba[o + 3] = al;
      }
      prev = line;
    }
  }
  return { width, height, data: rgba };
}

/* ---------- auf 32 x 34 LED-Pixel ---------- */

/** Dunkle Farben leuchten auf LED kaum, Schwarz gar nicht: aufhellen. */
function ledColour(r, g, b) {
  const mx = Math.max(r, g, b), mn = Math.min(r, g, b);
  const sat = mx > 0 ? (mx - mn) / mx : 0;
  if (mx < 70 && sat < 0.35) return [200, 204, 208];
  if (mx < 150) {
    const f = 150 / Math.max(mx, 1);
    return [Math.min(255, r * f), Math.min(255, g * f), Math.min(255, b * f)];
  }
  return [r, g, b];
}

/** Nur ein deckender weisser Rand ist Hintergrund. Farbige Kacheln bleiben, wie sie sind. */
function hasWhiteBackground(img) {
  const { width: sw, height: sh, data } = img;
  return [0, sw - 1, (sh - 1) * sw, sh * sw - 1].every((p) => {
    const i = p * 4;
    return data[i + 3] > 200 && Math.min(data[i], data[i + 1], data[i + 2]) > 235;
  });
}

/* ---------- Schrift neben dem Symbol erkennen ---------- */

// Kuerzel, bei denen die Schrift zum Logo gehoert und bleiben soll.
const KEEP_TEXT = new Set(['UAE']);

function foregroundMask(img) {
  const { width: w, height: h, data } = img;
  const white = hasWhiteBackground(img);
  const mask = new Uint8Array(w * h);
  for (let p = 0; p < w * h; p++) {
    const i = p * 4;
    let al = data[i + 3] / 255;
    if (white) al *= Math.min(1, (255 - Math.min(data[i], data[i + 1], data[i + 2])) / 60);
    mask[p] = al > 0.35 ? 1 : 0;
  }
  return mask;
}

/** Belegte Zeilen (axis y) oder Spalten (axis x) im Rechteck, zu Baendern zusammengefasst. */
function bandsAlong(mask, w, box, axis) {
  const [x0, y0, x1, y1] = box;
  const len = axis === 'y' ? y1 - y0 : x1 - x0;
  const occupied = new Array(len).fill(false);
  for (let k = 0; k < len; k++) {
    let n = 0;
    for (let j = axis === 'y' ? x0 : y0; j < (axis === 'y' ? x1 : y1); j++) {
      const x = axis === 'y' ? j : x0 + k;
      const y = axis === 'y' ? y0 + k : j;
      n += mask[y * w + x];
    }
    occupied[k] = n >= 2;
  }
  const gap = Math.max(2, Math.round(len * 0.02));
  const bands = [];
  for (let k = 0; k < len; k++) {
    if (!occupied[k]) continue;
    const last = bands[bands.length - 1];
    if (last && k - last[1] <= gap) last[1] = k + 1;
    else bands.push([k, k + 1]);
  }
  return bands.map(([a, b]) => (axis === 'y' ? [x0, y0 + a, x1, y0 + b] : [x0 + a, y0, x0 + b, y1]));
}

/** Rechteck eng um die belegten Pixel. */
function tight(mask, w, box) {
  let [minx, miny, maxx, maxy] = [Infinity, Infinity, -1, -1];
  for (let y = box[1]; y < box[3]; y++) for (let x = box[0]; x < box[2]; x++) {
    if (mask[y * w + x]) { minx = Math.min(minx, x); miny = Math.min(miny, y); maxx = Math.max(maxx, x); maxy = Math.max(maxy, y); }
  }
  return maxx < 0 ? null : [minx, miny, maxx + 1, maxy + 1];
}

/** Zusammenhaengende Flecken ab 3 Pixeln im Rechteck, je als Rechteck [x0, y0, x1, y1]. */
function blobs(mask, w, box) {
  const seen = new Uint8Array(mask.length);
  const list = [];
  for (let y = box[1]; y < box[3]; y++) for (let x = box[0]; x < box[2]; x++) {
    const p = y * w + x;
    if (!mask[p] || seen[p]) continue;
    let size = 0, x0 = x, y0 = y, x1 = x, y1 = y;
    const stack = [p];
    seen[p] = 1;
    while (stack.length) {
      const q = stack.pop();
      size++;
      const qx = q % w, qy = (q - qx) / w;
      x0 = Math.min(x0, qx); x1 = Math.max(x1, qx); y0 = Math.min(y0, qy); y1 = Math.max(y1, qy);
      for (const [nx, ny] of [[qx + 1, qy], [qx - 1, qy], [qx, qy + 1], [qx, qy - 1]]) {
        if (nx < box[0] || ny < box[1] || nx >= box[2] || ny >= box[3]) continue;
        const r = ny * w + nx;
        if (mask[r] && !seen[r]) { seen[r] = 1; stack.push(r); }
      }
    }
    if (size >= 3) list.push([x0, y0, x1 + 1, y1 + 1]);
  }
  return list;
}

function blobCount(mask, w, box) {
  return blobs(mask, w, box).length;
}

/**
 * Sieht ein Band aus wie eine Schriftzeile? Mindestens vier Buchstaben-Flecken,
 * dicht nebeneinander, die Zeile deutlich breiter als hoch und hoechstens halb so
 * hoch wie der Rest des Logos. Eine Reihe weit auseinander stehender Punkte (wie
 * bei Brussels Airlines) ist keine Schrift.
 */
function looksLikeText(mask, w, band, rest) {
  const t = tight(mask, w, band);
  if (!t) return false;
  const bw = t[2] - t[0], bh = t[3] - t[1];
  if (bw / Math.max(1, bh) < 2 || bh > (rest[3] - rest[1]) * 0.5) return false;
  const letters = blobs(mask, w, t).sort((m, n) => m[0] - n[0]);
  if (letters.length < 4) return false;
  const widths = letters.map((l) => l[2] - l[0]).sort((m, n) => m - n);
  const gaps = [];
  for (let i = 1; i < letters.length; i++) gaps.push(Math.max(0, letters[i][0] - letters[i - 1][2]));
  gaps.sort((m, n) => m - n);
  const median = (arr) => arr[Math.floor(arr.length / 2)];
  return median(gaps) <= Math.max(2, median(widths) * 0.6);
}

/**
 * Besteht das ganze Logo nur aus einem Schriftzug mit vielen Buchstaben? Liefert
 * 0 (kein Schriftzug), 1 (Schriftzug, auf 32 Pixeln Breite noch mindestens 7 Pixel
 * hoch und damit lesbar, wie flybe oder HiFly) oder 2 (zu flach, nicht lesbar).
 * Drei Buchstaben wie SAS oder DHL zaehlen nicht als Schriftzug.
 */
function wordmarkLevel(img) {
  const mask = foregroundMask(img);
  const box = tight(mask, img.width, [0, 0, img.width, img.height]);
  if (!box) return 0;
  const aspect = (box[2] - box[0]) / Math.max(1, box[3] - box[1]);
  if (aspect < 2 || bandsAlong(mask, img.width, box, 'y').length > 2) return 0;
  if (blobCount(mask, img.width, box) < 5) return 0;
  return aspect > 4.5 ? 2 : 1;
}

/** Schneidet Schriftzeilen unter oder rechts neben dem Symbol ab. Liefert das Rechteck oder null. */
function symbolBox(img) {
  const { width: w, height: h } = img;
  const mask = foregroundMask(img);
  let box = tight(mask, w, [0, 0, w, h]);
  if (!box) return null;
  let cut = false;
  for (const axis of ['y', 'x']) {
    for (let round = 0; round < 3; round++) {
      const bands = bandsAlong(mask, w, box, axis);
      if (bands.length < 2) break;
      const lastBand = bands[bands.length - 1];
      const rest = tight(mask, w, axis === 'y' ? [box[0], box[1], box[2], lastBand[1]] : [box[0], box[1], lastBand[0], box[3]]);
      if (!rest) break;
      if (!looksLikeText(mask, w, lastBand, rest)) break;
      box = rest;
      cut = true;
    }
  }
  return cut ? box : null;
}

function crop(img, box) {
  const pad = 1;
  const x0 = Math.max(0, box[0] - pad), y0 = Math.max(0, box[1] - pad);
  const x1 = Math.min(img.width, box[2] + pad), y1 = Math.min(img.height, box[3] + pad);
  const w = x1 - x0, h = y1 - y0;
  const data = new Uint8Array(w * h * 4);
  for (let y = 0; y < h; y++) {
    const from = ((y0 + y) * img.width + x0) * 4;
    data.set(img.data.subarray(from, from + w * 4), y * w * 4);
  }
  return { width: w, height: h, data, whiteBg: hasWhiteBackground(img) };
}

function toPanel(img) {
  const { width: sw, height: sh, data } = img;
  const at = (x, y) => (y * sw + x) * 4;
  const whiteBg = img.whiteBg !== undefined ? img.whiteBg : hasWhiteBackground(img);
  const scale = Math.min(W / sw, (H - 2) / sh);
  const ox = (W - sw * scale) / 2;
  const oy = (H - sh * scale) / 2;
  const rgb = Buffer.alloc(W * H * 3);
  let lit = 0, top = H, bottom = -1;
  for (let y = 0; y < H; y++) {
    for (let x = 0; x < W; x++) {
      const sx0 = (x - ox) / scale, sx1 = (x + 1 - ox) / scale;
      const sy0 = (y - oy) / scale, sy1 = (y + 1 - oy) / scale;
      let r = 0, g = 0, b = 0, a = 0;
      for (let yy = Math.max(0, Math.floor(sy0)); yy < Math.min(sh, Math.ceil(sy1)); yy++) {
        const wy = Math.min(sy1, yy + 1) - Math.max(sy0, yy);
        if (wy <= 0) continue;
        for (let xx = Math.max(0, Math.floor(sx0)); xx < Math.min(sw, Math.ceil(sx1)); xx++) {
          const wx = Math.min(sx1, xx + 1) - Math.max(sx0, xx);
          if (wx <= 0) continue;
          const i = at(xx, yy);
          let al = data[i + 3] / 255;
          if (whiteBg) al *= Math.min(1, (255 - Math.min(data[i], data[i + 1], data[i + 2])) / 60);
          const w = wx * wy * al;
          r += data[i] * w; g += data[i + 1] * w; b += data[i + 2] * w; a += w;
        }
      }
      const cover = a / ((sx1 - sx0) * (sy1 - sy0));
      if (a <= 0 || cover < 0.2) continue;
      const [cr, cg, cb] = ledColour(r / a, g / a, b / a);
      const k = Math.min(1, 0.35 + cover);
      const o = (y * W + x) * 3;
      rgb[o] = Math.round(cr * k); rgb[o + 1] = Math.round(cg * k); rgb[o + 2] = Math.round(cb * k);
      lit++;
      top = Math.min(top, y);
      bottom = Math.max(bottom, y);
    }
  }
  return { rgb, lit, rows: bottom < 0 ? 0 : bottom - top + 1 };
}

/* ---------- Sammlung laden ---------- */

async function get(url, binary) {
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const r = await fetch(url, { headers: { 'User-Agent': 'TheWall-logos-build (+https://thewall.godart.lu)' } });
      if (r.status === 404) return null;
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return binary ? Buffer.from(await r.arrayBuffer()) : await r.json();
    } catch (e) {
      if (attempt === 3) throw e;
      await new Promise((res) => setTimeout(res, 800 * attempt));
    }
  }
  return null;
}

async function main() {
  fs.mkdirSync(cacheDir, { recursive: true });
  const treeFile = path.join(cacheDir, 'tree.json');
  let tree;
  if (fs.existsSync(treeFile)) tree = JSON.parse(fs.readFileSync(treeFile, 'utf8'));
  else {
    tree = await get(`https://api.github.com/repos/${REPO}/git/trees/${ref}?recursive=1`, false);
    if (!tree || !tree.tree) throw new Error('Verzeichnis der Sammlung nicht lesbar');
    fs.writeFileSync(treeFile, JSON.stringify(tree));
  }

  const plan = new Map(); // Kuerzel -> Liste moeglicher Quellen in Reihenfolge
  const add = (code, entry) => { if (!plan.has(code)) plan.set(code, []); plan.get(code).push(entry); };
  if (extra && fs.existsSync(extra)) {
    for (const f of fs.readdirSync(extra)) {
      const m = f.match(/^([A-Za-z0-9]{2,4})\.png$/);
      if (m) add(m[1].toUpperCase(), { source: 'eigen', file: path.join(extra, f) });
    }
  }
  if (more && fs.existsSync(more)) {
    for (const f of fs.readdirSync(more)) {
      const m = f.match(/^([A-Za-z0-9]{3,4})\.png$/);
      if (m) add(m[1].toUpperCase(), { source: 'nachgeladen', file: path.join(more, f) });
    }
  }
  for (const [name, folder] of SOURCES) {
    for (const e of tree.tree) {
      const m = e.type === 'blob' && e.path.match(new RegExp('^' + folder + '/([A-Z0-9]{3,4})\\.png$'));
      if (m) add(m[1], { source: name, path: e.path });
    }
  }
  const codes = [...plan.keys()].filter((c) => (!only || only.has(c)) && !NEVER.has(c)).sort();
  console.log(`${codes.length} Kuerzel, Quelle ${REPO}@${ref.slice(0, 12)}`);

  fs.mkdirSync(out, { recursive: true });
  const index = { repo: REPO, ref, built: new Date().toISOString(), size: [W, H], format: 'rgb888', logos: {}, cropped: [], wordmarks: [], unreadable: [] };
  const beforeCrop = new Map();
  const failed = [];
  let done = 0;
  const queue = codes.slice();
  const worker = async () => {
    while (queue.length) {
      const code = queue.shift();
      let result = null;
      const candidates = [];
      for (const entry of plan.get(code)) {
        try {
          let buf;
          if (entry.file) buf = fs.readFileSync(entry.file);
          else {
            const local = path.join(cacheDir, entry.path);
            if (fs.existsSync(local)) buf = fs.readFileSync(local);
            else {
              buf = await get(`https://raw.githubusercontent.com/${REPO}/${ref}/${entry.path}`, true);
              if (!buf) continue;
              fs.mkdirSync(path.dirname(local), { recursive: true });
              fs.writeFileSync(local, buf);
            }
          }
          const img = decodePng(buf);
          let panel = toPanel(img);
          let before = null;
          let shape = img;
          if (entry.source !== 'eigen' && !KEEP_TEXT.has(code)) {
            const box = symbolBox(img);
            if (box && (box[2] - box[0]) * (box[3] - box[1]) >= img.width * img.height * 0.06) {
              before = panel;
              shape = crop(img, box);
              panel = toPanel(shape);
            }
          }
          if (panel.lit < 12) continue; // leer oder kaum sichtbar: naechste Quelle
          let wordmark = entry.source !== 'eigen' && !KEEP_TEXT.has(code) ? wordmarkLevel(shape) : 0;
          // Ein Schriftzug, der auf dem Panel keine 10 Zeilen hoch wird, ist nicht lesbar.
          if (wordmark === 1 && panel.rows < 10) wordmark = 2;
          candidates.push({ panel, before, source: entry.source, wordmark });
          if (!wordmark) break; // ein Symbol gefunden, fertig
          // nur Schriftzug: die naechste Quelle hat vielleicht ein Symbol
        } catch (e) {
          // kaputte Datei: naechste Quelle
        }
      }
      // Zuerst ein Symbol, sonst ein lesbarer Schriftzug. Ein unlesbarer bekommt keine
      // Datei: Geraet und Vorschau zeigen dann den Farbblock mit dem Kuerzel.
      result = candidates.find((c) => c.wordmark === 0) || candidates.find((c) => c.wordmark === 1) || null;
      if (!result && candidates.length) index.unreadable.push(code);
      if (result) {
        if (result.wordmark) index.wordmarks.push(code);
        fs.writeFileSync(path.join(out, code + '.rgb'), result.panel.rgb);
        index.logos[code] = result.source;
        if (result.before) {
          index.cropped.push(code);
          beforeCrop.set(code, result.before.rgb);
        }
      } else failed.push(code);
      done++;
      if (done % 200 === 0) console.log(`  ${done} von ${codes.length}`);
    }
  };
  await Promise.all(Array.from({ length: 8 }, worker));

  index.aliases = {};
  for (const [code, target] of Object.entries(ALIASES)) {
    if ((only && !only.has(code)) || index.logos[code] || !fs.existsSync(path.join(out, target + '.rgb'))) continue;
    fs.copyFileSync(path.join(out, target + '.rgb'), path.join(out, code + '.rgb'));
    index.logos[code] = 'wie ' + target;
    index.aliases[code] = target;
  }

  // Dateien alter Laeufe, die es in der Sammlung nicht mehr gibt, entfernen (nur bei vollem Lauf)
  if (!only) {
    for (const f of fs.readdirSync(out)) {
      const m = f.match(/^([A-Z0-9]{2,4})\.rgb$/);
      if (m && !index.logos[m[1]]) fs.unlinkSync(path.join(out, f));
    }
  } else {
    const oldIndex = path.join(out, 'index.json');
    if (fs.existsSync(oldIndex)) {
      const prev = JSON.parse(fs.readFileSync(oldIndex, 'utf8'));
      index.logos = { ...(prev.logos || {}), ...index.logos };
      index.aliases = { ...(prev.aliases || {}), ...index.aliases };
    }
  }
  index.count = Object.keys(index.logos).length;
  fs.writeFileSync(path.join(out, 'index.json'), JSON.stringify(index, null, 1));
  const bySource = {};
  for (const s of Object.values(index.logos)) bySource[s] = (bySource[s] || 0) + 1;
  const broken = failed.filter((c) => !index.unreadable.includes(c));
  console.log(`fertig: ${index.count} Logos in ${path.relative(root, out)}`, bySource,
    `beschnitten ${index.cropped.length}, lesbarer Schriftzug ${index.wordmarks.length}, unlesbarer Schriftzug ${index.unreadable.length}`,
    broken.length ? `kaputt: ${broken.join(' ')}` : '');

  if (wantPreview) {
    // Drei Abschnitte: beschnittene Logos vorher und nachher, eine gleichmaessig
    // verteilte Stichprobe, und die haeufigen Airlines ueber Luxemburg.
    const b64 = (buf) => buf.toString('base64');
    const read = (c) => b64(fs.readFileSync(path.join(out, c + '.rgb')));
    const all = Object.keys(index.logos).sort();
    const step = Math.max(1, Math.floor(all.length / 120));
    const sample = all.filter((_, i) => i % step === 0);
    const common = ['LGL', 'CLX', 'DLH', 'RYR', 'EZY', 'BAW', 'AFR', 'KLM', 'THY', 'SWR', 'EWG', 'WZZ', 'UAE', 'QTR', 'TAP', 'BEL', 'CFG', 'FDX', 'DHK', 'BCS', 'UPS', 'IBE', 'VLG', 'AUA', 'SAS', 'LOT', 'AEE', 'FIN', 'ICE', 'UAL', 'DAL', 'AAL', 'ETD', 'TVF', 'TRA', 'NJE', 'SIA', 'CPA', 'ACA', 'ELY']
      .filter((c) => index.logos[c]);
    const sections = [
      ['Beschnitten: vorher, nachher', index.cropped.map((c) => [c, index.logos[c], b64(beforeCrop.get(c)), read(c)])],
      ['Nur Schriftzug, aber lesbar', index.wordmarks.map((c) => [c, index.logos[c], null, read(c)])],
      ['Haeufig ueber Luxemburg', common.map((c) => [c, index.logos[c], null, read(c)])],
      ['Stichprobe, jedes ' + step + '. Logo von ' + all.length, sample.map((c) => [c, index.logos[c], null, read(c)])],
    ];
    const html = `<!doctype html><meta charset="utf-8"><title>Logos ${all.length}</title>
<style>body{background:#08090A;color:#8B949C;font:11px ui-monospace,monospace;margin:16px}
h2{font-size:12px;color:#FFAA00;letter-spacing:.1em;text-transform:uppercase;margin:22px 0 10px}
.g{display:grid;grid-template-columns:repeat(auto-fill,minmax(86px,1fr));gap:10px}
.g.pairs{grid-template-columns:repeat(auto-fill,minmax(170px,1fr))}
figure{margin:0;text-align:center} .row{display:flex;gap:6px;justify-content:center} canvas{display:block}</style>
<p>${all.length} Logos, ${REPO}@${ref.slice(0, 12)}, 32 x 34 LED-Pixel. f = FlightAware, r = RadarBox, n = nachgeladen, e = eigen, w = wie ein anderes Kuerzel.</p>
<div id="root"></div>
<script>const S=${JSON.stringify(sections)};
function draw(b){const cv=document.createElement('canvas');cv.width=80;cv.height=85;const x=cv.getContext('2d');
x.fillStyle='#050607';x.fillRect(0,0,80,85);const d=atob(b);
for(let i=0;i<32*34;i++){const r=d.charCodeAt(i*3),g=d.charCodeAt(i*3+1),bb=d.charCodeAt(i*3+2);
x.fillStyle=(r|g|bb)?'rgb('+r+','+g+','+bb+')':'#12171B';x.fillRect((i%32)*2.5,Math.floor(i/32)*2.5,2,2);}return cv;}
const root=document.getElementById('root');
for(const [title,items] of S){const h=document.createElement('h2');h.textContent=title+' ('+items.length+')';root.append(h);
const g=document.createElement('div');g.className='g'+(items.some(i=>i[2])?' pairs':'');root.append(g);
for(const [c,src,before,after] of items){const f=document.createElement('figure');const row=document.createElement('div');row.className='row';
if(before)row.append(draw(before));row.append(draw(after));const t=document.createElement('figcaption');t.textContent=c+' '+src[0];f.append(row,t);g.append(f);}}
</script>`;
    fs.writeFileSync(path.join(path.dirname(cacheDir), 'preview.html'), html);
    console.log('Vorschau: tools/.logo-cache/preview.html, beschnitten: ' + index.cropped.length);
  }
}

main().catch((e) => { console.error(e.message); process.exit(1); });
