#include "settings.h"

#include <Preferences.h>

namespace {
Preferences prefs;
Settings s;

// Preferences meldet fehlende Schluessel als Fehler im Log, deshalb erst nachsehen.
String getString(const char *key, const char *fallback) { return prefs.isKey(key) ? prefs.getString(key, fallback) : String(fallback); }

void putString(const char *key, String &field, const String &value) {
  if (field == value) return;
  field = value;
  prefs.putString(key, value);
}
}  // namespace

namespace settings {

void begin() {
  prefs.begin("wall", false);
  s.ssid = getString("ssid", "");
  s.pass = getString("pass", "");
  s.key = getString("key", "");
  s.tz = getString("tz", DEFAULT_TZ);
  s.lang = getString("lang", "en");
  s.bright = prefs.getUChar("bright", 140);
  s.clock = prefs.getBool("clock", true);
  s.reduce = prefs.getBool("reduce", false);
  s.noteRev = prefs.getInt("note_rev", -1);
  s.restarts = prefs.getUInt("restarts", 0) + 1;
  prefs.putUInt("restarts", s.restarts);
  s.otaBad = getString("ota_bad", "");
  s.alarmOn = prefs.getBool("al_on", false);
  s.alarmH = prefs.getUChar("al_h", 7);
  s.alarmM = prefs.getUChar("al_m", 0);
  s.alarmDays = prefs.getUChar("al_days", 0x7F);
  s.alarmAck = prefs.getUInt("al_ack", 0);
  s.alarmSet = prefs.getUInt("al_set", 0);
  // Aus dem Testbild von Stufe 1, wird nicht mehr gebraucht.
  if (prefs.isKey("variant")) prefs.remove("variant");
  if (prefs.isKey("cycling")) prefs.remove("cycling");
  if (prefs.isKey("cycle")) prefs.remove("cycle");
}

const Settings &get() { return s; }

void setWifi(const String &ssid, const String &pass) {
  putString("ssid", s.ssid, ssid);
  putString("pass", s.pass, pass);
}

void setKey(const String &key) { putString("key", s.key, key); }

void setTz(const String &tz) {
  if (tz.length() == 0 || tz.length() > 60) return;
  putString("tz", s.tz, tz);
}

void setLang(const String &lang) { putString("lang", s.lang, lang == "de" ? String("de") : String("en")); }

void setBright(uint8_t b) {
  if (s.bright == b) return;
  s.bright = b;
  prefs.putUChar("bright", b);
}

void setClock(bool on) {
  if (s.clock == on) return;
  s.clock = on;
  prefs.putBool("clock", on);
}

void setReduce(bool on) {
  if (s.reduce == on) return;
  s.reduce = on;
  prefs.putBool("reduce", on);
}

void setNoteRev(int32_t rev) {
  if (s.noteRev == rev) return;
  s.noteRev = rev;
  prefs.putInt("note_rev", rev);
}

void setAlarm(bool on, uint8_t h, uint8_t m, uint8_t days, uint32_t ack, uint32_t set) {
  if (s.alarmOn == on && s.alarmH == h && s.alarmM == m && s.alarmDays == days && s.alarmAck == ack && s.alarmSet == set) return;
  s.alarmOn = on;
  s.alarmH = h;
  s.alarmM = m;
  s.alarmDays = days;
  s.alarmAck = ack;
  s.alarmSet = set;
  prefs.putBool("al_on", on);
  prefs.putUChar("al_h", h);
  prefs.putUChar("al_m", m);
  prefs.putUChar("al_days", days);
  prefs.putUInt("al_ack", ack);
  prefs.putUInt("al_set", set);
}

void forget() {
  setWifi("", "");
  setKey("");
}

bool resetArmed() { return prefs.getBool("en_armed", false); }

void setResetArmed(bool armed) {
  if (resetArmed() != armed) prefs.putBool("en_armed", armed);
}

bool lastRunLong() { return prefs.getBool("long_run", false); }

void setLastRunLong(bool longRun) {
  if (lastRunLong() != longRun) prefs.putBool("long_run", longRun);
}

String resetLog() { return getString("reset_log", ""); }

void noteReset(uint32_t number, const char *reason) {
  String log = String(number) + ":" + reason;
  String old = getString("reset_log", "");
  if (old.length()) log += "," + old;
  int entries = 1;
  for (unsigned int i = 0; i < log.length(); i++) {
    if (log[i] != ',') continue;
    if (++entries > 10) {
      log = log.substring(0, i);
      break;
    }
  }
  prefs.putString("reset_log", log);
}

bool otaFresh() { return prefs.getBool("ota_fresh", false); }

void setOtaFresh(bool fresh) { prefs.putBool("ota_fresh", fresh); }

uint8_t bootTries() { return prefs.getUChar("boot_tries", 0); }

void setBootTries(uint8_t n) { prefs.putUChar("boot_tries", n); }

void setOtaBad(const String &version) {
  if (s.otaBad == version) return;
  s.otaBad = version;
  if (version.length())
    prefs.putString("ota_bad", version);
  else if (prefs.isKey("ota_bad"))
    prefs.remove("ota_bad");
}

}  // namespace settings
