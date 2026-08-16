import { createFileRoute } from "@tanstack/react-router";
import { RequireRole } from "@/components/auth/RequireRole";
import { AppShell } from "@/components/layout/AppShell";
import { ReviewView } from "@/components/review/ReviewView";

export const Route = createFileRoute("/review")({
  component: ReviewRoute,
});

function ReviewRoute() {
  return (
    <RequireRole capability="review_pharmacies">
      <AppShell title="Onboarding & Verification">
        <ReviewView />
      </AppShell>
    </RequireRole>
  );
}
