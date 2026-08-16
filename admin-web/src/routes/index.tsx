import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useAuth } from "@/context/AuthContext";

export const Route = createFileRoute("/")({
  component: Index,
});

function Index() {
  const { status } = useAuth();
  if (status === "authenticated") return <Navigate to="/review" replace />;
  return <Navigate to="/login" replace />;
}
