import { createFileRoute } from "@tanstack/react-router";
import { RequireRole } from "@/components/auth/RequireRole";
import { AppShell } from "@/components/layout/AppShell";
import { DashboardView } from "@/components/dashboard/DashboardView";

export const Route = createFileRoute("/dashboard")({
  component: DashboardRoute,
});

function DashboardRoute() {
  return (
    <RequireRole capability="review_pharmacies">
      <AppShell title="Overview">
        <DashboardView />
      </AppShell>
    </RequireRole>
  );
}
