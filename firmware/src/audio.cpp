#include "audio.h"

#include <Wire.h>
#include <driver/i2s.h>
#include <math.h>

#include "config.h"

namespace {

constexpr uint8_t ES8311 = 0x18;
constexpr i2s_port_t PORT = I2S_NUM_0;
constexpr int RATE = 16000;
constexpr int CHUNK = 256;                 // Stereo-Frames je Schreiben, 16 ms
constexpr int EDGE = RATE / 200;           // 5 ms An- und Abschwellen, sonst knackt jeder Ton
constexpr float LOUD = 11000.0f;           // von 32767; der NS4150B schafft 3 W, das reicht leise

struct Step {
  uint16_t hz;  // 0 ist Pause
  uint16_t ms;
};

// Timer: dreimal kurz und hoch, dann Pause. Wecker: drei steigende Toene, beginnt leise und
// wird in 30 Sekunden laut. Test: zwei Toene, einmal.
const Step TIMER_PAT[] = {{1760, 120}, {0, 110}, {1760, 120}, {0, 110}, {1760, 120}, {0, 800}};
const Step ALARM_PAT[] = {{988, 180}, {0, 60}, {1319, 180}, {0, 60}, {1568, 260}, {0, 900}};
const Step TEST_PAT[] = {{1319, 150}, {0, 60}, {1760, 220}, {0, 200}};

bool ok = false;
uint16_t chip = 0;
volatile audio::Tone want = audio::Tone::Off;
volatile audio::Tone current = audio::Tone::Off;
TaskHandle_t taskHandle = nullptr;
int16_t sine[256];

bool wr(uint8_t reg, uint8_t v) {
  Wire.beginTransmission(ES8311);
  Wire.write(reg);
  Wire.write(v);
  return Wire.endTransmission() == 0;
}

int rd(uint8_t reg) {
  Wire.beginTransmission(ES8311);
  Wire.write(reg);
  if (Wire.endTransmission(false) != 0) return -1;
  if (Wire.requestFrom(ES8311, (uint8_t)1) != 1) return -1;
  return Wire.read();
}

// Nur die Bits ausserhalb der Maske behalten und den Rest setzen.
void keep(uint8_t reg, uint8_t mask, uint8_t bits) {
  int v = rd(reg);
  if (v >= 0) wr(reg, (uint8_t)((v & mask) | bits));
}

bool i2sInit() {
  i2s_config_t c = {};
  c.mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_TX);
  c.sample_rate = RATE;
  c.bits_per_sample = I2S_BITS_PER_SAMPLE_16BIT;
  c.channel_format = I2S_CHANNEL_FMT_RIGHT_LEFT;
  c.communication_format = I2S_COMM_FORMAT_STAND_I2S;
  c.intr_alloc_flags = 0;
  c.dma_buf_count = 4;
  c.dma_buf_len = CHUNK;
  c.use_apll = false;
  c.tx_desc_auto_clear = true;
  c.mclk_multiple = I2S_MCLK_MULTIPLE_256;  // 4,096 MHz an MCLK, wie der Codec es erwartet
  if (i2s_driver_install(PORT, &c, 0, nullptr) != ESP_OK) return false;
  i2s_pin_config_t p = {};
  p.mck_io_num = PIN_I2S_MCLK;
  p.bck_io_num = PIN_I2S_BCLK;
  p.ws_io_num = PIN_I2S_WS;
  p.data_out_num = PIN_I2S_DOUT;
  p.data_in_num = I2S_PIN_NO_CHANGE;
  if (i2s_set_pin(PORT, &p) != ESP_OK) {
    i2s_driver_uninstall(PORT);
    return false;
  }
  i2s_zero_dma_buffer(PORT);
  return true;
}

// Registerwerte wie in Espressifs Treiber es8311 (esp-bsp, Apache 2.0), fuer MCLK 4,096 MHz
// vom ESP32, 16 kHz, 16 Bit, der Codec als Slave. Nachgeschrieben, nicht kopiert.
bool codecInit() {
  int id1 = rd(0xFD), id2 = rd(0xFE);
  if (id1 < 0 || id2 < 0) return false;
  chip = (uint16_t)(id1 << 8 | id2);
  wr(0x00, 0x1F);  // zuruecksetzen
  delay(20);
  wr(0x00, 0x00);
  wr(0x00, 0x80);  // einschalten
  wr(0x01, 0x3F);  // alle Takte an, MCLK vom Pin
  keep(0x06, 0xE0, 0x03);  // BCLK-Teiler 4, nicht invertiert
  keep(0x02, 0x07, 0x00);  // pre_div 1, pre_multi 1
  wr(0x03, 0x10);          // fs_mode einfach, adc_osr 16
  wr(0x04, 0x10);          // dac_osr 16
  wr(0x05, 0x00);          // adc_div und dac_div 1
  keep(0x07, 0xC0, 0x00);  // lrck_h
  wr(0x08, 0xFF);          // lrck_l
  keep(0x00, 0xBF, 0x00);  // Slave: der ESP32 gibt BCLK und WS vor
  wr(0x09, 0x0C);          // DAC: I2S, 16 Bit
  wr(0x0A, 0x0C);          // ADC: I2S, 16 Bit
  wr(0x0D, 0x01);          // Analogteil an
  wr(0x0E, 0x02);
  wr(0x12, 0x00);          // DAC an
  wr(0x13, 0x10);
  wr(0x1C, 0x6A);
  wr(0x37, 0x08);
  wr(0x32, 0xBF);          // Lautstaerke 0 dB, die Hoehe regelt die Firmware ueber die Amplitude
  keep(0x31, 0x9F, 0x00);  // nicht stumm
  return true;
}

void amp(bool on) { digitalWrite(PIN_AMP_EN, on ? HIGH : LOW); }

void task(void *) {
  static int16_t buf[CHUNK * 2];
  const Step *pat = nullptr;
  int count = 0, idx = 0;
  uint32_t left = 0, total = 0, phase = 0, startedAt = 0;
  audio::Tone cur = audio::Tone::Off;
  for (;;) {
    audio::Tone w = want;
    if (w != cur) {
      cur = w;
      current = w;
      if (cur == audio::Tone::Off) {
        i2s_zero_dma_buffer(PORT);
        amp(false);
      } else {
        pat = cur == audio::Tone::Alarm ? ALARM_PAT : cur == audio::Tone::Test ? TEST_PAT : TIMER_PAT;
        count = cur == audio::Tone::Alarm ? sizeof ALARM_PAT / sizeof ALARM_PAT[0]
                : cur == audio::Tone::Test ? sizeof TEST_PAT / sizeof TEST_PAT[0]
                                           : sizeof TIMER_PAT / sizeof TIMER_PAT[0];
        idx = 0;
        total = left = pat[0].ms * RATE / 1000;
        phase = 0;
        startedAt = millis();
        amp(true);
      }
    }
    if (cur == audio::Tone::Off) {
      ulTaskNotifyTake(pdTRUE, pdMS_TO_TICKS(200));
      continue;
    }
    // Der Wecker beginnt bei einem Viertel und ist nach 30 Sekunden voll da.
    float gain = cur == audio::Tone::Alarm ? min(1.0f, 0.25f + (millis() - startedAt) / 40000.0f) : cur == audio::Tone::Test ? 0.6f : 0.8f;
    bool finished = false;
    for (int i = 0; i < CHUNK; i++) {
      if (left == 0) {
        if (++idx >= count) {
          if (cur == audio::Tone::Test) {
            finished = true;
          }
          idx = 0;
        }
        total = left = pat[idx].ms * RATE / 1000;
        phase = 0;
      }
      int16_t v = 0;
      const Step &st = pat[idx];
      if (st.hz && !finished) {
        uint32_t pos = total - left;
        float env = pos < (uint32_t)EDGE ? pos / (float)EDGE : left < (uint32_t)EDGE ? left / (float)EDGE : 1.0f;
        v = (int16_t)(sine[phase >> 24] * env * gain);
        phase += (uint32_t)(((uint64_t)st.hz << 32) / RATE);
      }
      left--;
      buf[i * 2] = v;
      buf[i * 2 + 1] = v;
    }
    size_t written = 0;
    i2s_write(PORT, buf, sizeof buf, &written, pdMS_TO_TICKS(100));
    if (finished && want == audio::Tone::Test) want = audio::Tone::Off;
  }
}

}  // namespace

namespace audio {

bool begin() {
  pinMode(PIN_AMP_EN, OUTPUT);
  amp(false);
  for (int i = 0; i < 256; i++) sine[i] = (int16_t)(LOUD * sinf(i * 2.0f * (float)PI / 256.0f));
  if (!i2sInit()) return false;
  if (!codecInit()) {
    i2s_driver_uninstall(PORT);
    return false;
  }
  ok = xTaskCreatePinnedToCore(task, "audio", 4096, nullptr, 3, &taskHandle, 0) == pdPASS;
  return ok;
}

bool present() { return ok; }

uint16_t chipId() { return chip; }

void play(Tone t) {
  if (!ok) return;
  want = t;
  if (taskHandle) xTaskNotifyGive(taskHandle);
}

Tone playing() { return current; }

}  // namespace audio
