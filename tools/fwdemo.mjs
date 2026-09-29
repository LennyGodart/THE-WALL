// Holt die vier naechsten Flugzeuge um Luxemburg-Stadt von adsb.lol und adsbdb und zeigt sie im Textraster.
// Aufruf: node tools/fwdemo.mjs <kontakt-mail>   Beide Dienste verlangen einen User-Agent mit Kontakt.
const CONTACT = process.argv[2];
if (!CONTACT) {
  console.error('Aufruf: node tools/fwdemo.mjs <kontakt-mail>');
  process.exit(1);
}

const LAT = 49.6116, LON = 6.1319, NM = 40;
const UA = { headers: { "User-Agent": `THE-WALL/0.1 (hobby LED display; ${CONTACT})` } };
const j = async (u) => (await fetch(u, UA)).json();

const near = await j(`https://api.adsb.lol/v2/point/${LAT}/${LON}/${NM}`);
const acs = (near.ac || []).filter(a => a.flight && a.lat).sort((x, y) => x.dst - y.dst).slice(0, 4);

const pad = (s, n) => String(s).slice(0, n);
for (const a of acs) {
  const cs = a.flight.trim();
  let route = null, airline = null;
  try {
    const r = await j(`https://api.adsbdb.com/v0/callsign/${cs}`);
    route = r?.response?.flightroute; airline = route?.airline;
  } catch {}
  const alt = a.alt_baro === 'ground' ? 'GND' : `${Math.round(a.alt_baro)}ft`;
  const spd = `${Math.round(a.gs * 1.15078)}mph`;
  const vr  = `${a.baro_rate > 0 ? '+' : ''}${Math.round((a.baro_rate || 0) / 60)}ft/s`;
  console.log('+--------------------------------+  128x64');
  console.log('| ' + pad(airline?.name || cs, 21).padEnd(21) + '        |');
  console.log('| ' + pad(route ? `${route.origin.iata_code}-${route.destination.iata_code}` : '--', 21).padEnd(21) + '        |');
  console.log('| ' + pad(a.t || '?', 21).padEnd(21) + '        |');
  console.log('| ' + pad(`Alt:${alt},Spd:${spd}`, 21).padEnd(21) + '        |');
  console.log('| ' + pad(`Trk:${Math.round(a.track)}deg,Vr:${vr}`, 21).padEnd(21) + '        |');
  console.log('|  ' + pad(route ? `${route.origin.municipality} > ${route.destination.municipality}` : '', 29).padEnd(29) + '   |');
  console.log(`+--------------------------------+  ${a.dst.toFixed(1)}nm, ${cs}`);
  console.log('');
}
