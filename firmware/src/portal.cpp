#include "portal.h"

#include <ArduinoJson.h>
#include <WebServer.h>
#include <WiFi.h>

#include "config.h"
#include "portal_page.h"
#include "wlan.h"

namespace {

WebServer server(80);
portal::Hooks hooks;

bool isIpHost(String host) {
  int colon = host.indexOf(':');
  if (colon >= 0) host = host.substring(0, colon);
  IPAddress ip;
  return host.length() > 0 && ip.fromString(host);
}

void noStore() {
  server.sendHeader("Cache-Control", "no-store");
  server.sendHeader("X-Content-Type-Options", "nosniff");
}

void sendJson(int code, const String &body) {
  noStore();
  server.send(code, "application/json", body);
}

void redirectToPortal() {
  server.sendHeader("Location", SETUP_URL, true);
  noStore();
  server.send(302, "text/plain", "");
}

// Internetpruefung eines Handys oder jede andere fremde Adresse: im Einrichtungsnetz
// zur Einrichtungsseite, sonst gibt es hier nichts.
void redirectOrMissing() {
  if (wlan::apOn()) {
    Serial.printf("Einrichtungsseite: %s%s umgeleitet\n", server.hostHeader().c_str(), server.uri().c_str());
    return redirectToPortal();
  }
  server.send(404, "text/plain", "Not found");
}

// Schreibende Anfragen: JSON, Host als IP, Origin passend zum Host.
bool readBody(JsonDocument &doc) {
  if (!isIpHost(server.hostHeader())) {
    sendJson(403, "{\"error\":\"host\"}");
    return false;
  }
  String origin = server.header("Origin");
  if (origin.length() && origin != String("http://") + server.hostHeader()) {
    sendJson(403, "{\"error\":\"origin\"}");
    return false;
  }
  if (!server.header("Content-Type").startsWith("application/json")) {
    sendJson(415, "{\"error\":\"json\"}");
    return false;
  }
  if (deserializeJson(doc, server.arg("plain"))) {
    sendJson(400, "{\"error\":\"json\"}");
    return false;
  }
  return true;
}

void handleRoot() {
  if (!isIpHost(server.hostHeader())) return redirectOrMissing();
  noStore();
  // Keine fremden Schriften: im Einrichtungsnetz gibt es kein Internet, und im Heimnetz
  // ginge die Adresse des Handys an den Schriftdienst. Der Systemstapel reicht.
  server.sendHeader("Content-Security-Policy",
                    "default-src 'self'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data:; "
                    "connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
  // Eigener Server per Build-Flag: dessen Name statt thewall.godart.lu. Nur dann eine Kopie im RAM.
  String host = serverHost();
  if (host == "thewall.godart.lu") {
    server.send_P(200, "text/html; charset=utf-8", PORTAL_PAGE);
  } else {
    String page(PORTAL_PAGE);
    page.replace("thewall.godart.lu", host);
    server.send(200, "text/html; charset=utf-8", page);
  }
}

void handleState() {
  if (!isIpHost(server.hostHeader())) return sendJson(403, "{\"error\":\"host\"}");
  sendJson(200, hooks.state());
}

void handleScan() {
  if (!isIpHost(server.hostHeader())) return sendJson(403, "{\"error\":\"host\"}");
  wlan::startScan();
  sendJson(200, String("{\"scanning\":") + (wlan::scanning() ? "true" : "false") + ",\"nets\":" + wlan::scanJson() + "}");
}

void handleWifi() {
  JsonDocument doc;
  if (!readBody(doc)) return;
  String ssid = doc["ssid"] | "";
  String pass = doc["pass"] | "";
  if (ssid.length() < 1 || ssid.length() > 32 || pass.length() > 63 || (pass.length() > 0 && pass.length() < 8)) {
    return sendJson(422, "{\"error\":\"wifi\"}");
  }
  sendJson(200, "{\"ok\":true}");
  hooks.wifi(ssid, pass);
}

void handleKey() {
  JsonDocument doc;
  if (!readBody(doc)) return;
  String key = doc["key"] | "";
  key.trim();
  bool shape = key.length() == 24 && key.startsWith("tw_live_");
  for (int i = 8; shape && i < 24; i++) shape = isxdigit((unsigned char)key[i]) && !isupper((unsigned char)key[i]);
  if (!shape) return sendJson(422, "{\"error\":\"key\"}");
  sendJson(200, "{\"ok\":true}");
  hooks.key(key);
}

void handleBright() {
  JsonDocument doc;
  if (!readBody(doc)) return;
  int v = doc["v"] | -1;
  // Unter 8 bleibt das Panel dunkel, und das Geraet wirkt tot (Fehlerliste F6).
  if (v < 8 || v > 255) return sendJson(422, "{\"error\":\"bright\"}");
  hooks.bright((uint8_t)v);
  sendJson(200, "{\"ok\":true}");
}

void handleClock() {
  JsonDocument doc;
  if (!readBody(doc)) return;
  hooks.clock(doc["on"] | true);
  sendJson(200, "{\"ok\":true}");
}

void handleTest() {
  JsonDocument doc;
  if (!readBody(doc)) return;
  hooks.test();
  sendJson(200, "{\"ok\":true}");
}

void handleLang() {
  JsonDocument doc;
  if (!readBody(doc)) return;
  hooks.lang(doc["lang"] | "en");
  sendJson(200, "{\"ok\":true}");
}

// Handys pruefen mit festen Adressen, ob sie ins Internet kommen: Android
// /generate_204, Apple /hotspot-detect.html, Windows /connecttest.txt und /ncsi.txt.
// Eine Umleitung heisst fuer sie "Anmeldung noetig", dann oeffnen sie die Seite.
void handleNotFound() { redirectOrMissing(); }

}  // namespace

namespace portal {

void begin(const Hooks &h) {
  hooks = h;
  const char *headers[] = {"Origin", "Content-Type"};
  server.collectHeaders(headers, 2);
  server.on("/", HTTP_GET, handleRoot);
  server.on("/api/state", HTTP_GET, handleState);
  server.on("/api/scan", HTTP_GET, handleScan);
  server.on("/api/wifi", HTTP_POST, handleWifi);
  server.on("/api/key", HTTP_POST, handleKey);
  server.on("/api/bright", HTTP_POST, handleBright);
  server.on("/api/clock", HTTP_POST, handleClock);
  server.on("/api/test", HTTP_POST, handleTest);
  server.on("/api/lang", HTTP_POST, handleLang);
  // Ohne Symbol und ohne Proxy-Suche, sonst fragt Windows endlos nach.
  server.on("/favicon.ico", HTTP_GET, []() { server.send(404, "text/plain", ""); });
  server.on("/wpad.dat", HTTP_GET, []() { server.send(404, "text/plain", ""); });
  server.onNotFound(handleNotFound);
  server.begin();
}

void loop() { server.handleClient(); }

}  // namespace portal
