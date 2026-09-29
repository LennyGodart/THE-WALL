-- THE WALL, erste Fassung der Tabellen fuer MySQL und MariaDB.
-- Zeiten sind Unix-Sekunden. Texte utf8mb4.

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(20) NOT NULL,
  email VARCHAR(190) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'user',
  api_key VARCHAR(24) NOT NULL,
  lang VARCHAR(2) NOT NULL DEFAULT 'en',
  email_verified_at BIGINT NULL,
  created_at BIGINT NOT NULL,
  updated_at BIGINT NOT NULL,
  UNIQUE KEY users_username (username),
  UNIQUE KEY users_email (email),
  UNIQUE KEY users_api_key (api_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
  id CHAR(64) NOT NULL PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  csrf CHAR(64) NOT NULL,
  created_at BIGINT NOT NULL,
  last_seen_at BIGINT NOT NULL,
  expires_at BIGINT NOT NULL,
  KEY sessions_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(16) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  user_id INT UNSIGNED NULL,
  email VARCHAR(190) NULL,
  device_id INT UNSIGNED NULL,
  rights VARCHAR(8) NULL,
  created_by INT UNSIGNED NULL,
  created_at BIGINT NOT NULL,
  expires_at BIGINT NOT NULL,
  used_at BIGINT NULL,
  UNIQUE KEY tokens_hash (token_hash),
  KEY tokens_device (device_id),
  KEY tokens_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE devices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  uid VARCHAR(32) NOT NULL,
  name VARCHAR(40) NOT NULL,
  settings MEDIUMTEXT NOT NULL,
  settings_rev INT UNSIGNED NOT NULL DEFAULT 1,
  note_rev INT UNSIGNED NOT NULL DEFAULT 0,
  fw VARCHAR(16) NULL,
  ssid VARCHAR(32) NULL,
  rssi INT NULL,
  uptime BIGINT NULL,
  temp INT NULL,
  flash INT NULL,
  restarts INT NULL,
  update_requested_at BIGINT NULL,
  last_seen_at BIGINT NULL,
  created_at BIGINT NOT NULL,
  updated_at BIGINT NOT NULL,
  UNIQUE KEY devices_owner_uid (owner_id, uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shares (
  device_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  rights VARCHAR(8) NOT NULL DEFAULT 'view',
  created_at BIGINT NOT NULL,
  PRIMARY KEY (device_id, user_id),
  KEY shares_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NOT NULL,
  updated_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache (
  k VARCHAR(190) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NOT NULL,
  created_at BIGINT NOT NULL,
  expires_at BIGINT NOT NULL,
  KEY cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE callsigns (
  cs VARCHAR(12) NOT NULL PRIMARY KEY,
  data MEDIUMTEXT NULL,
  found TINYINT NOT NULL DEFAULT 0,
  fetched_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE budget (
  service VARCHAR(16) NOT NULL,
  hour BIGINT NOT NULL,
  requests INT UNSIGNED NOT NULL DEFAULT 0,
  cached INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (service, hour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  at BIGINT NOT NULL,
  source VARCHAR(16) NOT NULL,
  message_en VARCHAR(255) NOT NULL,
  message_de VARCHAR(255) NOT NULL,
  KEY events_at (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  k VARCHAR(190) NOT NULL PRIMARY KEY,
  window_start BIGINT NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE firmware (
  version VARCHAR(16) NOT NULL PRIMARY KEY,
  file VARCHAR(190) NOT NULL,
  sha256 CHAR(64) NOT NULL,
  size INT UNSIGNED NOT NULL,
  created_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
