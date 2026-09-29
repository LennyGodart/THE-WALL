#include "fetch.h"

#include <HTTPClient.h>
#include <Update.h>
#include <WiFiClientSecure.h>
#include <mbedtls/sha256.h>

#include <atomic>

#include "certs.h"
#include "config.h"

namespace {

enum class Kind : uint8_t { Frame, Rev, Logo, Firmware, RingStop };

struct Job {
  Kind kind;
  char key[40];
  char id[33];
  char arg[200];  // Logo-Kuerzel oder Firmware-URL
  char sha256[65];
  uint32_t size;
  fetch::Telemetry tel;
};

// ArduinoJson legt die Antwort ins PSRAM, der interne Speicher bleibt fuer TLS.
struct SpiRamAllocator : ArduinoJson::Allocator {
  void *allocate(size_t n) override { return heap_caps_malloc(n, MALLOC_CAP_SPIRAM); }
  void deallocate(void *p) override { heap_caps_free(p); }
  void *reallocate(void *p, size_t n) override { return heap_caps_realloc(p, n, MALLOC_CAP_SPIRAM); }
};

SpiRamAllocator psram;
QueueHandle_t jobs = nullptr;
QueueHandle_t frames = nullptr;
QueueHandle_t logos = nullptr;
QueueHandle_t revs = nullptr;
std::atomic<bool> framePending{false};
std::atomic<bool> logoPending{false};
std::atomic<bool> revPending{false};
std::atomic<uint8_t> otaStateValue{(uint8_t)fetch::Ota::Idle};
std::atomic<int> otaProgress{0};
SemaphoreHandle_t textLock = nullptr;
String otaErrorText;
String lastErrorText;
WiFiClientSecure client;

void setText(String &field, const String &value) {
  xSemaphoreTake(textLock, portMAX_DELAY);
  field = value;
  xSemaphoreGive(textLock);
}

String getText(const String &field) {
  xSemaphoreTake(textLock, portMAX_DELAY);
  String copy = field;
  xSemaphoreGive(textLock);
  return copy;
}

// Eine Verbindung fuer Frame, Revision und Logos. HTTPClient schliesst sie in seinem
// Destruktor, deshalb lebt er so lange wie die Aufgabe; zwischen den Abrufen bleibt
// TLS offen und jeder Abruf spart rund zwei Sekunden Handshake.
HTTPClient api;

// Header duerfen nur druckbares ASCII enthalten. Ein Netzname mit Umlauten wird zu "?".
String headerSafe(const char *s) {
  String out;
  for (; *s; s++) out += (*s >= 0x20 && *s < 0x7F) ? *s : '?';
  return out;
}

// GET auf dem eigenen Server mit Schluessel. Hat der Server eine offene Verbindung
// inzwischen geschlossen, gibt es genau einen zweiten Versuch mit neuer Verbindung.
int apiGet(const String &path, const Job &job, bool telemetry, bool &reused) {
  for (int attempt = 0; attempt < 2; attempt++) {
    reused = client.connected();
    if (!api.begin(client, String(WALL_SERVER) + path)) return 0;
    api.setReuse(true);
    api.setConnectTimeout(8000);
    api.setTimeout(10000);
    api.setUserAgent("THEWALL/" FW_VERSION);
    api.addHeader("Authorization", String("Bearer ") + job.key);
    if (job.id[0]) api.addHeader("X-Wall-Id", job.id);
    if (telemetry) {
      api.addHeader("X-Wall-Fw", FW_VERSION);
      api.addHeader("X-Wall-Ssid", headerSafe(job.tel.ssid));
      api.addHeader("X-Wall-Rssi", String(job.tel.rssi));
      api.addHeader("X-Wall-Uptime", String(job.tel.uptime));
      api.addHeader("X-Wall-Temp", String(job.tel.temp));
      api.addHeader("X-Wall-Flash", String(job.tel.flash));
      api.addHeader("X-Wall-Restarts", String(job.tel.restarts));
      if (job.tel.fwBad[0]) api.addHeader("X-Wall-Fw-Bad", headerSafe(job.tel.fwBad));
    }
    const char *keys[] = {"Retry-After"};
    api.collectHeaders(keys, 1);
    int code = api.GET();
    if (code > 0) return code;
    setText(lastErrorText, HTTPClient::errorToString(code));
    api.end();
    client.stop();
    if (!reused) return code;
  }
  return 0;
}

// Antwort fertig lesen und die Verbindung fuer den naechsten Abruf offen lassen.
String apiBody() { return api.getString(); }

void apiDone(int code) {
  if (code <= 0) {
    client.stop();
    return;
  }
  api.end();
}

void doFrame(const Job &job) {
  fetch::FrameResult r{};
  uint32_t t0 = millis();
  int code = apiGet("/api/v1/frame", job, true, r.reused);
  r.arrivedAt = millis();
  r.rttMs = r.arrivedAt - t0;
  if (code > 0) {
    r.status = code;
    String body = apiBody();
    if (code == 200) {
      JsonDocument *doc = new JsonDocument(&psram);
      DeserializationError err = deserializeJson(*doc, body);
      if (err) {
        delete doc;
        r.status = -1;
        setText(lastErrorText, String("JSON ") + err.c_str());
      } else {
        r.doc = doc;
        setText(lastErrorText, "");
      }
    } else {
      if (code == 429) r.retryAfter = api.header("Retry-After").toInt();
      setText(lastErrorText, String("HTTP ") + code);
    }
  }
  apiDone(code);
  if (xQueueSend(frames, &r, 0) != pdTRUE && r.doc) delete r.doc;
  framePending = false;
}

void doRev(const Job &job) {
  fetch::RevResult r{};
  uint32_t t0 = millis();
  bool reused = false;
  int code = apiGet("/api/v1/rev", job, false, reused);
  r.rttMs = millis() - t0;
  r.status = code > 0 ? code : 0;
  if (code > 0) {
    String body = apiBody();
    if (code == 200) {
      JsonDocument doc;
      if (!deserializeJson(doc, body)) strncpy(r.rev, doc["rev"] | "", sizeof(r.rev) - 1);
    } else if (code == 429) {
      r.retryAfter = api.header("Retry-After").toInt();
    }
  }
  apiDone(code);
  xQueueSend(revs, &r, 0);
  revPending = false;
}

void doLogo(const Job &job) {
  fetch::LogoResult r{};
  strncpy(r.code, job.arg, 3);
  bool reused = false;
  int code = apiGet(String("/api/v1/logo/") + job.arg, job, false, reused);
  r.status = code > 0 ? code : 0;
  if (code > 0) {
    String body = apiBody();
    if (code == 200) {
      if (body.length() == fetch::LOGO_BYTES) {
        r.rgb = (uint8_t *)heap_caps_malloc(fetch::LOGO_BYTES, MALLOC_CAP_SPIRAM);
        if (r.rgb) memcpy(r.rgb, body.c_str(), fetch::LOGO_BYTES);
      } else {
        r.status = -1;
      }
    }
  }
  apiDone(code);
  if (xQueueSend(logos, &r, 0) != pdTRUE && r.rgb) heap_caps_free(r.rgb);
  logoPending = false;
}

// POST ohne Inhalt auf derselben Verbindung. Ein Fehler bleibt still: kommt der Stopp nicht an,
// klingelt es auf dem Server hoechstens bis zum Ende seiner Viertelstunde, das Geraet bleibt ruhig.
void doRingStop(const Job &job) {
  if (!api.begin(client, String(WALL_SERVER) + "/api/v1/ring/stop")) return;
  api.setReuse(true);
  api.setConnectTimeout(8000);
  api.setTimeout(10000);
  api.setUserAgent("THEWALL/" FW_VERSION);
  api.addHeader("Authorization", String("Bearer ") + job.key);
  api.addHeader("X-Wall-Id", job.id);
  api.addHeader("Content-Type", "application/json");
  int code = api.POST("{}");
  if (code > 0) {
    apiBody();
  } else {
    setText(lastErrorText, HTTPClient::errorToString(code));
  }
  apiDone(code);
}

void otaFail(const String &why) {
  setText(otaErrorText, why);
  otaStateValue = (uint8_t)fetch::Ota::Failed;
}

void doFirmware(const Job &job) {
  otaProgress = 0;
  setText(otaErrorText, "");
  String url = job.arg;
  // Nur vom eigenen Server, nie von einer Adresse, die in der Antwort steht.
  if (!url.startsWith(String(WALL_SERVER) + "/api/v1/firmware/")) return otaFail("URL");

  HTTPClient http;
  http.setReuse(false);
  http.setConnectTimeout(8000);
  http.setTimeout(15000);
  http.setUserAgent("THEWALL/" FW_VERSION);
  if (!http.begin(client, url)) return otaFail("begin");
  http.addHeader("Authorization", String("Bearer ") + job.key);
  http.addHeader("X-Wall-Id", job.id);
  const char *keys[] = {"X-Checksum-Sha256"};
  http.collectHeaders(keys, 1);
  int code = http.GET();
  if (code != 200) {
    http.end();
    client.stop();
    return otaFail(String("HTTP ") + code);
  }
  int len = http.getSize();
  if (len <= 0 || (job.size && (uint32_t)len != job.size)) {
    http.end();
    client.stop();
    return otaFail("Groesse");
  }
  if (!Update.begin(len)) {
    http.end();
    client.stop();
    return otaFail(Update.errorString());
  }

  mbedtls_sha256_context sha;
  mbedtls_sha256_init(&sha);
  mbedtls_sha256_starts(&sha, 0);
  WiFiClient *stream = http.getStreamPtr();
  static uint8_t buf[4096];
  int done = 0;
  uint32_t lastData = millis();
  bool writeError = false;
  while (done < len) {
    int want = min((int)sizeof(buf), len - done);
    int n = stream->read(buf, want);
    if (n > 0) {
      if (Update.write(buf, n) != (size_t)n) {
        writeError = true;
        break;
      }
      mbedtls_sha256_update(&sha, buf, n);
      done += n;
      lastData = millis();
      otaProgress = (int)((int64_t)done * 1000 / len);
    } else {
      if (!stream->connected() && !stream->available()) break;
      if (millis() - lastData > 20000) break;
      delay(2);
    }
  }
  uint8_t digest[32];
  mbedtls_sha256_finish(&sha, digest);
  mbedtls_sha256_free(&sha);
  char hex[65];
  for (int i = 0; i < 32; i++) sprintf(hex + i * 2, "%02x", digest[i]);
  String header = http.header("X-Checksum-Sha256");
  header.toLowerCase();
  String expected = job.sha256;
  expected.toLowerCase();
  http.end();
  client.stop();

  if (writeError) {
    Update.abort();
    return otaFail(Update.errorString());
  }
  if (done != len) {
    Update.abort();
    return otaFail("abgebrochen");
  }
  if (expected != hex || (header.length() && header != hex)) {
    Update.abort();
    return otaFail("SHA-256");
  }
  if (!Update.end(true)) return otaFail(Update.errorString());
  otaProgress = 1000;
  otaStateValue = (uint8_t)fetch::Ota::Done;
}

void task(void *) {
  Job job;
  for (;;) {
    if (xQueueReceive(jobs, &job, portMAX_DELAY) != pdTRUE) continue;
    switch (job.kind) {
      case Kind::Frame: doFrame(job); break;
      case Kind::Rev: doRev(job); break;
      case Kind::Logo: doLogo(job); break;
      case Kind::Firmware: doFirmware(job); break;
      case Kind::RingStop: doRingStop(job); break;
    }
  }
}

bool enqueue(const Job &job) { return xQueueSend(jobs, &job, 0) == pdTRUE; }

}  // namespace

namespace fetch {

bool begin() {
  textLock = xSemaphoreCreateMutex();
  jobs = xQueueCreate(4, sizeof(Job));
  frames = xQueueCreate(2, sizeof(FrameResult));
  logos = xQueueCreate(4, sizeof(LogoResult));
  revs = xQueueCreate(2, sizeof(RevResult));
  client.setCACert(ROOT_CERTS);
  client.setHandshakeTimeout(12);
  return xTaskCreatePinnedToCore(task, "fetch", 16384, nullptr, 1, nullptr, 0) == pdPASS;
}

bool requestFrame(const String &key, const String &id, const Telemetry &t) {
  if (framePending) return false;
  Job job{};
  job.kind = Kind::Frame;
  strncpy(job.key, key.c_str(), sizeof(job.key) - 1);
  strncpy(job.id, id.c_str(), sizeof(job.id) - 1);
  job.tel = t;
  framePending = true;
  if (!enqueue(job)) {
    framePending = false;
    return false;
  }
  return true;
}

bool requestRev(const String &key, const String &id) {
  if (revPending) return false;
  Job job{};
  job.kind = Kind::Rev;
  strncpy(job.key, key.c_str(), sizeof(job.key) - 1);
  strncpy(job.id, id.c_str(), sizeof(job.id) - 1);
  revPending = true;
  if (!enqueue(job)) {
    revPending = false;
    return false;
  }
  return true;
}

bool requestLogo(const String &key, const char *code) {
  if (logoPending) return false;
  Job job{};
  job.kind = Kind::Logo;
  strncpy(job.key, key.c_str(), sizeof(job.key) - 1);
  strncpy(job.arg, code, 3);
  logoPending = true;
  if (!enqueue(job)) {
    logoPending = false;
    return false;
  }
  return true;
}

bool requestFirmware(const String &key, const String &id, const String &url, const String &sha256, uint32_t size) {
  if (otaStateValue == (uint8_t)Ota::Running) return false;
  Job job{};
  job.kind = Kind::Firmware;
  strncpy(job.key, key.c_str(), sizeof(job.key) - 1);
  strncpy(job.id, id.c_str(), sizeof(job.id) - 1);
  strncpy(job.arg, url.c_str(), sizeof(job.arg) - 1);
  strncpy(job.sha256, sha256.c_str(), sizeof(job.sha256) - 1);
  job.size = size;
  otaStateValue = (uint8_t)Ota::Running;
  if (!enqueue(job)) {
    otaStateValue = (uint8_t)Ota::Idle;
    return false;
  }
  return true;
}

bool requestRingStop(const String &key, const String &id) {
  Job job{};
  job.kind = Kind::RingStop;
  strncpy(job.key, key.c_str(), sizeof(job.key) - 1);
  strncpy(job.id, id.c_str(), sizeof(job.id) - 1);
  return enqueue(job);
}

bool frameBusy() { return framePending; }

bool logoBusy() { return logoPending; }

bool revBusy() { return revPending; }

bool takeFrame(FrameResult &out) { return xQueueReceive(frames, &out, 0) == pdTRUE; }

bool takeLogo(LogoResult &out) { return xQueueReceive(logos, &out, 0) == pdTRUE; }

bool takeRev(RevResult &out) { return xQueueReceive(revs, &out, 0) == pdTRUE; }

Ota otaState() { return (Ota)otaStateValue.load(); }

int otaPermille() { return otaProgress; }

String otaError() { return getText(otaErrorText); }

String lastError() { return getText(lastErrorText); }

}  // namespace fetch
