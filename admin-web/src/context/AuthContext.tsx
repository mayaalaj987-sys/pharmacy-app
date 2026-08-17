import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { AdminApiAbortError, AdminApiError } from "@/lib/adminApi";
import { fetchSession, login as loginRequest, logout as logoutRequest } from "@/lib/sessionApi";
import type { AdminAccount, Navigation } from "@/lib/types";

type AuthStatus = "resolving" | "authenticated" | "unauthenticated";

interface AuthState {
  status: AuthStatus;
  admin: AdminAccount | null;
  navigation: Navigation | null;
  /** Inactivity window enforced by the server, in minutes. */
  sessionLifetimeMinutes: number | null;
}

interface AuthContextValue extends AuthState {
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({
    status: "resolving",
    admin: null,
    navigation: null,
    sessionLifetimeMinutes: null,
  });
  const bootAbortRef = useRef<AbortController | null>(null);
  const logoutInFlightRef = useRef<Promise<void> | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    bootAbortRef.current = controller;
    fetchSession(controller.signal)
      .then((res) => {
        setState({
          status: "authenticated",
          admin: res.data!.admin,
          navigation: res.data!.navigation,
          sessionLifetimeMinutes: res.data!.session_lifetime_minutes,
        });
      })
      .catch((error) => {
        if (error instanceof AdminApiAbortError) return;
        setState({
          status: "unauthenticated",
          admin: null,
          navigation: null,
          sessionLifetimeMinutes: null,
        });
      });
    return () => controller.abort();
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const res = await loginRequest(email, password);
    setState({
      status: "authenticated",
      admin: res.data!.admin,
      navigation: res.data!.navigation,
      sessionLifetimeMinutes: res.data!.session_lifetime_minutes,
    });
  }, []);

  const logout = useCallback(async () => {
    if (logoutInFlightRef.current) return logoutInFlightRef.current;
    bootAbortRef.current?.abort();
    const run = (async () => {
      try {
        await logoutRequest();
      } catch (error) {
        // Even if the server call fails (already expired, network hiccup), clear local state
        // so the UI reliably returns to a logged-out view rather than getting stuck.
        if (!(error instanceof AdminApiError)) throw error;
      } finally {
        setState({
          status: "unauthenticated",
          admin: null,
          navigation: null,
          sessionLifetimeMinutes: null,
        });
        logoutInFlightRef.current = null;
      }
    })();
    logoutInFlightRef.current = run;
    return run;
  }, []);

  return (
    <AuthContext.Provider value={{ ...state, login, logout }}>{children}</AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
}
