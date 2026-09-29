// Einstellungen im NVS unter "wall". Geschrieben wird nur, wenn sich ein Wert aendert.
#pragma once
#include <Arduino.h>

struct Settings {
  String ssid;
  String pass;
  String key;       // tw_live_ und 16 Hex-Zeichen, verlaesst das Geraet nur zum Server
  String tz;        // POSIX, vom Server; Vorgabe Luxemburg
  String lang;      // "de" oder "en", vom Server oder von der Geraeteseite
  uint8_t bright;   // Helligkeit, bis der Server eine schickt
  bool clock;       // Uhr zeigen, solange kein Konto verbunden ist
  bool reduce;      // Bewegung reduzieren, vom Server ("motion":"reduce"), gilt auch offline
  int32_t noteRev;  // zuletzt angezeigte Notiz, damit sie nach einem Neustart nicht erneut blinkt
  uint32_t restarts;
  String otaBad;    // Version, die nicht anlief und zurueckgerollt wurde, leer ohne
  // Wecker vom Server (ab 0.2.1), damit er auch ohne Server klingelt: Uhrzeit, Tage als Bits
  // (Bit 0 Montag bis Bit 6 Sonntag), gestoppt (ack) und gestellt (set) in Sekunden seit 1970.
  bool alarmOn;
  uint8_t alarmH, alarmM, alarmDays;
  uint32_t alarmAck, alarmSet;
};

namespace settings {

constexpr const char *DEFAULT_TZ = "CET-1CEST,M3.5.0,M10.5.0/3";

void begin();
const Settings &get();
void setWifi(const String &ssid, const String &pass);
void setKey(const String &key);
void setTz(const String &tz);
void setLang(const String &lang);
void setBright(uint8_t b);
void setClock(bool on);
void setReduce(bool on);
void setNoteRev(int32_t rev);
// Wecker, geschrieben nur, wenn sich etwas aendert.
void setAlarm(bool on, uint8_t h, uint8_t m, uint8_t days, uint32_t ack, uint32_t set);
// WLAN und Schluessel vergessen, der Rest bleibt.
void forget();

// EN zweimal: der erste Start setzt die Marke, der zweite findet sie.
bool resetArmed();
void setResetArmed(bool armed);
// Lief der vorige Start laenger als 10 Sekunden? Nur dann oeffnet das Fenster fuer EN.
bool lastRunLong();
void setLastRunLong(bool longRun);

// Gruende der letzten zehn Starts, neuester zuerst, etwa "17:Neustart/12,16:USB/21".
// Die Zahl hinter dem Schraegstrich ist der Grund laut ROM (15 Unterspannung, 21 USB).
String resetLog();
void noteReset(uint32_t number, const char *reason);

// Frisch installierte Firmware: Startversuche zaehlen, bis sie 60 Sekunden laeuft.
bool otaFresh();
void setOtaFresh(bool fresh);
uint8_t bootTries();
void setBootTries(uint8_t n);
// Diese Version nie wieder installieren, bis eine andere neu installierte stabil lief.
void setOtaBad(const String &version);

}  // namespace settings
