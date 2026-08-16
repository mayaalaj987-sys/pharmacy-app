import { AnimatePresence, motion } from "framer-motion";
import { CheckCircle2, AlertCircle, Info, X } from "lucide-react";
import { createContext, useCallback, useContext, useState, type ReactNode } from "react";

type Variant = "success" | "error" | "info";
interface Toast {
  id: number;
  variant: Variant;
  title: string;
  description?: string;
}
interface Ctx {
  push: (t: Omit<Toast, "id">) => void;
}

const ToastCtx = createContext<Ctx | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const push = useCallback((t: Omit<Toast, "id">) => {
    const id = Date.now() + Math.random();
    setToasts((prev) => [...prev, { ...t, id }]);
    setTimeout(() => setToasts((prev) => prev.filter((x) => x.id !== id)), 4200);
  }, []);
  return (
    <ToastCtx.Provider value={{ push }}>
      {children}
      <div className="pointer-events-none fixed top-4 right-4 z-[100] flex flex-col gap-2 w-[360px] max-w-[calc(100vw-2rem)]">
        <AnimatePresence>
          {toasts.map((t) => {
            const Icon =
              t.variant === "success" ? CheckCircle2 : t.variant === "error" ? AlertCircle : Info;
            const color =
              t.variant === "success"
                ? "text-emerald-500"
                : t.variant === "error"
                  ? "text-red-500"
                  : "text-blue-500";
            return (
              <motion.div
                key={t.id}
                initial={{ opacity: 0, y: -12, scale: 0.96 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, x: 40 }}
                transition={{ type: "spring", stiffness: 380, damping: 28 }}
                className="pointer-events-auto relative overflow-hidden rounded-xl bg-card border shadow-elevated p-3 pr-4 flex items-start gap-3"
              >
                <Icon className={`${color} shrink-0 mt-0.5`} size={20} strokeWidth={1.75} />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold">{t.title}</p>
                  {t.description && (
                    <p className="text-xs text-muted-foreground mt-0.5">{t.description}</p>
                  )}
                </div>
                <button
                  onClick={() => setToasts((prev) => prev.filter((x) => x.id !== t.id))}
                  className="text-muted-foreground hover:text-foreground transition"
                >
                  <X size={14} strokeWidth={1.75} />
                </button>
                <motion.div
                  initial={{ width: "100%" }}
                  animate={{ width: 0 }}
                  transition={{ duration: 4.2, ease: "linear" }}
                  className={`absolute bottom-0 left-0 h-0.5 ${t.variant === "success" ? "bg-emerald-500" : t.variant === "error" ? "bg-red-500" : "bg-blue-500"}`}
                />
              </motion.div>
            );
          })}
        </AnimatePresence>
      </div>
    </ToastCtx.Provider>
  );
}

export function useToast() {
  const c = useContext(ToastCtx);
  if (!c) throw new Error("useToast must be inside ToastProvider");
  return c;
}
