#include "panel.h"

#include <ESP32-HUB75-MatrixPanel-I2S-DMA.h>

#include "config.h"

namespace {
MatrixPanel_I2S_DMA *dmd = nullptr;
Grid shown;
uint8_t level = 0;
}  // namespace

namespace panel {

bool begin(uint8_t b) {
  if (!shown.begin()) return false;
  HUB75_I2S_CFG::i2s_pins pins = {PIN_R1, PIN_G1, PIN_B1, PIN_R2, PIN_G2, PIN_B2, PIN_A,
                                  PIN_B,  PIN_C,  PIN_D,  PIN_E,  PIN_LAT, PIN_OE, PIN_CLK};
  HUB75_I2S_CFG cfg(Grid::W, Grid::H, 1, pins);
  // Auf dem Panel QiangLi Q2.5 mit DP32019A und DP5125D geprueft, siehe hardware/README.md.
  cfg.driver = HUB75_I2S_CFG::FM6126A;
  cfg.line_decoder = HUB75_I2S_CFG::TYPE138;
  dmd = new MatrixPanel_I2S_DMA(cfg);
  if (!dmd->begin()) {
    delete dmd;
    dmd = nullptr;
    return false;
  }
  level = b;
  dmd->setBrightness8(b);
  dmd->clearScreen();
  shown.clear();
  return true;
}

void show(const Grid &g) {
  if (!dmd) return;
  for (int i = 0; i < Grid::N; i++) {
    uint32_t c = g.px[i];
    if (c == shown.px[i]) continue;
    shown.px[i] = c;
    dmd->drawPixelRGB888(i % Grid::W, i / Grid::W, (c >> 16) & 0xFF, (c >> 8) & 0xFF, c & 0xFF);
  }
}

void setBrightness(uint8_t b) {
  if (!dmd || b == level) return;
  level = b;
  dmd->setBrightness8(b);
}

uint8_t brightness() { return level; }

const Grid &frame() { return shown; }

void invalidate() {
  // Ein Wert, den kein Bild enthaelt, erzwingt das Neuschreiben.
  for (int i = 0; i < Grid::N; i++) shown.px[i] = 0xFF000000;
}

}  // namespace panel
