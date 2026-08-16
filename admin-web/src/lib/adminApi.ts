import { AdminApiError, AdminApiAbortError } from "@/lib/errors";
import { parseErrorFromResponse } from "@/lib/parseError";

const BASE_URL = (import.meta.env.VITE_ADMIN_API_BASE_URL ?? "http://localhost:8000").replace(
  /\/+$/,
  "",
);

export { AdminApiError, AdminApiAbortError };

function readCookie(name: string): string | null {
  const match = document.cookie.match(
    new RegExp(`(?:^|; )${name.replace(/[.$?*|{}()[\]\\/+^]/g, "\\$&")}=([^;]*)`),
  );
  return match ? decodeURIComponent(match[1]) : null;
}

function isStateChanging(method: string): boolean {
  return method !== "GET" && method !== "HEAD";
}

async function ensureCsrfCookie(signal?: AbortSignal): Promise<void> {
  if (readCookie("XSRF-TOKEN")) return;
  await fetch(`${BASE_URL}/api/admin/csrf`, {
    method: "GET",
    credentials: "include",
    headers: { Accept: "application/json" },
    signal,
  });
}

interface RequestOptions {
  method?: string;
  body?: unknown;
  signal?: AbortSignal;
  /** Internal: prevents infinite recursion on the bounded CSRF retry. */
  _isRetry?: boolean;
}

/**
 * Credentialed JSON request against the Gate 1C admin API.
 * Applies exactly one bounded CSRF refresh+retry on a 419 for state-changing methods,
 * per Gate 1D's "no infinite retry loops" requirement.
 */
export async function adminRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? "GET";
  if (isStateChanging(method)) {
    await ensureCsrfCookie(options.signal);
  }

  const headers: Record<string, string> = { Accept: "application/json" };
  if (options.body !== undefined) headers["Content-Type"] = "application/json";
  const xsrfToken = readCookie("XSRF-TOKEN");
  if (isStateChanging(method) && xsrfToken) headers["X-XSRF-TOKEN"] = xsrfToken;

  let response: Response;
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      method,
      credentials: "include",
      headers,
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
      signal: options.signal,
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      throw new AdminApiAbortError();
    }
    throw new AdminApiError(
      "Could not reach the server. Check your connection and try again.",
      "network_error",
      0,
    );
  }

  if (response.status === 419 && !options._isRetry && isStateChanging(method)) {
    await ensureCsrfCookie(options.signal);
    return adminRequest<T>(path, { ...options, _isRetry: true });
  }

  if (!response.ok) {
    throw await parseErrorFromResponse(response);
  }

  if (response.status === 204) return undefined as T;
  return (await response.json()) as T;
}

/** Fetches a private document as a Blob through the authenticated, credentialed endpoint. Never derive this URL from client input. */
export async function fetchAdminDocumentBlob(
  relativeUrl: string,
  signal?: AbortSignal,
): Promise<Blob> {
  const response = await fetch(`${BASE_URL}${relativeUrl}`, {
    method: "GET",
    credentials: "include",
    headers: { Accept: "*/*" },
    signal,
  });
  if (!response.ok) {
    throw await parseErrorFromResponse(response);
  }
  return response.blob();
}

export { BASE_URL as ADMIN_API_BASE_URL };
