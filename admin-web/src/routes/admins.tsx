import { createFileRoute } from "@tanstack/react-router";
import { RequireRole } from "@/components/auth/RequireRole";
import { AppShell } from "@/components/layout/AppShell";
import { AdminsView } from "@/components/admins/AdminsView";

export const Route = createFileRoute("/admins")({
  component: AdminsRoute,
});

function AdminsRoute() {
  return (
    <RequireRole capability="manage_admins">
      <AppShell title="Administrator Management">
        <AdminsView />
      </AppShell>
    </RequireRole>
  );
}
