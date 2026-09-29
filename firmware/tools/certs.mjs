// Schreibt die Stammzertifikate fuer die Verbindung zum Server nach src/certs.h.
//
// thewall.godart.lu liegt hinter Cloudflare, und Cloudflare stellt Zertifikate
// wechselnd bei Google Trust Services, Let's Encrypt und SSL.com aus. Deshalb
// stehen die Wurzeln aller drei drin, dazu DigiCert und Sectigo (USERTrust).
// Am 14. September 2026 lautete die Kette: godart.lu, WE1, GTS Root R4.
//
// Quelle ist eine cacert.pem im Format von certifi (Mozilla-Stammliste).
// Aufruf aus dem Ordner firmware: node tools/certs.mjs <pfad/zu/cacert.pem>
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const LABELS = [
  'GTS Root R1',
  'GTS Root R3',
  'GTS Root R4',
  'ISRG Root X1',
  'ISRG Root X2',
  'SSL.com TLS RSA Root CA 2022',
  'SSL.com TLS ECC Root CA 2022',
  'SSL.com Root Certification Authority RSA',
  'SSL.com Root Certification Authority ECC',
  'GlobalSign Root R46',
  'GlobalSign Root E46',
  'DigiCert Global Root G2',
  'USERTrust RSA Certification Authority',
  'USERTrust ECC Certification Authority',
];

const source = process.argv[2];
if (!source) {
  console.error('Aufruf: node tools/certs.mjs <pfad/zu/cacert.pem>');
  process.exit(1);
}
const here = path.dirname(fileURLToPath(import.meta.url));
const out = path.resolve(here, '../src/certs.h');
const text = fs.readFileSync(source, 'utf8').replace(/\r\n/g, '\n');

const blocks = [];
for (const label of LABELS) {
  const at = text.indexOf(`# Label: "${label}"\n`);
  if (at < 0) throw new Error('Nicht gefunden: ' + label);
  const begin = text.indexOf('-----BEGIN CERTIFICATE-----', at);
  const end = text.indexOf('-----END CERTIFICATE-----', begin) + '-----END CERTIFICATE-----'.length;
  blocks.push(`// ${label}\n` + text.slice(begin, end).split('\n').map((l) => `"${l}\\n"`).join('\n'));
}

fs.writeFileSync(
  out,
  `// Erzeugt von tools/certs.mjs, nicht von Hand aendern. Stammzertifikate fuer den Server.
#pragma once

static const char ROOT_CERTS[] =
${blocks.join('\n')};
`
);
console.log(`${blocks.length} Stammzertifikate nach ${path.relative(process.cwd(), out)}`);
