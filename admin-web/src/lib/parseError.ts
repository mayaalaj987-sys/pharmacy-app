import { AdminApiError } from "@/lib/errors";

export async function parseErrorFromResponse(response: Response): Promise<AdminApiError> {
  const requestId = response.headers.get("X-Request-ID") ?? undefined;
  let payload: { message?: string; code?: string; errors?: Record<string, string[]> } = {};
  try {
    payload = await response.json();
  } catch {
    // Non-JSON error body (e.g. an infrastructure-level 502); fall through to a generic error.
  }
  const message = payload.message ?? "The request failed.";
  const code = payload.code ?? (response.status === 419 ? "csrf_token_mismatch" : "unknown_error");
  return new AdminApiError(message, code, response.status, payload.errors, requestId);
}
