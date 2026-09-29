-- Spotify: Verbindung je Konto. Zugangs- und Erneuerungs-Token verschluesselt (seal), wie der
-- SMTP-Schluessel. error: not_allowed (Konto im Entwicklungsmodus nicht freigeschaltet) oder
-- revoked (Zugang in Spotify widerrufen, neu verbinden).
CREATE TABLE spotify_links (
  user_id INTEGER PRIMARY KEY,
  spotify_id TEXT NOT NULL,
  display_name TEXT NULL,
  refresh_token TEXT NOT NULL,
  access_token TEXT NULL,
  expires_at INTEGER NOT NULL DEFAULT 0,
  scope TEXT NULL,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  error TEXT NULL,
  error_at INTEGER NULL
);
