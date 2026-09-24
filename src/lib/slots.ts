function toMinutes(hhmm: string): number {
  const [h, m] = hhmm.split(":").map(Number);
  return h * 60 + m;
}

function toHHMM(minutes: number): string {
  const h = Math.floor(minutes / 60)
    .toString()
    .padStart(2, "0");
  const m = (minutes % 60).toString().padStart(2, "0");
  return `${h}:${m}`;
}

export interface OpenHours {
  openTime: string; // "HH:MM" or "HH:MM:SS"
  closeTime: string;
  breakStart: string | null;
  breakEnd: string | null;
}

/** Builds hourly slot labels between open/close, skipping any hour inside the break window. */
export function buildHourlySlots(hours: OpenHours): string[] {
  const open = toMinutes(hours.openTime.slice(0, 5));
  const close = toMinutes(hours.closeTime.slice(0, 5));
  const breakFrom = hours.breakStart ? toMinutes(hours.breakStart.slice(0, 5)) : null;
  const breakTo = hours.breakEnd ? toMinutes(hours.breakEnd.slice(0, 5)) : null;

  const slots: string[] = [];
  for (let current = open; current + 60 <= close; current += 60) {
    if (breakFrom !== null && breakTo !== null && current >= breakFrom && current < breakTo) {
      continue;
    }
    slots.push(toHHMM(current));
  }
  return slots;
}

/** 0 = Sunday … 6 = Saturday, matching PHP's `date('w')` / Carbon's `format('w')`. */
export function dayOfWeek(dateStr: string): number {
  const [y, m, d] = dateStr.split("-").map(Number);
  return new Date(Date.UTC(y, m - 1, d)).getUTCDay();
}
