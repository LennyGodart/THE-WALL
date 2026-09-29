// Ton ueber den Codec ES8311, den Verstaerker NS4150B und den Lautsprecher an J2. Nur Toene
// aus der Firmware, keine Dateien: Klingeln fuer Timer und Wecker und ein Testton. Gerechnet
// wird in einer eigenen Aufgabe, loop() wartet nie auf den Ton.
#pragma once
#include <Arduino.h>

namespace audio {

enum class Tone : uint8_t { Off, Timer, Alarm, Test };

// I2S starten und den Codec einrichten, der I2C-Bus laeuft schon (board::begin()). false,
// wenn der Codec nicht antwortet: dann bleibt das Geraet still, alles andere geht weiter.
bool begin();
bool present();
// Chip-ID des Codecs fuer den seriellen Befehl "audio", 0x8311 wenn er da ist.
uint16_t chipId();
// Klingeln starten oder beenden. Test spielt einmal und endet von selbst.
void play(Tone t);
Tone playing();

}  // namespace audio
