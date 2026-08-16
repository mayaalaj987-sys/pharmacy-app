import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useAuth } from "@/context/AuthContext";
import { LoginView } from "@/components/auth/LoginView";

export const Route = createFileRoute("/login")({
  component: LoginRoute,
});

function LoginRoute() {
  const { status } = useAuth();
  if (status === "authenticated") return <Navigate to="/review" replace />;
  return <LoginView />;
}
