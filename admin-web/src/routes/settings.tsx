import { createFileRoute } from "@tanstack/react-router";
import { RequireRole } from "@/components/auth/RequireRole";
import { AppShell } from "@/components/layout/AppShell";
import { SettingsView } from "@/components/settings/SettingsView";

export const Route = createFileRoute("/settings")({
  component: SettingsRoute,
});

function SettingsRoute() {
  return (
    <RequireRole capability="review_pharmacies">
      <AppShell title="Settings">
        <SettingsView />
      </AppShell>
    </RequireRole>
  );
}
