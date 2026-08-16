import { useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, PackageCheck, RotateCcw } from "lucide-react";
import { useState } from "react";
import { StatusBadge } from "@/components/ui-custom/StatusBadge";
import { useToast } from "@/components/ui-custom/Toast";
import { AdminApiError } from "@/lib/adminApi";
import { fetchApplications } from "@/lib/reviewApi";
import type { PharmacyApplication } from "@/lib/types";
import { ReviewDrawer } from "@/components/review/ReviewDrawer";

const PER_PAGE = 20;

function statusLabel(status: PharmacyApplication["status"]): "Pending" | "Active" | "Blocked" {
  if (status === "approved") return "Active";
  if (status === "rejected") return "Blocked";
  return "Pending";
}

export function ReviewView() {
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<PharmacyApplication | null>(null);
  const queryClient = useQueryClient();
  const { push } = useToast();

  const query = useQuery({
    queryKey: ["admin", "review", "applications", page],
    queryFn: ({ signal }) => fetchApplications(page, PER_PAGE, signal),
  });

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: ["admin", "review", "applications"] });

  if (query.isPending) {
    return (
      <div
        className="bg-card rounded-2xl border shadow-soft p-16 text-center text-sm text-muted-foreground"
        role="status"
      >
        Loading applications…
      </div>
    );
  }

  if (query.isError) {
    const message =
      query.error instanceof AdminApiError
        ? query.error.message
        : "Could not load pharmacy applications.";
    return (
      <div className="bg-card rounded-2xl border shadow-soft p-16 text-center">
        <AlertTriangle className="mx-auto text-red-500" size={28} />
        <p className="text-sm font-medium mt-3">{message}</p>
        <button
          onClick={() => query.refetch()}
          className="mt-4 inline-flex items-center gap-2 h-9 px-4 rounded-lg border text-sm hover:bg-accent transition"
        >
          <RotateCcw size={14} /> Retry
        </button>
      </div>
    );
  }

  const rows = query.data.data;

  return (
    <div className="bg-card rounded-2xl border shadow-soft overflow-hidden relative">
      <div className="p-5 flex items-center justify-between border-b">
        <div>
          <h3 className="text-base font-semibold">Pending applications</h3>
          <p className="text-xs text-muted-foreground mt-1">
            {query.data.meta.total} awaiting review
          </p>
        </div>
      </div>

      {rows.length === 0 ? (
        <EmptyState />
      ) : (
        <div className="overflow-auto scrollbar-thin">
          <table className="w-full text-sm">
            <thead className="bg-muted/40 sticky top-0 label-caption text-muted-foreground">
              <tr>
                <th className="px-4 py-3 text-left">Application</th>
                <th className="px-4 py-3 text-left">Owner</th>
                <th className="px-4 py-3 text-left">Submitted</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr
                  key={r.id}
                  onClick={() => setSelected(r)}
                  className="border-t hover:bg-emerald-50/50 dark:hover:bg-emerald-500/5 cursor-pointer transition"
                >
                  <td className="px-4 py-3">
                    <p className="font-medium">{r.name}</p>
                    <p className="text-xs text-muted-foreground">
                      #{r.id} · {r.address}
                    </p>
                  </td>
                  <td className="px-4 py-3">
                    <p>{r.owner?.name ?? "—"}</p>
                    <p className="text-xs text-muted-foreground">{r.owner?.email ?? ""}</p>
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">
                    {r.submitted_at ? new Date(r.submitted_at).toLocaleDateString() : "—"}
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge status={statusLabel(r.status)} />
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button className="text-xs text-primary font-medium hover:underline">
                      Review →
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {query.data.meta.last_page > 1 && (
        <div className="p-4 border-t flex items-center justify-between text-xs text-muted-foreground">
          <span>
            Page {query.data.meta.current_page} of {query.data.meta.last_page}
          </span>
          <div className="flex gap-2">
            <button
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="h-8 px-3 rounded-lg border disabled:opacity-40 hover:bg-accent transition"
            >
              Previous
            </button>
            <button
              disabled={page >= query.data.meta.last_page}
              onClick={() => setPage((p) => p + 1)}
              className="h-8 px-3 rounded-lg border disabled:opacity-40 hover:bg-accent transition"
            >
              Next
            </button>
          </div>
        </div>
      )}

      <ReviewDrawer
        application={selected}
        onClose={() => setSelected(null)}
        onDecided={(updated) => {
          setSelected(updated);
          invalidate();
        }}
        onToast={push}
      />
    </div>
  );
}

function EmptyState() {
  return (
    <div className="p-16 text-center">
      <div className="w-16 h-16 mx-auto rounded-2xl bg-emerald-500/10 grid place-items-center text-emerald-600">
        <PackageCheck size={26} strokeWidth={1.75} />
      </div>
      <p className="text-base font-semibold mt-4">All caught up!</p>
      <p className="text-sm text-muted-foreground mt-1">No pending verifications right now.</p>
    </div>
  );
}
