import { motion } from "framer-motion";
import {
  ShieldCheck,
  Users,
  BarChart3,
  LayoutDashboard,
  Building2,
  LifeBuoy,
  Megaphone,
  ChevronsLeft,
  LogOut,
} from "lucide-react";
import { Link, useRouterState } from "@tanstack/react-router";
import logo from "@/assets/logo.jpg";
import { useAuth } from "@/context/AuthContext";
import { useUi } from "@/context/UiContext";

export function Sidebar() {
  const { navigation, logout } = useAuth();
  const { sidebarCollapsed, toggleSidebar } = useUi();
  const pathname = useRouterState({ select: (s) => s.location.pathname });

  const items = [
    { to: "/dashboard" as const, label: "Dashboard", icon: LayoutDashboard },
    { to: "/review" as const, label: "Verification", icon: ShieldCheck },
    { to: "/pharmacies" as const, label: "Pharmacies", icon: Building2 },
    { to: "/reports" as const, label: "Analytics", icon: BarChart3 },
    { to: "/tickets" as const, label: "Support", icon: LifeBuoy },
    { to: "/announcements" as const, label: "Announcements", icon: Megaphone },
    ...(navigation?.manage_admins
      ? [{ to: "/admins" as const, label: "Administrators", icon: Users }]
      : []),
  ];

  return (
    <aside
      className={`hidden md:flex flex-col shrink-0 bg-sidebar text-sidebar-foreground border-r border-sidebar-border transition-[width] duration-300 ${sidebarCollapsed ? "w-16" : "w-64"}`}
    >
      <div
        className={`h-16 flex items-center gap-3 px-4 border-b border-sidebar-border ${sidebarCollapsed ? "justify-center px-0" : ""}`}
      >
        <div className="w-9 h-9 rounded-lg bg-emerald-500/10 grid place-items-center shrink-0">
          <img src={logo} alt="" className="w-6 h-6" />
        </div>
        {!sidebarCollapsed && (
          <div className="min-w-0">
            <p className="text-sm font-semibold tracking-tight truncate">Smart Pharmacy</p>
            <p className="text-[10px] label-caption text-sidebar-foreground/60">Admin Console</p>
          </div>
        )}
      </div>

      <nav className="flex-1 px-3 py-4 space-y-1 relative">
        {items.map((it) => {
          const active = pathname.startsWith(it.to);
          const Icon = it.icon;
          return (
            <Link
              key={it.to}
              to={it.to}
              className={`relative w-full h-10 flex items-center gap-3 px-3 rounded-lg text-sm transition-colors ${active ? "text-sidebar-primary-foreground" : "text-sidebar-foreground/80 hover:text-sidebar-foreground hover:bg-sidebar-accent"}`}
            >
              {active && (
                <motion.span
                  layoutId="nav-pill"
                  className="absolute inset-0 rounded-lg bg-gradient-to-r from-emerald-600 to-emerald-500 shadow-glow-emerald"
                  transition={{ type: "spring", stiffness: 380, damping: 30 }}
                />
              )}
              <Icon size={18} strokeWidth={1.75} className="relative z-10 shrink-0" />
              {!sidebarCollapsed && <span className="relative z-10 truncate">{it.label}</span>}
            </Link>
          );
        })}
      </nav>

      <div className="p-3 border-t border-sidebar-border space-y-1">
        <button
          onClick={toggleSidebar}
          className="w-full h-10 flex items-center gap-3 px-3 rounded-lg text-sm text-sidebar-foreground/70 hover:bg-sidebar-accent transition"
        >
          <ChevronsLeft
            size={18}
            strokeWidth={1.75}
            className={`transition-transform ${sidebarCollapsed ? "rotate-180" : ""}`}
          />
          {!sidebarCollapsed && <span>Collapse</span>}
        </button>
        <button
          onClick={() => void logout()}
          className="w-full h-10 flex items-center gap-3 px-3 rounded-lg text-sm text-sidebar-foreground/70 hover:bg-sidebar-accent transition"
        >
          <LogOut size={18} strokeWidth={1.75} />
          {!sidebarCollapsed && <span>Log out</span>}
        </button>
      </div>
    </aside>
  );
}
