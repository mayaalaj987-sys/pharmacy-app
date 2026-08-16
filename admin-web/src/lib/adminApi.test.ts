import { describe, expect, it, beforeEach } from "vitest";
import { http, HttpResponse } from "msw";
import { server } from "@/test/mswServer";
import { adminRequest, AdminApiError } from "@/lib/adminApi";

const BASE = "http://localhost:8000";

function clearCookies() {
  document.cookie.split(";").forEach((c) => {
    const name = c.split("=")[0].trim();
    if (name) document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/`;
  });
}

describe("adminRequest", () => {
  beforeEach(() => clearCookies());

  it("bootstraps CSRF before a state-changing request and sends the decoded token as X-XSRF-TOKEN", async () => {
    let csrfCalls = 0;
    let receivedHeader: string | null = null;
    server.use(
      http.get(`${BASE}/api/admin/csrf`, () => {
        csrfCalls += 1;
        document.cookie = "XSRF-TOKEN=hello%20world; path=/";
        return HttpResponse.json({ message: "ok", code: "csrf_ready" });
      }),
      http.post(`${BASE}/api/admin/login`, ({ request }) => {
        receivedHeader = request.headers.get("X-XSRF-TOKEN");
        return HttpResponse.json({ message: "ok", code: "admin_authenticated", data: {} });
      }),
    );

    await adminRequest("/api/admin/login", {
      method: "POST",
      body: { email: "a@b.com", password: "x" },
    });

    expect(csrfCalls).toBe(1);
    expect(receivedHeader).toBe("hello world");
  });

  it("does not call the CSRF endpoint for GET requests", async () => {
    let csrfCalls = 0;
    server.use(
      http.get(`${BASE}/api/admin/csrf`, () => {
        csrfCalls += 1;
        return HttpResponse.json({ message: "ok", code: "csrf_ready" });
      }),
      http.get(`${BASE}/api/admin/session`, () =>
        HttpResponse.json({ message: "ok", code: "admin_session_active", data: {} }),
      ),
    );

    await adminRequest("/api/admin/session");
    expect(csrfCalls).toBe(0);
  });

  it("retries exactly once on a 419 and then surfaces the error instead of looping forever", async () => {
    let postAttempts = 0;
    server.use(
      http.get(`${BASE}/api/admin/csrf`, () => {
        document.cookie = "XSRF-TOKEN=token; path=/";
        return HttpResponse.json({ message: "ok", code: "csrf_ready" });
      }),
      http.post(`${BASE}/api/admin/review/applications/1/approve`, () => {
        postAttempts += 1;
        return HttpResponse.json(
          { message: "The CSRF token is missing or expired.", code: "csrf_token_mismatch" },
          { status: 419 },
        );
      }),
    );

    await expect(
      adminRequest("/api/admin/review/applications/1/approve", {
        method: "POST",
        body: { review_version: 1 },
      }),
    ).rejects.toMatchObject({
      code: "csrf_token_mismatch",
    });
    // Initial attempt + exactly one bounded retry, never more.
    expect(postAttempts).toBe(2);
  });

  it("surfaces server error codes as AdminApiError with status and code", async () => {
    server.use(
      http.get(`${BASE}/api/admin/csrf`, () =>
        HttpResponse.json({ message: "ok", code: "csrf_ready" }),
      ),
      http.get(`${BASE}/api/admin/session`, () =>
        HttpResponse.json(
          { message: "Unauthenticated.", code: "unauthenticated" },
          { status: 401 },
        ),
      ),
    );

    await expect(adminRequest("/api/admin/session")).rejects.toThrow(AdminApiError);
    try {
      await adminRequest("/api/admin/session");
      throw new Error("expected rejection");
    } catch (error) {
      expect(error).toBeInstanceOf(AdminApiError);
      expect((error as AdminApiError).code).toBe("unauthenticated");
      expect((error as AdminApiError).status).toBe(401);
    }
  });
});
