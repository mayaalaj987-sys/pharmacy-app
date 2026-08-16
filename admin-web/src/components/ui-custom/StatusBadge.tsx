type S = "Active" | "Pending" | "Blocked" | "Frozen" | "Open" | "Resolved" | "Under Review";
const map: Record<S, { bg: string; text: string; dot: string }> = {
  Active: {
    bg: "bg-emerald-500/10",
    text: "text-emerald-700 dark:text-emerald-300",
    dot: "bg-emerald-500",
  },
  Pending: {
    bg: "bg-amber-500/10",
    text: "text-amber-700 dark:text-amber-300",
    dot: "bg-amber-500",
  },
  "Under Review": {
    bg: "bg-blue-500/10",
    text: "text-blue-700 dark:text-blue-300",
    dot: "bg-blue-500",
  },
  Blocked: { bg: "bg-red-500/10", text: "text-red-700 dark:text-red-300", dot: "bg-red-500" },
  Frozen: {
    bg: "bg-slate-500/10",
    text: "text-slate-700 dark:text-slate-300",
    dot: "bg-slate-500",
  },
  Open: { bg: "bg-amber-500/10", text: "text-amber-700 dark:text-amber-300", dot: "bg-amber-500" },
  Resolved: {
    bg: "bg-emerald-500/10",
    text: "text-emerald-700 dark:text-emerald-300",
    dot: "bg-emerald-500",
  },
};
export function StatusBadge({ status }: { status: S }) {
  const s = map[status];
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium ${s.bg} ${s.text}`}
    >
      <span className={`w-1.5 h-1.5 rounded-full ${s.dot}`} />
      {status}
    </span>
  );
}
