-- Einfuehrung ins Geraet: wann das Konto sie gesehen oder uebersprungen hat. NULL heisst noch nie,
-- dann oeffnet die Geraeteseite sie fuer den Besitzer von selbst.
ALTER TABLE users ADD COLUMN tour_seen_at BIGINT NULL;
