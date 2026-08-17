import { afterEach, describe, expect, it, vi } from "vitest";
import { exactTime, relativeTime } from "@/lib/relativeTime";

const NOW = new Date("2026-08-18T12:00:00Z");

function ago(seconds: number): string {
  return new Date(NOW.getTime() - seconds * 1000).toISOString();
}

describe("relativeTime", () => {
  afterEach(() => vi.useRealTimers());

  function freeze() {
    vi.useFakeTimers();
    vi.setSystemTime(NOW);
  }

  it("collapses anything very recent into 'just now'", () => {
    freeze();
    // Seconds are noise in a feed; nobody needs "12 seconds ago".
    expect(relativeTime(ago(1))).toBe("just now");
    expect(relativeTime(ago(44))).toBe("just now");
  });

  it("counts minutes and hours, singular and plural", () => {
    freeze();
    expect(relativeTime(ago(60))).toBe("1 minute ago");
    expect(relativeTime(ago(60 * 5))).toBe("5 minutes ago");
    expect(relativeTime(ago(3600))).toBe("1 hour ago");
    expect(relativeTime(ago(3600 * 5))).toBe("5 hours ago");
  });

  it("says yesterday rather than '1 day ago'", () => {
    freeze();
    expect(relativeTime(ago(3600 * 24))).toBe("yesterday");
    expect(relativeTime(ago(3600 * 24 * 3))).toBe("3 days ago");
  });

  it("falls back to a date once it is over a week old", () => {
    freeze();
    // "37 days ago" is harder to place than the date itself.
    expect(relativeTime(ago(3600 * 24 * 40))).toBe("9 Jul");
  });

  it("formats dates in English whatever the machine's locale is", () => {
    freeze();
    // The console is English throughout; an OS set to Arabic must not render
    // "٩ تموز" in the middle of an English page.
    expect(relativeTime(ago(3600 * 24 * 40))).toMatch(/^[0-9]+ [A-Za-z]+$/);
    expect(exactTime(NOW.toISOString())).toMatch(/[0-9]/);
  });

  it("never reads as the future when the clocks disagree", () => {
    freeze();
    // A server slightly ahead of the browser must not produce "-3 seconds ago".
    expect(relativeTime(new Date(NOW.getTime() + 3000).toISOString())).toBe("just now");
  });

  it("handles a missing or unparsable timestamp", () => {
    expect(relativeTime(null)).toBe("—");
    expect(relativeTime("not a date")).toBe("—");
    expect(exactTime(null)).toBe("");
    expect(exactTime("not a date")).toBe("");
  });

  it("still exposes the exact moment for a tooltip", () => {
    expect(exactTime(NOW.toISOString())).not.toBe("");
  });
});
