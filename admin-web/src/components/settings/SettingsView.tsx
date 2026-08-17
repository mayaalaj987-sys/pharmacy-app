import { Moon, Sun, Timer } from "lucide-react";
import { useAuth } from "@/context/AuthContext";
import { useUi } from "@/context/UiContext";

/**
 * Console preferences.
 *
 * Only two settings appear because only two exist. The prototype this console
 * was built from also listed "Two-factor authentication: Enabled" — nothing
 * behind it was ever implemented, and showing it would claim a protection the
 * platform does not have.
 */
export function SettingsView() {
  const { theme, toggleTheme } = useUi();
  const { admin, sessionLifetimeMinutes } = useAuth();

  return (
    <div className="space-y-4 max-w-2xl">
      <div>
        <h2 className="text-lg font-semibold">Settings</h2>
        <p className="text-xs text-muted-foreground mt-0.5">
          Console-wide defaults for your admin session.
        </p>
      </div>

      <section className="bg-card rounded-2xl border shadow-soft divide-y">
        <Row
          icon={theme === "light" ? Moon : Sun}
          title="Appearance"
          detail={`Currently using the ${theme} theme.`}
        >
          <button
            onClick={toggleTheme}
            className="h-9 px-4 rounded-lg border text-sm hover:bg-accent transition"
          >
            Switch to {theme === "light" ? "dark" : "light"}
          </button>
        </Row>

        <Row
          icon={Timer}
          title="Session timeout"
          detail="Automatic sign-out after inactivity. Set on the server; the console only reports it."
        >
          <span className="text-sm tabular-nums text-muted-foreground">
            {sessionLifetimeMinutes === null ? "—" : `${sessionLifetimeMinutes} minutes`}
          </span>
        </Row>
      </section>

      <section className="bg-card rounded-2xl border shadow-soft p-6">
        <h3 className="text-base font-semibold">Your account</h3>
        <dl className="mt-4 space-y-3 text-sm">
          <Field label="Name" value={admin?.name ?? "—"} />
          <Field label="Email" value={admin?.email ?? "—"} />
          <Field
            label="Role"
            value={admin?.role === "super_admin" ? "Super admin" : "Pharmacy reviewer"}
          />
        </dl>
        <p className="mt-5 pt-4 border-t text-[11px] text-muted-foreground">
          Passwords are changed with the <code>admin:reset-password</code> console command, which
          also signs out every open session for that account.
        </p>
      </section>
    </div>
  );
}

function Row({
  icon: Icon,
  title,
  detail,
  children,
}: {
  icon: typeof Timer;
  title: string;
  detail: string;
  children: React.ReactNode;
}) {
  return (
    <div className="p-6 flex items-center justify-between gap-6">
      <div className="flex gap-3 min-w-0">
        <span className="w-9 h-9 shrink-0 rounded-lg bg-emerald-500/10 text-emerald-600 grid place-items-center">
          <Icon size={16} strokeWidth={1.75} />
        </span>
        <div className="min-w-0">
          <p className="text-sm font-medium">{title}</p>
          <p className="text-xs text-muted-foreground">{detail}</p>
        </div>
      </div>
      <div className="shrink-0">{children}</div>
    </div>
  );
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-4">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium text-right truncate">{value}</dd>
    </div>
  );
}
