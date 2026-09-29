-- Home Assistant: gekoppelte Instanzen je Geraet (api/ha.php). Der Schluessel steht nur als
-- SHA-256 hier, im Klartext sieht ihn Home Assistant einmal beim Koppeln.
CREATE TABLE ha_links (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  device_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  created_at INTEGER NOT NULL,
  last_used_at INTEGER NULL
);
CREATE INDEX ha_links_device ON ha_links (device_id);

-- Offene Kopplungen: der Code steht fuenf Minuten auf dem Panel, nach fuenf falschen Versuchen
-- ist er weg.
CREATE TABLE ha_pairings (
  id TEXT PRIMARY KEY,
  device_id INTEGER NOT NULL,
  code TEXT NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL,
  expires_at INTEGER NOT NULL
);
CREATE INDEX ha_pairings_device ON ha_pairings (device_id);
