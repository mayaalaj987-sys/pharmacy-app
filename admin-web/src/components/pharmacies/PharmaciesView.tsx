import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useRef, useState } from "react";
import { Ban, RotateCcw, Star } from "lucide-react";
import { AdminApiError } from "@/lib/adminApi";
import {
  blockPharmacy,
  fetchPharmacies,
  unblockPharmacy,
  type PharmacyFilter,
} from "@/lib/pharmaciesApi";
import type { ControlledPharmacy } from "@/lib/types";
import { StatusBadge } from "@/components/ui-custom/StatusBadge";
import { useToast } from "@/components/ui-custom/Toast";

const PER_PAGE = 25;
const FILTERS: PharmacyFilter[] = ["all", "approved", "pending", "rejected", "blocked"];

/**
 * The operational register: who is trading, and suspending anyone who should
 * not be.
 *
 * Suspension is separate from the review decision — a suspended pharmacy keeps
 * its approval and gets it back untouched when restored.
 */
export function PharmaciesView() {
  const [filter, setFilter] = useState<PharmacyFilter>("all");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [pending, setPending] = useState<ControlledPharmacy | null>(null);
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["pharmacies", filter, search, page],
    queryFn: ({ signal }) => fetchPharmacies(page, PER_PAGE, filter, search, signal),
  });

  if (query.isPending) {
    return (
      <div role="status" className="text-sm text-muted-foreground">
        Loading pharmacies…
      </div>
    );
  }

  if (query.isError) {
    return (
      <div role="alert" className="text-sm text-red-600">
        {query.error instanceof AdminApiError ? query.error.message : "Unable to load pharmacies."}
        <button
          onClick={() => void query.refetch()}
          className="ml-3 h-8 px-3 rounded-lg border text-foreground hover:bg-accent transition"
        >
          Retry
        </button>
      </div>
    );
  }

  const rows = query.data.data;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h2 className="text-lg font-semibold">Pharmacy Control</h2>
          <p className="text-xs text-muted-foreground mt-0.5">
            {query.data.meta.total} registered · {query.data.meta.blocked_total} suspended
          </p>
        </div>
        <div className="flex items-center gap-1 bg-card border rounded-xl p-1 shadow-soft">
          {FILTERS.map((value) => (
            <button
              key={value}
              onClick={() => {
                setFilter(value);
                setPage(1);
              }}
              className={`h-8 px-3 rounded-lg text-xs font-medium capitalize transition ${
                filter === value
                  ? "bg-primary text-primary-foreground"
                  : "text-muted-foreground hover:text-foreground"
              }`}
            >
              {value}
            </button>
          ))}
        </div>
      </div>

      <input
        value={search}
        onChange={(event) => {
          setSearch(event.target.value);
          setPage(1);
        }}
        placeholder="Search by name or address…"
        aria-label="Search pharmacies"
        className="w-full h-10 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-1 focus:ring-ring"
      />

      <section className="bg-card rounded-2xl border shadow-soft overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-[10px] uppercase tracking-wide text-muted-foreground">
                <th className="text-left font-medium px-5 py-3">Pharmacy</th>
                <th className="text-left font-medium px-5 py-3">Owner</th>
                <th className="text-left font-medium px-5 py-3">Branches</th>
                <th className="text-left font-medium px-5 py-3">App feedback</th>
                <th className="text-left font-medium px-5 py-3">Status</th>
                <th className="text-right font-medium px-5 py-3">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {rows.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center text-xs text-muted-foreground">
                    No pharmacy matches this view.
                  </td>
                </tr>
              )}
              {rows.map((pharmacy) => (
                <tr key={pharmacy.id} className="hover:bg-accent/40 transition">
                  <td className="px-5 py-3">
                    <p className="font-medium">{pharmacy.name}</p>
                    <p className="text-[11px] text-muted-foreground">{pharmacy.address}</p>
                  </td>
                  <td className="px-5 py-3">
                    <p>{pharmacy.owner.name ?? "—"}</p>
                    <p className="text-[11px] text-muted-foreground">
                      {pharmacy.owner.email ?? ""}
                    </p>
                  </td>
                  <td className="px-5 py-3 tabular-nums">{pharmacy.owner.branches}</td>
                  <td className="px-5 py-3">
                    <Rating
                      value={pharmacy.owner.app_rating}
                      note={pharmacy.owner.app_rating_note}
                      ratedAt={pharmacy.owner.app_rated_at}
                    />
                  </td>
                  <td className="px-5 py-3">
                    <StatusBadge
                      status={
                        pharmacy.is_blocked
                          ? "Blocked"
                          : pharmacy.status === "approved"
                            ? "Active"
                            : pharmacy.status === "pending"
                              ? "Pending"
                              : "Blocked"
                      }
                    />
                    {pharmacy.is_blocked && pharmacy.blocked_reason && (
                      <p className="text-[11px] text-muted-foreground mt-1 max-w-56">
                        {pharmacy.blocked_reason}
                      </p>
                    )}
                  </td>
                  <td className="px-5 py-3 text-right">
                    {pharmacy.is_blocked ? (
                      <RestoreButton
                        pharmacy={pharmacy}
                        onDone={() =>
                          void queryClient.invalidateQueries({ queryKey: ["pharmacies"] })
                        }
                      />
                    ) : pharmacy.status === "approved" ? (
                      <button
                        onClick={() => setPending(pharmacy)}
                        className="h-8 px-3 rounded-lg border text-xs inline-flex items-center gap-1.5 hover:bg-accent transition"
                      >
                        <Ban size={13} /> Suspend
                      </button>
                    ) : (
                      <span className="text-[11px] text-muted-foreground">Not trading</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {query.data.meta.last_page > 1 && (
          <div className="flex items-center justify-between px-5 py-3 border-t text-xs">
            <button
              disabled={page <= 1}
              onClick={() => setPage((p) => p - 1)}
              className="h-8 px-3 rounded-lg border disabled:opacity-40 hover:bg-accent transition"
            >
              Previous
            </button>
            <span className="text-muted-foreground">
              {query.data.meta.current_page} / {query.data.meta.last_page}
            </span>
            <button
              disabled={page >= query.data.meta.last_page}
              onClick={() => setPage((p) => p + 1)}
              className="h-8 px-3 rounded-lg border disabled:opacity-40 hover:bg-accent transition"
            >
              Next
            </button>
          </div>
        )}
      </section>

      {pending && (
        <SuspendDialog
          pharmacy={pending}
          onClose={() => setPending(null)}
          onDone={() => {
            setPending(null);
            void queryClient.invalidateQueries({ queryKey: ["pharmacies"] });
          }}
        />
      )}
    </div>
  );
}

/**
 * The owner's verdict on the app, and what they said about it.
 *
 * The note is the point. A star tells an admin somebody was unhappy and
 * nothing else; what they wrote is the only part anyone can act on, so it is
 * shown in full rather than hidden behind a hover — a tooltip is not somewhere
 * feedback gets read.
 */
function Rating({
  value,
  note,
  ratedAt,
}: {
  value: number | null;
  note: string | null;
  ratedAt: string | null;
}) {
  if (value === null) {
    return <span className="text-[11px] text-muted-foreground">Not rated</span>;
  }

  return (
    <div className="max-w-[22rem] space-y-1">
      <span
        className="inline-flex items-center gap-1"
        title={ratedAt ? `Rated on ${ratedAt}` : undefined}
      >
        <Star size={13} className="fill-amber-400 text-amber-400" />
        <span className="tabular-nums">{value}/5</span>
      </span>
      {note ? (
        <p className="text-[11px] leading-snug text-muted-foreground whitespace-pre-line">
          “{note}”
        </p>
      ) : (
        <p className="text-[11px] text-muted-foreground/70">No comment left</p>
      )}
    </div>
  );
}

function RestoreButton({ pharmacy, onDone }: { pharmacy: ControlledPharmacy; onDone: () => void }) {
  const [busy, setBusy] = useState(false);
  const inFlight = useRef(false);
  const { push } = useToast();

  async function restore() {
    if (inFlight.current) return;
    inFlight.current = true;
    setBusy(true);
    try {
      await unblockPharmacy(pharmacy.id);
      push({
        title: "Pharmacy restored",
        description: `${pharmacy.name} can trade again.`,
        variant: "success",
      });
    } catch (error) {
      push({
        title: "Not restored",
        description: error instanceof AdminApiError ? error.message : "Unable to restore.",
        variant: "error",
      });
    } finally {
      inFlight.current = false;
      setBusy(false);
      onDone();
    }
  }

  return (
    <button
      onClick={() => void restore()}
      disabled={busy}
      className="h-8 px-3 rounded-lg border text-xs inline-flex items-center gap-1.5 disabled:opacity-50 hover:bg-accent transition"
    >
      <RotateCcw size={13} /> {busy ? "Restoring…" : "Restore"}
    </button>
  );
}

function SuspendDialog({
  pharmacy,
  onClose,
  onDone,
}: {
  pharmacy: ControlledPharmacy;
  onClose: () => void;
  onDone: () => void;
}) {
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const inFlight = useRef(false);
  const { push } = useToast();

  async function submit() {
    if (reason.trim().length < 5) {
      push({
        title: "Reason required",
        description: "Explain why in at least 5 characters.",
        variant: "error",
      });
      return;
    }
    if (inFlight.current) return;
    inFlight.current = true;
    setBusy(true);

    try {
      await blockPharmacy(pharmacy.id, reason.trim());
      push({
        title: "Pharmacy suspended",
        description: `${pharmacy.name} can no longer trade.`,
        variant: "success",
      });
      onDone();
    } catch (error) {
      push({
        title: "Not suspended",
        description: error instanceof AdminApiError ? error.message : "Unable to suspend.",
        variant: "error",
      });
      onDone();
    } finally {
      inFlight.current = false;
      setBusy(false);
    }
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={`Suspend ${pharmacy.name}`}
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
    >
      <div className="w-full max-w-md bg-card rounded-2xl border shadow-lg p-6 space-y-4">
        <div>
          <h3 className="text-base font-semibold">Suspend {pharmacy.name}?</h3>
          <p className="text-xs text-muted-foreground mt-1">
            Selling, stock and reports stop immediately for the owner and their employees. The
            approval itself is kept and returns when you restore the pharmacy.
          </p>
        </div>

        <div>
          <label htmlFor="suspend-reason" className="text-xs font-medium block mb-1.5">
            Reason <span className="text-red-500">*</span>
          </label>
          <textarea
            id="suspend-reason"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            rows={3}
            maxLength={500}
            placeholder="Why is this pharmacy being suspended?"
            className="w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-1 focus:ring-ring"
          />
        </div>

        <div className="flex justify-end gap-2">
          <button
            onClick={onClose}
            className="h-9 px-4 rounded-lg border text-sm hover:bg-accent transition"
          >
            Cancel
          </button>
          <button
            onClick={() => void submit()}
            disabled={busy}
            className="h-9 px-4 rounded-lg bg-red-600 text-white text-sm font-medium disabled:opacity-50 hover:opacity-90 transition"
          >
            {busy ? "Suspending…" : "Suspend"}
          </button>
        </div>
      </div>
    </div>
  );
}
