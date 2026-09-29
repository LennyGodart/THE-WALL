// Uhrzeit und Datum als Text, genau wie clockText() und dateText() in ops.js.
#pragma once
#include <stddef.h>
#include <time.h>

// 20:14, 20:14:33 oder 08:14 PM. Ohne suffixed fehlt " AM" und " PM", dann zeichnet
// der Aufrufer es selbst, klein neben den Ziffern.
void clockText(char *out, size_t n, const struct tm &t, bool h24, bool sec, bool suffixed = true);

// FRI 25 SEP, der Tag ohne fuehrende Null.
void dateText(char *out, size_t n, const struct tm &t, bool de);
