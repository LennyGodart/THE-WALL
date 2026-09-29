#include "wlan.h"

#include <ArduinoJson.h>
#include <DNSServer.h>
#include <WiFi.h>
#include <esp_wifi.h>

#include <algorithm>
#include <atomic>
#include <vector>

#include "config.h"

namespace {

constexpr int NOT_FOUND_LIMIT = 3;
constexpr int WRONG_LIMIT = 2;
int wrongCount = 0;

wlan::State current = wlan::State::Idle;
uint32_t changedAt = 0;
bool wasUp = false;
bool ap = false;
DNSServer dns;
String joinSsid, joinPass;
int notFoundCount = 0;
bool scanRunning = false;
String scanCache = "[]";

std::atomic<bool> gotIp{false};
std::atomic<bool> dropped{false};
std::atomic<uint8_t> dropReason{0};

void setState(wlan::State s) {
  if (s == current) return;
  current = s;
  changedAt = millis();
  if (s == wlan::State::Up) wasUp = true;
}

void onEvent(arduino_event_id_t event, arduino_event_info_t info) {
  if (event == ARDUINO_EVENT_WIFI_STA_GOT_IP) {
    gotIp = true;
  } else if (event == ARDUINO_EVENT_WIFI_STA_DISCONNECTED) {
    dropReason = info.wifi_sta_disconnected.reason;
    dropped = true;
  }
}

// Gruende, die sicher auf ein falsches Passwort deuten.
bool wrongPassword(uint8_t reason) {
  return reason == WIFI_REASON_AUTH_FAIL || reason == WIFI_REASON_4WAY_HANDSHAKE_TIMEOUT ||
         reason == WIFI_REASON_HANDSHAKE_TIMEOUT || reason == WIFI_REASON_MIC_FAILURE;
}

bool notFound(uint8_t reason) { return reason == WIFI_REASON_NO_AP_FOUND; }

IPAddress setupIp() { return IPAddress(SETUP_IP[0], SETUP_IP[1], SETUP_IP[2], SETUP_IP[3]); }

bool apWpa2 = false;  // letzte Rueckmeldung des Treibers: Einrichtungsnetz mit WPA2 und Passwort

// Einrichtungsnetz mit WPA2 und Passwort. Die Konfiguration wird hier selbst gebaut und
// genullt, statt WiFi.softAP() zu nehmen, das ein ungenulltes wifi_config_t fuellt und einen
// Fehler nur im Log meldet: dann liefe das Netz mit der alten oder der offenen Vorgabe weiter.
// Danach wird zurueckgelesen, ob es wirklich verschluesselt ist, bis zu drei Versuche.
void startSetupAp() {
  for (int attempt = 0; attempt < 3; attempt++) {
    wifi_config_t c;
    memset(&c, 0, sizeof c);
    strlcpy((char *)c.ap.ssid, SETUP_SSID, sizeof c.ap.ssid);
    c.ap.ssid_len = strlen(SETUP_SSID);
    strlcpy((char *)c.ap.password, SETUP_PASS, sizeof c.ap.password);
    c.ap.channel = 1;
    c.ap.authmode = WIFI_AUTH_WPA2_PSK;
    c.ap.pairwise_cipher = WIFI_CIPHER_TYPE_CCMP;
    c.ap.max_connection = 4;
    c.ap.beacon_interval = 100;
    esp_err_t err = esp_wifi_set_config(WIFI_IF_AP, &c);
    wifi_config_t back;
    memset(&back, 0, sizeof back);
    esp_wifi_get_config(WIFI_IF_AP, &back);
    apWpa2 = err == ESP_OK && back.ap.authmode == WIFI_AUTH_WPA2_PSK &&
                !strncmp((const char *)back.ap.password, SETUP_PASS, sizeof back.ap.password);
    if (apWpa2) break;
    Serial.printf("Einrichtungsnetz: Konfiguration fehlgeschlagen (%d), Versuch %d\n", (int)err, attempt + 1);
    delay(50);
  }
  Serial.printf("Einrichtungsnetz %s, %s\n", SETUP_SSID, apWpa2 ? "WPA2 mit Passwort" : "OFFEN, Passwort nicht gesetzt");
}

void applyMode() {
  WiFi.mode(ap ? WIFI_AP_STA : WIFI_STA);
  if (ap) {
    WiFi.softAPConfig(setupIp(), setupIp(), IPAddress(255, 255, 255, 0));
    startSetupAp();
    // Feste Adresse statt WiFi.softAPIP(): direkt nach softAP() kann die noch 0.0.0.0
    // sein, dann beantwortet der DNS jede Frage mit 0.0.0.0 und das Handy meldet "kein Internet".
    dns.setTTL(300);
    dns.setErrorReplyCode(DNSReplyCode::NoError);
    dns.start(53, "*", setupIp());
    Serial.printf("Einrichtungsnetz an, Adresse %s\n", WiFi.softAPIP().toString().c_str());
  } else {
    dns.stop();
  }
}

}  // namespace

namespace wlan {

void begin(const String &hostname) {
  WiFi.persistent(false);
  WiFi.onEvent(onEvent);
  WiFi.setHostname(hostname.c_str());
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.setAutoReconnect(false);
  changedAt = millis();
}

void loop() {
  if (ap) dns.processNextRequest();

  if (gotIp.exchange(false)) {
    notFoundCount = 0;
    wrongCount = 0;
    setState(State::Up);
  }
  if (dropped.exchange(false)) {
    uint8_t reason = dropReason;
    Serial.printf("WLAN getrennt, Grund %u\n", reason);
    if (current == State::Joining) {
      // Ein einzelner Handshake-Fehler ist kein Beweis: direkt nach einem Neustart kennt
      // der Router das Geraet oft noch, und der erste Versuch scheitert trotz richtigem Passwort.
      if (wrongPassword(reason) && ++wrongCount >= WRONG_LIMIT) {
        WiFi.disconnect();
        setState(State::Wrong);
      } else if (notFound(reason) && ++notFoundCount >= NOT_FOUND_LIMIT) {
        WiFi.disconnect();
        setState(State::NotFound);
      } else {
        // Das erste Mal findet der Scan das Netz manchmal nicht, also nochmal.
        WiFi.begin(joinSsid.c_str(), joinPass.c_str());
      }
    } else if (current == State::Up) {
      // Verbindung verloren: sofort neu versuchen, der Aufrufer sieht Joining.
      setState(State::Joining);
      WiFi.begin(joinSsid.c_str(), joinPass.c_str());
    }
  }
  if (current == State::Joining && millis() - changedAt > wlan::JOIN_TIMEOUT_MS) {
    WiFi.disconnect();
    setState(State::Failed);
  }

  if (scanRunning) {
    int n = WiFi.scanComplete();
    if (n >= 0) {
      JsonDocument doc;
      JsonArray list = doc.to<JsonArray>();
      for (int i = 0; i < n; i++) {
        String name = WiFi.SSID(i);
        if (name.length() == 0) continue;
        bool seen = false;
        for (JsonObject item : list) {
          if (name == (item["ssid"] | "")) {
            if (WiFi.RSSI(i) > (item["rssi"] | -127)) item["rssi"] = WiFi.RSSI(i);
            seen = true;
            break;
          }
        }
        if (seen) continue;
        JsonObject item = list.add<JsonObject>();
        item["ssid"] = name;
        item["rssi"] = WiFi.RSSI(i);
        item["open"] = WiFi.encryptionType(i) == WIFI_AUTH_OPEN;
      }
      // Staerkstes Netz zuerst.
      std::vector<JsonObject> sorted;
      for (JsonObject item : list) sorted.push_back(item);
      std::sort(sorted.begin(), sorted.end(),
                [](JsonObject a, JsonObject b) { return (a["rssi"] | -127) > (b["rssi"] | -127); });
      JsonDocument out;
      JsonArray outList = out.to<JsonArray>();
      for (JsonObject item : sorted) outList.add(item);
      scanCache = "";
      serializeJson(out, scanCache);
      WiFi.scanDelete();
      scanRunning = false;
    } else if (n == WIFI_SCAN_FAILED) {
      scanRunning = false;
    }
  }
}

void join(const String &ssid, const String &pass) {
  joinSsid = ssid;
  joinPass = pass;
  notFoundCount = 0;
  wrongCount = 0;
  WiFi.disconnect();
  current = State::Idle;
  setState(State::Joining);
  WiFi.begin(joinSsid.c_str(), joinPass.c_str());
}

State state() { return current; }

uint32_t stateAge() { return millis() - changedAt; }

bool everUp() { return wasUp; }

String ssid() { return current == State::Up ? WiFi.SSID() : joinSsid; }

int rssi() { return current == State::Up ? WiFi.RSSI() : 0; }

IPAddress ip() { return current == State::Up ? WiFi.localIP() : IPAddress(); }

void setAp(bool on) {
  if (on == ap) return;
  ap = on;
  applyMode();
  // Beim Wechsel der Betriebsart kann ein laufender Versuch abbrechen, eine
  // bestehende Verbindung bleibt.
  if (current == State::Joining) WiFi.begin(joinSsid.c_str(), joinPass.c_str());
}

bool apOn() { return ap; }

bool apSecured() { return ap && apWpa2; }

IPAddress apIp() { return setupIp(); }

void startScan() {
  if (scanRunning || current == State::Joining) return;
  if (WiFi.scanNetworks(true, false) == WIFI_SCAN_RUNNING) scanRunning = true;
}

bool scanning() { return scanRunning; }

String scanJson() { return scanCache; }

}  // namespace wlan
