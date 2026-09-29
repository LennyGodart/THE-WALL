#include "grid.h"

#include "glyphs.h"

namespace {

// Naechster Codepunkt aus UTF-8. Kaputte Folgen geben U+FFFD und laufen weiter.
uint32_t nextCp(const char *&p) {
  uint8_t c = (uint8_t)*p++;
  if (c < 0x80) return c;
  if ((c & 0xE0) == 0xC0 && ((uint8_t)p[0] & 0xC0) == 0x80) {
    uint32_t cp = ((c & 0x1F) << 6) | ((uint8_t)p[0] & 0x3F);
    p += 1;
    return cp;
  }
  if ((c & 0xF0) == 0xE0 && ((uint8_t)p[0] & 0xC0) == 0x80 && ((uint8_t)p[1] & 0xC0) == 0x80) {
    uint32_t cp = ((c & 0x0F) << 12) | (((uint8_t)p[0] & 0x3F) << 6) | ((uint8_t)p[1] & 0x3F);
    p += 2;
    return cp;
  }
  if ((c & 0xF8) == 0xF0 && ((uint8_t)p[0] & 0xC0) == 0x80 && ((uint8_t)p[1] & 0xC0) == 0x80 &&
      ((uint8_t)p[2] & 0xC0) == 0x80) {
    p += 3;
    return 0xFFFD;
  }
  return 0xFFFD;
}

uint32_t upper(uint32_t cp) {
  if (cp >= 'a' && cp <= 'z') return cp - 32;
  // Latin-1: a mit Umlaut bis y mit Akzent, ohne das Divisionszeichen.
  if (cp >= 0xE0 && cp <= 0xFE && cp != 0xF7) return cp - 0x20;
  return cp;
}

const Glyph *findGlyph(uint32_t cp) {
  int lo = 0, hi = GLYPH_COUNT - 1;
  while (lo <= hi) {
    int mid = (lo + hi) / 2;
    if (GLYPHS[mid].cp == cp) return &GLYPHS[mid];
    if (GLYPHS[mid].cp < cp)
      lo = mid + 1;
    else
      hi = mid - 1;
  }
  return nullptr;
}

}  // namespace

bool Grid::begin() {
  if (px) return true;
  px = (uint32_t *)heap_caps_calloc(N, sizeof(uint32_t), MALLOC_CAP_SPIRAM);
  if (!px) px = (uint32_t *)calloc(N, sizeof(uint32_t));
  return px != nullptr;
}

void Grid::clear() { memset(px, 0, N * sizeof(uint32_t)); }

void Grid::set(int x, int y, uint32_t c) {
  if (x < 0 || y < 0 || x >= W || y >= H) return;
  px[y * W + x] = c;
}

uint32_t Grid::get(int x, int y) const {
  if (x < 0 || y < 0 || x >= W || y >= H) return 0;
  return px[y * W + x];
}

void Grid::rect(int x, int y, int w, int h, uint32_t c) {
  int x0 = max(x, 0), y0 = max(y, 0);
  int x1 = min(x + w, W), y1 = min(y + h, H);
  for (int yy = y0; yy < y1; yy++)
    for (int xx = x0; xx < x1; xx++) px[yy * W + xx] = c;
}

void Grid::frame(int x, int y, int w, int h, uint32_t c) {
  if (w <= 0 || h <= 0) return;
  // Wie PX.frame, aber ohne Schleife ueber Pixel ausserhalb des Panels.
  for (int xx = max(x, 0); xx < min(x + w, W); xx++) {
    set(xx, y, c);
    set(xx, y + h - 1, c);
  }
  for (int yy = max(y, 0); yy < min(y + h, H); yy++) {
    set(x, yy, c);
    set(x + w - 1, yy, c);
  }
}

int Grid::text(int x, int y, const char *s, uint32_t c, int scale, bool mixed) {
  if (scale < 1) scale = 1;
  int cx = x;
  if (s) {
    const char *p = s;
    while (*p) {
      uint32_t cp = nextCp(p);
      if (!mixed) cp = upper(cp);
      const Glyph *gl = findGlyph(cp);
      if (!gl && mixed) gl = findGlyph(upper(cp));
      if (gl && cx < W && cx + 5 * scale > 0 && y < H && y + 7 * scale > 0) {
        for (int r = 0; r < 7; r++) {
          uint8_t bits = gl->rows[r];
          if (!bits) continue;
          for (int col = 0; col < 5; col++) {
            if (!(bits & (0x10 >> col))) continue;
            if (scale == 1) {
              set(cx + col, y + r, c);
            } else {
              rect(cx + col * scale, y + r * scale, scale, scale, c);
            }
          }
        }
      }
      cx += 6 * scale;
    }
  }
  return cx - x - scale;
}

void Grid::copyFrom(const Grid &o) { memcpy(px, o.px, N * sizeof(uint32_t)); }

int Grid::width(const char *s, int scale) {
  if (scale < 1) scale = 1;
  int n = 0;
  if (s) {
    const char *p = s;
    while (*p) {
      nextCp(p);
      n++;
    }
  }
  return n * 6 * scale - scale;
}

int Grid::centreX(const char *s, int scale) { return jround((W - width(s, scale)) / 2.0); }

uint32_t parseColour(const char *hex, uint32_t fallback) {
  if (!hex) return fallback;
  if (*hex == '#') hex++;
  uint32_t v = 0;
  for (int i = 0; i < 6; i++) {
    char ch = hex[i];
    uint32_t d;
    if (ch >= '0' && ch <= '9')
      d = ch - '0';
    else if (ch >= 'a' && ch <= 'f')
      d = ch - 'a' + 10;
    else if (ch >= 'A' && ch <= 'F')
      d = ch - 'A' + 10;
    else
      return fallback;
    v = (v << 4) | d;
  }
  return hex[6] == '\0' ? v : fallback;
}
