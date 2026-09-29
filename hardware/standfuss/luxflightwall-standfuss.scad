// =====================================================================
//  LuxFlightWall - offener Standfuss statt Gehaeuse
//
//  Zwei Klauen, die das Panel an der Unterkante greifen und leicht
//  nach hinten neigen. Die rechte Klaue traegt zusaetzlich das Board,
//  quer auf vier Schnapp-Pins statt Schrauben.
//  Hinten offen, keine Rueckplatte, kein Rahmen.
//
//  Bauteile:  fuss_links, fuss_rechts   (= die zwei Druckteile)
//             probe                     (= Rahmen mit den vier Schnapp-Pins zum Testen)
//             alles                     (= Vorschau mit Panel und Board)
//
//  Beide Fuesse drucken ohne Stuetzen, liegend auf der Grundplatte.
// =====================================================================

// ---------------------------------------------------------------------
//  MESSEN, sobald das Panel da ist
// ---------------------------------------------------------------------
panel_t     = 14;    // Dicke der Platine samt LEDs. Anprobe am 14.09.2026: mit 13 war der Schlitz 1 mm zu eng
panel_w     = 320;   // Panelbreite
panel_h     = 160;   // Panelhoehe

// ---------------------------------------------------------------------
//  Board SEENGREAT RGB Matrix HUB75 S3. Umriss und Lochdurchmesser aus der
//  Masszeichnung im Wiki von Seengreat, die Lochabstaende im Originalbild
//  (1600 x 1067) ausgemessen, etwa +-0,3 mm. Vor dem grossen Druck mit dem
//  Lineal pruefen oder teil = "probe" drucken.
// ---------------------------------------------------------------------
board_w     = 65;    // liegt quer hinter der Ruecklasche
board_h     = 57.5;
hole_dx     = 60.2;  // Lochabstand quer, Mitte zu Mitte
hole_dy     = 52.5;  // Lochabstand hoch, Mitte zu Mitte
hole_d      = 2.6;   // Befestigungsloch fuer M2.5. Seengreat nennt 2,5 mm, auf den Fotos wirkt es
                     // eher wie 2,7 mm. Mit 2,6 halten die Pins in beiden Faellen
pcb_t       = 1.6;   // Platinendicke, nicht angegeben, Standardwert
standoff_min = 12;   // Luft zwischen Lasche und Platinenunterseite an den oberen Pins. Unten sitzt
                     // eine 8,5 mm hohe Buchsenleiste (HUB75 zum direkten Aufstecken), oben der Lautsprecher
standoff_d  = 5;     // Auflage unter der Platine, die Loecher sitzen 2,5 mm vom Rand
board_zm    = 54;    // Mitte des Boards ueber dem Tisch

// Schnapp-Pins: gespaltener Schaft mit Rastnase, das Board wird aufgedrueckt.
// Zum Abnehmen die Nasen mit zwei Fingern zusammendruecken. PETG federt besser als PLA.
pin_d       = hole_d - 0.3;  // Schaft
pin_slot    = 0.6;           // Schlitz, die zwei Beine federn seitlich in der Schichtebene
barb_d      = hole_d + 0.4;  // Rastnase, 0,2 mm Ueberstand je Seite ueber dem Lochrand
barb_len    = 1.5;           // Einfuehrschraege
pin_grip    = pcb_t;         // Auflage bis Rastnase: genau die Platine, damit nichts klappert
slot_depth  = 3;             // so tief reicht der Schlitz in den Abstandshalter
pin_flat    = pin_d / 2 - 0.05;  // flache Unterkante von Schaft und Nase unter der Pinmitte

// ---------------------------------------------------------------------
//  Form. Hier darfst du nach Geschmack drehen.
// ---------------------------------------------------------------------
tilt        = 8;     // Neigung nach hinten, in Grad
foot_w      = 72;    // Breite der linken Klaue
foot_w_board = 80;   // Breite der rechten Klaue, das Board liegt quer
base_d      = 80;    // Tiefe der Grundplatte
base_t      = 4;     // Dicke der Grundplatte
wall        = 3.2;   // Wandstaerke der Klaue
lip         = 1.5;   // wie weit die Vorderlippe vorn ueber die Panelunterkante reicht.
                     // Die LEDs gehen bis an die Kante, eine Zeile sind 2,5 mm. Die erste
                     // Fassung reichte 14 mm hoch und verdeckte fuenf bis sechs Zeilen.
                     // 0 heisst ohne Lippe, dann halten nur die Schrauben hinten.
tab_h       = 40;    // Hoehe der Ruecklasche, linke Klaue
tab_h_board = 92;    // Hoehe der Ruecklasche, rechte Klaue mit Board
rest_h      = 6;     // worauf die Panelunterkante aufsitzt
slot_y      = 22;    // Abstand Vorderkante Grundplatte bis Panel
fit         = 0.6;   // Spiel im Schlitz
ecke_r      = 6;     // Radius der oberen Ecken der Ruecklasche und der hinteren Ecken der
                     // Grundplatte. Runde Ecken heben sich beim Druck weniger vom Bett. 0 = eckig

// Bolzen hinten am Panel, der in die Ruecklasche greift. Anprobe am 19.09.2026: er rieb am
// rechten Fuss eine waagerechte Spur 2,5 bis 5 mm ueber dem alten Langloch, das nur bis
// 11,7 mm ueber die Panelunterkante reichte. Die Spur ist die Mitte des Bolzens. Der erste
// Querschlitz mit der Mitte bei 15,5 mm sass laut Projektinhaber 2 mm zu hoch.
bolzen_ab_kante = 13.5;  // Mitte des Bolzens ueber der Unterkante des Panels
bolzen_spiel    = 10;    // so weit darf er links und rechts neben der Fussmitte sitzen
bolzen_luft     = 7;     // Hoehe des Querschlitzes: Bolzen bis 3 mm dick, 2 mm hoeher oder tiefer

teil        = "alles";

$fn = 40;

slot   = panel_t + fit;
claw_d = wall + slot + wall;

// Rueckseite der geneigten Lasche auf Hoehe z ueber dem Tisch
function lasche_y(z) = slot_y + claw_d * cos(tilt) + (z + claw_d * sin(tilt)) * tan(tilt);

// Das Board steht senkrecht, nicht geneigt wie das Panel: so liegen alle Pins
// waagerecht und drucken ohne Stuetzen. Die unteren Abstandshalter sind dafuer laenger.
board_top_z = board_zm + hole_dy / 2;
board_y     = lasche_y(board_top_z) + standoff_min;

echo(str("Klaue: ", foot_w, " x ", base_d, " mm Grundflaeche"));
echo(str("Schlitz: ", slot, " mm"));
echo(str("Vorderlippe: ", lip, " mm ueber der Panelunterkante"));
echo(str("Board: ", board_w, " x ", board_h, " mm quer, Loecher ", hole_dx, " x ", hole_dy,
         " mm, Abstandshalter oben ", standoff_min, " mm, unten ",
         board_y - lasche_y(board_zm - hole_dy / 2), " mm"));

// ---------------------------------------------------------------------
//  Grundplatte mit angeschraegter Vorderkante
// ---------------------------------------------------------------------
module basis(w) {
    r = max(0.01, ecke_r);
    hull() {
        translate([0, 6, 0])            cube([w, base_d - 6 - r, base_t]);
        // hinten abgerundet
        for (x = [r, w - r]) translate([x, base_d - r, 0]) cylinder(r = r, h = base_t);
        translate([4, 0, 0])            cube([w - 8, 6, base_t - 1.6]);
    }
}

// Was von einer Ecke wegfaellt, damit sie den Radius r bekommt: Ecke im Ursprung,
// das Teil liegt bei +x und +y.
module ecke_2d(r) {
    difference() {
        translate([-1, -1]) square([r + 1, r + 1]);
        translate([r, r]) circle(r = r);
    }
}

// ---------------------------------------------------------------------
//  Die geneigte Klaue, die das Panel haelt
// ---------------------------------------------------------------------
module klaue(hoehe, w) {
    difference() {
        translate([0, 0, -12])
            cube([w, claw_d, hoehe + 12]);

        // Schlitz fuer das Panel
        translate([-1, wall, rest_h])
            cube([w + 2, slot, hoehe + 20]);

        // Vorderlippe nur lip mm ueber die Auflage stehen lassen
        translate([-1, -1, rest_h + lip])
            cube([w + 2, wall + 1, hoehe + 20]);

        // Fenster in der Ruecklasche spart Filament und Zeit
        if (hoehe < 60)
            translate([w/2 - 20, wall + slot - 1, rest_h + 12])
                cube([40, wall + 2, hoehe - rest_h - 22]);

        // obere Ecken der Ruecklasche rund. Beim Druck wird die Lasche oben nur schmaler,
        // das braucht keine Stuetzen.
        if (ecke_r > 0)
            for (seite = [0, 1])
                translate([seite * w, -1, hoehe])
                    rotate([-90, 0, 0])   // 2D-y zeigt danach nach unten, extrudiert wird nach +y
                        linear_extrude(height = claw_d + 2)
                            scale([1 - 2 * seite, 1]) ecke_2d(ecke_r);
    }
}

// ---------------------------------------------------------------------
//  Langloch in der Ruecklasche: erwischt das Magnetgewinde des Panels, egal
//  wie hoch es sitzt, und nimmt eine M3-Schraube hinein. Hoehen in der Lasche,
//  gemessen ab ihrem Fuss (das Panel sitzt bei rest_h). Mit quer_b dazu ein
//  Querschlitz, in dem der Bolzen des Panels links und rechts Spiel hat.
// ---------------------------------------------------------------------
module langloch(w, z_unten, z_oben, quer_z = 0, quer_b = 0, quer_h = 0) {
    translate([w/2, wall + slot - 1, 0])
        rotate([-90, 0, 0]) {
            // nach dem Drehen zeigt -y in der Lasche nach oben
            hull() {
                translate([0, -z_unten, 0]) cylinder(d = 3.4, h = wall + 2);
                translate([0, -z_oben, 0]) cylinder(d = 3.4, h = wall + 2);
            }
            if (quer_b > 0)
                hull() {
                    translate([-quer_b, -quer_z, 0]) cylinder(d = quer_h, h = wall + 2);
                    translate([ quer_b, -quer_z, 0]) cylinder(d = quer_h, h = wall + 2);
                }
        }
}

// ---------------------------------------------------------------------
//  Querschnitt von Schaft und Rastnase: Kreis, unten flach abgeschnitten und
//  mit 45-Grad-Flanken dorthin. +y ist nach dem Drehen unten, zum Druckbett.
// ---------------------------------------------------------------------
module pin_profil(r) {
    x = max(0.01, sqrt(2) * r - pin_flat);
    intersection() {
        hull() {
            circle(r = r);
            translate([-x, pin_flat - 0.01]) square([2 * x, 0.01]);
        }
        translate([-r - 1, -r - 1]) square([2 * r + 2, r + 1 + pin_flat]);
    }
}

// Schaft durch die Platine und Rastnase, entlang +z ab der Auflage
module pin_spitze() {
    linear_extrude(height = pin_grip) pin_profil(pin_d / 2);
    translate([0, 0, pin_grip])
        hull() {
            linear_extrude(height = 0.01) pin_profil(barb_d / 2);
            translate([0, 0, barb_len - 0.01])
                linear_extrude(height = 0.01) pin_profil(pin_d / 2 - 0.3);
        }
}

// ---------------------------------------------------------------------
//  Vier Halter fuer das Board. Jeder Abstandshalter ist ein Keil: oben
//  waagerecht, unten mit mindestens 45 Grad in die Lasche. Die Pins zeigen
//  waagerecht nach hinten.
// ---------------------------------------------------------------------
module board_halter(w) {
    s = standoff_d / 2;
    for (x = [-hole_dx/2, hole_dx/2], dz = [-hole_dy/2, hole_dy/2]) {
        zc  = board_zm + dz;
        len = board_y - lasche_y(zc);
        // tief genug, dass die Unterseite trotz Neigung der Lasche 45 Grad hat
        z_low = zc - (len + s + 1) / (1 - tan(tilt)) - s;
        difference() {
            union() {
                hull() {
                    // Auflage fuer die Platine
                    translate([w/2 + x - s, board_y - 0.01, zc - s]) cube([standoff_d, 0.01, standoff_d]);
                    // 1 mm in der Lasche, parallel zu ihrer Rueckseite
                    translate([w/2 + x - s, lasche_y(z_low) - 1, z_low]) cube([standoff_d, 0.01, 0.01]);
                    translate([w/2 + x - s, lasche_y(zc + s) - 1, zc + s - 0.01]) cube([standoff_d, 0.01, 0.01]);
                }
                translate([w/2 + x, board_y, zc]) rotate([-90, 0, 0]) pin_spitze();
            }
            // Schlitz: die Beine federn nach links und rechts
            translate([w/2 + x - pin_slot/2, board_y - slot_depth, zc - s - slot_depth - 1])
                cube([pin_slot, slot_depth + pin_grip + barb_len + 1, standoff_d + slot_depth + 2]);
        }
    }
}

// Umriss des Boards, nur fuer die Vorschau
module board_umriss(w) {
    translate([w/2 - board_w/2, board_y, board_zm - board_h/2])
        cube([board_w, pcb_t, board_h]);
}

// ---------------------------------------------------------------------
//  Eine komplette Klaue
// ---------------------------------------------------------------------
module fuss(mit_board = false) {
    hoehe = mit_board ? tab_h_board : tab_h;
    w = mit_board ? foot_w_board : foot_w;
    difference() {
        union() {
            basis(w);
            translate([0, slot_y, 0])
                rotate([-tilt, 0, 0])
                    klaue(hoehe, w);
            if (mit_board) board_halter(w);
        }

        // Langloch zum Anschrauben ans Panel. Beim rechten Fuss liegt es hinter dem Board:
        // erst schrauben, dann das Board aufdruecken. Dort sitzt es seit der Anprobe vom
        // 19.09.2026 auf Hoehe des Bolzens, mit Querschlitz fuer Spiel links und rechts.
        // Das Langloch endet in der Mitte des Querschlitzes und ragt nicht darueber hinaus.
        // Links liegt diese Hoehe ohnehin im Fenster der Lasche, das Langloch bleibt.
        translate([0, slot_y, 0]) rotate([-tilt, 0, 0])
            if (mit_board)
                langloch(w, rest_h + bolzen_ab_kante - 10, rest_h + bolzen_ab_kante,
                         rest_h + bolzen_ab_kante, bolzen_spiel, bolzen_luft);
            else
                langloch(w, 6, 20);

        // Zwei Senkloecher, falls du die Fuesse aufs Brett schraubst
        for (y = [base_d - 16, base_d - 44])
            translate([w/2, y, -1]) {
                cylinder(d = 4.5, h = base_t + 2);
                translate([0, 0, base_t - 2.2]) cylinder(d1 = 4.5, d2 = 9, h = 2.4);
            }

        // alles unter der Tischplatte abschneiden
        translate([-20, -20, -40]) cube([w + 40, base_d + 40, 40]);
    }
}

// ---------------------------------------------------------------------
//  Probestueck: Rahmen mit vier Pins im Lochbild des Boards, flach gedruckt.
//  Passen Abstand und Rastnasen, passt auch der rechte Fuss.
// ---------------------------------------------------------------------
module probe() {
    difference() {
        translate([-board_w/2 - 2, -board_h/2 - 2, 0]) cube([board_w + 4, board_h + 4, 2]);
        translate([-board_w/2 + 6, -board_h/2 + 6, -1]) cube([board_w - 12, board_h - 12, 4]);
    }
    for (x = [-hole_dx/2, hole_dx/2], y = [-hole_dy/2, hole_dy/2])
        translate([x, y, 0])
            difference() {
                union() {
                    cylinder(d = standoff_d, h = 6);
                    translate([0, 0, 6]) pin_spitze();
                }
                translate([-pin_slot/2, -standoff_d, 6 - slot_depth])
                    cube([pin_slot, 2 * standoff_d, slot_depth + pin_grip + barb_len + 1]);
            }
}

// ---------------------------------------------------------------------
//  Ausgabe
// ---------------------------------------------------------------------
if (teil == "fuss_links")       fuss(false);
else if (teil == "fuss_rechts") fuss(true);
else if (teil == "probe")       probe();
else {
    // Vorschau: beide Fuesse mit Panel dazwischen
    color("#3a3a3a") translate([-panel_w/2 + 30, 0, 0]) fuss(false);
    color("#3a3a3a") translate([ panel_w/2 - 30 - foot_w_board, 0, 0]) fuss(true);
    color("#1f6f3a") translate([ panel_w/2 - 30 - foot_w_board, 0, 0]) board_umriss(foot_w_board);

    // Beide Fuesse sitzen 30 mm innerhalb der Panelkanten
    color("#0d0d0d")
        translate([-panel_w/2, slot_y + wall, rest_h])
            rotate([-tilt, 0, 0])
                translate([0, 0, 0])
                    cube([panel_w, panel_t, panel_h]);
}
