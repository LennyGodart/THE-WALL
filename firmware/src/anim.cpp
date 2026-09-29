#include "anim.h"

#include "clocktext.h"
#include "config.h"

namespace {

constexpr double PI_D = 3.14159265358979323846;

const AnimInfo LIST[] = {
    {"boot", 2.2f, false},     {"wifi", 3.0f, true},      {"connecting", 2.4f, true}, {"address", 3.0f, true},
    {"paired", 15.0f, true},   {"modeswap", 1.6f, true},  {"waiting", 4.0f, true},    {"note", 2.6f, false},
    {"resting", 14.0f, true},  {"noserver", 3.2f, true},  {"nowifi", 3.2f, true},     {"updating", 5.0f, false},
    {"poweroff", 2.0f, false}, {"pixeldemo", 4.0f, true},
};
constexpr int LIST_COUNT = sizeof(LIST) / sizeof(LIST[0]);

Grid scratchA, scratchB;

// PX.rnd aus pixelfont.js mit derselben Rechnung in double, damit Staub und
// Pixel an denselben Stellen stehen wie in der Vorschau.
struct Rnd {
  double s;
  explicit Rnd(double seed) : s(seed) {}
  double operator()() {
    double prod = s * 1103515245.0;
    prod = prod + 12345.0;
    double m = fmod(prod, 4294967296.0);
    if (m < 0) m += 4294967296.0;
    s = (double)((uint32_t)m & 0x7fffffffu);
    return s / 2147483647.0;
  }
};

void centre(Grid &g, int y, const char *s, uint32_t col, int z = 1) { g.text(Grid::centreX(s, z), y, s, col, z); }

// Unter Helligkeit 96 (nachts 35 Prozent von 168 sind 59) verschwinden Toene wie #243038
// auf dem Panel: die Panel-Bibliothek legt eine CIE-Kurve ueber jede Farbe, die Helligkeit
// tastet danach noch einmal. Dann gilt der hellere Ton.
uint32_t night(const AnimOpts &o, uint32_t day, uint32_t dark) { return o.bright < 96 ? dark : day; }

// Von oben gesehen, Nase links, weil hier jedes Flugzeug nach links fliegt.
void plane(Grid &g, int x, int y, uint32_t col) {
  for (int i = -3; i <= 2; i++) g.set(x + i, y, col);
  for (int j = -2; j <= 2; j++) g.set(x, y + j, col);
  g.set(x + 2, y - 1, col);
  g.set(x + 2, y + 1, col);
}

void nowText(char *out, size_t n, const AnimOpts &o) {
  if (o.now)
    clockText(out, n, *o.now, o.h24, false);
  else
    snprintf(out, n, "--:--");
}

// Text auf hoechstens max Zeichen kuerzen, gemessen in Codepunkten.
void clip(char *s, int max) {
  int n = 0;
  for (char *p = s; *p; p++) {
    if (((uint8_t)*p & 0xC0) != 0x80) {
      if (n == max) {
        *p = '\0';
        return;
      }
      n++;
    }
  }
}

// 01: Zeilenscan wie beim echten Hochfahren, am Ende ein einziges Absacken.
void boot(Grid &g, double t, double dur, const AnimOpts &) {
  Grid &full = scratchA;
  full.clear();
  full.text(16, 18, "THE", C_ACCENT, 2);
  full.text(64, 18, "WALL", C_WHITE, 2);
  full.text(34, 40, "128 X 64", C_DIM);
  int rows = (int)floor(64 * min(1.0, t / (dur * 0.62)));
  for (int y = 0; y <= rows && y < 64; y++) {
    bool edge = y == rows && t < dur * 0.62;
    for (int x = 0; x < 128; x++) {
      uint32_t col = full.px[y * 128 + x];
      if (col) g.set(x, y, edge ? C_CYAN : col);
    }
    if (edge)
      for (int x2 = 0; x2 < 128; x2 += 2) g.set(x2, y, C_CYAN);
  }
  if (t > dur * 0.62 && t < dur * 0.78) {
    double k = (t - dur * 0.62) / (dur * 0.16);
    if (k < 0.5)
      for (int i = 0; i < Grid::N; i++)
        if (g.px[i]) g.px[i] = 0x3A2C00;
  }
}

// 02: Einrichtungsnetz. Oben, was zu tun ist, unten Netzname und Passwort im Klartext,
// dazwischen eine kleine Szene: Funkboegen, die nacheinander aufleuchten, auf der
// gepunkteten Linie laeuft ein Strich zum Handy, auf dem Handy warten drei Punkte.
// Ist ein Handy verbunden, leuchtet es auf und zeigt die Seite: ein Wechsel, kein
// Blinken. Bis 0.1.7 stand hier ein QR-Code; auf den LED-Punkten fand ihn die Kamera
// eines Handys nicht zuverlaessig (CHANGELOG 39, 40, 42). Wie A.wifi in anim.js.
void wifi(Grid &g, double t, double dur, const AnimOpts &o) {
  int cx = 44, cy = 33;
  double phase = (t / dur) * 3;
  for (int arc = 0; arc < 3; arc++) {
    int r = 5 + arc * 5;
    bool lit = o.reduce ? true : fmod(phase, 3.0) >= arc;
    uint32_t col = lit ? C_ACCENT : 0x3A2C00;
    for (int deg = 35; deg <= 145; deg += 3) {
      double rad = deg * PI_D / 180;
      g.set(jround(cx + cos(rad) * r), jround(cy - sin(rad) * r), col);
    }
  }
  g.rect(cx - 1, cy - 1, 3, 3, C_ACCENT);
  g.rect(0, 44, 128, 1, C_LINE);
  if (o.phone) {
    // Oben, dass das Handy drin ist, unten, wohin es jetzt geht. Meist oeffnet das Handy die
    // Seite von selbst, die Adresse steht fuer den Fall, dass nicht.
    centre(g, 1, o.de ? "HANDY VERBUNDEN" : "PHONE CONNECTED", C_GREEN);
    g.rect(62, 25, 15, 1, C_CYAN);
    g.frame(78, 10, 22, 30, C_WHITE);
    g.rect(86, 12, 6, 1, C_WHITE);
    g.rect(81, 16, 16, 20, 0x1A7A96);
    g.text(83, 22, "WI", C_WHITE);
    centre(g, 47, o.de ? "IM BROWSER OEFFNEN" : "OPEN IN A BROWSER", C_DIM);
    char url[24];
    snprintf(url, sizeof url, "HTTP://%d.%d.%d.%d", SETUP_IP[0], SETUP_IP[1], SETUP_IP[2], SETUP_IP[3]);
    centre(g, 56, url, C_ACCENT);
    return;
  }
  centre(g, 1, o.de ? "HANDY VERBINDEN" : "CONNECT A PHONE", C_ACCENT);
  // Die Linie gepunktet, weil noch keine Verbindung steht. Der Strich laeuft zweimal je
  // Durchlauf, die Punkte auf dem Handy wandern im selben Takt: jeder leuchtet 0,5 s.
  for (int x = 62; x <= 76; x += 2) g.set(x, 25, 0x1A7A96);
  if (!o.reduce) g.rect(62 + (int)floor(fmod((t / dur) * 2, 1.0) * 14), 25, 2, 1, C_CYAN);
  g.frame(78, 10, 22, 30, C_DIM);
  g.rect(86, 12, 6, 1, C_DIM);
  int on = (int)floor((t / dur) * 6) % 3;
  for (int i = 0; i < 3; i++) g.rect(83 + i * 5, 25, 2, 2, o.reduce || i == on ? C_CYAN : 0x1A7A96);
  centre(g, 47, SETUP_SSID, C_WHITE);
  // "PASSWORT 12345678" als eine Zeile gemittelt, das Wort leiser als das Passwort.
  const char *label = o.de ? "PASSWORT " : "PASSWORD ";
  char line[40];
  snprintf(line, sizeof line, "%s%s", label, SETUP_PASS);
  int x0 = Grid::centreX(line);
  g.text(x0, 56, label, C_DIM);
  g.text(x0 + Grid::width(label) + 1, 56, SETUP_PASS, C_ACCENT);
}

// 03: Verbindungsaufbau mit Netzname und Balken. Mit progress zeigt der Balken, wie
// viel von der Wartezeit bis zum Aufgeben verstrichen ist.
void connecting(Grid &g, double t, double dur, const AnimOpts &o) {
  g.text(2, 10, o.ssid ? o.ssid : "HEIMNETZ-2G", C_WHITE);
  const char *msg = o.de ? "VERBINDE" : "CONNECTING";
  g.text(2, 22, msg, C_ACCENT);
  int dots = o.reduce ? 3 : (int)floor((t / dur) * 8) % 4;
  for (int i = 0; i < dots; i++) g.text(2 + Grid::width(msg) + 4 + i * 6, 22, ".", C_ACCENT);
  double prog = o.progress >= 0 ? min(1.0, (double)o.progress) : min(1.0, t / dur);
  g.rect(2, 38, 124, 5, C_LINE);
  g.rect(2, 38, jround(124 * prog), 5, C_ACCENT);
  char pct[8];
  snprintf(pct, sizeof pct, "%d%%", jround(prog * 100));
  g.text(2, 50, pct, C_DIM);
}

// 04: die Adresse, einzeilig und gerahmt, weil man sie abtippt.
void address(Grid &g, double t, double, const AnimOpts &o) {
  centre(g, 6, o.de ? "IM BROWSER OEFFNEN" : "OPEN IN A BROWSER", C_DIM);
  g.frame(8, 20, 112, 20, 0x3A2C00);
  centre(g, 27, o.ip ? o.ip : "192.168.1.42", C_ACCENT);
  centre(g, 47, o.status ? o.status : (o.de ? "SCHLUESSEL FEHLT" : "NO KEY YET"), C_DIM);
  bool blink = o.reduce ? true : (int)floor(t * 2) % 2 == 0;
  g.rect(8, 59, 112, 2, blink ? C_GREEN : 0x1E3A2C);
}

// 05: Auspacken in drei Akten: Radar, Anmeldung, Begruessung.
void paired(Grid &g, double t, double dur, const AnimOpts &o) {
  double radarEnd = dur * 0.34, logEnd = dur * 0.74;
  if (t < radarEnd) {
    int cx = 64, cy = 28;
    for (int r : {10, 20, 30}) {
      for (int deg = 0; deg < 360; deg += 5) {
        double rad = deg * PI_D / 180;
        g.set(jround(cx + cos(rad) * r), jround(cy + sin(rad) * r * 0.6), 0x1E3A2C);
      }
    }
    // Mit reduzierter Bewegung steht der Strahl, und der Fund blinkt nicht (Fehlerliste F5).
    double ang = o.reduce ? 1.2 : (t / radarEnd) * PI_D * 4;
    for (int r2 = 2; r2 < 31; r2++) g.set(jround(cx + cos(ang) * r2), jround(cy + sin(ang) * r2 * 0.6), C_GREEN);
    g.set(cx, cy, C_GREEN);
    if (t > radarEnd * 0.66) {
      plane(g, 88, 18, C_ACCENT);
      // Zwei Blitze pro Sekunde, unter der Grenze von drei (vorher genau drei).
      if (!o.reduce && (int)floor(t * 4) % 2 == 0) {
        for (int dd = 4; dd < 9; dd++) {
          for (int dg = 0; dg < 360; dg += 30) {
            double rr = dg * PI_D / 180;
            g.set(jround(88 + cos(rr) * dd), jround(18 + sin(rr) * dd * 0.6), 0x6B4A00);
          }
        }
      }
      // Die Vorlage zeigt hier ein Rufzeichen. Das Geraet kennt noch keinen Flug,
      // deshalb steht der Server da, mit dem es gerade verbunden ist.
      static String host;
      if (host.isEmpty()) {
        host = serverHost();
        host.toUpperCase();
      }
      g.text(2, 56, host.c_str(), C_DIM);
    } else {
      g.text(2, 56, o.de ? "SUCHE..." : "SCANNING...", 0x3A5A48);
    }
    return;
  }
  if (t < logEnd) {
    char l1[40], l2[24], l3[40], l4[40], l5[24];
    char ssid[16];
    snprintf(ssid, sizeof ssid, "%s", o.ssid ? o.ssid : "");
    clip(ssid, 11);
    snprintf(l1, sizeof l1, "%s %s OK", o.de ? "> WLAN" : "> WIFI", ssid);
    snprintf(l2, sizeof l2, "%s", o.de ? "> SCHLUESSEL OK" : "> KEY ACCEPTED");
    snprintf(l3, sizeof l3, "%s %s", o.de ? "> KONTO" : "> ACCOUNT", o.user ? o.user : "");
    snprintf(l4, sizeof l4, "%s %s", o.de ? "> GERAET" : "> DEVICE", o.device ? o.device : "WALL");
    snprintf(l5, sizeof l5, "%s", o.de ? "> BEREIT" : "> READY");
    clip(l1, 21);
    clip(l3, 21);
    clip(l4, 21);
    const char *lines[5] = {l1, l2, l3, l4, l5};
    constexpr int count = 5;
    double span = logEnd - radarEnd;
    double per = span / (count + 0.5);
    double local = t - radarEnd;
    for (int i = 0; i < count; i++) {
      double start = i * per;
      if (local < start) break;
      int len = strlen(lines[i]);
      int typed = o.reduce ? len : min(len, (int)ceil(((local - start) / per) * len * 1.7));
      char part[40];
      snprintf(part, sizeof part, "%.*s", typed, lines[i]);
      g.text(2, 2 + i * 12, part, i == count - 1 ? C_GREEN : C_DIM);
      if (typed < len) g.rect(2 + typed * 6, 2 + i * 12, 5, 7, C_ACCENT);
    }
    return;
  }
  centre(g, 10, o.de ? "HALLO" : "HELLO", C_DIM, 2);
  centre(g, 28, o.user ? o.user : "", C_ACCENT, 2);
  bool lit = o.reduce ? true : (int)floor((t - logEnd) * 1.2) % 2 == 0;
  centre(g, 50, o.de ? "MODUS WAEHLEN" : "PICK A MODE", lit ? C_DIM : 0x2A3239);
}

void flightFrame(Grid &g) {
  g.rect(2, 2, 32, 34, 0x0B4DA2);
  g.text(8, 10, "LGL", 0xDCE8F7);
  g.rect(8, 22, 18, 1, 0xDCE8F7);
  g.text(38, 2, "LUXAIR", C_ACCENT);
  g.text(38, 14, "LUX\xE2\x86\x92LIS", C_WHITE);
  g.text(38, 26, "737-800", C_CYAN);
  g.text(2, 38, "ALT:8.8KFT,SPD:720KMH", C_WHITE);
  g.text(2, 50, "TRK:214DEG,VR:+3.7M/S", C_DIM);
}

// 06: der alte Inhalt wird nach links geschoben, der neue kommt nach. Als Vorfuehrung
// mit den Bildern der Vorlage; im Betrieb schiebt anim::push die echten Seiten.
void modeswap(Grid &g, double t, double dur, const AnimOpts &o) {
  scratchA.clear();
  scratchB.clear();
  flightFrame(scratchA);
  scratchB.text(22, 18, "20:14", C_ACCENT, 2);
  scratchB.text(38, 40, o.de ? "FRE 25 SEP" : "FRI 25 SEP", C_DIM);
  anim::push(g, scratchA, scratchB, t / dur);
}

// 07: ein leerer Radarschirm statt eines leeren Panels.
void waiting(Grid &g, double t, double dur, const AnimOpts &o) {
  int cx = 64, cy = 26;
  for (int r : {9, 18, 27}) {
    for (int deg = 0; deg < 360; deg += 6) {
      double rad = deg * PI_D / 180;
      g.set(jround(cx + cos(rad) * r), jround(cy + sin(rad) * r * 0.6), 0x1E3A2C);
    }
  }
  double ang = o.reduce ? 1.2 : (t / dur) * PI_D * 2;
  for (int r2 = 2; r2 < 28; r2++) g.set(jround(cx + cos(ang) * r2), jround(cy + sin(ang) * r2 * 0.6), 0x2C6B4F);
  g.set(cx, cy, C_GREEN);
  centre(g, 44, o.de ? "KEIN FLUGZEUG" : "NO AIRCRAFT", C_DIM);
  char now[16];
  nowText(now, sizeof now, o);
  centre(g, 55, now, C_WHITE);
}

// 08: dreimal blinken, dann ruhig. Gut zwei Wechsel pro Sekunde, unter drei Blitzen.
void note(Grid &g, double t, double dur, const AnimOpts &o) {
  double win = dur * 0.55;
  bool bright = false;
  if (!o.reduce && t < win) bright = (int)floor((t / win) * 3 * 2) % 2 == 0;
  centre(g, 18, "ALLES GUTE", bright ? C_WHITE : C_ACCENT);
  centre(g, 34, "ZUM GEBURTSTAG", bright ? C_WHITE : C_DIM);
  if (bright) g.frame(0, 0, 128, 64, C_ACCENT);
}

// 09: nie wirklich aus. Schwache Uhr, Staub, ab und zu ein Gast.
void resting(Grid &g, double t, double dur, const AnimOpts &o) {
  char now[16];
  nowText(now, sizeof now, o);
  centre(g, 22, now, night(o, 0x6B4A00, 0xB37800), 2);
  Rnd r(5);
  for (int i = 0; i < 26; i++) {
    double seed = r();
    double speed = 0.25 + seed * 0.5;
    int x = jround(fmod(seed * 128 + (o.reduce ? 0 : t * speed * 9), 128.0));
    int y = jround(r() * 64);
    if (!g.get(x, y)) g.set(x, y, night(o, 0x243038, 0x4E5A63));
  }
  if (o.reduce) return;
  int slot = (int)floor(t / dur) % 3;
  double local = fmod(t, dur) / dur;
  if (slot == 0 && local > 0.15 && local < 0.62) {
    double prog = (local - 0.15) / 0.47;
    int px = jround(140 - prog * 156), py = 52;
    plane(g, px, py, night(o, 0x7A5200, 0xB37800));
    for (int k = 3; k < 16; k++) {
      if (px + k < 128 && px + k >= 0 && !g.get(px + k, py))
        g.set(px + k, py, k < 8 ? night(o, 0x3A2C00, 0x6B4A00) : night(o, 0x241B00, 0x4A3800));
    }
  }
  if (slot == 1 && local > 0.3 && local < 0.45) {
    double p2 = (local - 0.3) / 0.15;
    int sx = jround(10 + p2 * 100), sy = jround(6 + p2 * 12);
    for (int s = 0; s < 5; s++) g.set(sx - s, sy - jround(s * 0.6), s == 0 ? night(o, 0x5A6670, 0x8E9AA3) : night(o, 0x2A3239, 0x4E5A63));
  }
  if (slot == 2 && local > 0.55) {
    int mx = 112, my = 12, rad = 6;
    for (int yy = -rad; yy <= rad; yy++) {
      for (int xx = -rad; xx <= rad; xx++) {
        if (hypot(xx, yy) > rad) continue;
        if (xx > -2 + sin((double)yy / rad * 1.2) * 2) g.set(mx + xx, my + yy, night(o, 0x2E3238, 0x4E5A63));
      }
    }
  }
}

// 10: die Uhr laeuft weiter, die Daten fehlen.
void noserver(Grid &g, double t, double, const AnimOpts &o) {
  char now[16], date[24];
  nowText(now, sizeof now, o);
  if (o.now)
    dateText(date, sizeof date, *o.now, o.de);
  else
    date[0] = '\0';
  centre(g, 6, now, C_ACCENT, 2);
  centre(g, 26, date, C_DIM);
  g.rect(0, 38, 128, 1, C_LINE);
  bool blink = o.reduce ? true : (int)floor(t * 1.4) % 2 == 0;
  centre(g, 44, o.de ? "KEIN SERVER" : "NO SERVER", blink ? C_RED : 0x5C1C0A);
  if (o.now)
    centre(g, 56, o.de ? "UHR LAEUFT WEITER" : "CLOCK STILL RUNS", C_DIM);
  else
    centre(g, 56, o.de ? "NOCH KEINE UHRZEIT" : "NO TIME YET", C_DIM);
}

// 11: ein Blick auf den Router hilft, deshalb steht der Netzname dabei.
void nowifi(Grid &g, double, double, const AnimOpts &o) {
  int cx = 64, cy = 34;
  for (int arc = 0; arc < 3; arc++) {
    int r = 9 + arc * 8;
    for (int deg = 35; deg <= 145; deg += 2) {
      double rad = deg * PI_D / 180;
      g.set(jround(cx + cos(rad) * r), jround(cy - sin(rad) * r), 0x5C1C0A);
    }
  }
  g.rect(cx - 1, cy - 1, 3, 3, 0x5C1C0A);
  for (int i = -14; i <= 14; i++) g.set(cx + i, cy - 12 + i, C_RED);
  centre(g, 45, o.de ? "WLAN WEG" : "WI-FI LOST", C_RED);
  centre(g, 56, o.ssid ? o.ssid : "HEIMNETZ-2G", C_DIM);
}

// 12: darf nicht schwarz werden. Ausstecken ist ungefaehrlich, das alte System startet wieder.
void updating(Grid &g, double t, double dur, const AnimOpts &o) {
  char head[32];
  snprintf(head, sizeof head, "UPDATE %s", o.version ? o.version : "0.5.0");
  g.text(2, 6, head, C_ACCENT);
  double prog = o.progress >= 0 ? min(1.0, (double)o.progress) : min(1.0, t / (dur * 0.9));
  g.rect(2, 22, 124, 8, C_LINE);
  g.rect(2, 22, jround(124 * prog), 8, C_ACCENT);
  char pct[8];
  snprintf(pct, sizeof pct, "%d%%", jround(prog * 100));
  g.text(2, 36, pct, C_WHITE);
  g.text(2, 52, o.de ? "ALTE VERSION BLEIBT" : "OLD VERSION KEPT", C_DIM);
}

// 13: ein Vorhang von beiden Raendern zur Mitte, die Wortmarke bis zuletzt.
void poweroff(Grid &g, double t, double dur, const AnimOpts &) {
  Grid &full = scratchA;
  full.clear();
  full.text(16, 25, "THE", C_ACCENT, 2);
  full.text(64, 25, "WALL", C_WHITE, 2);
  int shrink = jround(32 * min(1.0, t / dur));
  for (int y = shrink; y < 64 - shrink; y++)
    for (int x = 0; x < 128; x++) {
      uint32_t col = full.px[y * 128 + x];
      if (col) g.set(x, y, col);
    }
  if (shrink > 0 && shrink < 32) {
    for (int x2 = 0; x2 < 128; x2 += 2) {
      g.set(x2, shrink, 0x3A2C00);
      g.set(x2, 63 - shrink, 0x3A2C00);
    }
  }
}

// Vorschau des Pixel-Editors, der erst nach dem 25. kommt.
void pixeldemo(Grid &g, double, double, const AnimOpts &) {
  Rnd r(11);
  for (int y = 0; y < 64; y++)
    for (int x = 0; x < 128; x++) {
      double v = r();
      if (v > 0.93)
        g.set(x, y, C_ACCENT);
      else if (v > 0.88)
        g.set(x, y, C_CYAN);
      else if (v > 0.84)
        g.set(x, y, 0x2A3239);
    }
  g.rect(30, 26, 68, 13, 0x050607);
  g.text(34, 28, "8192 PIXEL", C_WHITE);
}

using Fn = void (*)(Grid &, double, double, const AnimOpts &);
const Fn FNS[LIST_COUNT] = {boot,    wifi,   connecting, address,  paired,   modeswap, waiting,
                            note,    resting, noserver,  nowifi,   updating, poweroff, pixeldemo};

}  // namespace

namespace anim {

bool begin() { return scratchA.begin() && scratchB.begin(); }

const AnimInfo *find(const char *key) {
  for (int i = 0; i < LIST_COUNT; i++)
    if (strcmp(LIST[i].key, key) == 0) return &LIST[i];
  return nullptr;
}

int count() { return LIST_COUNT; }

const AnimInfo &at(int i) { return LIST[i]; }

bool draw(const char *key, Grid &g, double t, const AnimOpts &o) {
  for (int i = 0; i < LIST_COUNT; i++) {
    if (strcmp(LIST[i].key, key) != 0) continue;
    double dur = LIST[i].dur;
    double local = t;
    if (strcmp(key, "resting") == 0) {
      local = o.reduce ? dur : t;
    } else if (LIST[i].loop) {
      local = fmod(t, dur);
    } else if (o.reduce) {
      // Einmalige Animationen stehen mit reduzierter Bewegung gleich im Endbild (Fehlerliste F5).
      local = dur;
    }
    FNS[i](g, local, dur, o);
    return true;
  }
  return false;
}

void drop(Grid &g, const Grid &to, const int *bands, int n, double k) {
  k = min(1.0, max(0.0, k));
  for (int y = 0; y < 64; y++) {
    int band = -1;
    for (int i = 0; i < n; i++)
      if (y >= bands[i] && y < bands[i] + 7) band = i;
    if (band < 0) {
      for (int x = 0; x < 128; x++)
        if (to.px[y * 128 + x]) g.set(x, y, to.px[y * 128 + x]);
      continue;
    }
    double at = 0.12 + band * 0.14;
    if (k < at) continue;
    int off = jround((1.0 - min(1.0, (k - at) / 0.16)) * 8);
    if (off <= 0) {
      for (int x = 0; x < 128; x++)
        if (to.px[y * 128 + x]) g.set(x, y, to.px[y * 128 + x]);
      continue;
    }
    // Nur die Linie (x 2 bis 25) rutscht von rechts herein, gedimmt.
    for (int x = 2; x < 26; x++)
      if (to.px[y * 128 + x]) g.set(x + off * 2, y, 0x3A2C00);
  }
}

void swap(Grid &g, const Grid &from, const Grid &to, double k) {
  k = min(1.0, max(0.0, k));
  int shift = jround(k * 12);
  for (int y = 0; y < 64; y++) {
    for (int x = 0; x < 128; x++) {
      uint32_t a = from.px[y * 128 + x], b = to.px[y * 128 + x];
      if (y < 38 && x >= 38) {
        // Rechter Block: jede Zeile rollt in ihrem eigenen Streifen von 12 Pixeln nach oben,
        // die alte hinaus, die neue von unten nach. Ohne den Streifen laege die neue Zeile 1
        // anfangs genau auf der alten Zeile 2.
        int slot = y / 12;
        int ya = y - shift, yb = y + 12 - shift;
        if (a && ya >= 0 && ya / 12 == slot) g.set(x, ya, a);
        if (b && yb < 38 && yb / 12 == slot) g.set(x, yb, b);
      } else if (y >= 61) {
        if (b) g.set(x, y, b);
      } else {
        // Logo und untere Zeilen: je Kanal linear gemischt, jedes Pixel einmal monoton.
        uint32_t r = jround(((a >> 16) & 0xFF) * (1 - k) + ((b >> 16) & 0xFF) * k);
        uint32_t gg = jround(((a >> 8) & 0xFF) * (1 - k) + ((b >> 8) & 0xFF) * k);
        uint32_t bl = jround((a & 0xFF) * (1 - k) + (b & 0xFF) * k);
        uint32_t c = (r << 16) | (gg << 8) | bl;
        if (c) g.set(x, y, c);
      }
    }
  }
}

// Dieselbe Abfahrt? Verglichen wird das Band links von x 70, Linie und Anfang des Ziels;
// die Zeit rechts aendert sich jede Minute. Leere Baender sind nie gleich. Wie sameRow in anim.js.
constexpr int ROW_KEY_W = 70;
static bool sameRow(const Grid &a, int ya, const Grid &b, int yb) {
  bool any = false;
  for (int r = 0; r < 7; r++) {
    for (int x = 0; x < ROW_KEY_W; x++) {
      uint32_t pa = a.px[(ya + r) * 128 + x], pb = b.px[(yb + r) * 128 + x];
      if (pa != pb) return false;
      if (pa) any = true;
    }
  }
  return any;
}

void roll(Grid &g, const Grid &from, const Grid &to, const int *bands, int n, double k) {
  k = min(1.0, max(0.0, k));
  int off = jround(k * 8);
  n = min(n, 6);
  // Woher kommt jede Zeile? -1 steht still, -2 ist neu, sonst die alte Zeile, aus der sie
  // nachrueckt: faellt oben eine Abfahrt weg, ruecken die anderen in 240 ms um eine Zeile auf.
  int src[6];
  bool moves = false;
  for (int i = 0; i < n; i++) {
    src[i] = -2;
    if (bands[i] < 0 || bands[i] > 57 || sameRow(from, bands[i], to, bands[i])) {
      src[i] = -1;
      continue;
    }
    for (int j = 0; j < n; j++) {
      if (j == i || bands[j] < 0 || bands[j] > 57) continue;
      if (sameRow(from, bands[j], to, bands[i])) {
        src[i] = j;
        moves = true;
        break;
      }
    }
  }
  g.copyFrom(to);
  for (int i = 0; i < n; i++) {
    int top = bands[i];
    if (top < 0 || top > 57) continue;
    if (src[i] >= 0 || (moves && src[i] == -2)) {
      // Nachrueckende und neue Zeilen zeichnet der zweite Durchgang.
      for (int r = 0; r < 7; r++)
        for (int x = 0; x < 128; x++) g.set(x, top + r, 0);
      continue;
    }
    // Bleibt stehen: nur die Spalten, die sich aendern, rollen um 8 Pixel nach oben.
    for (int x = 0; x < 128; x++) {
      bool changed = false;
      for (int r = 0; r < 7 && !changed; r++) changed = from.px[(top + r) * 128 + x] != to.px[(top + r) * 128 + x];
      if (!changed) continue;
      for (int r = 0; r < 7; r++) g.set(x, top + r, 0);
      for (int r = 0; r < 7; r++) {
        uint32_t a = from.px[(top + r) * 128 + x];
        uint32_t b = to.px[(top + r) * 128 + x];
        if (a && r - off >= 0) g.set(x, top + r - off, a);
        if (b && r + 8 - off < 7) g.set(x, top + r + 8 - off, b);
      }
    }
  }
  if (!moves) return;
  int lo = 64, hi = 0;
  for (int i = 0; i < n; i++) {
    if (bands[i] < 0 || bands[i] > 57) continue;
    lo = min(lo, bands[i]);
    hi = max(hi, bands[i] + 7);
  }
  for (int i = 0; i < n; i++) {
    int top = bands[i];
    if (top < 0 || top > 57) continue;
    if (src[i] >= 0) {
      // Rueckt von ihrer alten Zeile an die neue, nur innerhalb der Tafel sichtbar.
      int y0 = jround(bands[src[i]] + (top - bands[src[i]]) * k);
      for (int r = 0; r < 7; r++) {
        int yy = y0 + r;
        if (yy < lo || yy >= hi) continue;
        for (int x = 0; x < 128; x++) {
          uint32_t b = to.px[(top + r) * 128 + x];
          if (b) g.set(x, yy, b);
        }
      }
    } else if (src[i] == -2) {
      // Neu auf der Tafel: rollt im eigenen Band von unten herein.
      for (int r = 0; r < 7; r++) {
        int rr = r + 8 - off;
        if (rr >= 7) continue;
        for (int x = 0; x < 128; x++) {
          uint32_t b = to.px[(top + r) * 128 + x];
          if (b) g.set(x, top + rr, b);
        }
      }
    }
  }
}

// ---------- Spotify (ab 0.2.0) ----------

static double bezAt(double t, double p1, double p2) { return ((1 - 3 * p2 + 3 * p1) * t * t + (3 * p2 - 6 * p1) * t + 3 * p1) * t; }
static double bezSlope(double t, double p1, double p2) { return 3 * (1 - 3 * p2 + 3 * p1) * t * t + 2 * (3 * p2 - 6 * p1) * t + 3 * p1; }

// Newton, notfalls Halbierung, wie bez() in anim.js und ops.js.
static double bezier(double x, double x1, double y1, double x2, double y2) {
  if (x <= 0) return 0;
  if (x >= 1) return 1;
  double t = x;
  for (int i = 0; i < 8; i++) {
    double s = bezSlope(t, x1, x2);
    if (fabs(s) < 1e-6) break;
    double d = bezAt(t, x1, x2) - x;
    if (fabs(d) < 1e-6) return bezAt(t, y1, y2);
    t -= d / s;
  }
  double lo = 0, hi = 1;
  t = x;
  for (int j = 0; j < 30; j++) {
    double v = bezAt(t, x1, x2);
    if (fabs(v - x) < 1e-6) break;
    if (v < x)
      lo = t;
    else
      hi = t;
    t = (lo + hi) / 2;
  }
  return bezAt(t, y1, y2);
}

double eio(double x) { return bezier(x, 0.77, 0, 0.175, 1); }
double eout(double x) { return bezier(x, 0.23, 1, 0.32, 1); }

static double clamp01(double k) { return k < 0 ? 0 : k > 1 ? 1 : k; }

// Senkrecht: alt nach oben hinaus, neu von unten, um s Punkte bei Hoehe D.
static void reelWin(Grid &g, const Grid &from, const Grid &to, const int *w, int s, int D) {
  for (int y = w[1]; y <= w[3]; y++) {
    for (int x = w[0]; x <= w[2]; x++) {
      uint32_t c = 0;
      int yf = y + s, yt = y + s - D;
      if (yf <= w[3]) c = from.px[yf * 128 + x];
      if (yt >= w[1] && yt <= w[3]) {
        uint32_t ct = to.px[yt * 128 + x];
        if (ct) c = ct;
      }
      g.px[y * 128 + x] = c;
    }
  }
}

// Waagerecht: alt nach links hinaus, neu von rechts.
static void slideWin(Grid &g, const Grid &from, const Grid &to, const int *w, int s) {
  int D = w[2] - w[0] + 1;
  for (int y = w[1]; y <= w[3]; y++) {
    for (int x = w[0]; x <= w[2]; x++) {
      int xf = x + s;
      g.px[y * 128 + x] = xf <= w[2] ? from.px[y * 128 + xf] : to.px[y * 128 + xf - D];
    }
  }
}

// Jede Textzeile rollt in ihrem Streifen hoch, 40 ms nach der vorigen, je 280 ms.
static void rollLines(Grid &g, const Grid &from, const Grid &to, const SongWin &w, double tMs) {
  const int *tw = w.t;
  int p = w.pitch;
  for (int y = tw[1]; y <= tw[3]; y++)
    for (int x = tw[0]; x <= tw[2]; x++) g.px[y * 128 + x] = to.px[y * 128 + x];
  for (int i = 0; i < w.nLines; i++) {
    int ly = w.lines[i];
    int s = jround(eout(clamp01((tMs - i * 40) / 280.0)) * p);
    int top = max(tw[1], ly - 1), end = min(tw[3] + 1, ly - 1 + p);
    for (int y = top; y < end; y++) {
      for (int x = tw[0]; x <= tw[2]; x++) {
        int yf = y + s, yt = y + s - p;
        uint32_t c = 0;
        if (yf < end)
          c = from.px[yf * 128 + x];
        else if (yt >= 0 && yt < 64)
          c = to.px[yt * 128 + x];
        g.px[y * 128 + x] = c;
      }
    }
  }
}

void song(Grid &g, const Grid &from, const Grid &to, const SongWin &w, double k, bool carousel) {
  k = clamp01(k);
  g.copyFrom(to);
  if (w.hasC) {
    if (carousel) {
      slideWin(g, from, to, w.c, jround(eio(k) * (w.c[2] - w.c[0] + 1)));
    } else {
      int D = w.c[3] - w.c[1] + 1;
      reelWin(g, from, to, w.c, jround(eio(k) * D), D);
    }
  }
  double tMs = k * 560 - 120;
  if (w.shift)
    reelWin(g, from, to, w.t, jround(eout(clamp01(tMs / 280.0)) * w.pitch), w.pitch);
  else
    rollLines(g, from, to, w, tMs);
}

void lines(Grid &g, const Grid &from, const Grid &to, const SongWin &w, double k) {
  g.copyFrom(to);
  rollLines(g, from, to, w, clamp01(k) * 400);
}

void push(Grid &g, const Grid &from, const Grid &to, double k) {
  int shift = jround(min(1.0, max(0.0, k)) * 128);
  for (int y = 0; y < 64; y++) {
    for (int x = 0; x < 128; x++) {
      uint32_t a = from.px[y * 128 + x];
      if (a && x - shift >= 0) g.set(x - shift, y, a);
      uint32_t b = to.px[y * 128 + x];
      if (b) g.set(x - shift + 128, y, b);
    }
  }
  if (shift > 0 && shift < 128)
    for (int y2 = 0; y2 < 64; y2 += 2) g.set(128 - shift, y2, 0x2C353C);
}

}  // namespace anim
