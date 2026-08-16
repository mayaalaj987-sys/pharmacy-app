import { http, HttpResponse } from "msw";
import type { AdminAccount, PharmacyApplication } from "@/lib/types";

const BASE = "http://localhost:8000";

export const mockSuperAdmin: AdminAccount = {
  id: "admin-1",
  name: "Aya Admin",
  email: "aya@smartpharmacy.test",
  role: "super_admin",
  is_active: true,
  last_login_at: "2026-08-16T08:00:00.000Z",
  password_changed_at: "2026-08-01T00:00:00.000Z",
  disabled_at: null,
  created_at: "2026-07-01T00:00:00.000Z",
};

export const mockReviewer: AdminAccount = {
  ...mockSuperAdmin,
  id: "admin-2",
  name: "Ryan Reviewer",
  email: "ryan@smartpharmacy.test",
  role: "pharmacy_reviewer",
};

export const mockApplication: PharmacyApplication = {
  id: 42,
  name: "Downtown Pharmacy",
  address: "12 Main St",
  status: "pending",
  submitted_at: "2026-08-10T00:00:00.000Z",
  review_version: 3,
  owner: { name: "Sam Owner", email: "sam@pharmacy.test", phone: "555-0100" },
  documents: [
    {
      id: "doc-1",
      type: "certificate",
      review_status: "pending",
      mime_category: "pdf",
      size_bytes: 20480,
      submitted_at: "2026-08-09T00:00:00.000Z",
      reviewed_at: null,
      preview_url: "/api/admin/review/applications/42/documents/doc-1/preview",
      download_url: "/api/admin/review/applications/42/documents/doc-1/download",
    },
  ],
  reviewed_at: null,
};

/** Mutable per-test session state. Call `setSessionAdmin` in a test to control who `/session` returns. */
let sessionAdmin: AdminAccount | null = null;

export function setSessionAdmin(admin: AdminAccount | null) {
  sessionAdmin = admin;
}

function sessionEnvelope(admin: AdminAccount) {
  return {
    message: "ok",
    code: "admin_session_active",
    data: {
      admin,
      navigation: {
        review_pharmacies: admin.is_active,
        manage_admins: admin.is_active && admin.role === "super_admin",
      },
    },
  };
}

export const handlers = [
  http.get(`${BASE}/api/admin/csrf`, () => {
    document.cookie = "XSRF-TOKEN=test-csrf-token; path=/";
    return HttpResponse.json({ message: "CSRF protection is ready.", code: "csrf_ready" });
  }),

  http.post(`${BASE}/api/admin/login`, async ({ request }) => {
    const body = (await request.json()) as { email: string; password: string };
    if (body.email === mockSuperAdmin.email && body.password === "correct-password") {
      sessionAdmin = mockSuperAdmin;
      return HttpResponse.json(sessionEnvelope(mockSuperAdmin));
    }
    if (body.email === mockReviewer.email && body.password === "correct-password") {
      sessionAdmin = mockReviewer;
      return HttpResponse.json(sessionEnvelope(mockReviewer));
    }
    return HttpResponse.json(
      { message: "The provided credentials are invalid.", code: "invalid_credentials" },
      { status: 401 },
    );
  }),

  http.get(`${BASE}/api/admin/session`, () => {
    if (!sessionAdmin) {
      return HttpResponse.json(
        { message: "Unauthenticated.", code: "unauthenticated" },
        { status: 401 },
      );
    }
    return HttpResponse.json(sessionEnvelope(sessionAdmin));
  }),

  http.post(`${BASE}/api/admin/logout`, () => {
    sessionAdmin = null;
    return HttpResponse.json({ message: "Administrator session ended.", code: "admin_logged_out" });
  }),

  http.get(`${BASE}/api/admin/review/applications`, () => {
    return HttpResponse.json({
      data: [mockApplication],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    });
  }),

  http.post(`${BASE}/api/admin/review/applications/:id/approve`, async ({ request }) => {
    const body = (await request.json()) as { review_version: number };
    if (body.review_version !== mockApplication.review_version) {
      return HttpResponse.json(
        {
          message: "The pharmacy application changed before this decision was applied.",
          code: "review_version_conflict",
        },
        { status: 409 },
      );
    }
    return HttpResponse.json({
      message: "The pharmacy review decision was applied.",
      code: "pharmacy_approved",
      data: {
        ...mockApplication,
        status: "approved",
        review_version: mockApplication.review_version + 1,
      },
    });
  }),

  http.post(`${BASE}/api/admin/review/applications/:id/reject`, async ({ request }) => {
    const body = (await request.json()) as { review_version: number; reason: string };
    if (!body.reason || body.reason.trim().length < 5) {
      return HttpResponse.json(
        {
          message: "A rejection reason between 5 and 500 characters is required.",
          code: "rejection_reason_invalid",
        },
        { status: 422 },
      );
    }
    return HttpResponse.json({
      message: "The pharmacy review decision was applied.",
      code: "pharmacy_rejected",
      data: {
        ...mockApplication,
        status: "rejected",
        review_version: mockApplication.review_version + 1,
      },
    });
  }),

  http.get(`${BASE}/api/admin/admins`, () => {
    return HttpResponse.json({ data: [mockSuperAdmin, mockReviewer] });
  }),

  http.post(`${BASE}/api/admin/admins`, async ({ request }) => {
    const body = (await request.json()) as {
      email: string;
      password: string;
      password_confirmation: string;
      role: string;
      name: string;
    };
    const strong =
      body.password.length >= 12 &&
      /[a-z]/.test(body.password) &&
      /[A-Z]/.test(body.password) &&
      /\d/.test(body.password) &&
      /[^A-Za-z0-9]/.test(body.password);
    if (!strong) {
      return HttpResponse.json(
        {
          message: "The given data was invalid.",
          code: "validation_failed",
          errors: { password: ["Password does not meet complexity requirements."] },
        },
        { status: 422 },
      );
    }
    return HttpResponse.json(
      {
        message: "Administrator account created.",
        code: "admin_created",
        data: { ...mockReviewer, id: "admin-3", name: body.name, email: body.email },
      },
      { status: 201 },
    );
  }),
];
