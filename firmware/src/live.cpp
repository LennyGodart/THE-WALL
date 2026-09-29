#include "live.h"

#include <LittleFS.h>

#include "anim.h"
#include "clocktext.h"
#include "config.h"
#include "fetch.h"

namespace {

JsonDocument *current = nullptr;
bool fsReady = false;

// Laufende Animation: der Takt laeuft weiter, solange dieselbe gezeigt wird,
// auch wenn jede Antwort neue Seiten mit neuen Zeitfenstern bringt.
char animId[24] = "";
uint32_t animStart = 0;

enum class LogoState : uint8_t { Free, Wanted, Loading, Ready, Missing };

struct Logo {
  char code[4];
  LogoState state;
  uint8_t *rgb;
  uint32_t stamp;  // letzte Benutzung, bei Missing der Zeitpunkt der Absage
  uint32_t wait;   // bei Missing: so lange bis zur naechsten Frage
  uint8_t fails;   // Fehler in Folge, ohne 404
};

constexpr int LOGO_SLOTS = 40;
constexpr uint32_t LOGO_RETRY_MS = 60UL * 60UL * 1000UL;
constexpr uint32_t LOGO_BACKOFF_MS = 60UL * 1000UL;
Logo logos[LOGO_SLOTS];

bool validCode(const char *code) {
  int n = strlen(code);
  if (n < 2 || n > 3) return false;
  for (int i = 0; i < n; i++)
    if (code[i] < 'A' || code[i] > 'Z') return false;
  return true;
}

String logoPath(const char *code) { return String("/logo/") + code + ".rgb"; }

Logo *findLogo(const char *code) {
  for (Logo &l : logos)
    if (l.state != LogoState::Free && strcmp(l.code, code) == 0) return &l;
  return nullptr;
}

Logo *slotForLogo() {
  Logo *oldest = nullptr;
  for (Logo &l : logos) {
    if (l.state == LogoState::Free) return &l;
    if (l.state == LogoState::Loading || l.state == LogoState::Wanted) continue;
    if (!oldest || l.stamp < oldest->stamp) oldest = &l;
  }
  if (oldest && oldest->rgb) {
    heap_caps_free(oldest->rgb);
    oldest->rgb = nullptr;
  }
  return oldest;
}

// Logo fuer den Befehl, aus dem Speicher, der Datei oder als Wunsch an den Server.
const uint8_t *logoFor(const char *code) {
  if (!validCode(code)) return nullptr;
  Logo *l = findLogo(code);
  uint32_t now = millis();
  if (!l) {
    l = slotForLogo();
    if (!l) return nullptr;
    strncpy(l->code, code, 3);
    l->code[3] = '\0';
    l->rgb = nullptr;
    l->state = LogoState::Wanted;
    l->wait = 0;
    l->fails = 0;
    if (fsReady && LittleFS.exists(logoPath(code))) {
      File f = LittleFS.open(logoPath(code), "r");
      if (f && f.size() == fetch::LOGO_BYTES) {
        l->rgb = (uint8_t *)heap_caps_malloc(fetch::LOGO_BYTES, MALLOC_CAP_SPIRAM);
        if (l->rgb && f.read(l->rgb, fetch::LOGO_BYTES) == fetch::LOGO_BYTES) l->state = LogoState::Ready;
      }
      if (f) f.close();
    }
  }
  if (l->state == LogoState::Missing && now - l->stamp > l->wait) l->state = LogoState::Wanted;
  if (l->state != LogoState::Missing) l->stamp = now;
  return l->state == LogoState::Ready ? l->rgb : nullptr;
}

void drawLogo(Grid &g, JsonObjectConst op) {
  const char *code = op["code"] | "";
  int x = op["x"] | 2, y = op["y"] | 2;
  const uint8_t *rgb = logoFor(code);
  if (rgb) {
    for (int i = 0; i < 32 * 34; i++) {
      uint32_t c = ((uint32_t)rgb[i * 3] << 16) | ((uint32_t)rgb[i * 3 + 1] << 8) | rgb[i * 3 + 2];
      if (c) g.set(x + (i % 32), y + i / 32, c);
    }
    return;
  }
  g.rect(x, y, 32, 34, 0x1E262C);
  if (*code) g.text(x + 6, y + 14, code, 0x8E9AA3);
}

int64_t num64(JsonVariantConst v) {
  if (v.is<int64_t>()) return v.as<int64_t>();
  if (v.is<double>()) return (int64_t)v.as<double>();
  return 0;
}

// Eine Zahl, ganz oder mit Komma, sonst 0.
double numD(JsonVariantConst v) { return v.is<double>() ? v.as<double>() : 0.0; }

// ---------- Spotify (ab 0.2.0) ----------
constexpr uint32_t C_DOTS = 0x4E5A63;
constexpr uint32_t C_GROOVE = 0x3B444B;
constexpr uint32_t C_LABEL = 0xB37800;

void mmss(char *out, size_t n, int64_t s) {
  if (s < 0) s = 0;
  snprintf(out, n, "%d:%02d", (int)(s / 60), (int)(s % 60));
}

// Wie clamp() im Entwurf: erst unten, dann oben pruefen.
int clampTo(int v, int a, int b) { return v < a ? a : v > b ? b : v; }

// Seitenkopien im PSRAM, fuer die Uebergaenge.
struct PsramAlloc : ArduinoJson::Allocator {
  void *allocate(size_t n) override { return heap_caps_malloc(n, MALLOC_CAP_SPIRAM); }
  void deallocate(void *p) override { heap_caps_free(p); }
  void *reallocate(void *p, size_t n) override { return heap_caps_realloc(p, n, MALLOC_CAP_SPIRAM); }
};
PsramAlloc psram;
JsonDocument *shownDoc = nullptr;
JsonDocument *oldDoc = nullptr;

// Platte: eigene Zeichnung rechts neben der Huelle, 33 1/3 Umdrehungen je Minute, ein heller
// Streifen zeigt die Drehung. Bei Pause und Weiter gleitet sie in 600 ms hinein oder heraus.
void drawDisc(Grid &g, JsonObjectConst op, int64_t nowMs, bool reduce) {
  double k = reduce ? 1.0 : (double)(nowMs - num64(op["t0"])) / 600.0;
  if (k < 0) k = 0;
  if (k > 1) k = 1;
  bool sp = (op["sp"] | 0) != 0;
  double out = sp ? anim::eio(k) : 1.0 - anim::eio(k);
  double ang = reduce ? 0.0 : (double)(nowMs % 1800) / 1800.0 * 2 * PI;
  int r = op["r"] | 0, cy = op["cy"] | 0, x0 = op["x"] | 0;
  int cx = (op["cx"] | 0) - jround((1 - out) * 17);
  for (int y = cy - r; y <= cy + r; y++) {
    for (int x = x0; x <= cx + r; x++) {
      double dx = x - cx, dy = y - cy, d = sqrt(dx * dx + dy * dy);
      if (d > r + 0.3) continue;
      uint32_t col = 0;
      if (d > r - 1)
        col = C_DOTS;
      else if (d < 5.5)
        col = d < 2 ? 0 : C_LABEL;
      else if (jround(d) % 3 == 0)
        col = C_GROOVE;
      if (col == C_GROOVE && out > 0.98) {
        double a = atan2(dy, dx);
        double diff = fabs(fmod(fmod(a - ang, 2 * PI) + 3 * PI, 2 * PI) - PI);
        if (diff < 0.4) col = C_DIM;
      }
      if (col) g.set(x, y, col);
    }
  }
}

// count: Minuten und Sekunden. v steht fest, to zaehlt bis dahin herunter, t0 seit dahin hoch
// (hoechstens m). a: l links ab x, r endet vor x, c mittig um x. pre steht davor.
void drawCount(Grid &g, JsonObjectConst op, int64_t nowMs) {
  int64_t v;
  if (!op["v"].isNull()) {
    v = num64(op["v"]);
  } else if (!op["to"].isNull()) {
    int64_t r = num64(op["to"]) - nowMs;
    v = r > 0 ? (r + 999) / 1000 : 0;
  } else {
    int64_t e = nowMs - num64(op["t0"]);
    v = e > 0 ? e / 1000 : 0;
  }
  if (!op["m"].isNull()) v = min(v, num64(op["m"]));
  char mm[16], s[32];
  mmss(mm, sizeof mm, v);
  snprintf(s, sizeof s, "%s%s", (const char *)(op["pre"] | ""), mm);
  int w = Grid::width(s), x = op["x"] | 0;
  const char *a = op["a"] | "l";
  if (!strcmp(a, "r"))
    x -= w;
  else if (!strcmp(a, "c"))
    x = jround(x - w / 2.0);
  g.text(x, op["y"] | 0, s, parseColour(op["c"] | "", C_ACCENT), 1, true);
}

}  // namespace

namespace live {

bool begin() {
  fsReady = LittleFS.begin(true);
  if (fsReady && !LittleFS.exists("/logo")) LittleFS.mkdir("/logo");
  return fsReady;
}

void setFrame(JsonDocument *doc) {
  delete current;
  current = doc;
}

JsonDocument *frame() { return current; }

JsonObjectConst pageAt(int64_t nowMs) {
  if (!current) return JsonObjectConst();
  JsonArrayConst pages = (*current)["pages"];
  if (pages.isNull() || pages.size() == 0) return JsonObjectConst();
  JsonObjectConst best;
  for (JsonObjectConst p : pages) {
    int64_t from = num64(p["from"]);
    int64_t to = num64(p["to"]);
    if (from <= nowMs && nowMs < to) return p;
    if (from <= nowMs) best = p;
  }
  if (!best.isNull()) return best;
  return pages[0];
}

// Ein Zeichen aus Base64 in seinen Wert, alles andere in -1.
int b64val(char c) {
  if (c >= 'A' && c <= 'Z') return c - 'A';
  if (c >= 'a' && c <= 'z') return c - 'a' + 26;
  if (c >= '0' && c <= '9') return c - '0' + 52;
  if (c == '+') return 62;
  if (c == '/') return 63;
  return -1;
}

// Die Karte: vier Bit je Punkt, 0 ist durchsichtig, sonst die Nummer in der Palette.
// Dekodiert wird im Durchlauf, ohne Zwischenpuffer: ein Bild von 128 x 51 waeren
// sonst 3,3 KB extra. Zeilenweise von links oben. Eine Nummer hinter dem Ende der Palette
// bleibt durchsichtig wie in ops.js, statt schwarz zu malen (Fehlerliste F7).
void drawBmp(Grid &g, int x0, int y0, int w, int h, const char *d, const uint32_t *cols, int n) {
  if (w <= 0 || h <= 0 || !d) return;
  const int total = w * h;
  uint32_t acc = 0;
  int bits = 0, idx = 0;
  for (const char *p = d; *p && idx < total; p++) {
    int v = b64val(*p);
    if (v < 0) continue;
    acc = (acc << 6) | (uint32_t)v;
    bits += 6;
    while (bits >= 8 && idx < total) {
      bits -= 8;
      uint8_t byte = (uint8_t)((acc >> bits) & 0xFF);
      for (int half = 0; half < 2 && idx < total; half++, idx++) {
        uint8_t nib = half == 0 ? (uint8_t)(byte >> 4) : (uint8_t)(byte & 0x0F);
        if (!nib || nib > n) continue;
        g.set(x0 + idx % w, y0 + idx / w, cols[nib]);
      }
    }
  }
}

bool covers(int64_t nowMs) {
  if (!current) return false;
  int64_t last = 0;
  for (JsonObjectConst p : (*current)["pages"].as<JsonArrayConst>()) last = max(last, num64(p["to"]));
  return last > 0 && nowMs < last + 5000;
}

void render(Grid &g, JsonObjectConst page, const Ctx &ctx) {
  if (page.isNull()) return;
  int texts = 0;
  // Neue Notiz: beide Zeilen blinken weiss, dazu ein Rahmen in Bernstein. Ohne
  // Rahmen sah man es kaum, wenn die Notiz nur in Zeile 2 steht, die schon weiss ist.
  bool flash = ctx.flash && !ctx.reduce && !page["flash"].isNull();
  bool sawAnim = false;
  for (JsonObjectConst op : page["ops"].as<JsonArrayConst>()) {
    const char *t = op["t"] | "";
    if (!strcmp(t, "text")) {
      const char *s = op["s"] | "";
      int z = constrain((int)(op["z"] | 1), 1, 8);
      int x = op["x"].is<const char *>() ? Grid::centreX(s, z) : (int)(op["x"] | 0);
      uint32_t col = parseColour(op["c"] | "", C_ACCENT);
      if (flash && texts < 2) col = C_WHITE;
      texts++;
      g.text(x, op["y"] | 0, s, col, z, true);
    } else if (!strcmp(t, "ticker")) {
      // Der Versatz kommt aus der Uhr, damit ein Seitenwechsel die Laufschrift nicht zuruecksetzt.
      String ts = String(op["s"] | "") + "      ";
      int x = op["x"] | 0, y = op["y"] | 0;
      int v = op["v"] | 14;
      if (v <= 0) v = 14;
      int tw = Grid::width(ts.c_str()) + 1;
      if (tw < 1) tw = 1;
      int shift = ctx.reduce ? 0 : (int)floor(fmod((ctx.nowMs / 1000.0) * v, (double)tw));
      int copies = (int)ceil((128.0 - x) / tw) + 1;
      uint32_t col = parseColour(op["c"] | "", C_ACCENT);
      for (int k = 0; k < copies && k < 64; k++) g.text(x - shift + k * tw, y, ts.c_str(), col, 1, true);
    } else if (!strcmp(t, "rect") || !strcmp(t, "bar")) {
      int x = op["x"] | 0, y = op["y"] | 0, w = op["w"] | 0;
      int h = op["h"] | 0;
      if (h == 0) h = 2;
      uint32_t col = parseColour(op["c"] | "", C_ACCENT);
      // dot: nur jede n-te Spalte, etwa der Rest einer Flugstrecke als Punkte.
      int dot = op["dot"] | 0;
      if (dot >= 2 && dot <= 16) {
        for (int i = 0; i < w; i += dot) g.rect(x + i, y, 1, h, col);
      } else {
        g.rect(x, y, w, h, col);
      }
    } else if (!strcmp(t, "bmp")) {
      uint32_t cols[16] = {0};
      int n = 0;
      for (JsonVariantConst c : op["p"].as<JsonArrayConst>()) {
        if (n < 15) cols[++n] = parseColour(c | "", C_ACCENT);
      }
      drawBmp(g, op["x"] | 0, op["y"] | 0, op["w"] | 0, op["h"] | 0, op["d"] | "", cols, n);
    } else if (!strcmp(t, "dot")) {
      // Punkt mit Geschwindigkeit: zwischen zwei Abrufen laeuft er selbst weiter.
      // vx und vy sind LEDs je Sekunde, t0 der Zeitpunkt der Position.
      double dt = 0;
      int64_t t0 = num64(op["t0"]);
      if (!ctx.reduce && t0 > 0 && ctx.nowMs > t0) dt = min(60.0, (double)(ctx.nowMs - t0) / 1000.0);
      // x und y als Kommazahl lesen: mit | 0 wurde aus 64.5 eine 0 (Fehlerliste F7).
      int x = jround(numD(op["x"]) + numD(op["vx"]) * dt);
      int y = jround(numD(op["y"]) + numD(op["vy"]) * dt);
      uint32_t col = parseColour(op["c"] | "", C_ACCENT);
      for (int dy = -2; dy <= 2; dy++)
        for (int dx = -2; dx <= 2; dx++)
          if (abs(dx) == 2 || abs(dy) == 2) g.set(x + dx, y + dy, 0);
      g.rect(x - 1, y - 1, 3, 3, col);
      g.set(x, y, C_WHITE);
    } else if (!strcmp(t, "prog")) {
      drawProg(g, op, ctx.nowMs);
    } else if (!strcmp(t, "count")) {
      drawCount(g, op, ctx.nowMs);
    } else if (!strcmp(t, "disc")) {
      drawDisc(g, op, ctx.nowMs, ctx.reduce);
    } else if (!strcmp(t, "frame")) {
      g.frame(op["x"] | 0, op["y"] | 0, op["w"] | 0, op["h"] | 0, parseColour(op["c"] | "", C_ACCENT));
    } else if (!strcmp(t, "logo")) {
      drawLogo(g, op);
    } else if (!strcmp(t, "clock")) {
      int x = op["x"] | 0, y = op["y"] | 0;
      int z = constrain((int)(op["z"] | 1), 1, 8);
      bool h24 = op["h24"] | true;
      // ap: AM und PM klein neben den Ziffern, unten buendig. So bleibt "09:14:33 PM"
      // bei Faktor 2, statt auf die kleinste Schrift zu fallen.
      bool ap = !h24 && z > 1 && (op["ap"] | false);
      char txt[16] = "--:--";
      const char *suffix = "";
      if (ctx.local) {
        clockText(txt, sizeof txt, *ctx.local, h24, op["sec"] | false, !ap);
        if (ap) suffix = ctx.local->tm_hour % 24 < 12 ? "AM" : "PM";
      }
      uint32_t col = parseColour(op["c"] | "", C_ACCENT);
      bool seg = op["seg"] | false;
      // Ohne Sekunden blinkt der Doppelpunkt im Sekundentakt, damit die Uhr nicht eine
      // Minute lang still steht. Im Segment-Gesicht und mit reduzierter Bewegung steht er.
      if (ctx.local && !(op["sec"] | false) && !seg && !ctx.reduce && (ctx.local->tm_sec & 1) && txt[2] == ':') txt[2] = ' ';
      if (seg) {
        // Geisterrahmen nur hinter Ziffern, nicht hinter Doppelpunkt, Leerzeichen, AM oder PM.
        for (int i = 0; txt[i]; i++) {
          if (txt[i] < '0' || txt[i] > '9') continue;
          g.frame(x + i * 6 * z, y, 5 * z, 7 * z, 0x2A2000);
        }
      }
      g.text(x, y, txt, col, z, true);
      if (*suffix) g.text(x + Grid::width(txt, z) + 4, y + 7 * z - 7, suffix, col, 1, true);
    } else if (!strcmp(t, "date")) {
      if (!ctx.local) continue;
      const char *lang = op["lang"] | (ctx.de ? "de" : "en");
      char d[24];
      dateText(d, sizeof d, *ctx.local, !strcmp(lang, "de"));
      g.text(Grid::centreX(d), op["y"] | 0, d, parseColour(op["c"] | "", C_ACCENT), 1, true);
    } else if (!strcmp(t, "anim")) {
      const char *id = op["id"] | "";
      if (strncmp(animId, id, sizeof(animId) - 1) != 0) {
        strncpy(animId, id, sizeof(animId) - 1);
        animStart = ctx.millisNow;
      }
      sawAnim = true;
      AnimOpts o;
      const char *lang = op["lang"] | (ctx.de ? "de" : "en");
      o.de = !strcmp(lang, "de");
      o.h24 = op["h24"] | true;
      o.now = ctx.local;
      o.reduce = ctx.reduce;
      o.bright = ctx.bright;
      anim::draw(id, g, (ctx.millisNow - animStart) / 1000.0, o);
    }
  }
  if (flash) g.frame(0, 0, 128, 64, C_ACCENT);
  if (!sawAnim) animId[0] = '\0';
}

void prefetchLogos(const JsonDocument &doc) {
  for (JsonObjectConst page : doc["pages"].as<JsonArrayConst>())
    for (JsonObjectConst op : page["ops"].as<JsonArrayConst>())
      if (!strcmp(op["t"] | "", "logo")) logoFor(op["code"] | "");
}

const char *wantedLogo() {
  for (Logo &l : logos)
    if (l.state == LogoState::Wanted) return l.code;
  return nullptr;
}

void logoLoading(const char *code) {
  Logo *l = findLogo(code);
  if (l && l->state == LogoState::Wanted) l->state = LogoState::Loading;
}

void logoArrived(const char *code, int status, uint8_t *rgb) {
  Logo *l = findLogo(code);
  if (!l) {
    if (rgb) heap_caps_free(rgb);
    return;
  }
  if (status == 200 && rgb) {
    if (l->rgb) heap_caps_free(l->rgb);
    l->rgb = rgb;
    l->state = LogoState::Ready;
    l->stamp = millis();
    l->fails = 0;
    if (fsReady) {
      File f = LittleFS.open(logoPath(code), "w");
      if (f) {
        f.write(rgb, fetch::LOGO_BYTES);
        f.close();
      }
    }
    return;
  }
  if (rgb) heap_caps_free(rgb);
  // 404: es gibt keins, in einer Stunde nochmal fragen. Jeder andere Fehler wartet erst eine
  // Minute, dann doppelt so lange, hoechstens eine Stunde. Bis 0.1.9 fragte das Logo sofort
  // wieder, und bei einem Serverfehler waren die 120 Abrufe je Minute in Sekunden weg.
  l->state = LogoState::Missing;
  l->stamp = millis();
  if (status == 404) {
    l->wait = LOGO_RETRY_MS;
  } else {
    l->wait = min<uint32_t>(LOGO_RETRY_MS, LOGO_BACKOFF_MS << min<uint8_t>(l->fails, 6));
    if (l->fails < 255) l->fails++;
  }
}

int storageUsedPercent() {
  if (!fsReady || LittleFS.totalBytes() == 0) return 0;
  return (int)((uint64_t)LittleFS.usedBytes() * 100 / LittleFS.totalBytes());
}

int progWidth(JsonObjectConst op, int64_t nowMs) {
  int span = (op["x1"] | 0) - (op["x"] | 0) + 1;
  int64_t d = max<int64_t>(1, num64(op["d"]));
  int64_t pos = !op["p"].isNull() ? num64(op["p"]) : nowMs - num64(op["t0"]);
  pos = max<int64_t>(0, min(d, pos));
  return max(0, min(span, jround((double)span * pos / d)));
}

// prog: Zeitleiste wie drawTimeRow() im Entwurf. Gespielt in c, Rest als Punkte (bernsteinfarben
// in den letzten zehn Sekunden mit soon), Kopf weiss, darueber 0:00 (mit s), die laufende Zeit
// ueber dem Kopf und das Ende. p ist die Position in der Pause, dann grau mit pl. nl ohne
// Zeiten, nh ohne Kopf. Wie drawProg() in ops.js.
void drawProg(Grid &g, JsonObjectConst op, int64_t nowMs, int over) {
  int x0 = op["x"] | 0, x1 = op["x1"] | 0, span = x1 - x0 + 1;
  if (span < 1) return;
  int64_t d = max<int64_t>(1, num64(op["d"]));
  bool paused = !op["p"].isNull();
  int64_t pos = paused ? num64(op["p"]) : nowMs - num64(op["t0"]);
  pos = max<int64_t>(0, min(d, pos));
  int w = over >= 0 ? over : jround((double)span * pos / d);
  w = max(0, min(span, w));
  int head = x0 + min(span - 1, w);
  bool soon = (op["soon"] | 0) != 0 && !paused && d - pos <= 10000;
  int y = op["y"] | 0, b = op["b"] | 0, hy = op["hy"] | 0;
  if ((op["nl"] | 0) == 0) {
    char cur[24], end[16];
    if (paused)
      snprintf(cur, sizeof cur, "%s", (const char *)(op["pl"] | "PAUSED"));
    else
      mmss(cur, sizeof cur, pos / 1000);
    mmss(end, sizeof end, d / 1000);
    int cw = Grid::width(cur), ew = Grid::width(end), sw = Grid::width("0:00");
    int left = x0 + (x0 == 0 ? 2 : 0);
    int cx = clampTo(head - (cw - 1) / 2, left, x1 - cw);
    int ex = x1 - ew;
    if ((op["s"] | 0) != 0 && cx > left + sw + 3) g.text(left, y, "0:00", C_DIM, 1, true);
    if (cx + cw + 3 < ex) g.text(ex, y, end, C_DIM, 1, true);
    g.text(cx, y, cur, paused ? C_DIM : C_WHITE, 1, true);
  }
  for (int i = w; i < span; i += 2) g.rect(x0 + i, b, 1, 2, soon ? C_ACCENT : C_DOTS);
  if (w > 0) g.rect(x0, b, w, 2, paused ? C_DIM : parseColour(op["c"] | "", C_GREEN));
  if ((op["nh"] | 0) == 0) g.rect(head, hy, 1, b + 2 - hy, paused ? C_DIM : C_WHITE);
}

void keepShown(JsonObjectConst page) {
  if (!shownDoc) shownDoc = new JsonDocument(&psram);
  shownDoc->set(page);
}

void keepOld() {
  JsonDocument *t = oldDoc;
  oldDoc = shownDoc;
  shownDoc = t;
}

JsonObjectConst oldPage() { return oldDoc ? oldDoc->as<JsonObjectConst>() : JsonObjectConst(); }

JsonObjectConst findOp(JsonObjectConst page, const char *t) {
  for (JsonObjectConst op : page["ops"].as<JsonArrayConst>())
    if (!strcmp(op["t"] | "", t)) return op;
  return JsonObjectConst();
}

}  // namespace live
