// Prueft gespeicherte Seiten gegen die Regeln aus CLAUDE.md. Aufruf: node tools/audit.mjs <ordner>
import {readFileSync, readdirSync, statSync} from 'fs';
import {join} from 'path';

const dir = process.argv[2];
const files = readdirSync(dir).filter(f => /\.(html|js|md)$/.test(f)).sort();

// relative Leuchtdichte fuer Kontrast
const lum = hex => {
  const v = hex.replace('#', '');
  const full = v.length === 3 ? v.split('').map(c => c + c).join('') : v;
  const [r, g, b] = [0, 2, 4].map(i => parseInt(full.substr(i, 2), 16) / 255)
    .map(c => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const contrast = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
const BG = '#08090A';

const emojiRe = /[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{1F000}-\u{1F2FF}\u{2B50}\u{2705}\u{274C}]/u;
const aiPhrases = /\b(seamless|elevate|unleash|next-gen|nahtlos|revolution\w*|game.?changer|cutting.?edge)\b/gi;

for (const f of files) {
  const s = readFileSync(join(dir, f), 'utf8');
  const isPage = /\.html$/.test(f);
  const out = [];

  const em = (s.match(/—/g) || []).length;
  if (em) out.push(`Em-Dashes: ${em}`);

  const fonts = [...new Set((s.match(/\b(Inter|Roboto|Arial|Fraunces|Helvetica)\b/g) || []))];
  if (fonts.length) out.push(`gesperrte/Fallback-Schriften erwaehnt: ${fonts.join(', ')}`);

  if (/fonts\.(googleapis|gstatic)\.com/.test(s)) out.push('GOOGLE FONTS');

  const banned = [...new Set((s.match(/#(5A636B|4A535B)\b/gi) || []).map(x => x.toUpperCase()))];
  if (banned.length) out.push(`gesperrte Grautoene: ${banned.join(', ')}`);

  if (emojiRe.test(s)) {
    const e = [...new Set([...s].filter(ch => emojiRe.test(ch)))].slice(0, 8).join(' ');
    out.push(`Emoji/Symbole: ${e}`);
  }

  const ai = [...new Set((s.match(aiPhrases) || []).map(x => x.toLowerCase()))];
  if (ai.length) out.push(`KI-Floskeln: ${ai.join(', ')}`);

  const hosts = [...new Set((s.match(/https?:\/\/[a-z0-9.-]+/gi) || []).map(u => u.replace(/^https?:\/\//, '')))]
    .filter(h => !/^(www\.w3\.org|example\.lu)$/.test(h));
  if (hosts.length) out.push(`externe Hosts: ${hosts.join(', ')}`);

  if (isPage) {
    const skip = /href=["']#(main|inhalt|content)|Zum Inhalt|Skip to/i.test(s);
    const focus = /:focus-visible/.test(s);
    const lang = /documentElement\.lang|<html[^>]*\slang=/i.test(s);
    const rm = /prefers-reduced-motion/.test(s);
    const canvases = (s.match(/<canvas\b[^>]*>/gi) || []);
    const badCanvas = canvases.filter(c => !/role=["']img["']|aria-hidden=["']true["']/i.test(c)).length;
    const a11y = [
      skip ? null : 'keine Sprungmarke',
      focus ? null : 'kein :focus-visible',
      lang ? null : 'html[lang] nicht gesetzt',
      rm ? null : 'kein prefers-reduced-motion',
      badCanvas ? `${badCanvas} von ${canvases.length} Canvas ohne role/aria-hidden` : null,
    ].filter(Boolean);
    if (a11y.length) out.push(`A11y: ${a11y.join('; ')}`);

    // Textfarben unter der Kontrastgrenze auf dem Grundton
    const cols = [...new Set((s.match(/(?<![-\w])color\s*:\s*['"]?#([0-9a-f]{6}|[0-9a-f]{3})\b/gi) || [])
      .map(x => '#' + x.split('#')[1].toUpperCase()))];
    const weak = cols.filter(c => contrast(c, BG) < 4.5).map(c => `${c} (${contrast(c, BG).toFixed(2)}:1)`);
    if (weak.length) out.push(`Textfarben unter 4,5:1 auf ${BG}: ${weak.join(', ')}`);

    const mixedName = (s.match(/>[^<]*\bThe Wall\b[^<]*</g) || []).length;
    if (mixedName) out.push(`"The Wall" statt "THE WALL" im Text: ${mixedName}x`);

    const touchSmall = (s.match(/(?:min-)?height\s*:\s*(\d+)px/g) || [])
      .map(x => +x.match(/(\d+)px/)[1]).filter(h => h >= 20 && h < 44).length;
    if (touchSmall) out.push(`Hoehenangaben zwischen 20 und 43px (Touch-Ziele pruefen): ${touchSmall}`);
  }

  console.log(`\n## ${f}  (${statSync(join(dir, f)).size} B)`);
  console.log(out.length ? out.map(x => '  - ' + x).join('\n') : '  - nichts auffaellig');
}
