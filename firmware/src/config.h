// Feste Werte der Firmware: Version, Pins, Server, Farben.
#pragma once
#include <Arduino.h>

#define FW_VERSION "0.2.1"
// Marke fuer das Hochladen im Admin-Bereich: der Server liest die Version aus der
// Datei selbst ("THEWALL-FW 0.1.7"), statt dem Dateinamen zu glauben.
#define FW_MARKER "THEWALL-FW " FW_VERSION

// Eigener Server: in platformio.ini mit -DWALL_SERVER=\"https://...\" ueberschreiben.
#ifndef WALL_SERVER
#define WALL_SERVER "https://thewall.godart.lu"
#endif

// Host aus WALL_SERVER ohne Schema und Pfad, etwa "thewall.godart.lu". Die Einrichtungsseite
// und die Animation "paired" nennen ihn, damit ein eigener Server nicht den fremden Namen zeigt.
inline String serverHost() {
  String s = WALL_SERVER;
  int i = s.indexOf("://");
  if (i >= 0) s = s.substring(i + 3);
  int j = s.indexOf('/');
  if (j >= 0) s = s.substring(0, j);
  return s;
}

// SEENGREAT RGB Matrix HUB75 S3 V1.0, Belegung laut Hersteller. Die Panel-Bibliothek
// erwartet vierzehn Pins in der Reihenfolge r1, g1, b1, r2, g2, b2, a, b, c, d, e, lat, oe, clk.
constexpr int PIN_R1 = 5;
constexpr int PIN_G1 = 4;
constexpr int PIN_B1 = 6;
constexpr int PIN_R2 = 15;
constexpr int PIN_G2 = 7;
constexpr int PIN_B2 = 17;
constexpr int PIN_A = 8;
constexpr int PIN_B = 18;
constexpr int PIN_C = 10;
constexpr int PIN_D = 9;
constexpr int PIN_E = 16;
constexpr int PIN_LAT = 11;
constexpr int PIN_OE = 13;
constexpr int PIN_CLK = 12;

// I2C-Bus des Boards laut Schaltplan V1.0: Uhrchip PCF85063A und Port-Baustein
// PCA9557, an dem das Drei-Wege-Rad haengt (K1 an IO1, K3 an IO2, K2 an IO3,
// gedrueckt ist 0). Am selben Bus liegen die Audio-Chips, die bleiben unberuehrt.
constexpr int PIN_SDA = 1;
constexpr int PIN_SCL = 2;

// Taste BOOT an IO0, gedrueckt ist 0. Im Betrieb ein gewoehnlicher Eingang: fuenf Sekunden
// halten setzt zurueck wie das Rad. Nur beim Einschalten oder beim Druck auf EN gehalten
// startet der Chip in den Download-Modus, dann bleibt das Panel dunkel bis zum naechsten Start.
constexpr int PIN_BOOT = 0;
// Ton ab 0.2.1: Codec ES8311 (I2C 0x18) an I2S, Verstaerker NS4150B mit Einschalten an IO3,
// ab Start aus. Der Mikrofon-Wandler ES7210 (0x40) bleibt unberuehrt. Versorgt werden beide
// ueber IO5 des Port-Bausteins, ab Start an; die Firmware schaltet daran nichts.
constexpr int PIN_I2S_MCLK = 38;
constexpr int PIN_I2S_BCLK = 48;
constexpr int PIN_I2S_WS = 21;
constexpr int PIN_I2S_DOUT = 14;
constexpr int PIN_AMP_EN = 3;
constexpr uint8_t I2C_RTC = 0x51;
constexpr uint8_t I2C_EXPANDER = 0x19;

// Einrichtungsnetz, fest wie im Entwurf. Das Passwort darf im Code stehen, es soll nur
// verhindern, dass sich jeder Vorbeikommende verbindet (Projektinhaber, 18. September 2026).
constexpr const char *SETUP_SSID = "THE WALL SETUP";
constexpr const char *SETUP_PASS = "12345678";

// Adresse des Geraets im Einrichtungsnetz. Absichtlich keine private Adresse wie
// 192.168.4.1: manche Android-Handys (Samsung) werten eine private Antwort auf ihre
// Internetpruefung als "kein Internet" und oeffnen die Einrichtungsseite nicht.
constexpr uint8_t SETUP_IP[4] = {4, 3, 2, 1};
constexpr const char *SETUP_URL = "http://4.3.2.1/";

// Farben wie in anim.js und frame.php.
constexpr uint32_t C_ACCENT = 0xFFAA00;
constexpr uint32_t C_WHITE = 0xF2F4F5;
constexpr uint32_t C_DIM = 0x8E9AA3;
constexpr uint32_t C_CYAN = 0x35D6FF;
constexpr uint32_t C_GREEN = 0x3DE07C;
constexpr uint32_t C_RED = 0xFF4A1C;
constexpr uint32_t C_LINE = 0x1B2126;
