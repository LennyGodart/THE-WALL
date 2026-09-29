// Die Antwort des Servers: Seiten mit Zeitfenstern und die vierzehn Zeichenbefehle,
// gezeichnet wie web/assets/js/lib/ops.js. Dazu der Speicher fuer Airline-Logos.
#pragma once
#include <ArduinoJson.h>
#include <time.h>

#include "grid.h"

namespace live {

struct Ctx {
  int64_t nowMs;            // Millisekunden seit 1970
  const struct tm *local;   // Ortszeit, nullptr ohne gueltige Uhrzeit
  bool de;
  bool flash;               // neue Notiz: Textzeilen gerade weiss, Rahmen in Bernstein
  uint32_t millisNow;       // fuer den Takt der Animationen
  bool reduce;              // Bewegung reduzieren: Laufschrift steht, Animationen im Endzustand
  uint8_t bright;           // Helligkeit des Panels, fuer die Animationen (dunkle Toene nachts heller)
};

bool begin();

// Uebernimmt die Antwort. Die vorige wird freigegeben.
void setFrame(JsonDocument *doc);
JsonDocument *frame();

// Laufende Seite wie pageAt() in ops.js, isNull() ohne Antwort.
JsonObjectConst pageAt(int64_t nowMs);
// Die Seiten reichen noch bis jetzt, mit fuenf Sekunden Nachlauf.
bool covers(int64_t nowMs);

void render(Grid &g, JsonObjectConst page, const Ctx &ctx);

// Logos aller Seiten einer neuen Antwort vormerken. Die Seiten reichen 25 Sekunden
// voraus, so ist ein Logo meist geladen, bevor seine Seite dran ist.
void prefetchLogos(const JsonDocument &doc);
// Logos: ein Kuerzel, das vom Server geholt werden soll, oder nullptr. Nach einem Fehler
// wartet ein Logo, erst eine Minute, dann doppelt so lange, hoechstens eine Stunde.
const char *wantedLogo();
void logoLoading(const char *code);
void logoArrived(const char *code, int status, uint8_t *rgb);
// Prozent belegter Dateispeicher fuer X-Wall-Flash.
int storageUsedPercent();

// Spotify ab 0.2.0. Zeitleiste (prog) zeichnen, mit over >= 0 in erzwungener Breite: so laeuft
// der alte Balken im Uebergang leer. progWidth ist die Breite zum Zeitpunkt nowMs.
void drawProg(Grid &g, JsonObjectConst op, int64_t nowMs, int over = -1);
int progWidth(JsonObjectConst op, int64_t nowMs);
// Kopien der gezeigten und der vorigen Seite. Im Uebergang wird die vorige weiter gezeichnet,
// auch wenn inzwischen eine neue Antwort kam und die alte Antwort weg ist.
void keepShown(JsonObjectConst page);
void keepOld();
JsonObjectConst oldPage();
// Erster Befehl einer Art auf einer Seite, isNull() ohne.
JsonObjectConst findOp(JsonObjectConst page, const char *t);

}  // namespace live
