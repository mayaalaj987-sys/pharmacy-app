import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { CreateAdminDialog } from "@/components/admins/CreateAdminDialog";

const BASE = "http://localhost:8000";

describe("CreateAdminDialog", () => {
  it("blocks submission and shows a field error when passwords don't match, without calling the API", async () => {
    let calls = 0;
    server.use(
      http.post(`${BASE}/api/admin/admins`, () => {
        calls += 1;
        return HttpResponse.json(
          { message: "ok", code: "admin_created", data: {} },
          { status: 201 },
        );
      }),
    );
    const onCreated = vi.fn();
    const user = userEvent.setup();
    render(<CreateAdminDialog open onOpenChange={() => {}} onCreated={onCreated} />);

    await user.type(screen.getByLabelText(/^name$/i), "New Admin");
    await user.type(screen.getByLabelText(/^email$/i), "new@smartpharmacy.test");
    await user.type(screen.getByLabelText(/^password$/i), "CorrectHorse12!");
    await user.type(screen.getByLabelText(/confirm password/i), "Mismatch12!");
    await user.click(screen.getByRole("button", { name: /create administrator/i }));

    expect(await screen.findByText(/passwords do not match/i)).toBeInTheDocument();
    expect(calls).toBe(0);
    expect(onCreated).not.toHaveBeenCalled();
  });

  it("surfaces server-side validation errors on the relevant field", async () => {
    const user = userEvent.setup();
    render(<CreateAdminDialog open onOpenChange={() => {}} onCreated={vi.fn()} />);

    await user.type(screen.getByLabelText(/^name$/i), "New Admin");
    await user.type(screen.getByLabelText(/^email$/i), "new@smartpharmacy.test");
    // 12+ chars so native minLength validation lets the form submit, but weak enough
    // (no uppercase/digit/symbol) to fail the server's complexity rule.
    await user.type(screen.getByLabelText(/^password$/i), "aaaaaaaaaaaa");
    await user.type(screen.getByLabelText(/confirm password/i), "aaaaaaaaaaaa");
    await user.click(screen.getByRole("button", { name: /create administrator/i }));

    expect(await screen.findByText(/complexity requirements/i)).toBeInTheDocument();
  });

  it("prevents duplicate submission while a request is in flight", async () => {
    let calls = 0;
    server.use(
      http.post(`${BASE}/api/admin/admins`, async () => {
        calls += 1;
        await new Promise((resolve) => setTimeout(resolve, 30));
        return HttpResponse.json(
          { message: "ok", code: "admin_created", data: {} },
          { status: 201 },
        );
      }),
    );
    const onCreated = vi.fn();
    const user = userEvent.setup();
    render(<CreateAdminDialog open onOpenChange={() => {}} onCreated={onCreated} />);

    await user.type(screen.getByLabelText(/^name$/i), "New Admin");
    await user.type(screen.getByLabelText(/^email$/i), "new@smartpharmacy.test");
    await user.type(screen.getByLabelText(/^password$/i), "CorrectHorse12!");
    await user.type(screen.getByLabelText(/confirm password/i), "CorrectHorse12!");

    // Fire two submits back-to-back with no await between them, simulating a double-click
    // landing in the same tick before React has re-rendered the disabled button. Proves the
    // synchronous ref guard (not just the `disabled` attribute) prevents a second request.
    const form = document.querySelector("form");
    if (!form) throw new Error("form not found");
    fireEvent.submit(form);
    fireEvent.submit(form);

    await waitFor(() => expect(onCreated).toHaveBeenCalledTimes(1));
    expect(calls).toBe(1);
  });
});
