import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { RouterProvider } from "@tanstack/react-router";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { AuthProvider, useAuth } from "@/context/AuthContext";
import { UiProvider } from "@/context/UiContext";
import { ToastProvider } from "@/components/ui-custom/Toast";
import { router } from "@/router";
import "./styles.css";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false, refetchOnWindowFocus: false },
    mutations: { retry: false },
  },
});

function InnerApp() {
  const auth = useAuth();
  if (auth.status === "resolving") {
    return (
      <div
        role="status"
        aria-live="polite"
        className="flex min-h-screen w-full items-center justify-center bg-background"
      >
        <span className="sr-only">Checking your session…</span>
        <div
          className="h-8 w-8 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-foreground"
          aria-hidden="true"
        />
      </div>
    );
  }
  return <RouterProvider router={router} />;
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <UiProvider>
        <ToastProvider>
          <AuthProvider>
            <InnerApp />
          </AuthProvider>
        </ToastProvider>
      </UiProvider>
    </QueryClientProvider>
  );
}

const rootEl = document.getElementById("root");
if (!rootEl) throw new Error("Root element not found");

createRoot(rootEl).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
