-- Home Assistant: gekoppelte Instanzen je Geraet (api/ha.php). Der Schluessel steht nur als
-- SHA-256 hier, im Klartext sieht ihn Home Assistant einmal beim Koppeln.
CREATE TABLE ha_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  device_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  name VARCHAR(64) NOT NULL,
  created_at BIGINT NOT NULL,
  last_used_at BIGINT NULL,
  UNIQUE KEY ha_links_token (token_hash),
  KEY ha_links_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Offene Kopplungen: der Code steht fuenf Minuten auf dem Panel, nach fuenf falschen Versuchen
-- ist er weg.
CREATE TABLE ha_pairings (
  id CHAR(32) NOT NULL PRIMARY KEY,
  device_id INT UNSIGNED NOT NULL,
  code CHAR(6) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  created_at BIGINT NOT NULL,
  expires_at BIGINT NOT NULL,
  KEY ha_pairings_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
