import { createFileRoute } from "@tanstack/react-router";
import { RequireRole } from "@/components/auth/RequireRole";
import { AppShell } from "@/components/layout/AppShell";
import { ReportsView } from "@/components/reports/ReportsView";

export const Route = createFileRoute("/reports")({
  component: ReportsRoute,
});

function ReportsRoute() {
  return (
    <RequireRole capability="review_pharmacies">
      <AppShell title="Analytics">
        <ReportsView />
      </AppShell>
    </RequireRole>
  );
}
