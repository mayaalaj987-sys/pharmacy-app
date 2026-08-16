import { useRef, useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { AdminApiError } from "@/lib/adminApi";
import { createAdmin } from "@/lib/adminAccountsApi";
import type { AdminRole } from "@/lib/types";

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreated: () => void;
}

const EMPTY = {
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  role: "pharmacy_reviewer" as AdminRole,
};

export function CreateAdminDialog({ open, onOpenChange, onCreated }: Props) {
  const [form, setForm] = useState(EMPTY);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  // Synchronous guard against a double-fire (two clicks in the same tick, before React
  // re-renders the disabled button). React state alone can't prevent that race.
  const submittingRef = useRef(false);

  const reset = () => {
    setForm(EMPTY);
    setErrors({});
    setFormError(null);
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (submittingRef.current) return;
    setErrors({});
    setFormError(null);
    if (form.password !== form.password_confirmation) {
      setErrors({ password_confirmation: ["Passwords do not match."] });
      return;
    }
    submittingRef.current = true;
    setSubmitting(true);
    try {
      await createAdmin(form);
      reset();
      onOpenChange(false);
      onCreated();
    } catch (error) {
      if (error instanceof AdminApiError) {
        if (error.code === "validation_failed" && error.errors) {
          setErrors(error.errors);
        } else if (error.code === "admin_email_exists") {
          setErrors({ email: ["An administrator with this email already exists."] });
        } else {
          setFormError(error.message);
        }
      } else {
        setFormError("Could not create the administrator.");
      }
    } finally {
      submittingRef.current = false;
      setSubmitting(false);
    }
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) reset();
        onOpenChange(next);
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New administrator</DialogTitle>
          <DialogDescription>
            Creates an account and sends no email. Share the password with them out of band.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={submit} className="space-y-4" noValidate>
          <div className="space-y-1.5">
            <Label htmlFor="admin-name">Name</Label>
            <Input
              id="admin-name"
              required
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
            />
            {errors.name && <p className="text-xs text-red-500">{errors.name[0]}</p>}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="admin-email">Email</Label>
            <Input
              id="admin-email"
              type="email"
              required
              value={form.email}
              onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
            />
            {errors.email && <p className="text-xs text-red-500">{errors.email[0]}</p>}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="admin-role">Role</Label>
            <select
              id="admin-role"
              value={form.role}
              onChange={(e) => setForm((f) => ({ ...f, role: e.target.value as AdminRole }))}
              className="flex h-9 w-full items-center rounded-md border border-input bg-transparent px-3 py-2 text-sm"
            >
              <option value="pharmacy_reviewer">Pharmacy reviewer</option>
              <option value="super_admin">Super admin</option>
            </select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="admin-password">Password</Label>
            <Input
              id="admin-password"
              type="password"
              autoComplete="new-password"
              required
              minLength={12}
              value={form.password}
              onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
            />
            <p className="text-[11px] text-muted-foreground">
              At least 12 characters with letters, mixed case, numbers, and symbols.
            </p>
            {errors.password && <p className="text-xs text-red-500">{errors.password[0]}</p>}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="admin-password-confirm">Confirm password</Label>
            <Input
              id="admin-password-confirm"
              type="password"
              autoComplete="new-password"
              required
              value={form.password_confirmation}
              onChange={(e) => setForm((f) => ({ ...f, password_confirmation: e.target.value }))}
            />
            {errors.password_confirmation && (
              <p className="text-xs text-red-500">{errors.password_confirmation[0]}</p>
            )}
          </div>
          {formError && (
            <p role="alert" className="text-xs text-red-500">
              {formError}
            </p>
          )}
          <DialogFooter>
            <Button type="submit" disabled={submitting}>
              {submitting ? "Creating…" : "Create administrator"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
