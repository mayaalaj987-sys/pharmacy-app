import { createFileRoute } from "@tanstack/react-router";
import { RequireRole } from "@/components/auth/RequireRole";
import { AppShell } from "@/components/layout/AppShell";
import { TicketsView } from "@/components/tickets/TicketsView";

export const Route = createFileRoute("/tickets")({
  component: TicketsRoute,
});

function TicketsRoute() {
  return (
    <RequireRole capability="review_pharmacies">
      <AppShell title="Support">
        <TicketsView />
      </AppShell>
    </RequireRole>
  );
}
