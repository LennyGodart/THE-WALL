// Gemeinsame Geometrie fuer die beiden Bauskripte geo-build.mjs (Luxemburg und
// Weltuebersicht) und geo-welt.mjs (Kacheln der ganzen Welt).
//
// Punkte sind ueberall [Breite, Laenge] in Grad, Laengen in Kilometern. GeoJSON dreht
// die Reihenfolge um, das wird beim Einlesen umgedreht und nicht hier.

export const rad = (d) => d * Math.PI / 180;

/** Luftlinie zwischen zwei Punkten in Kilometern, flach gerechnet. Reicht bis ein paar hundert km. */
export const kmBetween = (a, b) =>
  Math.hypot((b[0] - a[0]) * 111.32, (b[1] - a[1]) * 111.32 * Math.cos(rad(a[0])));

/** Laenge eines Linienzugs in Kilometern. */
export const laenge = (pts) => pts.slice(1).reduce((s, p, i) => s + kmBetween(pts[i], p), 0);

/** Abstand eines Punktes von der Strecke a,b in Kilometern. */
export function abstand(p, a, b) {
  const bx = (b[1] - a[1]) * 111.32 * Math.cos(rad(a[0])), by = (b[0] - a[0]) * 111.32;
  const px = (p[1] - a[1]) * 111.32 * Math.cos(rad(a[0])), py = (p[0] - a[0]) * 111.32;
  const len2 = bx * bx + by * by;
  if (!len2) return Math.hypot(px, py);
  const t = Math.max(0, Math.min(1, (px * bx + py * by) / len2));
  return Math.hypot(px - bx * t, py - by * t);
}

/* Douglas-Peucker in Kilometern. Ohne Rekursion, weil eine Kuestenlinie aus Natural
   Earth 1:10 Mio ueber 100 000 Punkte haben kann und der Stapel dann ausgeht. */
export function vereinfachen(pts, tolKm) {
  if (pts.length < 3) return pts;
  const behalten = new Uint8Array(pts.length);
  behalten[0] = 1;
  behalten[pts.length - 1] = 1;
  const stapel = [[0, pts.length - 1]];
  while (stapel.length) {
    const [i0, i1] = stapel.pop();
    let max = 0, idx = -1;
    for (let i = i0 + 1; i < i1; i++) {
      const d = abstand(pts[i], pts[i0], pts[i1]);
      if (d > max) { max = d; idx = i; }
    }
    if (idx >= 0 && max > tolKm) {
      behalten[idx] = 1;
      stapel.push([i0, idx], [idx, i1]);
    }
  }
  const out = [];
  for (let i = 0; i < pts.length; i++) if (behalten[i]) out.push(pts[i]);
  return out;
}

/** Koordinaten auf n Nachkommastellen runden. Vier Stellen sind etwa elf Meter. */
export const rund = (pts, n) => {
  const f = 10 ** n;
  return pts.map(([la, lo]) => [Math.round(la * f) / f, Math.round(lo * f) / f]);
};

/**
 * Linienzuege an gemeinsamen Endpunkten verketten. Natural Earth schneidet Grenzen je
 * Laenderpaar; einzeln gezeichnet sind das dutzende kurze Stuecke, von denen die
 * Laengenregel im Renderer fast alle wegwirft.
 */
export function verketten(linien, genau = 4) {
  const key = (p) => p[0].toFixed(genau) + ',' + p[1].toFixed(genau);
  const offen = linien.map((l) => l.slice());
  const enden = new Map();
  offen.forEach((l, i) => {
    for (const k of [key(l[0]), key(l[l.length - 1])]) {
      if (!enden.has(k)) enden.set(k, []);
      enden.get(k).push(i);
    }
  });
  const benutzt = new Array(offen.length).fill(false);
  const out = [];
  for (let i = 0; i < offen.length; i++) {
    if (benutzt[i]) continue;
    benutzt[i] = true;
    let zug = offen[i];
    // an beiden Enden weitersuchen, solange genau ein unbenutzter Nachbar passt
    for (const richtung of [0, 1]) {
      for (;;) {
        const ende = richtung === 0 ? zug[zug.length - 1] : zug[0];
        const kandidaten = (enden.get(key(ende)) || []).filter((j) => !benutzt[j]);
        if (kandidaten.length !== 1) break;
        const j = kandidaten[0];
        let n = offen[j];
        benutzt[j] = true;
        const trifft = (p, q) => key(p) === key(q);
        if (richtung === 0) {
          if (trifft(n[0], ende)) zug = zug.concat(n.slice(1));
          else if (trifft(n[n.length - 1], ende)) zug = zug.concat(n.slice().reverse().slice(1));
          else break;
        } else {
          if (trifft(n[n.length - 1], ende)) zug = n.slice(0, -1).concat(zug);
          else if (trifft(n[0], ende)) zug = n.slice().reverse().slice(0, -1).concat(zug);
          else break;
        }
      }
    }
    out.push(zug);
  }
  return out;
}

/**
 * Text fuer das Panel: ohne Akzente, Grossbuchstaben, nur Zeichen die pixelfont.js kennt.
 * Der Server schreibt Umlaute um, bevor er sendet, das ist hier dieselbe Regel.
 */
export const rein = (s) => String(s || '')
  .normalize('NFD').replace(/[̀-ͯ]/g, '')
  .replace(/ß/g, 'SS').replace(/[Øø]/g, 'O').replace(/[Ææ]/g, 'AE').replace(/[Đđ]/g, 'D')
  .replace(/[Łł]/g, 'L').replace(/[Þþ]/g, 'TH')
  .toUpperCase().replace(/[^A-Z0-9 .\-']/g, '').replace(/\s+/g, ' ').trim();

/**
 * Ein Polygon an einer Geraden abschneiden (Sutherland-Hodgman). drin() sagt, welche
 * Seite bleibt, kreuz() rechnet den Schnittpunkt einer Kante mit der Geraden.
 */
function schneiden(ring, drin, kreuz) {
  const out = [];
  for (let i = 0; i < ring.length; i++) {
    const a = ring[i], b = ring[(i + 1) % ring.length];
    const da = drin(a), db = drin(b);
    if (da) out.push(a);
    if (da !== db) out.push(kreuz(a, b));
  }
  return out;
}

/** Ein Polygon auf das Rechteck sued/west/nord/ost zuschneiden. Leer, wenn nichts bleibt. */
export function kasten(ring, s, w, n, o) {
  const lerp = (a, b, t) => [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];
  let r = ring;
  r = schneiden(r, (p) => p[0] >= s, (a, b) => { const q = lerp(a, b, (s - a[0]) / (b[0] - a[0])); q[0] = s; return q; });
  if (r.length < 3) return [];
  r = schneiden(r, (p) => p[0] <= n, (a, b) => { const q = lerp(a, b, (n - a[0]) / (b[0] - a[0])); q[0] = n; return q; });
  if (r.length < 3) return [];
  r = schneiden(r, (p) => p[1] >= w, (a, b) => { const q = lerp(a, b, (w - a[1]) / (b[1] - a[1])); q[1] = w; return q; });
  if (r.length < 3) return [];
  r = schneiden(r, (p) => p[1] <= o, (a, b) => { const q = lerp(a, b, (o - a[1]) / (b[1] - a[1])); q[1] = o; return q; });
  return r.length < 3 ? [] : r;
}

/** Flaeche eines Rings in Quadratkilometern, ueber die Gausssche Trapezformel. */
export function flaeche(ring) {
  let a = 0;
  for (let i = 0; i < ring.length; i++) {
    const p = ring[i], q = ring[(i + 1) % ring.length];
    a += p[1] * q[0] - q[1] * p[0];
  }
  const mitte = ring.reduce((s, p) => s + p[0], 0) / ring.length;
  return Math.abs(a / 2) * 111.32 * 111.32 * Math.cos(rad(mitte));
}

/** Umschliessendes Rechteck eines Linienzugs: [sued, west, nord, ost]. */
export function fenster(pts) {
  let s = 90, w = 180, n = -90, o = -180;
  for (const [la, lo] of pts) {
    if (la < s) s = la;
    if (la > n) n = la;
    if (lo < w) w = lo;
    if (lo > o) o = lo;
  }
  return [s, w, n, o];
}
