import { createFileRoute } from "@tanstack/react-router";
import { RequireRole } from "@/components/auth/RequireRole";
import { AppShell } from "@/components/layout/AppShell";
import { AnnouncementsView } from "@/components/announcements/AnnouncementsView";

export const Route = createFileRoute("/announcements")({
  component: AnnouncementsRoute,
});

function AnnouncementsRoute() {
  return (
    <RequireRole capability="review_pharmacies">
      <AppShell title="Announcements">
        <AnnouncementsView />
      </AppShell>
    </RequireRole>
  );
}
