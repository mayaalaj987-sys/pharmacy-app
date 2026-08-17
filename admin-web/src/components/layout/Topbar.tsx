import { AnimatePresence, motion } from "framer-motion";
import { Sun, Moon, ChevronDown, LogOut } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "@/context/AuthContext";
import { useUi } from "@/context/UiContext";
import { NotificationBell } from "@/components/layout/NotificationBell";

export function Topbar({ title }: { title: string }) {
  const { admin, logout } = useAuth();
  const { theme, toggleTheme } = useUi();
  const [profileOpen, setProfileOpen] = useState(false);
  const profRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const h = (e: MouseEvent) => {
      if (profRef.current && !profRef.current.contains(e.target as Node)) setProfileOpen(false);
    };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, []);

  const initials = admin?.name
    ? admin.name
        .split(" ")
        .map((p) => p[0])
        .slice(0, 2)
        .join("")
        .toUpperCase()
    : "?";

  return (
    <header className="h-16 shrink-0 bg-sidebar border-b border-sidebar-border text-sidebar-foreground flex items-center px-6 gap-4">
      <div className="flex flex-col">
        <p className="text-[10px] label-caption text-sidebar-foreground/50">Console</p>
        <p className="text-sm font-semibold tracking-tight">{title}</p>
      </div>

      <div className="ml-auto" />

      <NotificationBell />

      <button
        onClick={toggleTheme}
        aria-label={theme === "light" ? "Switch to dark mode" : "Switch to light mode"}
        className="w-10 h-10 rounded-xl bg-sidebar-accent/60 hover:bg-sidebar-accent grid place-items-center transition"
      >
        {theme === "light" ? (
          <Moon size={16} strokeWidth={1.75} />
        ) : (
          <Sun size={16} strokeWidth={1.75} />
        )}
      </button>

      <div ref={profRef} className="relative">
        <button
          onClick={() => setProfileOpen((o) => !o)}
          aria-haspopup="menu"
          aria-expanded={profileOpen}
          className="flex items-center gap-2 h-10 px-2 pr-3 rounded-xl bg-sidebar-accent/60 hover:bg-sidebar-accent transition"
        >
          <div className="w-7 h-7 rounded-lg bg-emerald-500 text-white text-xs font-semibold grid place-items-center">
            {initials}
          </div>
          <div className="hidden md:flex flex-col items-start leading-tight">
            <span className="text-xs font-medium">{admin?.name}</span>
            <span className="text-[10px] text-sidebar-foreground/60">
              {admin?.role === "super_admin" ? "Super Admin" : "Pharmacy Reviewer"}
            </span>
          </div>
          <ChevronDown size={14} strokeWidth={1.75} />
        </button>
        <AnimatePresence>
          {profileOpen && (
            <motion.div
              role="menu"
              initial={{ opacity: 0, y: -6, scale: 0.98 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: -6, scale: 0.98 }}
              transition={{ duration: 0.15 }}
              className="absolute right-0 top-12 w-56 rounded-2xl bg-popover text-popover-foreground border shadow-elevated overflow-hidden z-50"
            >
              <div className="p-3 border-b">
                <p className="text-sm font-semibold">{admin?.name}</p>
                <p className="text-xs text-muted-foreground">{admin?.email}</p>
              </div>
              <div className="p-1">
                <button
                  role="menuitem"
                  onClick={() => void logout()}
                  className="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm hover:bg-accent text-red-500"
                >
                  <LogOut size={14} strokeWidth={1.75} /> Log out
                </button>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </header>
  );
}
