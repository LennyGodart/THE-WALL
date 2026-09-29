-- THE WALL, erste Fassung der Tabellen fuer SQLite (lokale Tests).
-- Muss inhaltlich 001_init.mysql.sql entsprechen.

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL UNIQUE,
  pass_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'user',
  api_key TEXT NOT NULL UNIQUE,
  lang TEXT NOT NULL DEFAULT 'en',
  email_verified_at INTEGER NULL,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);

CREATE TABLE sessions (
  id TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL,
  csrf TEXT NOT NULL,
  created_at INTEGER NOT NULL,
  last_seen_at INTEGER NOT NULL,
  expires_at INTEGER NOT NULL
);
CREATE INDEX sessions_user ON sessions (user_id);

CREATE TABLE tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kind TEXT NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  user_id INTEGER NULL,
  email TEXT NULL,
  device_id INTEGER NULL,
  rights TEXT NULL,
  created_by INTEGER NULL,
  created_at INTEGER NOT NULL,
  expires_at INTEGER NOT NULL,
  used_at INTEGER NULL
);
CREATE INDEX tokens_device ON tokens (device_id);
CREATE INDEX tokens_user ON tokens (user_id);

CREATE TABLE devices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  owner_id INTEGER NOT NULL,
  uid TEXT NOT NULL,
  name TEXT NOT NULL,
  settings TEXT NOT NULL,
  settings_rev INTEGER NOT NULL DEFAULT 1,
  note_rev INTEGER NOT NULL DEFAULT 0,
  fw TEXT NULL,
  ssid TEXT NULL,
  rssi INTEGER NULL,
  uptime INTEGER NULL,
  temp INTEGER NULL,
  flash INTEGER NULL,
  restarts INTEGER NULL,
  update_requested_at INTEGER NULL,
  last_seen_at INTEGER NULL,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  UNIQUE (owner_id, uid)
);

CREATE TABLE shares (
  device_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  rights TEXT NOT NULL DEFAULT 'view',
  created_at INTEGER NOT NULL,
  PRIMARY KEY (device_id, user_id)
);
CREATE INDEX shares_user ON shares (user_id);

CREATE TABLE settings (
  k TEXT PRIMARY KEY,
  v TEXT NOT NULL,
  updated_at INTEGER NOT NULL
);

CREATE TABLE cache (
  k TEXT PRIMARY KEY,
  v TEXT NOT NULL,
  created_at INTEGER NOT NULL,
  expires_at INTEGER NOT NULL
);
CREATE INDEX cache_expires ON cache (expires_at);

CREATE TABLE callsigns (
  cs TEXT PRIMARY KEY,
  data TEXT NULL,
  found INTEGER NOT NULL DEFAULT 0,
  fetched_at INTEGER NOT NULL
);

CREATE TABLE budget (
  service TEXT NOT NULL,
  hour INTEGER NOT NULL,
  requests INTEGER NOT NULL DEFAULT 0,
  cached INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (service, hour)
);

CREATE TABLE events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  at INTEGER NOT NULL,
  source TEXT NOT NULL,
  message_en TEXT NOT NULL,
  message_de TEXT NOT NULL
);
CREATE INDEX events_at ON events (at);

CREATE TABLE rate_limits (
  k TEXT PRIMARY KEY,
  window_start INTEGER NOT NULL,
  hits INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE firmware (
  version TEXT PRIMARY KEY,
  file TEXT NOT NULL,
  sha256 TEXT NOT NULL,
  size INTEGER NOT NULL,
  created_at INTEGER NOT NULL
);
