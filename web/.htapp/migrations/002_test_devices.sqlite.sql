-- Testgeraete: vom Admin fuer ein Konto angelegt, damit es die Geraeteseite ohne Hardware sieht.
ALTER TABLE devices ADD COLUMN test INTEGER NOT NULL DEFAULT 0;
