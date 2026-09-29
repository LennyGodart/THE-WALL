// WLAN: Heimnetz als Station, Einrichtungsnetz als Access Point mit DNS, der jede
// Adresse auf das Geraet zeigt, damit das Handy die Seite von selbst oeffnet.
#pragma once
#include <Arduino.h>
#include <IPAddress.h>

namespace wlan {

enum class State : uint8_t { Idle, Joining, Up, Wrong, NotFound, Failed };

// So lange darf ein Verbindungsversuch dauern, mit Wiederholungen.
constexpr uint32_t JOIN_TIMEOUT_MS = 30000;

void begin(const String &hostname);
void loop();

// Verbindungsversuch mit dem Heimnetz. Ein laufender wird abgebrochen.
void join(const String &ssid, const String &pass);
State state();
// Millisekunden seit dem letzten Zustandswechsel.
uint32_t stateAge();
// Seit dem Start schon einmal verbunden gewesen.
bool everUp();
String ssid();
int rssi();
IPAddress ip();

void setAp(bool on);
bool apOn();
// Einrichtungsnetz laut Rueckmeldung des WLAN-Treibers mit WPA2 und Passwort.
bool apSecured();
IPAddress apIp();

// Netzsuche, laeuft im Hintergrund. Ergebnis als JSON-Liste [{ssid, rssi, open}].
void startScan();
bool scanning();
String scanJson();

}  // namespace wlan
