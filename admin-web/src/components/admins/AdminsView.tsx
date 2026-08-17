import { useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, Plus, RotateCcw, ShieldOff, ShieldCheck } from "lucide-react";
import { useRef, useState } from "react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { useToast } from "@/components/ui-custom/Toast";
import { AdminApiError } from "@/lib/adminApi";
import {
  changeAdminRole,
  disableAdmin,
  fetchAdmins,
  reactivateAdmin,
} from "@/lib/adminAccountsApi";
import type { AdminAccount, AdminRole } from "@/lib/types";
import { useAuth } from "@/context/AuthContext";
import { CreateAdminDialog } from "@/components/admins/CreateAdminDialog";
import { exactTime } from "@/lib/relativeTime";

function workflowMessage(error: unknown): string {
  if (error instanceof AdminApiError) {
    switch (error.code) {
      case "last_active_super_admin":
        return "This is the last active super administrator — it cannot be disabled or demoted.";
      case "unsupported_admin_role":
        return "That role is not supported.";
      case "forbidden":
        return "You are not authorized to perform this action.";
      default:
        return error.message || "The action could not be completed.";
    }
  }
  return "The action could not be completed.";
}

type PendingAction = {
  admin: AdminAccount;
  kind: "disable" | "reactivate" | "role";
  role?: AdminRole;
} | null;

export function AdminsView() {
  const { admin: currentAdmin } = useAuth();
  const queryClient = useQueryClient();
  const { push } = useToast();
  const [createOpen, setCreateOpen] = useState(false);
  const [pending, setPending] = useState<PendingAction>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  // Synchronous guard: React state can't prevent two clicks in the same tick.
  const busyRef = useRef(false);

  const query = useQuery({
    queryKey: ["admin", "accounts"],
    queryFn: ({ signal }) => fetchAdmins(signal),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["admin", "accounts"] });

  const confirmAction = async () => {
    if (!pending || busyRef.current) return;
    busyRef.current = true;
    setBusyId(pending.admin.id);
    try {
      if (pending.kind === "disable") await disableAdmin(pending.admin.id);
      else if (pending.kind === "reactivate") await reactivateAdmin(pending.admin.id);
      else if (pending.kind === "role" && pending.role)
        await changeAdminRole(pending.admin.id, pending.role);
      push({ variant: "success", title: "Administrator updated" });
      invalidate();
    } catch (error) {
      push({ variant: "error", title: "Update failed", description: workflowMessage(error) });
    } finally {
      busyRef.current = false;
      setBusyId(null);
      setPending(null);
    }
  };

  if (query.isPending) {
    return (
      <div
        className="bg-card rounded-2xl border shadow-soft p-16 text-center text-sm text-muted-foreground"
        role="status"
      >
        Loading administrators…
      </div>
    );
  }

  if (query.isError) {
    const message =
      query.error instanceof AdminApiError ? query.error.message : "Could not load administrators.";
    return (
      <div className="bg-card rounded-2xl border shadow-soft p-16 text-center">
        <AlertTriangle className="mx-auto text-red-500" size={28} />
        <p className="text-sm font-medium mt-3">{message}</p>
        <Button variant="outline" className="mt-4" onClick={() => query.refetch()}>
          <RotateCcw size={14} className="mr-2" /> Retry
        </Button>
      </div>
    );
  }

  return (
    <div className="bg-card rounded-2xl border shadow-soft overflow-hidden">
      <div className="p-5 flex items-center justify-between border-b">
        <div>
          <h3 className="text-base font-semibold">Administrators</h3>
          <p className="text-xs text-muted-foreground mt-1">{query.data.data.length} accounts</p>
        </div>
        <Button onClick={() => setCreateOpen(true)}>
          <Plus size={14} className="mr-2" /> New administrator
        </Button>
      </div>

      <div className="overflow-auto scrollbar-thin">
        <table className="w-full text-sm">
          <thead className="bg-muted/40 sticky top-0 label-caption text-muted-foreground">
            <tr>
              <th className="px-4 py-3 text-left">Name</th>
              <th className="px-4 py-3 text-left">Role</th>
              <th className="px-4 py-3 text-left">Status</th>
              <th className="px-4 py-3 text-left">Last login</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {query.data.data.map((a) => {
              const isSelf = a.id === currentAdmin?.id;
              return (
                <tr key={a.id} className="border-t">
                  <td className="px-4 py-3">
                    <p className="font-medium">{a.name}</p>
                    <p className="text-xs text-muted-foreground">{a.email}</p>
                  </td>
                  <td className="px-4 py-3">
                    <select
                      aria-label={`Change role for ${a.name}`}
                      value={a.role}
                      disabled={busyId === a.id}
                      onChange={(e) =>
                        setPending({ admin: a, kind: "role", role: e.target.value as AdminRole })
                      }
                      className="h-8 px-2 rounded-lg border bg-card text-xs"
                    >
                      <option value="super_admin">Super admin</option>
                      <option value="pharmacy_reviewer">Pharmacy reviewer</option>
                    </select>
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium ${a.is_active ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" : "bg-slate-500/10 text-slate-700 dark:text-slate-300"}`}
                    >
                      <span
                        className={`w-1.5 h-1.5 rounded-full ${a.is_active ? "bg-emerald-500" : "bg-slate-500"}`}
                      />
                      {a.is_active ? "Active" : "Disabled"}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">
                    {a.last_login_at ? exactTime(a.last_login_at) : "Never"}
                  </td>
                  <td className="px-4 py-3 text-right">
                    {a.is_active ? (
                      <button
                        disabled={busyId === a.id || isSelf}
                        title={isSelf ? "You cannot disable your own account" : undefined}
                        onClick={() => setPending({ admin: a, kind: "disable" })}
                        className="text-xs text-red-600 font-medium hover:underline disabled:opacity-40 disabled:no-underline inline-flex items-center gap-1"
                      >
                        <ShieldOff size={13} /> Disable
                      </button>
                    ) : (
                      <button
                        disabled={busyId === a.id}
                        onClick={() => setPending({ admin: a, kind: "reactivate" })}
                        className="text-xs text-emerald-600 font-medium hover:underline disabled:opacity-40 inline-flex items-center gap-1"
                      >
                        <ShieldCheck size={13} /> Reactivate
                      </button>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {pending?.kind === "disable" && `Disable ${pending.admin.name}?`}
              {pending?.kind === "reactivate" && `Reactivate ${pending.admin.name}?`}
              {pending?.kind === "role" && `Change ${pending.admin.name}'s role?`}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {pending?.kind === "disable" &&
                "This immediately signs them out of all sessions and revokes access."}
              {pending?.kind === "reactivate" &&
                "This restores their ability to sign in and use the console."}
              {pending?.kind === "role" &&
                `This changes their permissions to ${pending.role === "super_admin" ? "Super admin" : "Pharmacy reviewer"} and signs out their existing sessions.`}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmAction()}>Confirm</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <CreateAdminDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
        onCreated={() => {
          invalidate();
          push({ variant: "success", title: "Administrator created" });
        }}
      />
    </div>
  );
}
