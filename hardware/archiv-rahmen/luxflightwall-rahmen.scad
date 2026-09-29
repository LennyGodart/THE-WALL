// =====================================================================
//  LuxFlightWall - Rahmen fuer ein P2.5 LED-Panel 320 x 160 mm
//  Vier Leisten auf Gehrung, Rueckplatte zweiteilig, Klebelehre.
//  Gedacht fuer Bambu P1S: die langen Leisten diagonal aufs Bett.
// =====================================================================

// ---------------------------------------------------------------------
//  MESSEN, sobald das Panel da ist. Bis dahin stehen hier Schaetzwerte.
// ---------------------------------------------------------------------
panel_w      = 320;   // Panelbreite laut Hersteller
panel_h      = 160;   // Panelhoehe laut Hersteller
panel_t      = 13;    // MESSEN: Dicke der Platine samt LEDs
rear_clear    = 14;   // MESSEN: hoechster Stecker auf der Rueckseite
                      //         (HUB75-Wanne oder Stromklemme)

// ---------------------------------------------------------------------
//  Rahmenprofil. Hier darfst du nach Geschmack drehen.
// ---------------------------------------------------------------------
lip          = 4.0;   // wie weit die Blende vorn aufs Panel greift
lip_t        = 2.4;   // Dicke dieser Blende
wall         = 3.0;   // Dicke der Seitenwand
back_t       = 2.4;   // Dicke der Rueckplatte
fit          = 0.4;   // Spiel rundum, damit das Panel reinrutscht

// Bauteil, das gerendert wird: "leiste_lang", "leiste_kurz",
// "rueckplatte", "lehre" oder "alles" fuer die Vorschau.
teil         = "alles";

$fn = 48;

// ---------------------------------------------------------------------
//  Abgeleitete Masse, nichts zu aendern
// ---------------------------------------------------------------------
depth     = lip_t + panel_t + rear_clear + back_t;  // Gesamttiefe
inner_w   = panel_w + 2*fit;
inner_h   = panel_h + 2*fit;
outer_w   = inner_w + 2*wall;
outer_h   = inner_h + 2*wall;
reach     = wall + lip;      // wie weit das Profil nach innen ragt

boss_d    = 7.0;             // Schraubdom fuer die Rueckplatte
boss_hole = 2.5;             // Kernloch fuer M3-Blechschraube

echo(str("Gesamttiefe   = ", depth, " mm"));
echo(str("Aussenmass    = ", outer_w, " x ", outer_h, " mm"));
echo(str("Lange Leiste  = ", outer_w, " mm, diagonal aufs Bett"));

// ---------------------------------------------------------------------
//  Querschnitt des Profils, liegend in der YZ-Ebene
//    y = nach innen, z = nach hinten
// ---------------------------------------------------------------------
module profil_2d() {
    polygon(points = [
        [0,     0],
        [reach, 0],
        [reach, lip_t],
        [wall,  lip_t],
        [wall,  depth],
        [0,     depth]
    ]);
}

// ---------------------------------------------------------------------
//  Eine Leiste: Profil ueber die Laenge gezogen, beide Enden auf 45 Grad
//  Zusaetzlich Schraubdome fuer die Rueckplatte.
// ---------------------------------------------------------------------
module leiste(laenge, dome = 3) {
    difference() {
        union() {
            // Profil entlang X ziehen
            rotate([90, 0, 90])
                linear_extrude(height = laenge)
                    profil_2d();

            // Schraubdome an der Innenseite, hinten buendig
            for (i = [0 : dome-1]) {
                x = laenge/2 + (i - (dome-1)/2) * (laenge - 4*reach) / max(dome-1, 1);
                translate([x, wall, depth - back_t - 6])
                    rotate([-90, 0, 0])
                        cylinder(d = boss_d, h = 6);
            }
        }

        // Gehrung links: alles wegnehmen, wo x kleiner als y ist
        translate([-1, -1, -1])
            rotate([0, 0, 0])
                linear_extrude(height = depth + 2)
                    polygon([[0,0], [reach+2, 0], [0, reach+2]]);

        // Gehrung rechts, gespiegelt
        translate([laenge + 1, -1, -1])
            linear_extrude(height = depth + 2)
                polygon([[0,0], [-(reach+2), 0], [0, reach+2]]);

        // Kernloecher in die Dome
        for (i = [0 : dome-1]) {
            x = laenge/2 + (i - (dome-1)/2) * (laenge - 4*reach) / max(dome-1, 1);
            translate([x, wall - 1, depth - back_t - 6])
                rotate([-90, 0, 0])
                    cylinder(d = boss_hole, h = 9);
        }
    }
}

module leiste_lang()  { leiste(outer_w, 4); }
module leiste_kurz()  { leiste(outer_h, 2); }

// ---------------------------------------------------------------------
//  Rueckplatte, zweiteilig mit ueberlappendem Stoss.
//  haelfte = 0 (links, mit Falz) oder 1 (rechts, mit Gegenfalz)
// ---------------------------------------------------------------------
lap = 20;   // Laenge der Ueberlappung

module rueckplatte(haelfte = 0) {
    w = inner_w / 2;
    h = inner_h;
    difference() {
        union() {
            cube([w, h, back_t]);
            // Falz, halbe Dicke, ragt ueber die Mitte
            if (haelfte == 0)
                translate([w, 0, 0]) cube([lap, h, back_t/2]);
        }
        if (haelfte == 1)
            translate([-lap - 0.2, -1, -0.1])
                cube([lap + 0.2, h + 2, back_t/2 + 0.1]);

        // Kabeldurchlass fuer USB-C
        if (haelfte == 1)
            translate([w - 34, h/2 - 7, -1]) cube([22, 14, back_t + 2]);
    }
}

// ---------------------------------------------------------------------
//  Klebelehre: haelt eine Gehrung rechtwinklig, waehrend der Kleber zieht
// ---------------------------------------------------------------------
module lehre() {
    arm = 45;
    t   = 6;
    difference() {
        union() {
            cube([arm, 12, t]);
            cube([12, arm, t]);
        }
        translate([-1, -1, t - 3]) cube([arm + 2, arm + 2, 4]);
    }
}

// ---------------------------------------------------------------------
//  Ausgabe
// ---------------------------------------------------------------------
if (teil == "leiste_lang")      leiste_lang();
else if (teil == "leiste_kurz") leiste_kurz();
else if (teil == "rueckplatte") rueckplatte(0);
else if (teil == "rueckplatte2")rueckplatte(1);
else if (teil == "lehre")       lehre();
else {
    // Vorschau: kompletter Rahmen zusammengesetzt
    color("#2b2b2b") {
        leiste_lang();
        translate([outer_w, outer_h, 0]) rotate([0, 0, 180]) leiste_lang();
        translate([0, outer_h, 0]) rotate([0, 0, -90]) leiste_kurz();
        translate([outer_w, 0, 0]) rotate([0, 0, 90]) leiste_kurz();
    }
    // Panel als Platzhalter
    color("#111111")
        translate([wall + fit, wall + fit, lip_t])
            cube([panel_w, panel_h, panel_t]);
}
