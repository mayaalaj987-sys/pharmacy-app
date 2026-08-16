import { createContext, useContext, useEffect, useState, type ReactNode } from "react";

type Theme = "light" | "dark";

interface UiCtx {
  theme: Theme;
  toggleTheme: () => void;
  sidebarCollapsed: boolean;
  toggleSidebar: () => void;
}

const Ctx = createContext<UiCtx | null>(null);

/** Cosmetic-only UI preferences (theme, sidebar collapse). Not session state — never persisted with credentials. */
export function UiProvider({ children }: { children: ReactNode }) {
  const [theme, setTheme] = useState<Theme>("light");
  const [sidebarCollapsed, setSC] = useState(false);

  useEffect(() => {
    document.documentElement.classList.toggle("dark", theme === "dark");
  }, [theme]);

  return (
    <Ctx.Provider
      value={{
        theme,
        toggleTheme: () => setTheme((t) => (t === "light" ? "dark" : "light")),
        sidebarCollapsed,
        toggleSidebar: () => setSC((v) => !v),
      }}
    >
      {children}
    </Ctx.Provider>
  );
}

export function useUi() {
  const c = useContext(Ctx);
  if (!c) throw new Error("useUi must be inside UiProvider");
  return c;
}
