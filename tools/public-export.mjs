// Oeffentlicher Stand von THE WALL. Dieses Repo bleibt privat, das oeffentliche Repo
// bekommt einen Auszug: nur freigegebene Dateien, ohne private Abschnitte, und erst
// nach einer Suche nach Spuren der eigenen Instanz.
//
// Aufruf aus dem Hauptverzeichnis, Node 18 oder neuer, keine Pakete:
//   node tools/public-export.mjs --check            nur pruefen, nichts schreiben
//   node tools/public-export.mjs --check --worktree Dateien auf der Platte pruefen, vor dem Commit
//   node tools/public-export.mjs ../THE-WALL-public   schreiben; ein vorhandenes .git bleibt
//   node tools/public-export.mjs ../THE-WALL-public --commit [--trailer "Zeile"]
//                                                     dazu dort committen, mit "Workshop: <sha>"
//   node tools/public-export.mjs ../ordner --force    auch in ein Repo schreiben, das nicht wie ein Auszug aussieht
//
// Beitraege von aussen kommen als Pull Request ins oeffentliche Repo und von dort mit
// tools/public-import.mjs hierher. Solange dort etwas liegt, das hier fehlt, schreibt der
// Auszug nicht, sonst ueberschriebe er es.
//
// Regeln:
// - Es gehen nur Dateien raus, die Git kennt (git ls-files) und die committet sind.
//   .htdata, der Logo-Cache und alles Ungetrackte koennen damit nie mitkommen.
// - INCLUDE nennt, was rausgeht, EXCLUDE die Ausnahmen darin.
// - In Markdown-Dateien fallen die Zeilen von <!-- privat:start --> bis <!-- privat:end -->
//   weg, samt der beiden Markierungen. Jede Markierung steht allein in ihrer Zeile.
// - TRACES: ein Treffer in einer Datei, die rausgehen soll, bricht ab.
// - Nennt eine Datei eine andere, die nicht mitgeht, gibt es einen Hinweis.
// - Das Ziel muss fehlen, leer sein oder ein frueherer Auszug: ein .git und eine
//   web/.htapp/bootstrap.php, oder ein Remote, der auf /THE-WALL.git endet. Dort wird alles
//   ausser .git durch den Auszug ersetzt. Jedes andere Git-Repo nur mit --force, denn ein
//   Tippfehler im Pfad wuerde sonst dessen Arbeitsverzeichnis ohne Rueckfrage leeren.

import fs from 'fs';
import path from 'path';
import { execFileSync } from 'child_process';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');

const INCLUDE = [
  'README.md', 'README.de.md', 'LICENSE', 'SECURITY.md', 'CLAUDE.md', '.gitignore', '.gitattributes',
  'CONTRIBUTING.md', 'CONTRIBUTING.de.md', '.github/',
  'web/', 'tools/', 'docs/server/', 'docs/images/', 'docs/LICENSE.md', 'hardware/', 'firmware/',
  // Die Integration fuer Home Assistant; fuer HACS kommt sie zusaetzlich in ein eigenes Repo.
  'homeassistant/',
];

const PRIVATE_TRACES = 'tools/public-export.private.txt';

const EXCLUDE = [
  'web/.htapp/logos/',        // Airline-Logos aus dem Entwurf, eingetragene Marken
  'tools/deploy.ps1',         // Laufwerk und Freigabe der eigenen Instanz
  'tools/design-parity.mjs',  // braucht design/, das privat bleibt
  PRIVATE_TRACES,
];

// Allgemeine Muster. Was nur diese Instanz verraet (Nutzernamen und Aehnliches),
// steht in PRIVATE_TRACES, je Zeile "regex<Tab>Beschreibung", und geht selbst nie raus.
// Die Muster sind so geschrieben, dass sie ihren eigenen Quelltext nicht treffen.
const TRACES = [
  [/192\.168\.178\./, 'Adresse im Heimnetz'],
  [/\bW:[\\/]/, 'Netzlaufwerk W:'],
  [/\/home\/[a-z0-9-]+\/htdocs/, 'Webroot auf dem Server'],
  [/C:[\\/]Users[\\/]/i, 'lokaler Windows-Pfad'],
  [/[a-z0-9._%+-]+@godart\.lu/i, 'E-Mail-Adresse unter godart.lu'],
  [/tw_live_(?!0123456789abcdef)[0-9a-f]{16}/, 'echter Geraeteschluessel'],
  [/accessId=(?!<|DEIN|REPLACE)[A-Za-z0-9-]{16,}/, 'Schluessel fuer mobiliteit.lu'],
  [/-----BEGIN [A-Z ]*PRIVATE KEY-----/, 'privater Schluessel'],
  // Ein halb geloester Konflikt aus public-import.mjs ginge sonst mit raus.
  [/^(<{7}|>{7}) /, 'Konfliktmarke'],
];
if (fs.existsSync(path.join(root, PRIVATE_TRACES))) {
  for (const line of fs.readFileSync(path.join(root, PRIVATE_TRACES), 'utf8').split(/\r?\n/)) {
    const [re, what] = line.split('\t');
    if (re && !re.startsWith('#')) TRACES.push([new RegExp(re, 'i'), what || 'private Angabe']);
  }
}

const START = '<!-- privat:start -->';
const END = '<!-- privat:end -->';

const args = process.argv.slice(2);
const check = args.includes('--check');
const worktree = args.includes('--worktree');
const force = args.includes('--force');
const commit = args.includes('--commit');
const trailers = args.flatMap((a, i) => (a === '--trailer' && args[i + 1] ? [args[i + 1]] : []));
const target = args.find((a, i) => !a.startsWith('--') && args[i - 1] !== '--trailer');
if (worktree && !check) {
  console.error('--worktree nur zusammen mit --check. Geschrieben wird immer der letzte Commit.');
  process.exit(2);
}
if (!check && !target) {
  console.error('Aufruf: node tools/public-export.mjs --check | <zielordner>');
  process.exit(2);
}

const git = (...a) => execFileSync('git', a, { cwd: root, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
// --worktree liest die Dateien von der Platte (nur zum Pruefen vor dem Commit), sonst den letzten Commit.
const tracked = (worktree ? git('ls-files', '-z') : git('ls-tree', '-r', '--name-only', '-z', 'HEAD')).split('\0').filter(Boolean);
const dirty = git('status', '--porcelain', '--untracked-files=no').trim();

const inList = (file, list) => list.some((p) => (p.endsWith('/') ? file.startsWith(p) : file === p));
const selected = tracked.filter((f) => inList(f, INCLUDE) && !inList(f, EXCLUDE));
const left = tracked.filter((f) => !selected.includes(f));
const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const mentions = left.map((other) => [other, other.includes('/') ? null : new RegExp('(^|[^\\w./-])' + escape(other) + '($|[^\\w-])')]);

const isText = (buf) => !buf.subarray(0, 8000).includes(0);

/* Private Abschnitte entfernen. Unvollstaendige Markierungen sind ein Fehler. */
function stripPrivate(text, file) {
  const out = [];
  let inside = false;
  let removed = 0;
  text.split('\n').forEach((line, i) => {
    const t = line.trim();
    if (t === START) {
      if (inside) throw new Error(`${file}:${i + 1}: privat:start ohne vorheriges Ende`);
      inside = true;
      removed++;
      return;
    }
    if (t === END) {
      if (!inside) throw new Error(`${file}:${i + 1}: privat:end ohne Anfang`);
      inside = false;
      removed++;
      return;
    }
    if (inside) {
      removed++;
      return;
    }
    if (line.includes('privat:start') || line.includes('privat:end')) {
      throw new Error(`${file}:${i + 1}: Markierung muss allein in der Zeile stehen`);
    }
    out.push(line);
  });
  if (inside) throw new Error(`${file}: privat:start ohne Ende`);
  // Doppelte Leerzeilen, die durch das Entfernen entstehen, zusammenziehen.
  const joined = out.join('\n').replace(/\n{3,}/g, '\n\n');
  return { text: joined, removed };
}

const files = new Map();
const traces = [];
const hints = [];
let privateLines = 0;

for (const file of selected) {
  // Aus dem Commit lesen, nicht von der Platte: ungespeicherte Aenderungen gehen nie raus.
  const buf = worktree ? fs.readFileSync(path.join(root, file)) : execFileSync('git', ['show', 'HEAD:' + file], { cwd: root, maxBuffer: 64 * 1024 * 1024 });
  if (!isText(buf)) {
    files.set(file, buf);
    continue;
  }
  // Markierungen gelten nur in Markdown. Code, der sie erwaehnt (dieses Skript), bleibt unberuehrt.
  const { text, removed } = file.endsWith('.md') ? stripPrivate(buf.toString('utf8'), file) : { text: buf.toString('utf8'), removed: 0 };
  privateLines += removed;
  text.split('\n').forEach((line, i) => {
    for (const [re, what] of TRACES) {
      if (re.test(line)) traces.push(`${file}:${i + 1}: ${what}: ${line.trim().slice(0, 120)}`);
    }
    for (const [other, re] of mentions) {
      if (re ? re.test(line) : line.includes(other)) hints.push(`${file}:${i + 1}: nennt ${other}`);
    }
    if (/(^|[\s`(])design\/|(^|[\s`(])email\//.test(line)) hints.push(`${file}:${i + 1}: nennt einen privaten Ordner`);
  });
  files.set(file, Buffer.from(text, 'utf8'));
}

const size = [...files.values()].reduce((n, b) => n + b.length, 0);
console.log(`${files.size} Dateien, ${(size / 1024).toFixed(0)} KB, ${privateLines} private Zeilen entfernt, ${left.length} Dateien bleiben privat`);
if (dirty && !worktree) console.log('Achtung: ungespeicherte Aenderungen an getrackten Dateien, exportiert wird der letzte Commit.');
for (const h of [...new Set(hints)]) console.log('Hinweis ' + h);
if (traces.length) {
  for (const t of traces) console.log('SPUR ' + t);
  console.error(`${traces.length} Spuren gefunden, nichts geschrieben.`);
  process.exit(1);
}
if (check) {
  console.log('Pruefung ohne Spuren. Mit einem Zielordner statt --check wird geschrieben.');
  process.exit(0);
}

const dest = path.resolve(process.cwd(), target);
if (dest === root || dest.startsWith(root + path.sep)) {
  console.error('Das Ziel darf nicht in diesem Repo liegen.');
  process.exit(2);
}
if (fs.existsSync(dest)) {
  const entries = fs.readdirSync(dest);
  if (entries.length && !entries.includes('.git')) {
    console.error('Das Ziel ist nicht leer und kein Git-Repo. Abgebrochen.');
    process.exit(2);
  }
  // Nur einen frueheren Auszug leeren, kein beliebiges Repo (Befund S2).
  if (entries.length > 1 && !force) {
    let remote = '';
    try {
      remote = execFileSync('git', ['remote', 'get-url', 'origin'], { cwd: dest, encoding: 'utf8' }).trim();
    } catch {
      remote = '';
    }
    const looksLikeExport = fs.existsSync(path.join(dest, 'web', '.htapp', 'bootstrap.php')) || /\/THE-WALL(\.git)?$/i.test(remote);
    if (!looksLikeExport) {
      console.error('Das Ziel ' + dest + ' ist ein Git-Repo, sieht aber nicht wie ein frueherer Auszug aus. Pfad pruefen, oder mit --force bewusst ueberschreiben.');
      process.exit(2);
    }
  }
  if (entries.includes('.git') && !force) guardContributions(dest);
  for (const e of entries) {
    if (e !== '.git') fs.rmSync(path.join(dest, e), { recursive: true, force: true });
  }
}
for (const [file, buf] of files) {
  const out = path.join(dest, file);
  fs.mkdirSync(path.dirname(out), { recursive: true });
  fs.writeFileSync(out, buf);
}
console.log('Geschrieben nach ' + dest);
if (commit) commitExport(dest);

/**
 * Bricht ab, wenn das oeffentliche Repo Beitraege hat, die hier noch fehlen: Commits auf
 * GitHub, die der Ordner nicht hat, oder Commits nach dem letzten Auszug, die
 * tools/public-import.mjs noch nicht uebernommen hat (.git/thewall-imported).
 */
function guardContributions(dir) {
  const pub = (...a) => execFileSync('git', a, { cwd: dir, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  const ok = (...a) => {
    try {
      pub(...a);
      return true;
    } catch {
      return false;
    }
  };
  if (!ok('rev-parse', '--verify', '--quiet', 'HEAD')) return; // noch kein Commit
  if (!ok('fetch', '--quiet', 'origin')) console.log('Hinweis: origin nicht erreichbar, geprueft wird nur der lokale Stand.');
  const stop = (why) => {
    console.error(why + ' Zuerst: node tools/public-import.mjs ' + path.relative(process.cwd(), dir) + ' --apply');
    process.exit(2);
  };
  if (ok('rev-parse', '--verify', '--quiet', 'origin/main') && Number(pub('rev-list', '--count', 'HEAD..origin/main')) > 0) {
    stop('Auf GitHub liegen Commits, die dieser Ordner noch nicht hat.');
  }
  let last = '';
  try {
    last = pub('log', '-1', '--first-parent', '--format=%H', '--grep=^Workshop: ');
  } catch {
    last = '';
  }
  if (!last || Number(pub('rev-list', '--count', last + '..HEAD')) === 0) return;
  const markFile = path.join(dir, '.git', 'thewall-imported');
  const mark = fs.existsSync(markFile) ? fs.readFileSync(markFile, 'utf8').trim() : '';
  if (!mark || !ok('merge-base', '--is-ancestor', 'HEAD', mark)) stop('Seit dem letzten Auszug gibt es Beitraege, die hier noch fehlen.');
}

/** Im Auszug committen, mit "Workshop: <sha>" als Marke fuer public-import.mjs. */
function commitExport(dir) {
  const pub = (...a) => execFileSync('git', a, { cwd: dir, encoding: 'utf8' }).trim();
  if (!fs.existsSync(path.join(dir, '.git'))) {
    console.error('--commit braucht ein Git-Repo im Ziel: dort zuerst git init -b main.');
    process.exit(2);
  }
  pub('add', '-A');
  if (!pub('diff', '--cached', '--name-only')) {
    console.log('Keine Aenderung gegenueber dem letzten Auszug, nichts committet.');
    return;
  }
  const sha = git('rev-parse', '--short', 'HEAD').trim();
  const body = ['Workshop: ' + sha, ...trailers].join('\n');
  execFileSync('git', ['commit', '-q', '-m', 'Update from the workshop', '-m', body], { cwd: dir });
  console.log('Committet: ' + pub('log', '-1', '--format=%h %s') + '. Pushen mit git -C ' + path.relative(process.cwd(), dir) + ' push');
}
