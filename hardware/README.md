# Hardware

Pro Gerät, Preise vom September 2026.

| Teil | Amazon | Preis |
| --- | --- | --- |
| Ocnvlia P2.5 LED-Panel, 128 × 64 Pixel, 320 × 160 mm, HUB75E | [B0H1MPC9YZ](https://www.amazon.de/dp/B0H1MPC9YZ) | 43,63 € |
| SEENGREAT RGB Matrix HUB75 S3 V1.0 mit ESP32-S3-WROOM-1-N16R8, 16 MB Flash, 8 MB PSRAM | [B0H69TFHJ7](https://www.amazon.de/dp/B0H69TFHJ7) | 35,88 € |
| Standfuß, zwei Druckteile aus `standfuss/` | | etwa 1 € Filament |

Flachband-, Strom- und USB-C-Kabel liegen dem Board bei. Strom kommt über die Buchse USB-C ans Board, ein Handy-Ladegerät mit 5 V und 3 A reicht. Das Board gibt die 5 V über die Klemme VH-4P und das beiliegende Stromkabel an das Panel weiter. Die zweite Buchse POWER bleibt frei.

## Panel

- Auf dem Aufkleber der gelieferten Panels (QiangLi Q2.5, 128 × 64) stehen die Chips DP32019A (Spalten) und DP5125D (Zeilen), nicht FM6126A wie im Angebot. Geprüft am 14. September 2026: das Panel läuft mit `driver = HUB75_I2S_CFG::FM6126A` und `line_decoder = HUB75_I2S_CFG::TYPE138`, die Schieberegister-Varianten SM5368 und SM5266P zeigen nur Striche
- Weitere Panels von AliExpress, angekommen am 24. September 2026, tragen dieselben Chips und laufen ohne jede Änderung an der Firmware. Sie passen auch ohne Änderung in die gedruckten Füße: Dicke (`panel_t` 14 mm) und Bolzen hinten sitzen wie beim ersten Panel
- Die Bibliothek erwartet ab Version 3 vierzehn Pins in dieser Reihenfolge: `r1, g1, b1, r2, g2, b2, a, b, c, d, e, lat, oe, clk`. Mit den elf Pins aus alten Beispielen rutschen LAT, OE und CLK auf falsche Pins, und es leuchten nur einzelne Zeilen in einer Hälfte
- 1/32 Scan, der E-Pin muss gesetzt sein
- Bibliothek: ESP32-HUB75-MatrixPanel-DMA
- Das Modul hat Octal-PSRAM. Unter PlatformIO braucht es `board_build.arduino.memory_type = qio_opi`

## Pinbelegung des Boards

| Signal | GPIO | Signal | GPIO |
| --- | --- | --- | --- |
| R1 | 5 | A | 8 |
| G1 | 4 | B | 18 |
| B1 | 6 | C | 10 |
| R2 | 15 | D | 9 |
| G2 | 7 | E | 16 |
| B2 | 17 | LAT | 11 |
| OE | 13 | CLK | 12 |

## Weitere Bauteile auf dem Board

Aus dem [Schaltplan V1.0](https://seengreat.com/upload/file/142%20RGB%20Matrix%20HUB75%20S3/RGB%20Matrix%20HUB75%20S3%20V1.0.pdf) und dem [Wiki](https://seengreat.com/wiki/214/rgb-matrix-hub75-s3) von Seengreat. Die vier I2C-Adressen hat die Firmware am 14. September 2026 auf dem Board gefunden.

| Teil | Baustein | Anschluss |
| --- | --- | --- |
| I2C-Bus | 2,2 kΩ auf 3,3 V | SDA IO1, SCL IO2, auch an J10 |
| Uhrchip | PCF85063A, 0x51 | am I2C-Bus. Pufferbatterie an J1, liegt nicht bei |
| Port-Baustein | PCA9557, 0x19, an 5 V | am I2C-Bus. IO1, IO2, IO3: Drei-Wege-Rad K1, K3, K2, gedrückt ist 0. IO4: Alarm des Uhrchips. IO5: Versorgung von Codec und Mikrofon-Wandler, ab Start an |
| microSD | SPI | CS IO39, MOSI IO40, CLK IO41, MISO IO42, keine Kartenerkennung |
| Codec | ES8311, 0x18 | MCLK IO38, BCLK IO48, WS IO21, Daten IO14 |
| Mikrofone | ES7210, 0x40, zwei analoge MEMS | Daten IO47 |
| Verstärker | NS4150B, Lautsprecher an J2 | Einschalten IO3, ab Start aus |
| Frei | | IO45 und IO46 (Strapping-Pins), TXD0 und RXD0 |

Den Port-Baustein nur lesen oder vor dem Umstellen von IO5 auf Ausgang dessen Bit auf 1 setzen, sonst verlieren Codec und Mikrofon-Wandler die Versorgung. Er setzt sich nur mit EN zurück, nicht bei einem Neustart der Firmware. Welche Richtung des Rads K1, K2 oder K3 ist, steht in keiner Quelle.

## Standfuß

`standfuss/luxflightwall-standfuss.scad` in OpenSCAD öffnen. `panel_t` ist die Dicke des Panels samt LEDs: nach der ersten Anprobe am 14. September 2026 steht sie auf 14 mm, mit 13 war der Schlitz einen Millimeter zu eng. `lip` ist die Lippe vorn, sie reicht 1,5 mm über die Unterkante des Panels. Die LEDs gehen bis an die Kante, eine Zeile sind 2,5 mm; die erste Fassung reichte 14 mm hoch und verdeckte fünf bis sechs Zeilen. Das Board sitzt quer an der Rücklasche des rechten Fußes, auf vier Schnapp-Pins statt Schrauben: aufdrücken, zum Abnehmen die Rastnasen zusammendrücken. Umriss 65 × 57,5 mm und Löcher Ø 2,5 mm stammen aus der Maßzeichnung von Seengreat (auf den Fotos eher 2,7 mm, die Pins halten in beiden), die Lochabstände `hole_dx` 60,2 und `hole_dy` 52,5 mm sind im Bild ausgemessen (etwa ±0,3 mm). Vor dem ganzen Fuß lohnt `teil = "probe"`, ein flacher Rahmen mit denselben vier Pins. Passt das Board nicht, `hole_dx` und `hole_dy` mit dem Lineal nachmessen; rastet es zu fest oder zu locker, `barb_d` um 0,1 mm ändern. `standoff_min` ist der Abstand zur Lasche an den oberen Pins, 12 mm, weil unten eine 8,5 mm hohe Buchsenleiste sitzt und oben der Lautsprecher; das Board steht senkrecht, damit die Pins waagerecht liegen, die unteren Abstandshalter sind deshalb 19,4 mm lang. In der Rücklasche sitzt ein Langloch für den Bolzen hinten am Panel, durch das sich auch eine M3-Schraube ins Magnetgewinde drehen lässt. Beim rechten Fuß ist es seit der Anprobe am 19. September 2026 ein umgedrehtes T: ein Querschlitz 7 mm hoch mit 10 mm Spiel nach links und rechts, seine Mitte 13,5 mm über der Unterkante des Panels (`bolzen_ab_kante`, `bolzen_spiel`, `bolzen_luft`), darunter das Langloch, 3,4 mm breit, ab 1,8 mm. Es endet in der Mitte des Querschlitzes und ragt nicht darüber hinaus. Vorher reichte das Langloch nur bis 11,7 mm, und der Bolzen rieb 2,5 bis 5 mm darüber an der Lasche; der erste Querschlitz mit der Mitte bei 15,5 mm saß 2 mm zu hoch. Beim linken Fuß liegt diese Höhe im Fenster der Lasche, sein Langloch bleibt. Die oberen Ecken der Rücklaschen und die hinteren Ecken der Grundplatten sind mit 6 mm gerundet (`ecke_r`, 0 für eckig). Beide Füße drucken liegend auf der Grundplatte ohne Stützen, der Querschlitz überbrückt oben 20 mm. PETG federt besser als PLA. Gedruckt werden beide Füße, der rechte trägt das Board. Filamentbedarf: `node hardware/vol.mjs`.

`archiv-rahmen/` ist der verworfene geschlossene Rahmen.
