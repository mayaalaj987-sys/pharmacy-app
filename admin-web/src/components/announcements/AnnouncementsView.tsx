import { useQuery } from "@tanstack/react-query";
import { useRef, useState } from "react";
import { Megaphone, Users } from "lucide-react";
import { AdminApiError } from "@/lib/adminApi";
import { fetchAudience, sendAnnouncement } from "@/lib/announcementsApi";
import { useToast } from "@/components/ui-custom/Toast";

/**
 * Compose an announcement for pharmacies.
 *
 * An announcement becomes an ordinary notification in each recipient's app, so
 * there is no separate inbox to manage here — only sending.
 */
export function AnnouncementsView() {
  const [title, setTitle] = useState("");
  const [message, setMessage] = useState("");
  const [target, setTarget] = useState<"all" | "pharmacy">("all");
  const [pharmacyId, setPharmacyId] = useState<number | "">("");
  const [sending, setSending] = useState(false);
  // A ref, not state: two clicks in the same tick must not both send.
  const inFlight = useRef(false);
  const { push } = useToast();

  const audience = useQuery({
    queryKey: ["announcements", "audience"],
    queryFn: ({ signal }) => fetchAudience(signal),
  });

  const pharmacies = audience.data?.data.pharmacies ?? [];
  const recipients =
    target === "all" ? (audience.data?.data.recipients ?? 0) : pharmacyId === "" ? 0 : 1;

  async function submit() {
    if (title.trim().length < 3 || message.trim().length < 10) {
      push({
        title: "Not sent",
        description: "Use a title of at least 3 characters and a message of at least 10.",
        variant: "error",
      });
      return;
    }
    if (target === "pharmacy" && pharmacyId === "") {
      push({
        title: "Not sent",
        description: "Choose the pharmacy to notify.",
        variant: "error",
      });
      return;
    }
    if (inFlight.current) return;
    inFlight.current = true;
    setSending(true);

    try {
      const result = await sendAnnouncement({
        title: title.trim(),
        message: message.trim(),
        target,
        ...(target === "pharmacy" ? { pharmacy_id: Number(pharmacyId) } : {}),
      });
      push({
        title: "Announcement sent",
        description: `Delivered to ${result.data?.recipients ?? 0} pharmacy(ies).`,
        variant: "success",
      });
      setTitle("");
      setMessage("");
    } catch (error) {
      push({
        title: "Not sent",
        description:
          error instanceof AdminApiError ? error.message : "Unable to send the announcement.",
        variant: "error",
      });
    } finally {
      inFlight.current = false;
      setSending(false);
    }
  }

  return (
    <div className="space-y-4 max-w-3xl">
      <div>
        <h2 className="text-lg font-semibold">Announcements</h2>
        <p className="text-xs text-muted-foreground mt-0.5">
          Send a message straight to a pharmacy's notifications in the app.
        </p>
      </div>

      <section className="bg-card rounded-2xl border shadow-soft p-6 space-y-5">
        <div>
          <span className="text-xs font-medium block mb-2">Send to</span>
          <div className="flex items-center gap-1 bg-muted/50 rounded-xl p-1 w-fit">
            {(["all", "pharmacy"] as const).map((value) => (
              <button
                key={value}
                onClick={() => setTarget(value)}
                className={`h-8 px-4 rounded-lg text-xs font-medium transition ${
                  target === value
                    ? "bg-primary text-primary-foreground"
                    : "text-muted-foreground hover:text-foreground"
                }`}
              >
                {value === "all" ? "All pharmacies" : "One pharmacy"}
              </button>
            ))}
          </div>
        </div>

        {target === "pharmacy" && (
          <div>
            <label htmlFor="announcement-pharmacy" className="text-xs font-medium block mb-1.5">
              Pharmacy <span className="text-red-500">*</span>
            </label>
            <select
              id="announcement-pharmacy"
              value={pharmacyId}
              onChange={(event) =>
                setPharmacyId(event.target.value === "" ? "" : Number(event.target.value))
              }
              className="w-full h-10 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-1 focus:ring-ring"
            >
              <option value="">Select a pharmacy…</option>
              {pharmacies.map((pharmacy) => (
                <option key={pharmacy.id} value={pharmacy.id}>
                  {pharmacy.name}
                </option>
              ))}
            </select>
          </div>
        )}

        <div>
          <label htmlFor="announcement-title" className="text-xs font-medium block mb-1.5">
            Title <span className="text-red-500">*</span>
          </label>
          <input
            id="announcement-title"
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            maxLength={120}
            placeholder="Scheduled maintenance"
            className="w-full h-10 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-1 focus:ring-ring"
          />
        </div>

        <div>
          <label htmlFor="announcement-message" className="text-xs font-medium block mb-1.5">
            Message <span className="text-red-500">*</span>
          </label>
          <textarea
            id="announcement-message"
            value={message}
            onChange={(event) => setMessage(event.target.value)}
            rows={4}
            maxLength={500}
            placeholder="What do the pharmacies need to know?"
            className="w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-1 focus:ring-ring"
          />
          <p className="mt-1 text-[10px] text-muted-foreground text-right">{message.length}/500</p>
        </div>

        <div className="flex items-center justify-between pt-2 border-t">
          <p className="text-xs text-muted-foreground inline-flex items-center gap-1.5">
            <Users size={13} />
            {audience.isPending
              ? "Counting recipients…"
              : `${recipients} pharmacy(ies) will receive this`}
          </p>
          <button
            onClick={() => void submit()}
            disabled={sending || recipients === 0}
            className="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-sm font-medium inline-flex items-center gap-2 disabled:opacity-50 hover:opacity-90 transition"
          >
            <Megaphone size={15} />
            {sending ? "Sending…" : "Send announcement"}
          </button>
        </div>

        {audience.data?.data.recipients === 0 && (
          <p role="alert" className="text-xs text-amber-600">
            No pharmacy has been approved yet, so there is nobody to notify.
          </p>
        )}
      </section>

      <p className="text-[11px] text-muted-foreground">
        Announcements appear in the recipient's notification list and unread badge. Sending is
        recorded in the admin audit log.
      </p>
    </div>
  );
}
