// Die dreizehn Geraete-Animationen aus web/assets/js/lib/anim.js, dazu pixeldemo.
// Jede ist eine Funktion der verstrichenen Zeit, nichts wird gespeichert.
//
// Wo die Vorlage Platzhalter zeigt (HEIMNETZ-2G, 192.168.1.42, LENNY, 0.5.0),
// setzt die Firmware die echten Werte aus AnimOpts ein. Erfundene Daten wie ein
// Rufzeichen oder "14 FLUGZEUGE NAH" zeigt das Geraet nicht.
#pragma once
#include <time.h>

#include "grid.h"

struct AnimOpts {
  bool de = false;
  bool reduce = false;
  bool h24 = true;
  uint8_t bright = 255;            // Helligkeit des Panels; darunter 96 werden dunkle Toene heller
  bool phone = false;              // wifi: ein Handy ist im Einrichtungsnetz
  const struct tm *now = nullptr;  // Ortszeit, nullptr ohne gueltige Uhrzeit
  const char *ssid = nullptr;
  const char *ip = nullptr;
  const char *status = nullptr;    // untere Zeile bei address
  const char *user = nullptr;
  const char *device = nullptr;
  const char *version = nullptr;
  float progress = -1;             // 0 bis 1, sonst aus der Zeit gerechnet
};

struct AnimInfo {
  const char *key;
  float dur;
  bool loop;
};

namespace anim {

bool begin();
const AnimInfo *find(const char *key);
int count();
const AnimInfo &at(int i);

// Zeichnet auf g, ohne vorher zu loeschen. t in Sekunden seit Beginn. Schleifen laufen
// ueber die Dauer, resting rechnet selbst mit der ganzen Zeit. false bei unbekanntem Namen.
bool draw(const char *key, Grid &g, double t, const AnimOpts &o);

// Uebergang wie modeswap: from rutscht nach links, to kommt nach. k von 0 bis 1.
void push(Grid &g, const Grid &from, const Grid &to, double k);

// Flugwechsel in 400 ms: rechts oben (x ab 38, y bis 37) rutscht der alte Flug nach oben
// hinaus und der neue von unten nach, Logo und untere Zeilen gehen linear ineinander
// ueber, der Balken (y 61 und 62) ist sofort neu. Jedes Pixel aendert sich einmal.
void swap(Grid &g, const Grid &from, const Grid &to, double k);

// Minutensprung auf der Abfahrtstafel in 240 ms: faellt eine Abfahrt weg, ruecken die Zeilen
// nach (erkannt an Linie und Ziel links von x 70), sonst rollen in jeder Zeile ab bands[i] nur die
// Spalten, die sich aendern, der alte Inhalt nach oben hinaus, der neue von unten nach.
void roll(Grid &g, const Grid &from, const Grid &to, const int *bands, int n, double k);

// Einstieg in die Abfahrtstafel wie im Entwurf (Transit.dc.html, sceneEnter): Kopf und
// Streifen sofort, die Zeilen ab bands[i] fallen nacheinander ein. Solange eine Zeile
// rutscht, steht nur ihre Linie in gedimmtem Bernstein da. k von 0 bis 1 ueber 1,1 s.
void drop(Grid &g, const Grid &to, const int *bands, int n, double k);

// Kurven wie auf der Webseite: cubic-bezier(0.77, 0, 0.175, 1) fuer Bewegung auf der Flaeche,
// cubic-bezier(0.23, 1, 0.32, 1) fuer das, was einrollt. Gleiche Rechnung wie in anim.js.
double eio(double x);
double eout(double x);

// Fenster eines Spotify-Uebergangs, aus fxw der Seite (ab 0.2.0): Cover c, Text t, Zeitleiste b
// als x0, y0, x1, y1; die Oberkanten der Textzeilen, ihr Abstand, und ob der Textblock nach der
// Ansage als Ganzes eine Zeile hoch schiebt (shift).
struct SongWin {
  bool hasC = false, hasB = false, shift = false;
  int c[4] = {0, 0, 0, 0}, t[4] = {0, 0, 0, 0}, b[4] = {0, 0, 0, 0};
  int lines[6] = {0, 0, 0, 0, 0, 0};
  int nLines = 0;
  int pitch = 12;
};

// Walze (carousel false) oder Karussell in 560 ms: das Cover-Fenster rollt senkrecht oder
// waagerecht, der Text folgt 120 ms spaeter, je Zeile 40 ms versetzt, oder als Block eine Zeile
// hoch. Die Zeitleiste zeichnet main.cpp: dort laeuft der alte Balken leer. Wie song() in anim.js.
void song(Grid &g, const Grid &from, const Grid &to, const SongWin &w, double k, bool carousel);
// Ansage in 400 ms: alles steht sofort, nur die Textzeilen rollen einzeln herein.
void lines(Grid &g, const Grid &from, const Grid &to, const SongWin &w, double k);

}  // namespace anim
