import { useQuery } from "@tanstack/react-query";
import { AnimatePresence, motion } from "framer-motion";
import { Link } from "@tanstack/react-router";
import { Bell, Building2, LifeBuoy } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { fetchInbox } from "@/lib/inboxApi";
import { relativeTime } from "@/lib/relativeTime";

/**
 * What is waiting for this administrator.
 *
 * The count is derived from the pending-application and open-ticket queues, so
 * it always matches what those screens show. There is no "mark as read" on
 * purpose: reviewing the application is what clears it, and dismissing a badge
 * would only hide work that still needs doing.
 */
export function NotificationBell() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  const query = useQuery({
    queryKey: ["inbox"],
    queryFn: ({ signal }) => fetchInbox(signal),
  });

  useEffect(() => {
    const close = (event: MouseEvent) => {
      if (ref.current && !ref.current.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, []);

  const inbox = query.data?.data;
  const total = inbox?.total ?? 0;

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={total === 0 ? "Notifications" : `Notifications, ${total} waiting`}
        className="relative w-10 h-10 rounded-xl bg-sidebar-accent/60 hover:bg-sidebar-accent grid place-items-center transition"
      >
        <Bell size={16} strokeWidth={1.75} />
        {total > 0 && (
          <span className="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold grid place-items-center">
            {total > 99 ? "99+" : total}
          </span>
        )}
      </button>

      <AnimatePresence>
        {open && (
          <motion.div
            role="menu"
            initial={{ opacity: 0, y: -6, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -6, scale: 0.98 }}
            transition={{ duration: 0.15 }}
            className="absolute right-0 top-12 w-80 rounded-2xl bg-popover text-popover-foreground border shadow-elevated overflow-hidden z-50"
          >
            <div className="px-4 py-3 border-b">
              <p className="text-sm font-semibold">Waiting for you</p>
              <p className="text-[11px] text-muted-foreground">
                {inbox
                  ? `${inbox.groups.pharmacy_applications} application(s) · ${inbox.groups.support_tickets} ticket(s)`
                  : "Checking…"}
              </p>
            </div>

            {inbox && inbox.items.length === 0 && (
              <p className="px-4 py-8 text-center text-xs text-muted-foreground">
                Nothing needs your attention.
              </p>
            )}

            <ul className="max-h-80 overflow-y-auto divide-y">
              {inbox?.items.map((item) => (
                <li key={item.id}>
                  <Link
                    to={item.kind === "pharmacy_application" ? "/review" : "/tickets"}
                    onClick={() => setOpen(false)}
                    className="flex gap-3 px-4 py-3 hover:bg-accent transition"
                  >
                    <span className="mt-0.5 w-7 h-7 shrink-0 rounded-lg bg-emerald-500/10 text-emerald-600 grid place-items-center">
                      {item.kind === "pharmacy_application" ? (
                        <Building2 size={14} strokeWidth={1.75} />
                      ) : (
                        <LifeBuoy size={14} strokeWidth={1.75} />
                      )}
                    </span>
                    <span className="min-w-0">
                      <span className="block text-sm font-medium truncate">{item.title}</span>
                      <span className="block text-[11px] text-muted-foreground truncate">
                        {item.detail}
                      </span>
                      <span className="block text-[11px] text-muted-foreground">
                        {relativeTime(item.at)}
                      </span>
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
