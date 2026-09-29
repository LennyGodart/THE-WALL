-- Spotify: Verbindung je Konto. Zugangs- und Erneuerungs-Token verschluesselt (seal), wie der
-- SMTP-Schluessel. error: not_allowed (Konto im Entwicklungsmodus nicht freigeschaltet) oder
-- revoked (Zugang in Spotify widerrufen, neu verbinden).
CREATE TABLE spotify_links (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  spotify_id VARCHAR(64) NOT NULL,
  display_name VARCHAR(190) NULL,
  refresh_token TEXT NOT NULL,
  access_token TEXT NULL,
  expires_at BIGINT NOT NULL DEFAULT 0,
  scope VARCHAR(255) NULL,
  created_at BIGINT NOT NULL,
  updated_at BIGINT NOT NULL,
  error VARCHAR(16) NULL,
  error_at BIGINT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
