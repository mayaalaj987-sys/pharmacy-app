import { describe, expect, it, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { createRootRoute, createRoute, createRouter, RouterProvider } from "@tanstack/react-router";
import { AuthProvider } from "@/context/AuthContext";
import { RequireRole } from "@/components/auth/RequireRole";
import { mockReviewer, mockSuperAdmin, setSessionAdmin } from "@/test/handlers";

function buildTestRouter() {
  const rootRoute = createRootRoute();
  const loginRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/login",
    component: () => <p>Login page</p>,
  });
  const reviewRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/review",
    component: () => (
      <RequireRole capability="review_pharmacies">
        <p>Review page</p>
      </RequireRole>
    ),
  });
  const adminsRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/admins",
    component: () => (
      <RequireRole capability="manage_admins">
        <p>Admins page</p>
      </RequireRole>
    ),
  });
  const routeTree = rootRoute.addChildren([loginRoute, reviewRoute, adminsRoute]);
  return createRouter({ routeTree });
}

describe("RequireRole", () => {
  beforeEach(() => setSessionAdmin(null));

  it("redirects an unauthenticated visitor away from a protected route to /login", async () => {
    const router = buildTestRouter();
    await router.navigate({ to: "/admins" });
    render(
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>,
    );
    await waitFor(() => expect(screen.getByText("Login page")).toBeInTheDocument());
  });

  it("blocks direct navigation to /admins for a pharmacy_reviewer and sends them to /review", async () => {
    setSessionAdmin(mockReviewer);
    const router = buildTestRouter();
    await router.navigate({ to: "/admins" });
    render(
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>,
    );
    await waitFor(() => expect(screen.getByText("Review page")).toBeInTheDocument());
    expect(screen.queryByText("Admins page")).not.toBeInTheDocument();
  });

  it("allows a super_admin to reach /admins directly", async () => {
    setSessionAdmin(mockSuperAdmin);
    const router = buildTestRouter();
    await router.navigate({ to: "/admins" });
    render(
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>,
    );
    await waitFor(() => expect(screen.getByText("Admins page")).toBeInTheDocument());
  });
});
