/**
 * A timestamp phrased the way a person reads it: "2 hours ago", "yesterday".
 *
 * Dates are formatted in a fixed English locale rather than the viewer's. The
 * console is English throughout, and letting the OS locale through renders
 * Arabic-Indic digits on an Arabic machine — an English page showing "٩ تموز"
 * looks broken rather than localised.
 *
 * Seconds are meaningless in an activity feed — what matters is whether
 * something happened just now, earlier today, or last week. Anything older
 * than a week falls back to a plain date, because "37 days ago" is harder to
 * place than the date itself.
 */
const LOCALE = "en-GB";

export function relativeTime(iso: string | null): string {
  if (iso === null) return "—";

  const then = new Date(iso);
  if (Number.isNaN(then.getTime())) return "—";

  const seconds = Math.round((Date.now() - then.getTime()) / 1000);

  // A clock skew between server and browser must not read as the future.
  if (seconds < 45) return "just now";

  const minutes = Math.round(seconds / 60);
  if (minutes < 60) return plural(minutes, "minute");

  const hours = Math.round(minutes / 60);
  if (hours < 24) return plural(hours, "hour");

  const days = Math.round(hours / 24);
  if (days === 1) return "yesterday";
  if (days < 7) return plural(days, "day");

  return then.toLocaleDateString(LOCALE, {
    day: "numeric",
    month: "short",
    year: then.getFullYear() === new Date().getFullYear() ? undefined : "numeric",
  });
}

/** The exact moment, for a tooltip on top of the friendly phrasing. */
export function exactTime(iso: string | null): string {
  if (iso === null) return "";
  const date = new Date(iso);

  return Number.isNaN(date.getTime()) ? "" : date.toLocaleString(LOCALE);
}

/** Date only, no time — same fixed English locale. */
export function shortDate(iso: string | null): string {
  if (iso === null) return "—";
  const date = new Date(iso);

  return Number.isNaN(date.getTime())
    ? "—"
    : date.toLocaleDateString(LOCALE, {
        day: "numeric",
        month: "short",
        year: "numeric",
      });
}

function plural(value: number, unit: string): string {
  return `${value} ${unit}${value === 1 ? "" : "s"} ago`;
}
