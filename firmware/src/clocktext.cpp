#include "clocktext.h"

#include <stdio.h>

namespace {
const char *const DAYS_EN[] = {"SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT"};
const char *const DAYS_DE[] = {"SON", "MON", "DIE", "MIT", "DON", "FRE", "SAM"};
const char *const MONTHS_EN[] = {"JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"};
const char *const MONTHS_DE[] = {"JAN", "FEB", "MRZ", "APR", "MAI", "JUN", "JUL", "AUG", "SEP", "OKT", "NOV", "DEZ"};
}  // namespace

void clockText(char *out, size_t n, const struct tm &t, bool h24, bool sec, bool suffixed) {
  int hour = t.tm_hour % 24;
  const char *suffix = "";
  if (!h24) {
    if (suffixed) suffix = hour < 12 ? " AM" : " PM";
    hour %= 12;
    if (hour == 0) hour = 12;
  }
  if (sec)
    snprintf(out, n, "%02d:%02d:%02d%s", hour, t.tm_min, t.tm_sec, suffix);
  else
    snprintf(out, n, "%02d:%02d%s", hour, t.tm_min, suffix);
}

void dateText(char *out, size_t n, const struct tm &t, bool de) {
  int wd = (t.tm_wday % 7 + 7) % 7;
  int mo = (t.tm_mon % 12 + 12) % 12;
  snprintf(out, n, "%s %d %s", de ? DAYS_DE[wd] : DAYS_EN[wd], t.tm_mday, de ? MONTHS_DE[mo] : MONTHS_EN[mo]);
}
