// Das LED-Panel: FM6126A mit 3-zu-8-Decoder, 128 x 64. Schreibt nur Pixel, die sich
// gegenueber dem letzten Bild geaendert haben.
#pragma once
#include "grid.h"

namespace panel {

bool begin(uint8_t brightness);
void show(const Grid &g);
void setBrightness(uint8_t b);
uint8_t brightness();
// Naechstes show() schreibt alle Pixel neu.
void invalidate();
// Das zuletzt gezeigte Bild, Ausgangspunkt fuer Uebergaenge.
const Grid &frame();

}  // namespace panel
