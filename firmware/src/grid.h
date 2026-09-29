// Raster von 128 x 64 Pixeln, Farben als 0xRRGGBB, 0 heisst aus. Zeichnet wie
// pixelfont.js auf der Webseite: Glyphen 5 x 7, 6 Pixel Vorschub je Zeichen.
#pragma once
#include <Arduino.h>

class Grid {
 public:
  static constexpr int W = 128;
  static constexpr int H = 64;
  static constexpr int N = W * H;

  uint32_t *px = nullptr;

  // Speicher im PSRAM holen. Vor der ersten Benutzung aufrufen.
  bool begin();
  void clear();
  void set(int x, int y, uint32_t c);
  uint32_t get(int x, int y) const;
  void rect(int x, int y, int w, int h, uint32_t c);
  void frame(int x, int y, int w, int h, uint32_t c);
  // Text in UTF-8. Ohne mixed in Grossbuchstaben wie PX.text(). Gibt die Breite zurueck.
  int text(int x, int y, const char *s, uint32_t c, int scale = 1, bool mixed = false);
  void copyFrom(const Grid &o);

  // Breite wie PX.width(): Zeichen mal 6 mal scale, minus scale.
  static int width(const char *s, int scale = 1);
  // Links-Versatz fuer mittigen Text wie centre() in anim.js.
  static int centreX(const char *s, int scale = 1);
};

// Math.round aus JavaScript: .5 rundet nach oben.
inline int jround(double v) { return (int)floor(v + 0.5); }

// Hex-Farbe ohne # wie "FFAA00". Ungueltig gibt fallback.
uint32_t parseColour(const char *hex, uint32_t fallback);
