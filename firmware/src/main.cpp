// THE WALL Firmware.
//
// Ablauf: Boot-Animation. Ohne WLAN das Einrichtungsnetz "THE WALL SETUP", Name und
// Passwort auf dem Panel, die Einrichtungsseite unter 4.3.2.1. Mit WLAN und
// Schluessel fragt das Geraet alle zehn Sekunden /api/v1/frame und zeichnet die
// laufende Seite. Ohne Server laufen Uhr und Animationen aus dem Geraet weiter.
//
// Drei-Wege-Rad: kurz druecken schaltet das Panel aus und wieder ein, klingelt gerade ein
// Timer oder der Wecker, stoppt es das Klingeln. Fuenf Sekunden halten vergisst WLAN und
// Schluessel.
//
// Ab 0.2.1 klingelt das Geraet selbst: Timer und Wecker kommen mit jedem Frame, der Ton
// laeuft ueber den Codec ES8311 (audio.cpp). Ohne Server klingelt der Wecker trotzdem, die
// Uhrzeit kommt dann aus dem Uhrchip. Dazu meldet es sich im Heimnetz als _thewall._tcp,
// damit Home Assistant es findet.
//
// Ueber die serielle Schnittstelle (115200):
//   status          Zustand, Adresse, Schluessel gesetzt, letzter Fehler
//   anim            Liste der Animationen; anim <name> zeigt eine, auto beendet das
//   lang de|en      Sprache des Panels
//   bright <0-255>  Helligkeit bis zum naechsten Wert vom Server
//   test            Testbild
//   rtc             Uhrchip lesen
//   i2c             Adressen am I2C-Bus
//   forget          WLAN und Schluessel vergessen, Neustart
//   mem             freier Speicher
//   audio           Codec gefunden?
//   beep            Testton
//   ring            Klingeln wie bei einem Timer, bis zum Druck aufs Rad

#include <Arduino.h>
#include <ESPmDNS.h>
#include <WiFi.h>
#include <esp_mac.h>
#include <esp_ota_ops.h>
#include <esp_sntp.h>
#include <esp_timer.h>
#include <esp32s3/rom/rtc.h>
#include <sys/time.h>

#include "anim.h"
#include "audio.h"
#include "board.h"
#include "clocktext.h"
#include "config.h"
#include "fetch.h"
#include "grid.h"
#include "live.h"
#include "panel.h"
#include "portal.h"
#include "settings.h"
#include "wlan.h"

namespace {

constexpr uint32_t FRAME_MS = 33;
constexpr uint32_t BOOT_MS = 2600;
constexpr uint32_t PAIRED_MS = 11100;          // Radar und Anmeldung, Begruessung macht der Server
constexpr uint32_t PUSH_MS = 1600;             // wie modeswap
constexpr uint32_t DROP_MS = 1100;             // Einstieg in die Abfahrtstafel, wie im Entwurf
constexpr uint32_t SWAP_MS = 400;              // Flugwechsel
constexpr uint32_t ROLL_MS = 240;              // Minutensprung auf der Abfahrtstafel
constexpr uint32_t REEL_MS = 560;              // Spotify: Walze und Karussell beim Titelwechsel
constexpr uint32_t LINES_MS = 400;             // Spotify: die Ansage rollt zeilenweise herein
constexpr uint32_t TEST_MS = 10000;
constexpr uint32_t OFF_ANIM_MS = 2000;
constexpr uint32_t AP_AFTER_SETUP_MS = 10UL * 60UL * 1000UL;
constexpr uint32_t AP_WHEN_OFFLINE_MS = 2UL * 60UL * 1000UL;
constexpr uint32_t UNPAIRED_AP_MS = 15UL * 60UL * 1000UL;
constexpr uint32_t ADDRESS_MS = 3UL * 60UL * 1000UL;
constexpr uint32_t STABLE_MS = 60000;
constexpr uint32_t HOLD_MS = 5000;
constexpr uint32_t REV_MS = 2000;               // kurze Nachfrage, ob sich auf der Webseite etwas geaendert hat
constexpr uint32_t WIPED_MS = 2500;             // Hinweis nach dem Zuruecksetzen
constexpr uint32_t EN_WINDOW_MS = 5000;         // so lange nach dem Start zaehlt ein zweiter Druck auf EN
constexpr uint32_t LONG_RUN_MS = 10000;         // erst nach so einem Lauf oeffnet der naechste Start das Fenster

enum class KeyState : uint8_t { None, Checking, Ok, Bad, Full, Offline };
enum class Screen : uint8_t {
  None, Boot, EnWindow, Wiped, Setup, Joining, Address, Clock, Paired, Live, NoServer, NoWifi, Updating, Test, Demo,
  Off, Hold, Ring
};

Grid screen, before, incoming;
String deviceId, macText;
KeyState keyState = KeyState::None;
bool keyFromPortal = false;
String userName, deviceName, updateVersion, failedVersion;
uint32_t bootAt = 0, pairedAt = 0, testAt = 0, offAt = 0, holdAt = 0;
bool enArmed = false;  // Fenster offen: ein Druck auf EN setzt beim naechsten Start zurueck
bool wiped = false;    // dieser Start hat zurueckgesetzt, das Panel sagt es kurz an
bool longRunNoted = false;
bool bootAnim = false, offAnim = false;  // Boot- und Ausschalt-Animation laufen gerade
uint32_t enArmedAt = 0, wipedAt = 0;
uint32_t nextPollAt = 0, wifiUpAt = 0, offlineSince = 0, apHoldUntil = 0, nextRevAt = 0;
bool upAddr = false, upLong = false, offLong = false;  // WLAN seit 3 bzw. 15 Minuten da, 2 Minuten weg
uint8_t authFails = 0;              // 401 in Folge
uint32_t keyGen = 0, frameGen = 0;  // Schluessel-Generation jetzt und beim laufenden Abruf
String lastRev;  // Revision aus der letzten Antwort, leer bei einem Server ohne /api/v1/rev
int lastRevStatus = 0;
uint32_t lastRevRtt = 0;
uint32_t failedAt = 0, otaDoneAt = 0, lastRtcWrite = 0;
bool testing = false, panelOff = false, holding = false, updating = false, stable = false;
bool verbose = false;  // serieller Befehl "log 1": Abrufe, Logos, Seitenwechsel
bool serverBright = false, timeValid = false, ntpRunning = false;
uint8_t serverLevel = 0;
int shownLevel = -1;     // was das Panel gerade zeigt; naehert sich dem Ziel um 2 Stufen je Bild
bool brightJump = true;  // naechstes Bild ohne Uebergang, etwa nach dem seriellen Befehl bright
String demoKey;
uint32_t demoAt = 0;
String line;

Screen lastScreen = Screen::None;
bool pushing = false;
uint32_t pushAt = 0;
bool dropping = false;
uint32_t dropAt = 0;
int dropBands[6];
int dropCount = 0;
bool swapping = false;
uint32_t swapAt = 0;
bool rolling = false;
uint32_t rollAt = 0;
int rollBands[6];
int rollCount = 0;
String lastPageId;
String lastPageMode;  // Modus der zuletzt gezeigten Seite, fuer den Einstieg nach "Uebernehmen"
int64_t lastPageFrom = -1;
bool flashing = false;
uint32_t flashAt = 0;
uint8_t songFx = 0;       // Spotify-Uebergang: 1 Walze, 2 Karussell, 3 Ansage
uint32_t songAt = 0;
int64_t songFreeze = 0;   // Zeitpunkt des Wechsels, fuer den leer laufenden alten Balken
anim::SongWin songWin;
volatile bool ntpSynced = false;

// Klingeln (ab 0.2.1). ringTimers: Enden der laufenden Timer aus dem letzten Frame, in ms.
// serverRing: der Server sagt, dass gerade etwas klingelt. ringStoppedAt: Druck aufs Rad, was
// bis dahin anfing, bleibt still. ringSince: klingelt gerade seit, 0 still. ringKind: 1 Timer,
// 2 Wecker.
constexpr int64_t RING_MS = 15LL * 60 * 1000;
int64_t ringTimers[8];
int ringTimerCount = 0;
bool serverRing = false;
int64_t serverRingSince = 0;
int64_t ringStoppedAt = 0;
int64_t ringSince = 0;
uint8_t ringKind = 0;
int64_t ringTestAt = 0;  // serieller Befehl "ring"
bool mdnsUp = false;

// ---------- Zeit ----------

int64_t epochMs() {
  struct timeval tv;
  gettimeofday(&tv, nullptr);
  return (int64_t)tv.tv_sec * 1000 + tv.tv_usec / 1000;
}

void setEpochMs(int64_t ms) {
  struct timeval tv;
  tv.tv_sec = (time_t)(ms / 1000);
  tv.tv_usec = (suseconds_t)((ms % 1000) * 1000);
  settimeofday(&tv, nullptr);
  timeValid = true;
}

void applyTz() {
  setenv("TZ", settings::get().tz.c_str(), 1);
  tzset();
}

bool localNow(struct tm &out) {
  if (!timeValid) return false;
  time_t t = time(nullptr);
  localtime_r(&t, &out);
  return true;
}

void writeRtcSoon(uint32_t now, bool force) {
  if (!board::rtcPresent() || !timeValid) return;
  if (!force && lastRtcWrite && now - lastRtcWrite < 3600000UL) return;
  if (board::rtcWrite(time(nullptr))) lastRtcWrite = now;
}

void onNtp(struct timeval *) { ntpSynced = true; }

// Zeitdienst nur ohne Schluessel: mit Konto stellt der Server die Uhr.
void manageNtp() {
  bool want = settings::get().key.isEmpty() && wlan::state() == wlan::State::Up;
  if (want && !ntpRunning) {
    sntp_set_time_sync_notification_cb(onNtp);
    configTzTime(settings::get().tz.c_str(), "pool.ntp.org");
    ntpRunning = true;
  } else if (!want && ntpRunning) {
    esp_sntp_stop();
    ntpRunning = false;
  }
}

// ---------- Fristen ----------

// millis() laeuft nach 49,7 Tagen ueber, und als int32_t verglichen kippt jeder Abstand nach
// 24,86 Tagen. Deshalb gilt: eine Frist ist ein Flag mit Zeitpunkt, das Flag faellt, sobald
// die Frist um ist, und 0 heisst nie "sofort" (Fehlerliste 24.09.2026, firmware/README.md).

// Das Einrichtungsnetz bis dahin offen halten. extend: nur verlaengern, nie kuerzen. Bit 0 ist
// immer gesetzt, damit ein gesetzter Wert nie 0 ist.
void holdAp(uint32_t ms, bool extend = true) {
  uint32_t until = (millis() + ms) | 1;
  if (!extend || !apHoldUntil || (int32_t)(until - apHoldUntil) > 0) apHoldUntil = until;
}

// Abgelaufene Fristen loeschen. Laeuft jede Runde, so bleibt keine ueber 24 Tage stehen.
void closeWindows(uint32_t now) {
  if (bootAnim && now - bootAt >= BOOT_MS) bootAnim = false;
  if (pairedAt && now - pairedAt >= PAIRED_MS) pairedAt = 0;
  if (wiped && now - wipedAt >= WIPED_MS) wiped = false;
  if (offAnim && now - offAt >= OFF_ANIM_MS) offAnim = false;
  if (apHoldUntil && (int32_t)(apHoldUntil - now) <= 0) apHoldUntil = 0;
}

// Laufzeit in Sekunden aus dem 64-Bit-Zeitgeber, sie springt nicht nach 49,7 Tagen auf 0.
uint32_t uptimeS() { return (uint32_t)(esp_timer_get_time() / 1000000); }

// ---------- Server ----------

int64_t num64(JsonVariantConst v) {
  if (v.is<int64_t>()) return v.as<int64_t>();
  if (v.is<double>()) return (int64_t)v.as<double>();
  return 0;
}

void requestFrame(uint32_t now) {
  const Settings &s = settings::get();
  if (s.key.isEmpty() || wlan::state() != wlan::State::Up || updating) return;
  if ((int32_t)(now - nextPollAt) < 0 || fetch::frameBusy()) return;
  fetch::Telemetry t{};
  strncpy(t.ssid, wlan::ssid().c_str(), sizeof(t.ssid) - 1);
  t.rssi = wlan::rssi();
  t.uptime = uptimeS();
  t.temp = (int)lround(temperatureRead());
  t.flash = live::storageUsedPercent();
  t.restarts = s.restarts;
  strncpy(t.fwBad, s.otaBad.c_str(), sizeof(t.fwBad) - 1);
  // Kommt nie eine Antwort, fragt das Geraet nach 30 Sekunden trotzdem wieder.
  if (fetch::requestFrame(s.key, deviceId, t)) {
    frameGen = keyGen;
    nextPollAt = now + 30000;
  }
}

void offerFirmware(JsonObjectConst fw, uint32_t now) {
  String version = fw["version"] | "";
  if (version.isEmpty() || version == FW_VERSION || updating) return;
  // Diese Version lief schon einmal nicht an und wurde zurueckgerollt (Fehlerliste P1.4).
  if (version == settings::get().otaBad) return;
  if (version == failedVersion && now - failedAt < 30UL * 60UL * 1000UL) return;
  if (fetch::requestFirmware(settings::get().key, deviceId, fw["url"] | "", fw["sha256"] | "", fw["size"] | 0)) {
    updating = true;
    updateVersion = version;
    Serial.printf("Update auf %s\n", version.c_str());
  }
}

// Timer, Wecker und Klingeln aus einem Frame. Der Wecker geht in den NVS, damit er nach einem
// Neustart ohne Server noch klingelt.
void ringFromFrame(JsonDocument &d) {
  ringTimerCount = 0;
  for (JsonVariantConst v : d["timers"].as<JsonArrayConst>())
    if (ringTimerCount < 8) ringTimers[ringTimerCount++] = num64(v);
  JsonObjectConst a = d["alarm"];
  if (a.isNull()) {
    const Settings &s = settings::get();
    settings::setAlarm(false, s.alarmH, s.alarmM, s.alarmDays, s.alarmAck, s.alarmSet);
  } else {
    settings::setAlarm(true, constrain((int)(a["h"] | 7), 0, 23), constrain((int)(a["m"] | 0), 0, 59), (uint8_t)((a["d"] | 0x7F) & 0x7F),
                       (uint32_t)(num64(a["ack"]) / 1000), (uint32_t)(num64(a["set"]) / 1000));
  }
  JsonObjectConst r = d["ring"];
  serverRing = !r.isNull();
  serverRingSince = serverRing ? num64(r["since"]) : 0;
}

void takeFrame(uint32_t now) {
  fetch::FrameResult r;
  if (!fetch::takeFrame(r)) return;
  // Noch mit dem alten Schluessel geholt, waehrend auf der Einrichtungsseite ein neuer kam.
  // Die Antwort gehoert nicht zum neuen, der naechste Abruf fragt gleich mit ihm.
  if (frameGen != keyGen) {
    delete r.doc;
    return;
  }
  if (r.status == 200 && r.doc) {
    JsonDocument &d = *r.doc;
    int64_t serverNow = num64(d["now"]);
    if (serverNow > 1700000000000LL) {
      int64_t estimate = serverNow + r.rttMs / 2 + (millis() - r.arrivedAt);
      bool first = !timeValid;
      if (first || llabs(estimate - epochMs()) > 250) setEpochMs(estimate);
      writeRtcSoon(now, first);
    }
    String tz = d["tz"] | "";
    if (tz.length() && tz != settings::get().tz) {
      settings::setTz(tz);
      applyTz();
    }
    String lang = d["lang"] | "";
    if (lang.length()) settings::setLang(lang);
    settings::setReduce(!strcmp(d["motion"] | "", "reduce"));
    if (!d["bright"].isNull()) {
      serverBright = true;
      serverLevel = constrain((int)(d["bright"] | 168), 0, 255);
    }
    userName = d["user"] | "";
    deviceName = d["device"]["name"] | "";
    nextPollAt = now + constrain((int)(d["ttl"] | 10), 5, 300) * 1000UL;
    bool fresh = keyState == KeyState::Checking || keyState == KeyState::Offline || keyState == KeyState::Bad;
    if (fresh && keyFromPortal) {
      pairedAt = now;
      keyFromPortal = false;
      // Die Seite soll "Fertig" noch zeigen koennen, dann schliesst das Einrichtungsnetz.
      if (wlan::apOn()) holdAp(60000, false);
    }
    keyState = KeyState::Ok;
    authFails = 0;
    if (verbose) {
      JsonArrayConst pages = d["pages"];
      Serial.printf("Abruf 200 in %u ms%s, %u Seiten:", r.rttMs, r.reused ? "" : " mit Handshake",
                    (unsigned)pages.size());
      for (JsonObjectConst p : pages) Serial.printf(" %s", (const char *)(p["id"] | "-"));
      Serial.printf(", Helligkeit %d%s\n", serverLevel, d["fw"].isNull() ? "" : ", Update angeboten");
    }
    if (!d["fw"].isNull()) offerFirmware(d["fw"].as<JsonObjectConst>(), now);
    lastRev = String((const char *)(d["rev"] | ""));
    nextRevAt = now + REV_MS;
    ringFromFrame(d);
    live::prefetchLogos(d);
    live::setFrame(r.doc);
    return;
  }
  if (verbose) Serial.printf("Abruf %d in %u ms: %s\n", r.status, r.rttMs, fetch::lastError().c_str());
  switch (r.status) {
    case 401:
      // Lief der Schluessel bisher, gilt er erst nach dem dritten 401 in Folge als abgelehnt,
      // 20 Sekunden auseinander. Ein einzelnes 401 kostete sonst Inhalt und Einrichtungsnetz.
      // Ein frisch eingegebener Schluessel meldet sofort (Fehlerliste 24.09.2026, P1.3).
      if (authFails < 255) authFails++;
      if (keyState == KeyState::Ok && authFails < 3) {
        nextPollAt = now + 20000;
        break;
      }
      keyState = KeyState::Bad;
      live::setFrame(nullptr);
      nextPollAt = now + 60000;
      break;
    case 403:
      keyState = KeyState::Full;
      live::setFrame(nullptr);
      nextPollAt = now + 60000;
      break;
    case 410:
      // Auf der Webseite entfernt: den Schluessel vergessen, das WLAN bleibt. Ohne
      // Schluessel oeffnet sich das Einrichtungsnetz, bis ein neuer eingesetzt ist.
      Serial.println("Geraet auf der Webseite entfernt, Schluessel vergessen");
      settings::setKey("");
      keyState = KeyState::None;
      authFails = 0;
      live::setFrame(nullptr);
      lastRev = "";
      nextPollAt = now + 60000;
      // Nach langer Laufzeit ist die Viertelstunde nach dem Verbinden laengst um. Ohne diesen
      // Halt bliebe das Einrichtungsnetz zu, obwohl das Panel dazu auffordert (Fehlerliste F4).
      holdAp(UNPAIRED_AP_MS);
      break;
    case 429:
      nextPollAt = now + max(2, r.retryAfter) * 1000UL;
      break;
    default:
      if (keyState == KeyState::Checking || keyState == KeyState::None) keyState = KeyState::Offline;
      nextPollAt = now + 10000;
      Serial.printf("Abruf fehlgeschlagen: %d %s\n", r.status, fetch::lastError().c_str());
  }
}

// Alle zwei Sekunden die Revision fragen. Hat sie sich seit der letzten Antwort
// geaendert (Uebernehmen, neue Notiz, Jetzt aktualisieren), sofort einen Frame holen.
void pollRev(uint32_t now) {
  fetch::RevResult rr;
  while (fetch::takeRev(rr)) {
    lastRevStatus = rr.status;
    lastRevRtt = rr.rttMs;
    if (rr.status == 200 && rr.rev[0] && lastRev.length() && lastRev != rr.rev) {
      if (verbose) Serial.printf("Revision %s statt %s, neuer Abruf\n", rr.rev, lastRev.c_str());
      nextPollAt = now;
    } else if (rr.status == 401 && authFails == 0) {
      nextPollAt = now;  // der Frame-Abruf entscheidet, ob der Schluessel wirklich weg ist
    } else if (rr.status == 429) {
      nextRevAt = now + max(2, rr.retryAfter) * 1000UL;
    }
  }
  const Settings &s = settings::get();
  if (lastRev.isEmpty() || s.key.isEmpty() || keyState != KeyState::Ok || authFails || updating) return;
  if (wlan::state() != wlan::State::Up || fetch::frameBusy() || fetch::revBusy()) return;
  if ((int32_t)(now - nextRevAt) < 0 || (int32_t)(nextPollAt - now) < 1000) return;
  if (fetch::requestRev(s.key, deviceId)) nextRevAt = now + REV_MS;
}

void handleLogos() {
  fetch::LogoResult lr;
  while (fetch::takeLogo(lr)) {
    if (verbose) Serial.printf("Logo %s: %d\n", lr.code, lr.status);
    live::logoArrived(lr.code, lr.status, lr.rgb);
  }
  // Logos nur mit einem Schluessel, der gerade geht. Mit einem abgelehnten verbrauchte jede
  // Frage nur die Grenze beim Server (Fehlerliste P1.3).
  if (keyState != KeyState::Ok || authFails) return;
  const char *code = live::wantedLogo();
  if (!code || fetch::logoBusy() || updating || wlan::state() != wlan::State::Up) return;
  if (settings::get().key.isEmpty()) return;
  if (fetch::requestLogo(settings::get().key, code)) live::logoLoading(code);
}

void handleOta(uint32_t now) {
  if (!updating) return;
  fetch::Ota st = fetch::otaState();
  if (st == fetch::Ota::Done) {
    if (!otaDoneAt) otaDoneAt = now;
    // 100 Prozent kurz stehen lassen, dann neu starten.
    if (now - otaDoneAt > 1500) {
      settings::setOtaFresh(true);
      settings::setBootTries(0);
      Serial.println("Update fertig, Neustart");
      delay(100);
      ESP.restart();
    }
  } else if (st == fetch::Ota::Failed) {
    Serial.printf("Update fehlgeschlagen: %s\n", fetch::otaError().c_str());
    failedVersion = updateVersion;
    failedAt = now;
    updating = false;
    nextPollAt = now + 2000;
  }
}

// ---------- WLAN ----------

void manageWifi(uint32_t now) {
  const Settings &s = settings::get();
  wlan::State w = wlan::state();
  bool up = w == wlan::State::Up;

  if (up) {
    if (!wifiUpAt) {
      wifiUpAt = now;
      Serial.printf("WLAN %s, %s\n", wlan::ssid().c_str(), wlan::ip().toString().c_str());
    }
    if (now - wifiUpAt >= ADDRESS_MS) upAddr = true;
    if (now - wifiUpAt >= UNPAIRED_AP_MS) upLong = true;
    offlineSince = 0;
    offLong = false;
  } else {
    wifiUpAt = 0;
    upAddr = upLong = false;
    if (!offlineSince) offlineSince = now;
    if (now - offlineSince > AP_WHEN_OFFLINE_MS) offLong = true;
  }

  if (!s.ssid.isEmpty() && !up) {
    if (w == wlan::State::Idle) {
      wlan::join(s.ssid, s.pass);
    } else if (w != wlan::State::Joining) {
      // Mit offenem Einrichtungsnetz seltener, jeder Versuch stoert es kurz.
      uint32_t wait = wlan::apOn() ? 60000 : 15000;
      if (wlan::stateAge() > wait) wlan::join(s.ssid, s.pass);
    }
  }

  // Das Einrichtungsnetz bleibt offen, solange etwas fehlt. Gastnetze lassen ein Handy
  // meist nicht zu anderen Geraeten, dann ist es der einzige Weg zur Einrichtungsseite.
  // Ohne Konto schliesst es 15 Minuten nach dem Verbinden, ausser ein Handy ist eingebucht.
  bool keyRejected = keyState == KeyState::Bad || keyState == KeyState::Full;
  bool unpaired = s.key.isEmpty() && (!up || !upLong || WiFi.softAPgetStationNum() > 0);
  // apHoldUntil ist 0, sobald der Halt um ist (closeWindows). Bis 0.1.9 stand hier nur der
  // Abstand, und der wurde nach 24,86 Tagen Laufzeit wieder positiv: dann stand das
  // Einrichtungsnetz rund 25 Tage offen (Fehlerliste 24.09.2026, P1.1).
  bool wantAp = s.ssid.isEmpty() || keyRejected || unpaired || apHoldUntil != 0 || (!up && offLong);
  wlan::setAp(wantAp);
}

// ---------- Einrichtungsseite ----------

const char *wifiStateName() {
  const Settings &s = settings::get();
  if (s.ssid.isEmpty()) return "none";
  switch (wlan::state()) {
    case wlan::State::Up: return "ok";
    case wlan::State::Wrong: return "wrong";
    case wlan::State::NotFound: return "notfound";
    case wlan::State::Failed: return "failed";
    default: return "busy";
  }
}

const char *keyStateName() {
  switch (keyState) {
    case KeyState::Checking: return "busy";
    case KeyState::Ok: return "ok";
    case KeyState::Bad: return "bad";
    case KeyState::Full: return "full";
    case KeyState::Offline: return "offline";
    default: return "none";
  }
}

String stateJson() {
  const Settings &s = settings::get();
  JsonDocument d;
  bool up = wlan::state() == wlan::State::Up;
  d["lang"] = s.lang;
  d["ap"] = wlan::apOn();
  d["here"] = up ? wlan::ip().toString() : wlan::apIp().toString();
  JsonObject w = d["wifi"].to<JsonObject>();
  w["state"] = wifiStateName();
  w["ssid"] = s.ssid;
  if (up) {
    w["ip"] = wlan::ip().toString();
    w["rssi"] = wlan::rssi();
  }
  JsonObject k = d["key"].to<JsonObject>();
  k["state"] = s.key.isEmpty() ? "none" : keyStateName();
  k["set"] = !s.key.isEmpty();
  if (keyState == KeyState::Ok) k["user"] = userName;
  d["id"] = deviceId;
  d["mac"] = macText;
  d["fw"] = FW_VERSION;
  d["panel"] = "128 x 64, FM6126A";
  d["bright"] = s.bright;
  d["clock"] = s.clock;
  String out;
  serializeJson(d, out);
  return out;
}

void startPortal() {
  portal::Hooks h;
  h.state = stateJson;
  h.wifi = [](const String &ssid, const String &pass) {
    settings::setWifi(ssid, pass);
    wlan::join(ssid, pass);
    wifiUpAt = 0;
    upAddr = upLong = false;
    holdAp(AP_AFTER_SETUP_MS);
    Serial.printf("Neues WLAN: %s\n", ssid.c_str());
  };
  h.key = [](const String &key) {
    settings::setKey(key);
    keyState = KeyState::Checking;
    keyFromPortal = true;
    keyGen++;
    authFails = 0;
    lastRev = "";
    live::setFrame(nullptr);
    // Jetzt fragen. 0 hiesse nach 24,86 Tagen Laufzeit "in 24 Tagen" (Fehlerliste F1).
    nextPollAt = millis();
    if (wlan::apOn()) holdAp(3UL * 60UL * 1000UL);
    Serial.println("Neuer Schluessel gesetzt");
  };
  h.bright = [](uint8_t v) { settings::setBright(v); };
  h.clock = [](bool on) { settings::setClock(on); };
  h.test = []() {
    testing = true;
    testAt = millis();
  };
  h.lang = [](const String &l) { settings::setLang(l); };
  portal::begin(h);
}

// ---------- Zeichnen ----------

bool drawTest(Grid &g, uint32_t ms) {
  switch (ms / 2000) {
    case 0: g.rect(0, 0, 128, 64, 0xFF0000); return true;
    case 1: g.rect(0, 0, 128, 64, 0x00FF00); return true;
    case 2: g.rect(0, 0, 128, 64, 0x0000FF); return true;
    case 3:
      for (int y = 8; y < 63; y += 8) g.rect(0, y, 128, 1, 0x282828);
      for (int x = 8; x < 127; x += 8) g.rect(x, 0, 1, 64, 0x282828);
      g.frame(0, 0, 128, 64, C_ACCENT);
      return true;
    case 4:
      g.rect(0, 0, 6, 6, 0xFFFFFF);
      g.rect(122, 0, 6, 6, 0xFFFFFF);
      g.rect(0, 58, 6, 6, 0xFFFFFF);
      g.rect(122, 58, 6, 6, 0xFFFFFF);
      g.set(0, 0, 0xFF0000);
      return true;
    default: return false;
  }
}

// Uhr ohne Konto, im Raster des Uhrmodus ohne Wetterleiste, dazu die Adresse.
void drawClock(Grid &g, const struct tm &t, bool de) {
  char txt[16], date[24];
  clockText(txt, sizeof txt, t, true, false);
  g.text(21, 19, txt, C_ACCENT, 3);
  dateText(date, sizeof date, t, de);
  g.text(Grid::centreX(date), 44, date, C_DIM);
  String ip = wlan::ip().toString();
  g.text(Grid::centreX(ip.c_str()), 56, ip.c_str(), 0x4E5A63);
}

void drawHold(Grid &g, uint32_t held, bool de) {
  int left = (int)((HOLD_MS - min(held, HOLD_MS) + 999) / 1000);
  char n[8];
  snprintf(n, sizeof n, "%d", left);
  g.text(Grid::centreX(de ? "LOSLASSEN BEHAELT" : "RELEASE TO KEEP"), 8, de ? "LOSLASSEN BEHAELT" : "RELEASE TO KEEP", C_DIM);
  g.text(Grid::centreX(de ? "WLAN UND SCHLUESSEL" : "WI-FI AND KEY"), 20, de ? "WLAN UND SCHLUESSEL" : "WI-FI AND KEY", C_WHITE);
  g.text(Grid::centreX(n, 2), 36, n, C_RED, 2);
  g.rect(8, 58, jround(112.0 * min(held, HOLD_MS) / HOLD_MS), 2, C_RED);
}

// Nach dem Zuruecksetzen: mit EN beim Start, mit Rad oder BOOT vor dem Neustart.
void drawWiped(Grid &g, bool de) {
  const char *l1 = de ? "ZURUECKGESETZT" : "RESET DONE";
  const char *l2 = de ? "WLAN UND SCHLUESSEL" : "WI-FI AND KEY";
  const char *l3 = de ? "SIND VERGESSEN" : "ARE FORGOTTEN";
  g.text(Grid::centreX(l1), 14, l1, C_ACCENT);
  g.text(Grid::centreX(l2), 30, l2, C_WHITE);
  g.text(Grid::centreX(l3), 42, l3, C_DIM);
}

// Fenster eines Spotify-Uebergangs aus fxw. false, wenn etwas fehlt oder nicht passt.
bool parseSongWin(JsonObjectConst f, anim::SongWin &w) {
  if (f.isNull()) return false;
  auto box = [](JsonArrayConst a, int *out) -> bool {
    if (a.size() != 4) return false;
    out[0] = constrain(a[0].as<int>(), 0, 127);
    out[1] = constrain(a[1].as<int>(), 0, 63);
    out[2] = constrain(a[2].as<int>(), 0, 127);
    out[3] = constrain(a[3].as<int>(), 0, 63);
    return out[0] <= out[2] && out[1] <= out[3];
  };
  if (!box(f["t"], w.t)) return false;
  w.hasC = box(f["c"], w.c);
  w.hasB = box(f["b"], w.b);
  w.pitch = constrain((int)(f["p"] | 12), 4, 32);
  w.shift = (f["s"] | 0) != 0;
  w.nLines = 0;
  for (JsonVariantConst v : f["l"].as<JsonArrayConst>())
    if (w.nLines < 6) w.lines[w.nLines++] = constrain(v.as<int>(), 0, 63);
  return true;
}

void drawLive(Grid &g, uint32_t now, const struct tm *local, bool de) {
  int64_t ms = epochMs();
  JsonObjectConst page = live::pageAt(ms);
  bool flash = false;
  if (!page["flash"].isNull()) {
    int32_t rev = page["flash"] | 0;
    if (rev != settings::get().noteRev) {
      settings::setNoteRev(rev);
      flashing = true;
      flashAt = now;
    }
    // Dreimal hell in 1,5 Sekunden: zwei Blitze pro Sekunde, unter der Grenze von drei.
    if (flashing) {
      uint32_t age = now - flashAt;
      if (age < 1500)
        flash = (age / 250) % 2 == 0;
      else
        flashing = false;
    }
  }
  bool reduce = settings::get().reduce;
  live::Ctx ctx{ms, local, de, flash, now, reduce, (uint8_t)max(0, shownLevel)};
  int64_t from = num64(page["from"]);
  if (from != lastPageFrom) {
    const char *fx = page["fx"] | "";
    const char *id = page["id"] | "";
    const char *mode = page["mode"] | "";
    // Anderer Modus ohne fx, etwa nach "Uebernehmen" auf der Webseite: derselbe Einstieg wie in
    // der Rotation, push oder drop (enter der Seite), statt eines harten Schnitts.
    if (!*fx && lastPageFrom >= 0 && *mode && lastPageMode.length() && lastPageMode != mode) fx = page["enter"] | "push";
    lastPageMode = mode;
    if (verbose) Serial.printf("Seite %s%s%s\n", *id ? id : "-", *fx ? " mit " : "", fx);
    if (lastPageFrom >= 0 && !strcmp(fx, "swap") && !reduce) {
      before.copyFrom(panel::frame());
      swapping = true;
      swapAt = now;
    }
    // Dieselbe Tafel mit neuen Minuten: die geaenderten Spalten rollen.
    if (lastPageFrom >= 0 && !*fx && !reduce && lastPageId == id && !page["bands"].isNull()) {
      rollCount = 0;
      for (JsonVariantConst b : page["bands"].as<JsonArrayConst>())
        if (rollCount < 6) rollBands[rollCount++] = constrain(b.as<int>(), 0, 63);
      before.copyFrom(panel::frame());
      rolling = rollCount > 0;
      rollAt = now;
    }
    lastPageId = id;
    if (lastPageFrom >= 0 && !strcmp(fx, "drop") && !reduce) {
      dropCount = 0;
      for (JsonVariantConst b : page["bands"].as<JsonArrayConst>())
        if (dropCount < 6) dropBands[dropCount++] = constrain(b.as<int>(), 0, 63);
      dropping = dropCount > 0;
      dropAt = now;
      pushing = false;
    }
    // Mit reduzierter Bewegung ein harter Schnitt statt des Schubs.
    if (lastPageFrom >= 0 && !strcmp(fx, "push") && !reduce) {
      before.copyFrom(panel::frame());
      pushing = true;
      pushAt = now;
    }
    // Spotify ab 0.2.0: Walze am Ende eines Titels, Karussell nach einem Sprung am Handy, die
    // Ansage rollt zeilenweise herein. Die alte Seite wird dabei als Kopie weiter gezeichnet,
    // sonst stuenden zwei Laufschriften versetzt uebereinander.
    bool song = !strcmp(fx, "reel") || !strcmp(fx, "carousel") || !strcmp(fx, "lines");
    if (lastPageFrom >= 0 && song && !reduce && parseSongWin(page["fxw"], songWin)) {
      live::keepOld();
      songFx = fx[0] == 'r' ? 1 : fx[0] == 'c' ? 2 : 3;
      songAt = now;
      songFreeze = ms;
      pushing = dropping = swapping = rolling = false;
    }
    live::keepShown(page);
    lastPageFrom = from;
  }
  if (pushing && now - pushAt < PUSH_MS) {
    incoming.clear();
    live::render(incoming, page, ctx);
    anim::push(g, before, incoming, (now - pushAt) / (double)PUSH_MS);
  } else if (dropping && now - dropAt < DROP_MS) {
    pushing = false;
    incoming.clear();
    live::render(incoming, page, ctx);
    anim::drop(g, incoming, dropBands, dropCount, (now - dropAt) / (double)DROP_MS);
  } else if (swapping && now - swapAt < SWAP_MS) {
    incoming.clear();
    live::render(incoming, page, ctx);
    anim::swap(g, before, incoming, (now - swapAt) / (double)SWAP_MS);
  } else if (rolling && now - rollAt < ROLL_MS) {
    incoming.clear();
    live::render(incoming, page, ctx);
    anim::roll(g, before, incoming, rollBands, rollCount, (now - rollAt) / (double)ROLL_MS);
  } else if (songFx && now - songAt < (songFx == 3 ? LINES_MS : REEL_MS)) {
    incoming.clear();
    live::render(incoming, page, ctx);
    JsonObjectConst old = live::oldPage();
    before.clear();
    if (!old.isNull()) live::render(before, old, ctx);
    double k = (now - songAt) / (double)(songFx == 3 ? LINES_MS : REEL_MS);
    if (songFx == 3) {
      anim::lines(g, before, incoming, songWin, k);
    } else {
      anim::song(g, before, incoming, songWin, k, songFx == 2);
      // Zeitleiste: in der ersten Haelfte laeuft der alte Balken leer, dann steht die neue.
      JsonObjectConst prog = live::findOp(old, "prog");
      if (songWin.hasB && k < 0.55 && !prog.isNull()) {
        int wF = live::progWidth(prog, songFreeze);
        before.clear();
        live::drawProg(before, prog, songFreeze, jround(wF * (1 - anim::eout(k / 0.55))));
        for (int y = songWin.b[1]; y <= songWin.b[3]; y++)
          for (int x = songWin.b[0]; x <= songWin.b[2]; x++) g.px[y * Grid::W + x] = before.px[y * Grid::W + x];
      }
    }
  } else {
    pushing = false;
    dropping = false;
    swapping = false;
    rolling = false;
    songFx = 0;
    live::render(g, page, ctx);
  }
}

Screen choose(uint32_t now) {
  const Settings &s = settings::get();
  if (holding && now - holdAt > 1000) return Screen::Hold;
  if (wiped) return Screen::Wiped;
  // Fenster fuer den zweiten Druck auf EN, ueber der Boot-Animation. Das Panel zeigt es,
  // sonst trifft man es nicht.
  if (enArmed) return Screen::EnWindow;
  // Klingeln geht vor dem ausgeschalteten Panel. Mit Seiten vom Server zeigt es deren Seite,
  // ohne zeichnet das Geraet selbst.
  if (ringSince) {
    if (!s.key.isEmpty() && keyState == KeyState::Ok && live::covers(epochMs())) return Screen::Live;
    return Screen::Ring;
  }
  if (panelOff) return Screen::Off;
  if (testing) return Screen::Test;
  if (demoKey.length()) return Screen::Demo;
  // Nach dem Zuruecksetzen gleich ins Einrichtungsnetz, ohne Boot-Animation hinterher:
  // bootAnim ist dann nie gesetzt (setup).
  if (bootAnim) return Screen::Boot;
  if (updating) return Screen::Updating;
  if (s.ssid.isEmpty()) return Screen::Setup;

  bool up = wlan::state() == wlan::State::Up;
  if (!s.key.isEmpty() && pairedAt) return Screen::Paired;
  // Die Seiten reichen 25 Sekunden, ein kurzer WLAN-Aussetzer faellt nicht auf.
  if (!s.key.isEmpty() && keyState == KeyState::Ok && live::covers(epochMs())) return Screen::Live;
  if (!up) {
    if (wlan::apOn() && offLong) return (now / 8000) % 2 ? Screen::Setup : Screen::NoWifi;
    if (!wlan::everUp()) return wlan::apOn() && wlan::state() != wlan::State::Joining ? Screen::Setup : Screen::Joining;
    return Screen::NoWifi;
  }
  // Solange das Einrichtungsnetz offen ist, im Wechsel Adresse und Einrichtungsnetz.
  bool alternate = wlan::apOn() && (now / 8000) % 2;
  if (s.key.isEmpty()) {
    if (wlan::apOn()) return alternate ? Screen::Setup : Screen::Address;
    return s.clock && timeValid && upAddr ? Screen::Clock : Screen::Address;
  }
  switch (keyState) {
    case KeyState::None: return Screen::Joining;  // erster Abruf nach dem Start laeuft
    case KeyState::Checking: return Screen::Address;
    case KeyState::Bad:
    case KeyState::Full: return alternate ? Screen::Setup : Screen::Address;
    default: return Screen::NoServer;
  }
}

void drawRing(Grid &g, const struct tm *local, bool de);

void draw(Screen sc, uint32_t now) {
  const Settings &s = settings::get();
  AnimOpts o;
  o.de = s.lang == "de";
  o.reduce = s.reduce;
  o.bright = (uint8_t)max(0, shownLevel);
  struct tm local;
  o.now = localNow(local) ? &local : nullptr;
  String ssid = s.ssid;
  o.ssid = ssid.c_str();
  double t = now / 1000.0;

  switch (sc) {
    case Screen::Boot: anim::draw("boot", screen, (now - bootAt) / 1000.0, o); break;
    case Screen::EnWindow: {
      // Die Boot-Animation laeuft weiter, darunter der Hinweis und ein roter Balken, der in
      // fuenf Sekunden schrumpft. Solange er steht, setzt ein Druck auf EN zurueck.
      anim::draw("boot", screen, min<uint32_t>(now - bootAt, BOOT_MS) / 1000.0, o);
      const char *hint = o.de ? "EN NOCHMAL = RESET" : "EN AGAIN = RESET";
      screen.text(Grid::centreX(hint), 50, hint, C_DIM);
      uint32_t left = EN_WINDOW_MS - min<uint32_t>(EN_WINDOW_MS, now - enArmedAt);
      int w = o.reduce ? 112 : jround(112.0 * left / EN_WINDOW_MS);
      screen.rect(8, 59, w, 2, C_RED);
      break;
    }
    case Screen::Wiped: drawWiped(screen, o.de); break;
    case Screen::Setup:
      o.phone = wlan::apOn() && WiFi.softAPgetStationNum() > 0;
      anim::draw("wifi", screen, t, o);
      break;
    case Screen::Joining:
      if (wlan::state() == wlan::State::Joining) o.progress = min(1.0f, wlan::stateAge() / (float)wlan::JOIN_TIMEOUT_MS);
      anim::draw("connecting", screen, t, o);
      break;
    case Screen::Address: {
      String ip = wlan::ip().toString();
      o.ip = ip.c_str();
      if (keyState == KeyState::Bad)
        o.status = o.de ? "SCHLUESSEL ABGELEHNT" : "KEY REJECTED";
      else if (keyState == KeyState::Full)
        o.status = o.de ? "ZU VIELE GERAETE" : "TOO MANY DEVICES";
      else if (keyState == KeyState::Checking)
        o.status = o.de ? "PRUEFE SCHLUESSEL" : "CHECKING KEY";
      anim::draw("address", screen, t, o);
      break;
    }
    case Screen::Clock: drawClock(screen, local, o.de); break;
    case Screen::Paired: {
      String user = userName, device = deviceName;
      o.user = user.c_str();
      o.device = device.c_str();
      anim::draw("paired", screen, (now - pairedAt) / 1000.0, o);
      break;
    }
    case Screen::Live: drawLive(screen, now, o.now, o.de); break;
    case Screen::NoServer: anim::draw("noserver", screen, t, o); break;
    case Screen::NoWifi: anim::draw("nowifi", screen, t, o); break;
    case Screen::Updating: {
      o.version = updateVersion.c_str();
      o.progress = fetch::otaPermille() / 1000.0f;
      anim::draw("updating", screen, t, o);
      break;
    }
    case Screen::Test:
      if (!drawTest(screen, now - testAt)) testing = false;
      break;
    case Screen::Demo: {
      String ip = wlan::ip().toString();
      o.ip = ip.c_str();
      o.user = userName.c_str();
      o.version = FW_VERSION;
      anim::draw(demoKey.c_str(), screen, (now - demoAt) / 1000.0, o);
      break;
    }
    case Screen::Off:
      if (offAnim) anim::draw("poweroff", screen, (now - offAt) / 1000.0, o);
      break;
    case Screen::Hold: drawHold(screen, now - holdAt, o.de); break;
    case Screen::Ring: drawRing(screen, o.now, o.de); break;
    default: break;
  }
}

// ---------- Klingeln ----------

// Beginn des Weckers heute in ms, wenn er an diesem Wochentag gilt, sonst 0. Gerechnet in der
// Zone des Geraets (TZ), wie der Server es in der Zone des Kontos tut.
int64_t alarmToday(const struct tm &local) {
  const Settings &s = settings::get();
  if (!s.alarmOn) return 0;
  int iso = local.tm_wday == 0 ? 7 : local.tm_wday;
  if (!(s.alarmDays & (1 << (iso - 1)))) return 0;
  struct tm t = local;
  t.tm_hour = s.alarmH;
  t.tm_min = s.alarmM;
  t.tm_sec = 0;
  t.tm_isdst = -1;
  time_t at = mktime(&t);
  return at > 0 ? (int64_t)at * 1000 : 0;
}

// Klingelt gerade etwas? Aus dem Server, solange seine Seiten gelten, und aus den eigenen
// Zeiten: ein Timer ab seinem Ende, der Wecker ab seiner Uhrzeit, beides hoechstens eine
// Viertelstunde und nur, was nach dem letzten Druck aufs Rad anfing. So klingelt es auf die
// Sekunde, nicht erst mit dem naechsten Abruf, und der Wecker auch ohne Server.
void updateRing() {
  // Fuenfmal pro Sekunde reicht, loop() laeuft sonst alle zwei Millisekunden durch mktime().
  static uint32_t lastCheck = 0;
  if (millis() - lastCheck < 200) return;
  lastCheck = millis();
  int64_t ms = epochMs();
  int64_t since = 0;
  uint8_t kind = 0;
  auto consider = [&](int64_t t, uint8_t k) {
    if (t <= 0 || t <= ringStoppedAt || ms < t || ms >= t + RING_MS) return;
    if (!since || t < since) {
      since = t;
      kind = k;
    }
  };
  if (timeValid) {
    for (int i = 0; i < ringTimerCount; i++) consider(ringTimers[i], 1);
    consider(ringTestAt, 1);
    struct tm local;
    if (localNow(local)) {
      const Settings &s = settings::get();
      int64_t t0 = alarmToday(local);
      if (t0 > (int64_t)s.alarmAck * 1000 && t0 >= (int64_t)s.alarmSet * 1000) consider(t0, 2);
    }
  }
  if (!since && serverRing && live::covers(ms)) consider(serverRingSince, 1);
  if (since && !ringSince) {
    ringSince = since;
    ringKind = kind;
    // Ein Panel, das am Rad ausgeschaltet wurde, geht zum Klingeln an.
    panelOff = false;
    offAnim = false;
    audio::play(kind == 2 ? audio::Tone::Alarm : audio::Tone::Timer);
    Serial.printf("Klingeln: %s\n", kind == 2 ? "Wecker" : "Timer");
  } else if (!since && ringSince) {
    ringSince = 0;
    ringKind = 0;
    audio::play(audio::Tone::Off);
  } else if (since) {
    ringSince = since;
  }
}

// Druck aufs Rad oder auf BOOT beim Klingeln: sofort still, dann dem Server Bescheid geben.
void stopRing() {
  ringStoppedAt = epochMs();
  ringSince = 0;
  ringKind = 0;
  audio::play(audio::Tone::Off);
  const Settings &s = settings::get();
  if (!s.key.isEmpty() && wlan::state() == wlan::State::Up) fetch::requestRingStop(s.key, deviceId);
  // Gleich einen neuen Frame holen, dann ist auch die Seite des Servers weg.
  nextPollAt = millis() + 800;
  Serial.println("Klingeln gestoppt");
}

// Klingeln ohne Seiten vom Server, etwa ohne Internet: gezeichnet wie auf dem Server.
void drawRing(Grid &g, const struct tm *local, bool de) {
  const char *title = ringKind == 2 ? (de ? "WECKER" : "ALARM") : "TIMER";
  g.text(Grid::centreX(title), 3, title, C_DIM);
  if (ringKind == 2 && local) {
    char txt[16];
    clockText(txt, sizeof txt, *local, true, false);
    g.text(Grid::centreX(txt, 3), 17, txt, C_ACCENT, 3);
  } else {
    g.text(Grid::centreX("0:00", 3), 17, "0:00", C_ACCENT, 3);
  }
  const char *hint = de ? "STOPP: RAD DRUECKEN" : "STOP: PRESS THE WHEEL";
  g.text(Grid::centreX(hint), 52, hint, C_DIM);
}

// Im Heimnetz als _thewall._tcp melden, mit Kennung, Firmware und Server im TXT-Eintrag. Home
// Assistant findet das Geraet so von selbst und fragt nach dem Code auf dem Panel.
void startMdns() {
  if (mdnsUp || wlan::state() != wlan::State::Up) return;
  if (!MDNS.begin(deviceId.c_str())) return;
  MDNS.addService("thewall", "tcp", 80);
  MDNS.addServiceTxt("thewall", "tcp", "id", deviceId.c_str());
  MDNS.addServiceTxt("thewall", "tcp", "fw", FW_VERSION);
  MDNS.addServiceTxt("thewall", "tcp", "srv", WALL_SERVER);
  mdnsUp = true;
  Serial.printf("Im Heimnetz als %s.local, _thewall._tcp\n", deviceId.c_str());
}

// ---------- Rad ----------

// Rad und Taste BOOT: fuenf Sekunden halten vergisst WLAN und Schluessel, nach einer
// Sekunde zaehlt das Panel herunter, wer vorher loslaesst, behaelt alles. Kurz aufs Rad
// schaltet das Panel aus und an; kurz auf BOOT tut nichts. EN zweimal steht in setup().
void handleWheel(uint32_t now) {
  static uint32_t lastRead = 0;
  static uint8_t previous = 0;
  static bool done = false, fromWheel = false;
  if (now - lastRead < 40) return;
  lastRead = now;
  bool wheelDown = board::wheelPresent() && board::wheel();
  bool bootDown = digitalRead(PIN_BOOT) == LOW;
  uint8_t keys = (wheelDown ? 1 : 0) | (bootDown ? 2 : 0);
  if (keys && !previous) {
    holdAt = now;
    holding = true;
    done = false;
    fromWheel = wheelDown;
  }
  if (keys && holding && !done && now - holdAt >= HOLD_MS) {
    done = true;
    Serial.printf("%s gehalten: WLAN und Schluessel vergessen\n", fromWheel ? "Rad" : "BOOT");
    settings::forget();
    screen.clear();
    drawWiped(screen, settings::get().lang == "de");
    panel::setBrightness(max<uint8_t>(max(0, shownLevel), 60));
    panel::show(screen);
    delay(WIPED_MS);
    ESP.restart();
  }
  if (!keys && previous) {
    if (!done && ringSince && now - holdAt < 1000) {
      stopRing();
    } else if (!done && fromWheel && now - holdAt < 1000) {
      panelOff = !panelOff;
      if (panelOff) {
        offAt = now;
        offAnim = true;
      } else {
        bootAt = now;
        bootAnim = true;
      }
    }
    holding = false;
  }
  previous = keys;
}

// ---------- Serielle Befehle ----------

void handleCommand(String cmd) {
  cmd.trim();
  if (cmd == "status") {
    const Settings &s = settings::get();
    Serial.printf("Firmware %s, Geraet %s, MAC %s\n", FW_VERSION, deviceId.c_str(), macText.c_str());
    Serial.printf("WLAN %s (%s), IP %s, RSSI %d, Einrichtungsnetz %s\n", s.ssid.c_str(), wifiStateName(),
                  wlan::ip().toString().c_str(), wlan::rssi(), !wlan::apOn() ? "aus" : wlan::apSecured() ? "an, WPA2" : "an, OFFEN");
    Serial.printf("Schluessel %s, Zustand %s, Konto %s, letzter Fehler: %s\n", s.key.isEmpty() ? "fehlt" : "gesetzt",
                  keyStateName(), userName.c_str(), fetch::lastError().c_str());
    struct tm t;
    char buf[32] = "keine";
    if (localNow(t)) strftime(buf, sizeof buf, "%Y-%m-%d %H:%M:%S", &t);
    Serial.printf("Uhrzeit %s, Zone %s, Uhrchip %s, Rad %s, Taste BOOT %s\n", buf, s.tz.c_str(),
                  board::rtcPresent() ? "da" : "fehlt", board::wheelPresent() ? "da" : "fehlt",
                  digitalRead(PIN_BOOT) == LOW ? "gedrueckt" : "offen");
    if (wlan::apOn()) Serial.printf("Im Einrichtungsnetz verbunden: %d\n", WiFi.softAPgetStationNum());
    Serial.printf("Letzte Starts: %s\n", settings::resetLog().c_str());
    Serial.printf("Revision %s, letzte Nachfrage HTTP %d in %u ms\n", lastRev.length() ? lastRev.c_str() : "-",
                  lastRevStatus, lastRevRtt);
    Serial.printf("Helligkeit %d, Neustarts %u, Laufzeit %u s\n", panel::brightness(), s.restarts, uptimeS());
    if (s.otaBad.length()) Serial.printf("Zurueckgerollt, wird nicht mehr installiert: %s\n", s.otaBad.c_str());
  } else if (cmd == "anim") {
    for (int i = 0; i < anim::count(); i++) {
      const AnimInfo &a = anim::at(i);
      Serial.printf("  %-11s %.1f s%s\n", a.key, a.dur, a.loop ? ", Schleife" : "");
    }
  } else if (cmd.startsWith("anim ")) {
    String key = cmd.substring(5);
    key.trim();
    if (anim::find(key.c_str())) {
      demoKey = key;
      demoAt = millis();
      Serial.printf("Zeige %s\n", key.c_str());
    } else {
      Serial.printf("Unbekannt: %s\n", key.c_str());
    }
  } else if (cmd == "auto") {
    demoKey = "";
    testing = false;
    Serial.println("Normaler Ablauf");
  } else if (cmd == "lang de" || cmd == "lang en") {
    settings::setLang(cmd.substring(5));
  } else if (cmd.startsWith("bright ")) {
    int b = constrain(cmd.substring(7).toInt(), 0, 255);
    settings::setBright(b);
    serverBright = false;
    brightJump = true;
    Serial.printf("Helligkeit %d\n", b);
  } else if (cmd == "test") {
    testing = true;
    testAt = millis();
  } else if (cmd == "rtc") {
    time_t utc;
    if (board::rtcRead(utc)) {
      struct tm t;
      gmtime_r(&utc, &t);
      char buf[32];
      strftime(buf, sizeof buf, "%Y-%m-%d %H:%M:%S UTC", &t);
      Serial.printf("Uhrchip: %s\n", buf);
    } else {
      Serial.printf("Uhrchip: %s\n", board::rtcPresent() ? "keine gueltige Zeit" : "nicht gefunden");
    }
  } else if (cmd == "i2c") {
    Serial.printf("I2C: %s\n", board::scan().c_str());
  } else if (cmd == "forget") {
    settings::forget();
    Serial.println("WLAN und Schluessel vergessen, Neustart");
    delay(200);
    ESP.restart();
  } else if (cmd == "shot") {
    // Das zuletzt gezeigte Bild, eine Zeile je Pixelzeile, sechs Hex-Zeichen je Pixel.
    const Grid &f = panel::frame();
    Serial.printf("SHOT %d %d %d\n", Grid::W, Grid::H, panel::brightness());
    char row[Grid::W * 6 + 1];
    for (int y = 0; y < Grid::H; y++) {
      for (int x = 0; x < Grid::W; x++) snprintf(row + x * 6, 7, "%06X", (unsigned)(f.px[y * Grid::W + x] & 0xFFFFFF));
      Serial.println(row);
    }
    Serial.println("END");
  } else if (cmd == "log 1" || cmd == "log 0") {
    verbose = cmd.endsWith("1");
    Serial.printf("Protokoll %s\n", verbose ? "an" : "aus");
  } else if (cmd == "restart") {
    Serial.println("Neustart");
    delay(100);
    ESP.restart();
  } else if (cmd == "audio") {
    Serial.printf("Codec %s, Chip-ID %04X, Ton %d\n", audio::present() ? "da" : "fehlt", audio::chipId(), (int)audio::playing());
  } else if (cmd == "beep") {
    audio::play(audio::Tone::Test);
    Serial.println(audio::present() ? "Testton" : "Kein Codec, kein Ton");
  } else if (cmd == "ring") {
    // Wie ein Timer, der gerade ablief: Ton und Anzeige, bis zum Druck aufs Rad.
    ringTestAt = epochMs() - 100;
    Serial.println("Klingeln wie bei einem Timer, Rad druecken zum Stoppen");
  } else if (cmd == "mem") {
    Serial.printf("Heap frei %u, groesster Block %u, PSRAM frei %u\n", ESP.getFreeHeap(), ESP.getMaxAllocHeap(),
                  ESP.getFreePsram());
  } else if (cmd.length()) {
    Serial.printf("Unbekannter Befehl: %s\n", cmd.c_str());
  }
}

void handleSerial() {
  while (Serial.available()) {
    char ch = (char)Serial.read();
    if (ch == '\n' || ch == '\r') {
      if (line.length()) handleCommand(line);
      line = "";
    } else if (line.length() < 80) {
      line += ch;
    }
  }
}

// Frisch aufgespielte Firmware, die dreimal nicht 60 Sekunden durchhaelt, gibt an die
// vorige ab. Die liegt nach einem Update noch in der anderen App-Partition.
void guardUpdate() {
  if (!settings::otaFresh()) return;
  uint8_t tries = settings::bootTries() + 1;
  if (tries <= 3) {
    settings::setBootTries(tries);
    return;
  }
  settings::setOtaFresh(false);
  settings::setBootTries(0);
  // Im NVS merken, dass diese Version nicht anlief. Sonst bietet der Server sie der vorigen
  // gleich wieder an, und sie wird immer wieder installiert (Fehlerliste 24.09.2026, P1.4).
  settings::setOtaBad(FW_VERSION);
  const esp_partition_t *other = esp_ota_get_next_update_partition(nullptr);
  if (other && esp_ota_set_boot_partition(other) == ESP_OK) {
    Serial.println("Neue Firmware startet nicht, zurueck zur vorigen");
    delay(100);
    ESP.restart();
  }
}

}  // namespace

void setup() {
  Serial.begin(115200);
  // Auf einen offenen Monitor warten, aber nur mit einem Rechner an der Buchse. Am Netzteil
  // kommt keiner, und das Panel blieb bei jedem Start 1,2 Sekunden dunkel, auch nach EN.
  uint32_t t0 = millis();
  while (!Serial && HWCDC::isPlugged() && millis() - t0 < 1200) delay(10);
  Serial.printf("\nTHE WALL %s\n", FW_VERSION);

  settings::begin();
  guardUpdate();
  applyTz();

  uint8_t mac[6];
  esp_read_mac(mac, ESP_MAC_WIFI_STA);
  char buf[24];
  snprintf(buf, sizeof buf, "wall-%02x%02x%02x", mac[3], mac[4], mac[5]);
  deviceId = buf;
  snprintf(buf, sizeof buf, "%02X:%02X:%02X:%02X:%02X:%02X", mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
  macText = buf;
  // Grund des Starts merken. Unterspannung (ROM 15) heisst meist: das Netzteil schafft
  // die Helligkeit nicht. Ein Absturz hinterlaesst zusaetzlich einen Bericht in coredump.
  const char *why;
  switch (esp_reset_reason()) {
    case ESP_RST_POWERON: why = "Strom"; break;
    case ESP_RST_SW: why = "Neustart"; break;
    case ESP_RST_PANIC: why = "Absturz"; break;
    case ESP_RST_INT_WDT:
    case ESP_RST_TASK_WDT:
    case ESP_RST_WDT: why = "Watchdog"; break;
    case ESP_RST_BROWNOUT: why = "Unterspannung"; break;
    default: why = rtc_get_reset_reason(0) == USB_UART_CHIP_RESET ? "USB" : "anderer"; break;
  }
  char reason[32];
  snprintf(reason, sizeof reason, "%s/%d", why, (int)rtc_get_reset_reason(0));
  settings::noteReset(settings::get().restarts, reason);

  // EN schaltet den Chip in der Hardware aus, solange die Taste unten ist. Den ersten Druck
  // kann keine Firmware abfangen, er startet neu wie das Einstecken. Danach stehen sofort
  // fuenf Sekunden EN NOCHMAL = RESET auf dem Panel und eine Marke im NVS. Findet der
  // naechste Start die Marke, war das der zweite Druck: WLAN und Schluessel sind weg, und
  // das Geraet geht ohne weiteren Neustart ins Einrichtungsnetz. Das Fenster oeffnet nur,
  // wenn der vorige Lauf laenger als 10 Sekunden ging: startet das Geraet durch einen
  // Wackelkontakt immer wieder neu, oeffnet es sich nicht jedes Mal.
  bool prevLong = settings::lastRunLong();
  settings::setLastRunLong(false);
  esp_reset_reason_t cause = esp_reset_reason();
  bool byHand = cause == ESP_RST_POWERON || cause == ESP_RST_EXT;
  if (byHand && settings::resetArmed()) {
    settings::setResetArmed(false);
    settings::forget();
    wiped = true;
    Serial.println("EN zweimal gedrueckt: WLAN und Schluessel vergessen");
  } else if (byHand && prevLong) {
    settings::setResetArmed(true);
    enArmed = true;
    Serial.println("EN-Fenster offen: fuenf Sekunden, ein Druck auf EN setzt zurueck");
  } else {
    settings::setResetArmed(false);
  }

  Serial.printf("Geraet %s, Start Nummer %u nach %s\n", deviceId.c_str(), settings::get().restarts, reason);

  Serial.println(FW_MARKER);
  if (!screen.begin() || !before.begin() || !incoming.begin() || !anim::begin()) Serial.println("Kein Speicher fuer das Raster");
  if (!panel::begin(settings::get().bright)) Serial.println("Panel: begin() fehlgeschlagen");
  // Das erste Bild gleich hier, nicht erst nach WLAN und Portal: nach einem Druck auf EN
  // soll das Fenster so schnell wie moeglich stehen.
  if (enArmed || wiped) {
    bootAt = enArmedAt = wipedAt = millis();
    screen.clear();
    draw(wiped ? Screen::Wiped : Screen::EnWindow, bootAt);
    panel::show(screen);
  }

  board::begin();
  pinMode(PIN_BOOT, INPUT_PULLUP);
  // Nach dem I2C-Bus: der Codec haengt daran. Ohne Codec bleibt das Geraet nur still.
  if (!audio::begin()) Serial.println("Kein Codec gefunden, Klingeln ohne Ton");
  time_t utc;
  if (board::rtcRead(utc)) {
    setEpochMs((int64_t)utc * 1000);
    Serial.println("Uhrzeit aus dem Uhrchip");
  }
  if (!live::begin()) Serial.println("Dateispeicher nicht bereit, Logos nur im RAM");
  if (!fetch::begin()) Serial.println("Abruf-Aufgabe startet nicht");

  wlan::begin(deviceId);
  startPortal();
  if (settings::get().ssid.isEmpty()) {
    wlan::setAp(true);
    Serial.println("Kein WLAN eingerichtet, Einrichtungsnetz an");
  } else {
    wlan::join(settings::get().ssid, settings::get().pass);
  }
  // Erst jetzt, das erste Einbinden des Dateispeichers formatiert ihn und dauert.
  bootAt = millis();
  bootAnim = !wiped;
}

void loop() {
  static uint32_t nextFrame = 0;
  uint32_t now = millis();

  closeWindows(now);
  handleSerial();
  wlan::loop();
  portal::loop();
  manageWifi(now);
  manageNtp();
  if (ntpSynced) {
    ntpSynced = false;
    timeValid = true;
    writeRtcSoon(now, true);
  }
  startMdns();
  handleWheel(now);
  updateRing();
  requestFrame(now);
  takeFrame(now);
  pollRev(now);
  handleLogos();
  handleOta(now);

  if (enArmed && now - enArmedAt > EN_WINDOW_MS) {
    enArmed = false;
    settings::setResetArmed(false);
    Serial.println("EN-Fenster zu");
  }
  if (!longRunNoted && now - bootAt > LONG_RUN_MS) {
    longRunNoted = true;
    settings::setLastRunLong(true);
  }

  if (!stable && now > STABLE_MS) {
    stable = true;
    if (settings::otaFresh()) {
      settings::setOtaFresh(false);
      settings::setBootTries(0);
      // Eine neu installierte Version laeuft: die Sperre einer frueher gescheiterten faellt.
      settings::setOtaBad("");
    }
    esp_ota_mark_app_valid_cancel_rollback();
  }

  if ((int32_t)(now - nextFrame) < 0) {
    delay(2);
    return;
  }
  nextFrame = now + FRAME_MS;

  Screen sc = choose(now);
  // Wechsel aus einer Geraete-Anzeige in die Seiten des Servers: schieben statt springen.
  if (sc == Screen::Live && lastScreen != Screen::Live && lastScreen != Screen::None && lastScreen != Screen::Off &&
      lastScreen != Screen::Boot && lastScreen != Screen::EnWindow && !settings::get().reduce) {
    before.copyFrom(panel::frame());
    pushing = true;
    pushAt = now;
    lastPageFrom = num64(live::pageAt(epochMs())["from"]);
  }
  if (sc != Screen::Live && lastScreen == Screen::Live) {
    pushing = dropping = swapping = rolling = false;
    songFx = 0;
  }

  screen.clear();
  draw(sc, now);
  lastScreen = sc;

  uint8_t level = serverBright ? serverLevel : settings::get().bright;
  // Test, Boot, das EN-Fenster und Zuruecksetzen bleiben sichtbar, auch bei Helligkeit 0: sonst
  // haelt man das Geraet fuer tot und trifft das Fenster fuer EN nicht (Fehlerliste F6).
  if (sc == Screen::Test || sc == Screen::Boot || sc == Screen::EnWindow || sc == Screen::Wiped || sc == Screen::Hold)
    level = max<uint8_t>(level, 60);
  // Die Einrichtung bleibt lesbar, auch wenn zuletzt nachts gedimmt war. Ab 96 halten auch
  // die dunklen Toene der Szene (night() in anim.cpp).
  if (sc == Screen::Setup) level = max<uint8_t>(level, 96);
  // Beim Klingeln hell genug, auch nachts gedimmt oder am Rad auf 0 gestellt.
  if (ringSince) level = max<uint8_t>(level, 96);
  // Ein neuer Wert vom Server, etwa bei Sonnenuntergang von 168 auf 59, kommt ueber knapp
  // zwei Sekunden statt als Sprung: 2 Stufen je Bild. Mit reduzierter Bewegung sofort.
  if (shownLevel < 0 || brightJump || settings::get().reduce || sc == Screen::Test) {
    shownLevel = level;
    brightJump = false;
  } else if (shownLevel < level) {
    shownLevel = min<int>(level, shownLevel + 2);
  } else if (shownLevel > level) {
    shownLevel = max<int>(level, shownLevel - 2);
  }
  panel::setBrightness((uint8_t)shownLevel);
  panel::show(screen);
}
