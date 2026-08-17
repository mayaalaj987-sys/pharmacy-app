import { useQuery } from "@tanstack/react-query";
import { motion } from "framer-motion";
import { Briefcase, TrendingUp, UserSearch } from "lucide-react";
import { fetchJobMarket, fetchOnboarding, fetchPharmacyFleet } from "@/lib/analyticsApi";
import type { JobMarketAnalytics, OnboardingAnalytics, PharmacyFleetAnalytics } from "@/lib/types";

/**
 * Platform analytics.
 *
 * Every figure is served live by `/api/admin/analytics/*`. There are
 * deliberately no period-over-period deltas: the platform keeps no historical
 * snapshots, so a "+12%" here could only ever be invented.
 */
export function ReportsView() {
  const fleet = useQuery({
    queryKey: ["analytics", "pharmacies"],
    queryFn: ({ signal }) => fetchPharmacyFleet(signal),
  });
  const jobs = useQuery({
    queryKey: ["analytics", "job-market"],
    queryFn: ({ signal }) => fetchJobMarket(signal),
  });
  const onboarding = useQuery({
    queryKey: ["analytics", "onboarding"],
    queryFn: ({ signal }) => fetchOnboarding(signal),
  });

  const failed = fleet.isError || jobs.isError || onboarding.isError;
  const loading = fleet.isPending || jobs.isPending || onboarding.isPending;

  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-semibold">Analytics Center</h2>
        <p className="text-xs text-muted-foreground mt-0.5">
          Live platform-wide statistics across every registered pharmacy.
        </p>
      </div>

      {failed && (
        <div
          role="alert"
          className="bg-card rounded-2xl border border-red-200 p-6 text-sm text-red-600"
        >
          Unable to load analytics. Check that the API is running, then retry.
          <button
            onClick={() => {
              void fleet.refetch();
              void jobs.refetch();
              void onboarding.refetch();
            }}
            className="ml-3 h-8 px-3 rounded-lg border text-foreground hover:bg-accent transition"
          >
            Retry
          </button>
        </div>
      )}

      {loading && !failed && <SkeletonGrid />}

      {!loading && !failed && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <FleetCard data={fleet.data!.data} />
          <OnboardingCard data={onboarding.data!.data} />
          <JobMarketCard data={jobs.data!.data} />
        </div>
      )}
    </div>
  );
}

function FleetCard({ data }: { data: PharmacyFleetAnalytics }) {
  const { distribution: d } = data;
  const operating = data.owners_operating;

  return (
    <section className="bg-card rounded-2xl border shadow-soft p-6">
      <h3 className="text-base font-semibold">Pharmacy Fleet</h3>
      <p className="text-xs text-muted-foreground mt-1">Single vs multi-branch distribution</p>

      {operating === 0 ? (
        <Empty>No approved pharmacies yet.</Empty>
      ) : (
        <div className="mt-6 flex items-center gap-6">
          <Donut single={d.single_branch_owners} total={operating} caption="Owners" />
          <div className="flex-1 space-y-3">
            <Legend
              color="oklch(0.58 0.14 160)"
              label="Single-branch"
              value={d.single_branch_owners}
              pct={d.single_branch_percentage}
            />
            <Legend
              color="oklch(0.75 0.15 80)"
              label="Multi-branch"
              value={d.multi_branch_owners}
              pct={d.multi_branch_percentage}
            />
          </div>
        </div>
      )}

      <dl className="mt-6 pt-4 border-t grid grid-cols-3 gap-2 text-center">
        <Stat label="Approved" value={data.branches.approved} />
        <Stat label="Pending" value={data.branches.pending} />
        <Stat label="Rejected" value={data.branches.rejected} />
      </dl>
    </section>
  );
}

function OnboardingCard({ data }: { data: OnboardingAnalytics }) {
  const peak = Math.max(1, ...data.points.map((p) => p.registrations));

  return (
    <section className="bg-card rounded-2xl border shadow-soft p-6">
      <h3 className="text-base font-semibold">Onboarding Trend</h3>
      <p className="text-xs text-muted-foreground mt-1">
        Pharmacist sign-ups over the last 12 months
      </p>

      <p className="mt-4 text-2xl font-semibold tabular-nums">{data.total}</p>
      <p className="text-[10px] uppercase tracking-wide text-muted-foreground">
        Total registrations
      </p>

      <div className="mt-5 flex items-end gap-1 h-28" aria-hidden="true">
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

      {/* The bars are decorative; this keeps the data readable to everyone. */}
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

function JobMarketCard({ data }: { data: JobMarketAnalytics }) {
  return (
    <section className="bg-card rounded-2xl border shadow-soft p-6">
      <h3 className="text-base font-semibold">Job Market Vitality</h3>
      <p className="text-xs text-muted-foreground mt-1">Recruitment across approved pharmacies</p>

      <div className="mt-4 divide-y">
        <Metric
          icon={Briefcase}
          label="Open positions"
          value={String(data.open_positions)}
          note={`${data.capacity.filled_slots} of ${data.capacity.total_slots} slots filled`}
        />
        <Metric
          icon={UserSearch}
          label="Active job seekers"
          value={String(data.active_seekers)}
          note={`${data.total_applicants} applied in total`}
        />
        <Metric
          icon={TrendingUp}
          label="Hire rate"
          value={`${data.hire_rate_percentage}%`}
          note={`${data.hired} hired, ${data.rejected} rejected`}
        />
      </div>

      <p className="mt-4 pt-3 border-t text-[10px] text-muted-foreground">
        Each approved pharmacy may employ up to {data.capacity.employees_per_pharmacy} people.
      </p>
    </section>
  );
}

function Donut({ single, total, caption }: { single: number; total: number; caption: string }) {
  const radius = 52;
  const circumference = 2 * Math.PI * radius;
  const filled = total > 0 ? (single / total) * circumference : 0;

  return (
    <div className="relative w-32 h-32 shrink-0">
      <svg viewBox="0 0 120 120" className="w-full h-full -rotate-90">
        <circle
          cx="60"
          cy="60"
          r={radius}
          fill="none"
          stroke="oklch(0.75 0.15 80)"
          strokeWidth="14"
        />
        <motion.circle
          cx="60"
          cy="60"
          r={radius}
          fill="none"
          stroke="oklch(0.58 0.14 160)"
          strokeWidth="14"
          strokeLinecap="round"
          initial={{ strokeDasharray: `0 ${circumference}` }}
          animate={{ strokeDasharray: `${filled} ${circumference}` }}
          transition={{ duration: 0.9 }}
        />
      </svg>
      <div className="absolute inset-0 grid place-items-center text-center">
        <div>
          <p className="text-2xl font-semibold tabular-nums">{total}</p>
          <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{caption}</p>
        </div>
      </div>
    </div>
  );
}

function Legend({
  color,
  label,
  value,
  pct,
}: {
  color: string;
  label: string;
  value: number;
  pct: number;
}) {
  return (
    <div className="flex items-center gap-2 text-xs">
      <span className="w-2.5 h-2.5 rounded-sm shrink-0" style={{ background: color }} />
      <span className="flex-1">{label}</span>
      <span className="tabular-nums font-medium">{value}</span>
      <span className="text-muted-foreground tabular-nums w-11 text-right">{pct}%</span>
    </div>
  );
}

function Metric({
  icon: Icon,
  label,
  value,
  note,
}: {
  icon: typeof Briefcase;
  label: string;
  value: string;
  note: string;
}) {
  return (
    <div className="py-3 flex items-center gap-3">
      <div className="w-9 h-9 rounded-lg bg-emerald-500/10 text-emerald-600 grid place-items-center shrink-0">
        <Icon size={16} strokeWidth={1.75} />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-lg font-semibold tabular-nums leading-tight">{value}</p>
        <p className="text-[10px] text-muted-foreground truncate">{note}</p>
      </div>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div>
      <dt className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</dt>
      <dd className="text-base font-semibold tabular-nums">{value}</dd>
    </div>
  );
}

function Empty({ children }: { children: React.ReactNode }) {
  return <p className="mt-8 mb-4 text-center text-xs text-muted-foreground">{children}</p>;
}

function SkeletonGrid() {
  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4" aria-busy="true">
      {[0, 1, 2].map((i) => (
        <div key={i} className="bg-card rounded-2xl border shadow-soft p-6 h-72 animate-pulse">
          <div className="h-4 w-32 bg-muted rounded" />
          <div className="mt-3 h-3 w-44 bg-muted rounded" />
          <div className="mt-8 h-28 bg-muted rounded-xl" />
        </div>
      ))}
    </div>
  );
}
