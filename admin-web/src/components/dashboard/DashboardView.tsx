import { useQuery } from "@tanstack/react-query";
import { motion } from "framer-motion";
import { Building2, Clock, ShieldCheck, TrendingDown, TrendingUp } from "lucide-react";
import { fetchActivity, fetchOverview } from "@/lib/dashboardApi";
import { fetchOnboarding } from "@/lib/analyticsApi";
import type {
  ActivityEntry,
  DashboardOverview,
  MetricChange,
  OnboardingAnalytics,
} from "@/lib/types";
import { StatusBadge } from "@/components/ui-custom/StatusBadge";

/**
 * Console overview.
 *
 * Every number is live. The month-over-month movement is reconstructed from
 * `created_at` and `reviewed_at`, so it is a real comparison rather than an
 * estimate — and it is simply omitted where the platform has no history.
 *
 * "Recent activity" is the administrator audit log, which is append-only at the
 * database level: what appears here cannot later be edited or removed.
 */
export function DashboardView() {
  const overview = useQuery({
    queryKey: ["dashboard", "overview"],
    queryFn: ({ signal }) => fetchOverview(signal),
  });
  const activity = useQuery({
    queryKey: ["dashboard", "activity"],
    queryFn: ({ signal }) => fetchActivity(12, signal),
  });
  const onboarding = useQuery({
    queryKey: ["analytics", "onboarding"],
    queryFn: ({ signal }) => fetchOnboarding(signal),
  });

  if (overview.isError) {
    return (
      <div role="alert" className="text-sm text-red-600">
        Unable to load the dashboard.
        <button
          onClick={() => void overview.refetch()}
          className="ml-3 h-8 px-3 rounded-lg border text-foreground hover:bg-accent transition"
        >
          Retry
        </button>
      </div>
    );
  }

  if (overview.isPending) {
    return (
      <div role="status" className="grid gap-4 md:grid-cols-3">
        {[0, 1, 2].map((i) => (
          <div key={i} className="h-28 bg-card rounded-2xl border shadow-soft animate-pulse" />
        ))}
      </div>
    );
  }

  const data = overview.data.data;

  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-semibold">Dashboard Overview</h2>
        <p className="text-xs text-muted-foreground mt-0.5">Compared with {data.compared_to}</p>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Kpi
          icon={Building2}
          label="Registered pharmacies"
          value={data.totals.registered.value}
          change={data.totals.registered.change}
        />
        <Kpi
          icon={ShieldCheck}
          label="Approved pharmacies"
          value={data.totals.approved.value}
          change={data.totals.approved.change}
        />
        <Kpi
          icon={Clock}
          label="Awaiting verification"
          value={data.totals.pending.value}
          change={data.totals.pending.change}
          lowerIsBetter
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-[1fr_minmax(0,20rem)]">
        <OnboardingChart data={onboarding.data?.data} />
        <PharmacyList pharmacies={data.pharmacies} />
      </div>

      <ActivityFeed
        entries={activity.data?.data}
        loading={activity.isPending}
        failed={activity.isError}
      />
    </div>
  );
}

function Kpi({
  icon: Icon,
  label,
  value,
  change,
  lowerIsBetter = false,
}: {
  icon: typeof Building2;
  label: string;
  value: number;
  change: MetricChange;
  lowerIsBetter?: boolean;
}) {
  const good = lowerIsBetter ? change.delta <= 0 : change.delta >= 0;
  const Arrow = change.delta >= 0 ? TrendingUp : TrendingDown;

  return (
    <section className="bg-card rounded-2xl border shadow-soft p-5">
      <div className="flex items-start justify-between">
        <div className="w-9 h-9 rounded-lg bg-emerald-500/10 text-emerald-600 grid place-items-center">
          <Icon size={17} strokeWidth={1.75} />
        </div>
      </div>
      <p className="mt-3 text-[10px] uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="text-2xl font-semibold tabular-nums">{value}</p>
      <p
        className={`mt-1 text-xs inline-flex items-center gap-1 ${
          change.delta === 0
            ? "text-muted-foreground"
            : good
              ? "text-emerald-600"
              : "text-amber-600"
        }`}
      >
        {change.delta !== 0 && <Arrow size={13} />}
        {change.delta === 0 ? "No change" : `${change.delta > 0 ? "+" : ""}${change.delta}`}
        {/* Omitted when the baseline was zero rather than shown as +100%. */}
        {change.percent !== null && change.delta !== 0 && (
          <span className="tabular-nums">
            ({change.percent > 0 ? "+" : ""}
            {change.percent}%)
          </span>
        )}
        <span className="text-muted-foreground">vs last month</span>
      </p>
    </section>
  );
}

function OnboardingChart({ data }: { data?: OnboardingAnalytics }) {
  if (!data) {
    return <section className="bg-card rounded-2xl border shadow-soft p-6 h-64 animate-pulse" />;
  }

  const peak = Math.max(1, ...data.points.map((p) => p.registrations));

  return (
    <section className="bg-card rounded-2xl border shadow-soft p-6">
      <h3 className="text-base font-semibold">Pharmacist Onboarding</h3>
      <p className="text-xs text-muted-foreground mt-1">
        Monthly trend over the last 12 months — {data.total} in total
      </p>

      <div className="mt-6 flex items-end gap-1.5 h-36" aria-hidden="true">
        {data.points.map((point) => (
          <div
            key={point.month}
            className="flex-1 flex flex-col justify-end h-full"
            title={`${point.label}: ${point.registrations}`}
          >
            <motion.div
              initial={{ height: 0 }}
              animate={{ height: `${(point.registrations / peak) * 100}%` }}
              transition={{ duration: 0.7, ease: "easeOut" }}
              className="w-full rounded-t bg-gradient-to-t from-emerald-600 to-emerald-400 min-h-[2px]"
            />
          </div>
        ))}
      </div>
      <div className="flex justify-between mt-2 text-[10px] text-muted-foreground">
        <span>{data.points[0]?.label}</span>
        <span>{data.points[data.points.length - 1]?.label}</span>
      </div>
      <ul className="sr-only">
        {data.points.map((point) => (
          <li key={point.month}>
            {point.label}: {point.registrations} registrations
          </li>
        ))}
      </ul>
    </section>
  );
}

function PharmacyList({ pharmacies }: { pharmacies: DashboardOverview["pharmacies"] }) {
  return (
    <section className="bg-card rounded-2xl border shadow-soft overflow-hidden">
      <div className="flex items-center justify-between px-5 py-4 border-b">
        <h3 className="text-base font-semibold">Pharmacies</h3>
        <span className="text-[11px] text-muted-foreground">{pharmacies.length} shown</span>
      </div>
      {pharmacies.length === 0 ? (
        <p className="p-8 text-center text-xs text-muted-foreground">
          No pharmacy has registered yet.
        </p>
      ) : (
        <ul className="divide-y max-h-72 overflow-y-auto">
          {pharmacies.map((pharmacy) => (
            <li key={pharmacy.id} className="px-5 py-3 flex items-center justify-between gap-3">
              <div className="min-w-0">
                <p className="text-sm font-medium truncate">{pharmacy.name}</p>
                <p className="text-[11px] text-muted-foreground truncate">
                  {pharmacy.owner ?? "Unknown owner"} · {pharmacy.address}
                </p>
              </div>
              <StatusBadge
                status={
                  pharmacy.status === "approved"
                    ? "Active"
                    : pharmacy.status === "pending"
                      ? "Pending"
                      : "Blocked"
                }
              />
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function ActivityFeed({
  entries,
  loading,
  failed,
}: {
  entries?: ActivityEntry[];
  loading: boolean;
  failed: boolean;
}) {
  return (
    <section className="bg-card rounded-2xl border shadow-soft p-6">
      <h3 className="text-base font-semibold">Recent Activity</h3>
      <p className="text-xs text-muted-foreground mt-1">
        From the administrator audit log, which cannot be edited or deleted.
      </p>

      {loading && <p className="mt-6 text-xs text-muted-foreground">Loading activity…</p>}
      {failed && <p className="mt-6 text-xs text-red-600">Unable to load activity.</p>}

      {entries && entries.length === 0 && (
        <p className="mt-6 text-xs text-muted-foreground">
          Nothing has happened yet. Approvals, replies and account changes appear here.
        </p>
      )}

      {entries && entries.length > 0 && (
        <ol className="mt-5 space-y-4">
          {entries.map((entry) => (
            <li key={entry.id} className="flex gap-3">
              <span
                className={`mt-1.5 w-2 h-2 rounded-full shrink-0 ${
                  entry.outcome === "success" ? "bg-emerald-500" : "bg-red-500"
                }`}
              />
              <div className="min-w-0">
                <p className="text-sm">
                  <span className="font-medium">{entry.actor}</span>{" "}
                  <span className="text-muted-foreground">{entry.label}</span>
                  {entry.target && <span className="text-muted-foreground"> · {entry.target}</span>}
                </p>
                <p className="text-[11px] text-muted-foreground">
                  {entry.logged_at ? new Date(entry.logged_at).toLocaleString() : "—"}
                  {entry.reason ? ` · ${entry.reason}` : ""}
                </p>
              </div>
            </li>
          ))}
        </ol>
      )}
    </section>
  );
}
