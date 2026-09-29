// Beitraege aus dem oeffentlichen Repo in diese Werkstatt holen, nach dem Merge eines Pull
// Requests auf GitHub. Aufruf aus dem Hauptverzeichnis, Node 18 oder neuer, keine Pakete:
//   node tools/public-import.mjs ../THE-WALL-public            zeigen, was kaeme
//   node tools/public-import.mjs ../THE-WALL-public --apply    uebernehmen, je Beitrag ein Commit
//   node tools/public-import.mjs ../THE-WALL-public --mark <sha>
//                                                              bis hierhin als uebernommen merken
//
// Der oeffentliche Ordner holt origin/main, nur vorspulen. Jeder Commit in erster Linie nach
// dem letzten Auszug (Nachricht mit "Workshop: <sha>", siehe public-export.mjs --commit) ist
// ein Beitrag, meist der Merge eines Pull Requests. Er kommt mit git cherry-pick hierher, als
// eigener Commit mit dem Beitragenden als Autor. Private Abschnitte in Markdown bleiben dabei
// stehen, weil cherry-pick drei Staende zusammenfuehrt. Bis wohin uebernommen ist, steht in
// .git/thewall-imported des oeffentlichen Ordners; erst danach schreibt public-export.mjs
// wieder dorthin. Ein Konflikt haelt an: loesen, committen, dann mit --mark den Commit als
// erledigt merken und das Skript erneut starten.

import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const args = process.argv.slice(2);
const apply = args.includes('--apply');
const markAt = args.includes('--mark') ? args[args.indexOf('--mark') + 1] || '' : null;
const target = args.find((a, i) => !a.startsWith('--') && args[i - 1] !== '--mark');
if (!target) {
  console.error('Aufruf: node tools/public-import.mjs <oeffentlicher Ordner> [--apply | --mark <sha>]');
  process.exit(2);
}
const dest = path.resolve(process.cwd(), target);
if (!fs.existsSync(path.join(dest, '.git'))) {
  console.error(dest + ' ist kein Git-Repo.');
  process.exit(2);
}

const run = (cwd) => (...a) => execFileSync('git', a, { cwd, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'] }).trim();
const here = run(root);
const pub = run(dest);
const markFile = path.join(dest, '.git', 'thewall-imported');
const mark = (sha) => fs.writeFileSync(markFile, sha + '\n');

if (markAt !== null) {
  const sha = pub('rev-parse', '--verify', markAt + '^{commit}');
  mark(sha);
  console.log('Als uebernommen gemerkt bis ' + sha.slice(0, 7) + '.');
  process.exit(0);
}

if (apply) {
  if (here('status', '--porcelain', '--untracked-files=no')) {
    console.error('Die Werkstatt hat ungespeicherte Aenderungen. Erst committen oder beiseitelegen.');
    process.exit(2);
  }
  if (here('rev-parse', '--abbrev-ref', 'HEAD') !== 'main') {
    console.error('Die Werkstatt steht nicht auf main.');
    process.exit(2);
  }
}

// Oeffentlicher Ordner: neuesten Stand holen, nur vorspulen.
pub('fetch', '--quiet', 'origin');
if (pub('rev-parse', '--abbrev-ref', 'HEAD') !== 'main') {
  console.error('Der oeffentliche Ordner steht nicht auf main.');
  process.exit(2);
}
try {
  pub('merge', '--ff-only', '--quiet', 'origin/main');
} catch {
  console.error('Der oeffentliche Ordner hat Commits, die GitHub nicht kennt, etwa einen Auszug, der noch nicht gepusht ist. Erst klaeren: git -C ' + target + ' status');
  process.exit(2);
}

let last = '';
try {
  last = pub('log', '-1', '--first-parent', '--format=%H', '--grep=^Workshop: ');
} catch {
  last = '';
}
if (!last) {
  console.error('Im oeffentlichen Repo gibt es keinen Auszug mit "Workshop: <sha>". Erst einmal mit public-export.mjs --commit schreiben.');
  process.exit(2);
}
let from = last;
if (fs.existsSync(markFile)) {
  const m = fs.readFileSync(markFile, 'utf8').trim();
  try {
    pub('merge-base', '--is-ancestor', last, m);
    from = m;
  } catch {
    // Die Marke liegt vor dem letzten Auszug: ab dem Auszug zaehlen.
  }
}

const isExport = (c) => /^Workshop: /m.test(pub('log', '-1', '--format=%B', c));
const commits = pub('rev-list', '--first-parent', '--reverse', from + '..HEAD').split('\n').filter(Boolean).filter((c) => !isExport(c));
if (!commits.length) {
  mark(pub('rev-parse', 'HEAD'));
  console.log('Nichts zu uebernehmen, alles auf dem Stand.');
  process.exit(0);
}

/** Wer, was, welcher Pull Request. Beim Merge-Commit zaehlt der Autor des Pull Requests. */
function describe(c) {
  const parents = pub('log', '-1', '--format=%P', c).split(' ').filter(Boolean);
  const merge = parents.length > 1;
  const subject = pub('log', '-1', '--format=%s', c);
  const body = pub('log', '-1', '--format=%b', c);
  let pr = '';
  let title = subject;
  let m = subject.match(/^Merge pull request #(\d+) from /);
  if (m) {
    pr = m[1];
    title = body.split('\n').find((l) => l.trim()) || subject;
  } else if ((m = subject.match(/\(#(\d+)\)\s*$/))) {
    pr = m[1];
    title = subject.replace(/\s*\(#\d+\)\s*$/, '');
  }
  const author = pub('log', '-1', '--format=%an <%ae>', merge ? parents[1] : c);
  const files = pub('diff', '--name-status', parents[0], c);
  return { c, merge, pr, title, author, files };
}

const list = commits.map(describe);
for (const d of list) {
  console.log(`${d.c.slice(0, 7)} ${d.pr ? 'PR #' + d.pr + ', ' : ''}${d.title} (${d.author})`);
  for (const f of d.files.split('\n').filter(Boolean)) console.log('    ' + f);
}
if (!apply) {
  console.log(`\n${list.length} Beitrag${list.length === 1 ? '' : 'e'}. Mit --apply uebernehmen.`);
  process.exit(0);
}

// Die Objekte des oeffentlichen Repos hierher holen, damit cherry-pick dessen Staende kennt.
here('fetch', '--quiet', dest, '+main:refs/public/main');
for (const d of list) {
  try {
    here('cherry-pick', '--no-commit', ...(d.merge ? ['-m', '1'] : []), d.c);
  } catch {
    console.error(`\nKonflikt bei ${d.c.slice(0, 7)}: ${d.title}\n${here('status', '--short')}`);
    console.error(`Loesen, committen mit --author "${d.author}", dann: node tools/public-import.mjs ${target} --mark ${d.c.slice(0, 7)} und erneut --apply.`);
    process.exit(1);
  }
  if (!here('diff', '--cached', '--name-only')) {
    console.log(`${d.c.slice(0, 7)} ist hier schon enthalten, uebersprungen.`);
  } else {
    const msg = 'Beitrag: ' + d.title + (d.pr ? ' (PR #' + d.pr + ')' : '');
    here('commit', '-q', '--author', d.author, '-m', msg, '-m', 'Aus dem oeffentlichen Repo, Commit ' + d.c.slice(0, 7) + '.');
    console.log('Uebernommen: ' + here('log', '-1', '--format=%h %s'));
  }
  mark(d.c);
}
console.log('\nFertig. Jetzt alle Pruefungen, Deploy, git push, dann public-export.mjs --commit und dort pushen.');
