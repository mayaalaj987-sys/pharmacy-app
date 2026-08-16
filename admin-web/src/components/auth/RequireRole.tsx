import { Navigate } from "@tanstack/react-router";
import type { ReactNode } from "react";
import { useAuth } from "@/context/AuthContext";
import type { Navigation } from "@/lib/types";

interface RequireRoleProps {
  /** Capability key from the server-derived `navigation` block. Server policies remain authoritative; this only hides/redirects the UI. */
  capability: keyof Navigation;
  children: ReactNode;
}

/**
 * Route guard used inside route components (not beforeLoad) so it composes with
 * the boot-time "resolving" gate in main.tsx and never flashes privileged content.
 */
export function RequireRole({ capability, children }: RequireRoleProps) {
  const { status, navigation } = useAuth();

  if (status === "unauthenticated") {
    return <Navigate to="/login" replace />;
  }

  if (status === "authenticated" && !navigation?.[capability]) {
    return <Navigate to="/review" replace />;
  }

  return <>{children}</>;
}
