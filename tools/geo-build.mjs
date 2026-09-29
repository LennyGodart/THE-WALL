// Rechnet die Geodaten fuer den Kartenmodus in zwei Dateien, die der Server liest:
//
//   web/.htapp/data/geo-world.json   Kuesten und Landesgrenzen der ganzen Welt (Natural Earth)
//   web/.htapp/data/geo-near.json    Fluesse, Seen, Autobahnen, Bahnen, Orte um Luxemburg (OSM)
//
// Die Rohdaten liegen nicht im Repo, sie sind zu gross. So kommt man an sie:
//
//   curl -O https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json
//   curl --data-binary @abfrage.overpass https://overpass-api.de/api/interpreter -o osm.json
//
// Die Overpass-Abfrage steht in design/karte/README.md. Im Dauerbetrieb waere ein
// Geofabrik-Auszug der bessere Weg, fuer zwei Geraete in Luxemburg reicht die eine Abfrage.
//
// Aufruf aus dem Hauptverzeichnis:
//   node tools/geo-build.mjs countries-110m.json osm.json
//
// Grenzen: Natural Earth schneidet sie je Laenderpaar. Einzeln gezeichnet sind das im Bild
// dutzende kurze Stuecke, von denen die Laengenregel fast alle wegwirft. Sie werden ueber
// gemeinsame Endpunkte zu langen Zuegen verkettet, bevor vereinfacht wird.
//
// Vereinfachen, Verketten und Runden stehen in tools/geo-lib.mjs, geteilt mit geo-welt.mjs,
// das dieselben Rechnungen fuer die ganze Welt macht.
import fs from 'fs';
import path from 'path';
import { laenge, vereinfachen, rund, verketten, rein } from './geo-lib.mjs';

const [weltFile, osmFile] = process.argv.slice(2);
if (!weltFile || !osmFile) {
  console.error('Aufruf: node tools/geo-build.mjs <countries-110m.json> <osm.json>');
  process.exit(2);
}
const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const outDir = path.join(root, 'web', '.htapp', 'data');
fs.mkdirSync(outDir, { recursive: true });

/* ---------- 1. Welt: Kueste und Grenzen ---------- */
const topo = JSON.parse(fs.readFileSync(weltFile, 'utf8'));
const [sx, sy] = topo.transform.scale, [tx, ty] = topo.transform.translate;
const boegen = topo.arcs.map((arc) => {
  let x = 0, y = 0;
  return arc.map(([dx, dy]) => { x += dx; y += dy; return [y * sy + ty, x * sx + tx]; });
});
const benutzt = new Array(boegen.length).fill(0);
const zaehlen = (geom) => {
  if (!geom) return;
  if (geom.type === 'Polygon') geom.arcs.forEach((r) => r.forEach((i) => benutzt[i < 0 ? ~i : i]++));
  else if (geom.type === 'MultiPolygon') geom.arcs.forEach((p) => p.forEach((r) => r.forEach((i) => benutzt[i < 0 ? ~i : i]++)));
  else if (geom.type === 'GeometryCollection') geom.geometries.forEach(zaehlen);
};
zaehlen(topo.objects.countries);
const kuesteRoh = [], grenzeRoh = [];
boegen.forEach((b, i) => (benutzt[i] >= 2 ? grenzeRoh : kuesteRoh).push(b));
const kueste = verketten(kuesteRoh).map((l) => rund(vereinfachen(l, 6), 3)).filter((l) => l.length > 1);
const grenze = verketten(grenzeRoh).map((l) => rund(vereinfachen(l, 6), 3)).filter((l) => l.length > 1);

/* ---------- 2. Nahbereich aus OpenStreetMap ---------- */
const osm = JSON.parse(fs.readFileSync(osmFile, 'utf8'));
const fluss = [], see = [], autobahn = [], bahn = [], ort = [];
let bbox = [90, 180, -90, -180];   // sued, west, nord, ost
const dehnen = (la, lo) => {
  bbox[0] = Math.min(bbox[0], la); bbox[1] = Math.min(bbox[1], lo);
  bbox[2] = Math.max(bbox[2], la); bbox[3] = Math.max(bbox[3], lo);
};
for (const e of osm.elements || []) {
  const t = e.tags || {};
  if (e.type === 'node') {
    if (/^(city|town)$/.test(t.place || '') && t.name) {
      ort.push({ n: rein(t.name), la: Math.round(e.lat * 1e4) / 1e4, lo: Math.round(e.lon * 1e4) / 1e4, e: Number(t.population || 0) || 0 });
      dehnen(e.lat, e.lon);
    }
    continue;
  }
  if (!e.geometry) continue;
  const pts = e.geometry.map((p) => [p.lat, p.lon]);
  pts.forEach(([la, lo]) => dehnen(la, lo));
  const l = laenge(pts);
  if (t.aeroway === 'runway' && l > 0.8) bahn.push({ r: t.ref || '', p: rund(vereinfachen(pts, 0.05), 4) });
  else if (t.waterway === 'river' && l > 4) fluss.push(rund(vereinfachen(pts, 0.15), 4));
  else if (t.natural === 'water' && l > 5) see.push(rund(vereinfachen(pts, 0.2), 4));
  else if (t.highway === 'motorway' && l > 0.8) autobahn.push(rund(vereinfachen(pts, 0.15), 4));
}
ort.sort((a, b) => b.e - a.e);

/* ---------- schreiben ---------- */
const kopf = {
  stand: new Date().toISOString().slice(0, 10),
  quelle: 'Kueste und Grenzen: Natural Earth 1:110 Mio ueber world-atlas, gemeinfrei. '
    + 'Fluesse, Seen, Autobahnen, Bahnen, Orte: OpenStreetMap, ODbL, Namensnennung Pflicht.',
};
const welt = { ...kopf, kueste, grenze };
const nah = { ...kopf, bbox: bbox.map((v) => Math.round(v * 1e4) / 1e4), fluss, see, autobahn, bahn, ort };
fs.writeFileSync(path.join(outDir, 'geo-world.json'), JSON.stringify(welt));
fs.writeFileSync(path.join(outDir, 'geo-near.json'), JSON.stringify(nah));
const kb = (f) => (fs.statSync(path.join(outDir, f)).size / 1024).toFixed(0) + ' KB';
console.log('Kueste  ', String(kueste.length).padStart(5), 'Zuege aus', kuesteRoh.length, 'Boegen');
console.log('Grenze  ', String(grenze.length).padStart(5), 'Zuege aus', grenzeRoh.length, 'Boegen');
console.log('Fluss   ', String(fluss.length).padStart(5), '| See', see.length, '| Autobahn', autobahn.length, '| Bahn', bahn.length, '| Orte', ort.length);
console.log('Fenster ', nah.bbox.join(', '));
console.log('Dateien ', 'geo-world.json', kb('geo-world.json'), '| geo-near.json', kb('geo-near.json'));
