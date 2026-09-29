// Rechnet die Geodaten fuer die nahe Kartenansicht der ganzen Welt. Damit sieht man ein
// Flugzeug in Tokio oder New York genauso landen wie in Luxemburg: Bahnen des Flugplatzes,
// Wasser, Stadtgebiet, Grenzen, Fluesse, Ortsnamen.
//
// Geschrieben werden vier Dateien nach web/.htapp/data/:
//
//   geo-airports.json   6 100 Flugplaetze mit Kuerzel und Ort, fuer die Suche "welcher
//                       Platz ist das" und fuer die Beschriftung
//   geo-places.json     Ortsnamen der Welt (Natural Earth), fuer alle Zoomstufen
//   geo-detail.idx      Inhaltsverzeichnis der Kacheln: Kachel -> Versatz und Laenge
//   geo-detail.bin      die Kacheln selbst, je Kachel ein gzip-JSON
//
// Eine Kachel sind fuenf Grad. Der Server liest nur die zwei bis vier Kacheln, die der
// Ausschnitt beruehrt, und nie die ganze Welt: eine Datei mit allem waere 40 MB und
// jeder Abruf muesste sie auspacken.
//
// Flaechen (Land, Seen, Stadtgebiet) werden auf die Kachel zugeschnitten und bleiben
// geschlossene Ringe. Der Server fuellt daraus Wasser und Stadt mit einem Punktraster,
// und das ist der Unterschied zwischen "ein paar Striche" und "da ist die Bucht, da
// die Stadt, da der Flugplatz". Die Kanten, die beim Zuschneiden auf der Kachelgrenze
// entstehen, kennt der Server an ihren Koordinaten und zeichnet sie nicht.
//
// Jede der 2 592 Kacheln steht im Verzeichnis, auch die leeren: nur so weiss der Server,
// ob eine Gegend Wasser ist oder ob ihm die Daten fehlen.
//
// Die Rohdaten liegen nicht im Repo (84 MB). Holen:
//
//   mkdir -p tools/.geo-cache && cd tools/.geo-cache
//   curl -O https://davidmegginson.github.io/ourairports-data/airports.csv
//   curl -O https://davidmegginson.github.io/ourairports-data/runways.csv
//   B=https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson
//   for f in ne_10m_land ne_10m_admin_0_boundary_lines_land ne_10m_lakes \
//            ne_10m_rivers_lake_centerlines ne_10m_populated_places_simple \
//            ne_10m_urban_areas; do curl -O $B/$f.geojson; done
//
// Aufruf aus dem Hauptverzeichnis (der Speicher reicht sonst nicht):
//   node --max-old-space-size=4096 tools/geo-welt.mjs
//
// Lizenzen: OurAirports gemeinfrei, Natural Earth gemeinfrei. Beide stehen trotzdem in
// der Datenschutzerklaerung, weil die Karte sonst aus dem Nichts kaeme.
import fs from 'fs';
import path from 'path';
import zlib from 'zlib';
import { laenge, vereinfachen, rund, verketten, rein, kasten, flaeche, fenster } from './geo-lib.mjs';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const cache = path.join(root, 'tools', '.geo-cache');
const outDir = path.join(root, 'web', '.htapp', 'data');
fs.mkdirSync(outDir, { recursive: true });

const GRAD = 5;                    // Kantenlaenge einer Kachel in Grad
const roh = (f) => path.join(cache, f);
const da = (f) => fs.existsSync(roh(f));
for (const f of ['airports.csv', 'runways.csv', 'ne_10m_land.geojson']) {
  if (!da(f)) { console.error('Fehlt:', roh(f), '\nSiehe Kopf dieser Datei.'); process.exit(2); }
}

/* ---------- CSV ---------- */
function csv(text) {
  const rows = [];
  let f = '', row = [], q = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (q) {
      if (c === '"') { if (text[i + 1] === '"') { f += '"'; i++; } else q = false; } else f += c;
    } else if (c === '"') q = true;
    else if (c === ',') { row.push(f); f = ''; }
    else if (c === '\n') { row.push(f); f = ''; if (row.length > 1) rows.push(row); row = []; }
    else if (c !== '\r') f += c;
  }
  if (f !== '' || row.length) { row.push(f); if (row.length > 1) rows.push(row); }
  const head = rows.shift();
  return rows.map((r) => { const o = {}; head.forEach((h, i) => (o[h] = r[i])); return o; });
}

/* ---------- Kacheln ---------- */
const kacheln = new Map();
const schluessel = (la, lo) => Math.floor(la / GRAD) * GRAD + ',' + Math.floor(lo / GRAD) * GRAD;
function ablegen(k, schicht, wert) {
  let t = kacheln.get(k);
  if (!t) { t = {}; kacheln.set(k, t); }
  (t[schicht] ||= []).push(wert);
}

/**
 * Einen Linienzug auf Kacheln verteilen. Wechselt er die Kachel, bekommt das
 * ueberquerende Stueck beide: sonst hoert der Fluss an der Kachelgrenze auf.
 */
function linieAblegen(schicht, pts) {
  if (pts.length < 2) return;
  let k = schluessel(pts[0][0], pts[0][1]);
  let stueck = [pts[0]];
  for (let i = 1; i < pts.length; i++) {
    const kn = schluessel(pts[i][0], pts[i][1]);
    stueck.push(pts[i]);
    if (kn !== k) {
      ablegen(k, schicht, stueck);
      k = kn;
      stueck = [pts[i - 1], pts[i]];
    }
  }
  if (stueck.length > 1) ablegen(k, schicht, stueck);
}

/**
 * Eine Flaeche auf Kacheln zuschneiden. Erst in Breitenbaender, dann in Laengenstreifen:
 * Eurasien hat 81 000 Punkte, und gegen jede der 2 592 Kacheln einzeln zu schneiden
 * waere eine Viertelmilliarde Rechenschritte.
 */
function flaecheAblegen(schicht, ring, minKm2) {
  const [s0, , n0] = fenster(ring);
  for (let la = Math.floor(s0 / GRAD) * GRAD; la < n0; la += GRAD) {
    const band = kasten(ring, la, -180, la + GRAD, 180);
    if (band.length < 3) continue;
    const [, w1, , o1] = fenster(band);
    for (let lo = Math.floor(w1 / GRAD) * GRAD; lo < o1; lo += GRAD) {
      const stueck = kasten(band, la, lo, la + GRAD, lo + GRAD);
      if (stueck.length < 3 || flaeche(stueck) < minKm2) continue;
      ablegen(la + ',' + lo, schicht, rund(stueck, 4));
    }
  }
}

/* ---------- GeoJSON einlesen ---------- */
/** Alle Linienzuege einer GeoJSON-Datei, als [Breite, Laenge]. Flaechen kommen als Ringe. */
function linienAus(datei, { flaechen = false } = {}) {
  const j = JSON.parse(fs.readFileSync(roh(datei), 'utf8'));
  const out = [];
  const dreh = (ring) => ring.map(([lo, la]) => [la, lo]);
  for (const f of j.features || []) {
    const g = f.geometry;
    if (!g) continue;
    if (!flaechen && g.type === 'LineString') out.push(dreh(g.coordinates));
    else if (!flaechen && g.type === 'MultiLineString') g.coordinates.forEach((l) => out.push(dreh(l)));
    else if (flaechen && g.type === 'Polygon') g.coordinates.forEach((r) => out.push(dreh(r)));
    else if (flaechen && g.type === 'MultiPolygon') g.coordinates.forEach((p) => p.forEach((r) => out.push(dreh(r))));
  }
  return out;
}

/** Linien: vereinfachen, runden, zu kurze wegwerfen, auf Kacheln verteilen. */
function linienBauen(name, linien, tolKm, minKm) {
  let vor = 0, nach = 0, stuecke = 0;
  for (const l of linien) {
    vor += l.length;
    if (l.length < 2 || laenge(l) < minKm) continue;
    const v = rund(vereinfachen(l, tolKm), 4);
    if (v.length < 2) continue;
    nach += v.length;
    stuecke++;
    linieAblegen(name, v);
  }
  melden(name, stuecke, vor, nach);
}

/** Flaechen: vereinfachen, auf Kacheln zuschneiden, winzige wegwerfen. */
function flaechenBauen(name, ringe, tolKm, minKm2) {
  let vor = 0, nach = 0, stuecke = 0;
  for (const r of ringe) {
    vor += r.length;
    if (r.length < 4) continue;
    const v = vereinfachen(r, tolKm);
    if (v.length < 4 || flaeche(v) < minKm2) continue;
    nach += v.length;
    stuecke++;
    flaecheAblegen(name, v, minKm2);
  }
  melden(name, stuecke, vor, nach);
}

const melden = (name, stuecke, vor, nach) => console.log(String(name).padEnd(9),
  String(stuecke).padStart(6), 'Zuege |', String(vor).padStart(8), 'Punkte ->', String(nach).padStart(7));

console.log('Schicht    Zuege        Punkte vorher -> nachher');
flaechenBauen('land', linienAus('ne_10m_land.geojson', { flaechen: true }), 0.12, 0.04);
if (da('ne_10m_lakes.geojson')) {
  flaechenBauen('see', linienAus('ne_10m_lakes.geojson', { flaechen: true }), 0.15, 1);
}
if (da('ne_10m_urban_areas.geojson')) {
  flaechenBauen('stadt', linienAus('ne_10m_urban_areas.geojson', { flaechen: true }), 0.3, 2);
}
linienBauen('grenze', verketten(linienAus('ne_10m_admin_0_boundary_lines_land.geojson')), 0.15, 2);
if (da('ne_10m_rivers_lake_centerlines.geojson')) {
  linienBauen('fluss', linienAus('ne_10m_rivers_lake_centerlines.geojson'), 0.2, 3);
}

/* ---------- Flugplaetze und Bahnen ---------- */
const A = csv(fs.readFileSync(roh('airports.csv'), 'utf8'));
const plaetze = A.filter((a) => a.latitude_deg && a.longitude_deg
  && (a.type === 'large_airport' || a.type === 'medium_airport'
    || (a.type === 'small_airport' && a.scheduled_service === 'yes')));
const nachIdent = new Map(plaetze.map((a) => [a.ident, a]));

const R = csv(fs.readFileSync(roh('runways.csv'), 'utf8'));
let bahnen = 0;
const mitBahn = new Set();
for (const r of R) {
  if (!nachIdent.has(r.airport_ident) || r.closed === '1') continue;
  if (!r.le_latitude_deg || !r.he_latitude_deg) continue;
  if (Number(r.length_ft || 0) < 2000) continue;
  const p = [
    [Math.round(Number(r.le_latitude_deg) * 1e4) / 1e4, Math.round(Number(r.le_longitude_deg) * 1e4) / 1e4],
    [Math.round(Number(r.he_latitude_deg) * 1e4) / 1e4, Math.round(Number(r.he_longitude_deg) * 1e4) / 1e4],
  ];
  if (!isFinite(p[0][0]) || !isFinite(p[1][0])) continue;
  ablegen(schluessel(p[0][0], p[0][1]), 'bahn', { r: rein(r.le_ident) + '/' + rein(r.he_ident), p });
  bahnen++;
  mitBahn.add(r.airport_ident);
}

/* Fuer die Suche "welcher Platz ist das": Kuerzel, Ort, Punkt. Kompakte Reihen statt
   Objekte, das spart bei 6 100 Eintraegen rund die Haelfte. */
const luft = plaetze.map((a) => [
  Math.round(Number(a.latitude_deg) * 1e4) / 1e4,
  Math.round(Number(a.longitude_deg) * 1e4) / 1e4,
  (a.iata_code || a.ident || '').toUpperCase(),
  (a.ident || '').toUpperCase(),
  rein(a.municipality).slice(0, 14),
  a.type === 'large_airport' ? 2 : (a.type === 'medium_airport' ? 1 : 0),
]).filter((p) => isFinite(p[0]) && isFinite(p[1]));
luft.sort((a, b) => a[0] - b[0]);

/* ---------- Ortsnamen ---------- */
let orte = [];
if (da('ne_10m_populated_places_simple.geojson')) {
  const j = JSON.parse(fs.readFileSync(roh('ne_10m_populated_places_simple.geojson'), 'utf8'));
  orte = (j.features || []).map((f) => {
    const p = f.properties || {};
    const c = (f.geometry || {}).coordinates || [];
    const n = rein(p.nameascii || p.name);
    return n && isFinite(c[0]) ? [Math.round(c[1] * 1e4) / 1e4, Math.round(c[0] * 1e4) / 1e4, n, Math.max(0, Number(p.pop_max) || 0)] : null;
  }).filter(Boolean);
  orte.sort((a, b) => b[3] - a[3]);
}

/* ---------- schreiben ---------- */
/* Jede Kachel der Welt kommt ins Verzeichnis, auch die leere mitten im Pazifik: ohne
   Eintrag kann der Server nicht unterscheiden, ob dort Wasser ist oder ob die Daten
   fehlen, und wuerde im Zweifel die ganze Karte blau tupfen. */
for (let la = -90; la < 90; la += GRAD) {
  for (let lo = -180; lo < 180; lo += GRAD) {
    const k = la + ',' + lo;
    const t = kacheln.get(k);
    if (!t) kacheln.set(k, { land: [] });
    else if (!t.land) t.land = [];
  }
}

const kopf = {
  stand: new Date().toISOString().slice(0, 10),
  quelle: 'Land, Seen, Stadtgebiete, Grenzen, Fluesse, Orte: Natural Earth 1:10 Mio, gemeinfrei. '
    + 'Flugplaetze und Bahnen: OurAirports, gemeinfrei.',
};

const namen = [...kacheln.keys()].sort();
const teile = [];
const idx = {};
let versatz = 0;
for (const k of namen) {
  const blob = zlib.gzipSync(Buffer.from(JSON.stringify(kacheln.get(k)), 'utf8'), { level: 9 });
  idx[k] = [versatz, blob.length];
  versatz += blob.length;
  teile.push(blob);
}
fs.writeFileSync(path.join(outDir, 'geo-detail.bin'), Buffer.concat(teile));
fs.writeFileSync(path.join(outDir, 'geo-detail.idx'), JSON.stringify({ ...kopf, grad: GRAD, k: idx }));
fs.writeFileSync(path.join(outDir, 'geo-airports.json'), JSON.stringify({ ...kopf, p: luft }));
fs.writeFileSync(path.join(outDir, 'geo-places.json'), JSON.stringify({ ...kopf, o: orte }));

const kb = (f) => (fs.statSync(path.join(outDir, f)).size / 1024).toFixed(0).padStart(6) + ' KB';
const groesste = namen.map((k) => [k, idx[k][1]]).sort((a, b) => b[1] - a[1])[0];
console.log('');
console.log('Kacheln  ', namen.length, 'Stueck a', GRAD, 'Grad, groesste', groesste[0], (groesste[1] / 1024).toFixed(0) + ' KB');
console.log('Plaetze  ', luft.length, 'davon mit Bahn', mitBahn.size, '| Bahnen', bahnen);
console.log('Orte     ', orte.length);
console.log('geo-detail.bin  ', kb('geo-detail.bin'));
console.log('geo-detail.idx  ', kb('geo-detail.idx'));
console.log('geo-airports.json', kb('geo-airports.json'));
console.log('geo-places.json ', kb('geo-places.json'));
