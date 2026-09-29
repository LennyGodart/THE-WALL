// Bauteile auf dem Board neben dem Panel: Uhrchip und Drei-Wege-Rad.
#pragma once
#include <Arduino.h>
#include <time.h>

namespace board {

void begin();

// Uhrchip PCF85063A, gespeichert wird UTC. read() ist false ohne Chip oder wenn der
// Oszillator stand, etwa nach einem Stromausfall ohne Pufferbatterie an J1.
bool rtcPresent();
bool rtcRead(time_t &utc);
bool rtcWrite(time_t utc);

// Gedrueckte Tasten des Rads als Bits: 1 K1, 2 K2, 4 K3. 0 ohne Port-Baustein.
bool wheelPresent();
uint8_t wheel();

// Adressen, die am I2C-Bus antworten, fuer den seriellen Befehl "i2c".
String scan();

}  // namespace board
