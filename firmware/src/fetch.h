// Abrufe beim Server in einer eigenen Aufgabe auf dem zweiten Kern, damit das
// Panel waehrend eines TLS-Handshakes weiterlaeuft. Ergebnisse holt loop() ab.
#pragma once
#include <Arduino.h>
#include <ArduinoJson.h>

namespace fetch {

struct Telemetry {
  char ssid[33];
  int rssi;
  uint32_t uptime;
  int temp;
  int flash;
  uint32_t restarts;
  char fwBad[16];       // zurueckgerollte Version, leer ohne; geht als X-Wall-Fw-Bad mit
};

struct FrameResult {
  int status;           // HTTP-Status; 0 Netzfehler, -1 Antwort kein JSON
  JsonDocument *doc;    // bei 200, gehoert danach dem Empfaenger
  uint32_t rttMs;       // Senden bis Kopfzeilen
  uint32_t arrivedAt;   // millis() beim Eintreffen der Kopfzeilen
  int retryAfter;       // Sekunden, bei 429
  bool reused;          // ueber eine offene Verbindung, ohne neuen Handshake
};

struct RevResult {
  int status;
  char rev[32];         // bei 200, etwa "12.3.0"
  uint32_t rttMs;
  int retryAfter;
};

struct LogoResult {
  char code[4];
  int status;
  uint8_t *rgb;         // 32 x 34 x 3 Byte bei 200, gehoert danach dem Empfaenger
};

enum class Ota : uint8_t { Idle, Running, Done, Failed };

constexpr size_t LOGO_BYTES = 32 * 34 * 3;

bool begin();

bool requestFrame(const String &key, const String &id, const Telemetry &t);
// Revision der Einstellungen, alle zwei Sekunden. Passt sie nicht zur letzten Antwort,
// holt loop() sofort einen neuen Frame.
bool requestRev(const String &key, const String &id);
bool requestLogo(const String &key, const char *code);
bool requestFirmware(const String &key, const String &id, const String &url, const String &sha256, uint32_t size);
// Ab 0.2.1: das Rad hat das Klingeln gestoppt, POST /api/v1/ring/stop. Ohne Antwort an loop(),
// der naechste Frame zeigt, ob es angekommen ist.
bool requestRingStop(const String &key, const String &id);

bool frameBusy();
bool logoBusy();
bool revBusy();
bool takeFrame(FrameResult &out);
bool takeLogo(LogoResult &out);
bool takeRev(RevResult &out);

Ota otaState();
int otaPermille();
String otaError();

// Letzter Fehler beim Abruf, fuer den seriellen Befehl "status".
String lastError();

}  // namespace fetch
