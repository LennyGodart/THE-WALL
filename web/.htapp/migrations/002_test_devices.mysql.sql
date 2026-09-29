-- Testgeraete: vom Admin fuer ein Konto angelegt, damit es die Geraeteseite ohne Hardware sieht.
ALTER TABLE devices ADD COLUMN test TINYINT NOT NULL DEFAULT 0;
