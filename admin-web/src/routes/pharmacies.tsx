import { createFileRoute } from "@tanstack/react-router";
import { RequireRole } from "@/components/auth/RequireRole";
import { AppShell } from "@/components/layout/AppShell";
import { PharmaciesView } from "@/components/pharmacies/PharmaciesView";

export const Route = createFileRoute("/pharmacies")({
  component: PharmaciesRoute,
});

function PharmaciesRoute() {
  return (
    <RequireRole capability="review_pharmacies">
      <AppShell title="Pharmacy Control">
        <PharmaciesView />
      </AppShell>
    </RequireRole>
  );
}
