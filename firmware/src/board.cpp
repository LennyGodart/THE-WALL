#include "board.h"

#include <Wire.h>

#include "config.h"

namespace {

bool rtcFound = false;
bool expanderFound = false;

bool probe(uint8_t addr) {
  Wire.beginTransmission(addr);
  return Wire.endTransmission() == 0;
}

bool readRegs(uint8_t addr, uint8_t reg, uint8_t *out, size_t n) {
  Wire.beginTransmission(addr);
  Wire.write(reg);
  if (Wire.endTransmission(false) != 0) return false;
  if (Wire.requestFrom(addr, (uint8_t)n) != n) return false;
  for (size_t i = 0; i < n; i++) out[i] = Wire.read();
  return true;
}

uint8_t bcd(uint8_t v) { return (v / 10) << 4 | (v % 10); }
uint8_t unbcd(uint8_t v) { return (v >> 4) * 10 + (v & 0x0F); }

// Sekunden seit 1970 aus einer UTC-Zeit, ohne die lokale Zone (days_from_civil).
time_t utcSeconds(const struct tm &t) {
  int y = t.tm_year + 1900, m = t.tm_mon + 1, d = t.tm_mday;
  y -= m <= 2;
  const int era = (y >= 0 ? y : y - 399) / 400;
  const unsigned yoe = (unsigned)(y - era * 400);
  const unsigned doy = (153 * (m + (m > 2 ? -3 : 9)) + 2) / 5 + d - 1;
  const unsigned doe = yoe * 365 + yoe / 4 - yoe / 100 + doy;
  const long days = era * 146097L + (long)doe - 719468L;
  return (time_t)days * 86400 + t.tm_hour * 3600 + t.tm_min * 60 + t.tm_sec;
}

}  // namespace

namespace board {

void begin() {
  Wire.begin(PIN_SDA, PIN_SCL, 100000);
  Wire.setTimeOut(20);
  rtcFound = probe(I2C_RTC);
  expanderFound = probe(I2C_EXPANDER);
}

bool rtcPresent() { return rtcFound; }

bool rtcRead(time_t &utc) {
  if (!rtcFound) return false;
  // Register 0x04 bis 0x0A: Sekunden, Minuten, Stunden, Tag, Wochentag, Monat, Jahr.
  uint8_t r[7];
  if (!readRegs(I2C_RTC, 0x04, r, 7)) return false;
  if (r[0] & 0x80) return false;  // OS: Oszillator stand, Zeit ungueltig
  struct tm t = {};
  t.tm_sec = unbcd(r[0] & 0x7F);
  t.tm_min = unbcd(r[1] & 0x7F);
  t.tm_hour = unbcd(r[2] & 0x3F);
  t.tm_mday = unbcd(r[3] & 0x3F);
  t.tm_mon = unbcd(r[5] & 0x1F) - 1;
  t.tm_year = unbcd(r[6]) + 100;
  if (t.tm_year < 124 || t.tm_mon < 0 || t.tm_mon > 11 || t.tm_mday < 1) return false;
  utc = utcSeconds(t);
  return utc > 1700000000;
}

bool rtcWrite(time_t utc) {
  if (!rtcFound) return false;
  struct tm t = {};
  gmtime_r(&utc, &t);
  if (t.tm_year < 100 || t.tm_year > 199) return false;
  Wire.beginTransmission(I2C_RTC);
  Wire.write(0x04);
  Wire.write(bcd(t.tm_sec));  // schreiben loescht auch das OS-Bit
  Wire.write(bcd(t.tm_min));
  Wire.write(bcd(t.tm_hour));
  Wire.write(bcd(t.tm_mday));
  Wire.write(t.tm_wday);
  Wire.write(bcd(t.tm_mon + 1));
  Wire.write(bcd(t.tm_year - 100));
  return Wire.endTransmission() == 0;
}

bool wheelPresent() { return expanderFound; }

uint8_t wheel() {
  if (!expanderFound) return 0;
  uint8_t in;
  // Register 0: Eingaenge. IO1 bis IO3 werden nicht invertiert, gedrueckt ist 0.
  if (!readRegs(I2C_EXPANDER, 0x00, &in, 1)) return 0;
  uint8_t keys = 0;
  if (!(in & 0x02)) keys |= 1;  // K1
  if (!(in & 0x08)) keys |= 2;  // K2
  if (!(in & 0x04)) keys |= 4;  // K3
  return keys;
}

String scan() {
  String out;
  for (uint8_t a = 1; a < 127; a++) {
    if (!probe(a)) continue;
    char buf[8];
    snprintf(buf, sizeof buf, "0x%02X ", a);
    out += buf;
  }
  return out.length() ? out : String("keine");
}

}  // namespace board
