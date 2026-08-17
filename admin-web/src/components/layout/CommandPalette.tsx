import { useQuery } from "@tanstack/react-query";
import { useNavigate } from "@tanstack/react-router";
import { AnimatePresence, motion } from "framer-motion";
import {
  BarChart3,
  Building2,
  LayoutDashboard,
  LifeBuoy,
  Megaphone,
  Search,
  Settings,
  ShieldCheck,
  Users,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { useAuth } from "@/context/AuthContext";
import { searchConsole } from "@/lib/searchApi";

type Destination =
  | "/dashboard"
  | "/review"
  | "/pharmacies"
  | "/reports"
  | "/tickets"
  | "/announcements"
  | "/admins"
  | "/settings";

interface Command {
  id: string;
  label: string;
  hint: string;
  to: Destination;
  icon: typeof Search;
}

/**
 * Ctrl/⌘-K search over the console.
 *
 * Two things at once: jump to a screen, and find a specific pharmacy or ticket.
 * Records are searched server-side; the destinations are static because they
 * are the console's own routes.
 *
 * Only pharmacies and tickets are searchable — medicines, sales and stock
 * belong to a pharmacy, and an administrator has no business browsing them.
 */
export function CommandPalette() {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const inputRef = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();
  const { navigation } = useAuth();

  const destinations = useMemo<Command[]>(
    () => [
      { id: "d", label: "Dashboard", hint: "Overview", to: "/dashboard", icon: LayoutDashboard },
      {
        id: "v",
        label: "Verification",
        hint: "Review applications",
        to: "/review",
        icon: ShieldCheck,
      },
      {
        id: "p",
        label: "Pharmacies",
        hint: "Operational register",
        to: "/pharmacies",
        icon: Building2,
      },
      { id: "r", label: "Analytics", hint: "Platform reports", to: "/reports", icon: BarChart3 },
      { id: "t", label: "Support", hint: "Ticket queue", to: "/tickets", icon: LifeBuoy },
      {
        id: "n",
        label: "Announcements",
        hint: "Message pharmacies",
        to: "/announcements",
        icon: Megaphone,
      },
      ...(navigation?.manage_admins
        ? [
            {
              id: "a",
              label: "Administrators",
              hint: "Manage accounts",
              to: "/admins" as const,
              icon: Users,
            },
          ]
        : []),
      { id: "s", label: "Settings", hint: "Console preferences", to: "/settings", icon: Settings },
    ],
    [navigation?.manage_admins],
  );

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        setOpen((o) => !o);
      }
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, []);

  useEffect(() => {
    if (open) {
      setQuery("");
      // The palette is useless without focus, and autoFocus fights the animation.
      requestAnimationFrame(() => inputRef.current?.focus());
    }
  }, [open]);

  const trimmed = query.trim();
  const results = useQuery({
    queryKey: ["console-search", trimmed],
    queryFn: ({ signal }) => searchConsole(trimmed, signal),
    enabled: open && trimmed.length >= 2,
  });

  const matchingDestinations = destinations.filter((destination) =>
    trimmed === ""
      ? true
      : `${destination.label} ${destination.hint}`.toLowerCase().includes(trimmed.toLowerCase()),
  );

  function go(to: Destination) {
    setOpen(false);
    void navigate({ to });
  }

  const hits = results.data?.data;
  const nothingFound =
    trimmed.length >= 2 &&
    !results.isPending &&
    matchingDestinations.length === 0 &&
    (hits?.pharmacies.length ?? 0) === 0 &&
    (hits?.tickets.length ?? 0) === 0;

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="hidden md:flex items-center gap-2 h-10 px-3 rounded-xl bg-sidebar-accent/60 hover:bg-sidebar-accent transition text-sidebar-foreground/70 min-w-64"
      >
        <Search size={15} strokeWidth={1.75} />
        <span className="text-xs">Search pharmacies, tickets, actions…</span>
        <kbd className="ml-auto text-[10px] px-1.5 py-0.5 rounded border border-sidebar-foreground/20">
          Ctrl K
        </kbd>
      </button>

      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 bg-black/40 p-4 pt-[10vh]"
            onMouseDown={() => setOpen(false)}
          >
            <motion.div
              role="dialog"
              aria-modal="true"
              aria-label="Console search"
              initial={{ opacity: 0, y: -8, scale: 0.98 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: -8, scale: 0.98 }}
              transition={{ duration: 0.15 }}
              onMouseDown={(event) => event.stopPropagation()}
              className="mx-auto w-full max-w-xl rounded-2xl bg-popover text-popover-foreground border shadow-elevated overflow-hidden"
            >
              <div className="flex items-center gap-3 px-4 border-b">
                <Search size={16} className="text-muted-foreground shrink-0" />
                <input
                  ref={inputRef}
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder="Search pharmacies, tickets, actions…"
                  aria-label="Search pharmacies, tickets, actions"
                  className="flex-1 h-14 bg-transparent outline-none text-sm"
                />
                <kbd className="text-[10px] px-1.5 py-0.5 rounded border text-muted-foreground">
                  Esc
                </kbd>
              </div>

              <div className="max-h-96 overflow-y-auto p-2">
                {matchingDestinations.length > 0 && (
                  <Group title="Go to">
                    {matchingDestinations.map((destination) => (
                      <Row
                        key={destination.id}
                        icon={destination.icon}
                        title={destination.label}
                        detail={destination.hint}
                        onSelect={() => go(destination.to)}
                      />
                    ))}
                  </Group>
                )}

                {trimmed.length >= 2 && results.isPending && (
                  <p className="px-3 py-4 text-xs text-muted-foreground">Searching…</p>
                )}

                {(hits?.pharmacies.length ?? 0) > 0 && (
                  <Group title="Pharmacies">
                    {hits!.pharmacies.map((hit) => (
                      <Row
                        key={`pharmacy-${hit.id}`}
                        icon={Building2}
                        title={hit.title}
                        detail={`${hit.detail} · ${hit.status}`}
                        onSelect={() => go("/pharmacies")}
                      />
                    ))}
                  </Group>
                )}

                {(hits?.tickets.length ?? 0) > 0 && (
                  <Group title="Support tickets">
                    {hits!.tickets.map((hit) => (
                      <Row
                        key={`ticket-${hit.id}`}
                        icon={LifeBuoy}
                        title={hit.title}
                        detail={`${hit.detail} · ${hit.status}`}
                        onSelect={() => go("/tickets")}
                      />
                    ))}
                  </Group>
                )}

                {nothingFound && (
                  <p className="px-3 py-6 text-center text-xs text-muted-foreground">
                    Nothing matches “{trimmed}”.
                  </p>
                )}
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  );
}

function Group({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="mb-1">
      <p className="px-3 py-1.5 text-[10px] uppercase tracking-wide text-muted-foreground">
        {title}
      </p>
      {children}
    </div>
  );
}

function Row({
  icon: Icon,
  title,
  detail,
  onSelect,
}: {
  icon: typeof Search;
  title: string;
  detail: string;
  onSelect: () => void;
}) {
  return (
    <button
      onClick={onSelect}
      className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left hover:bg-accent transition"
    >
      <span className="w-8 h-8 shrink-0 rounded-lg bg-emerald-500/10 text-emerald-600 grid place-items-center">
        <Icon size={14} strokeWidth={1.75} />
      </span>
      <span className="min-w-0">
        <span className="block text-sm font-medium truncate">{title}</span>
        <span className="block text-[11px] text-muted-foreground truncate">{detail}</span>
      </span>
    </button>
  );
}
