// Webserver des Geraets: die Einrichtungsseite und ihre kleine JSON-Schnittstelle.
// Laeuft im Einrichtungsnetz und im Heimnetz. Aenderungen nehmen nur Anfragen mit
// JSON an, deren Host eine IP-Adresse ist, damit keine fremde Webseite im selben
// Netz die Einstellungen umschreiben kann.
#pragma once
#include <Arduino.h>

#include <functional>

namespace portal {

struct Hooks {
  std::function<String()> state;                                 // JSON fuer /api/state
  std::function<void(const String &, const String &)> wifi;      // Netzname, Passwort
  std::function<void(const String &)> key;
  std::function<void(uint8_t)> bright;
  std::function<void(bool)> clock;
  std::function<void()> test;
  std::function<void(const String &)> lang;
};

void begin(const Hooks &hooks);
void loop();

}  // namespace portal
