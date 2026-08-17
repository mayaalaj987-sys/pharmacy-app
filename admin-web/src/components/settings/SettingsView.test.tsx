import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { SettingsView } from "@/components/settings/SettingsView";

const toggleTheme = vi.fn();

vi.mock("@/context/UiContext", () => ({
  useUi: () => ({ theme: "light", toggleTheme, sidebarCollapsed: false, toggleSidebar: vi.fn() }),
}));

vi.mock("@/context/AuthContext", () => ({
  useAuth: () => ({
    admin: {
      id: 2,
      name: "Aya",
      email: "aya@example.test",
      role: "super_admin",
      is_active: true,
      last_login_at: null,
    },
    navigation: { review_pharmacies: true, manage_admins: true },
    sessionLifetimeMinutes: 120,
    status: "authenticated",
    logout: vi.fn(),
  }),
}));

describe("SettingsView", () => {
  it("reports the session timeout the server actually enforces", () => {
    render(<SettingsView />);

    // 120 comes from config, not a number typed into the console.
    expect(screen.getByText("120 minutes")).toBeInTheDocument();
    expect(screen.getByText(/set on the server/i)).toBeInTheDocument();
  });

  it("toggles the theme", async () => {
    render(<SettingsView />);

    await userEvent.click(screen.getByRole("button", { name: /switch to dark/i }));
    expect(toggleTheme).toHaveBeenCalled();
  });

  it("shows the signed-in administrator", () => {
    render(<SettingsView />);

    expect(screen.getByText("Aya")).toBeInTheDocument();
    expect(screen.getByText("aya@example.test")).toBeInTheDocument();
    expect(screen.getByText("Super admin")).toBeInTheDocument();
  });

  it("claims no protection the platform does not have", () => {
    render(<SettingsView />);

    // The prototype listed "Two-factor authentication: Enabled" with nothing
    // behind it. Showing that would be a lie about the platform's security.
    expect(screen.queryByText(/two-factor/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/^Enabled$/)).not.toBeInTheDocument();
  });
});
