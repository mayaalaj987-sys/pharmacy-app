import { ADMIN_API_BASE_URL } from "@/lib/adminApi";
import { parseErrorFromResponse } from "@/lib/parseError";

function filenameFromContentDisposition(header: string | null, fallback: string): string {
  if (!header) return fallback;
  const utf8Match = header.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match) return decodeURIComponent(utf8Match[1]);
  const plainMatch = header.match(/filename="?([^";]+)"?/i);
  return plainMatch ? plainMatch[1] : fallback;
}

/** Downloads a private document through the authenticated endpoint. Never derive the URL from client input. */
export async function downloadAdminDocument(
  relativeUrl: string,
  fallbackName: string,
  signal?: AbortSignal,
): Promise<void> {
  const response = await fetch(`${ADMIN_API_BASE_URL}${relativeUrl}`, {
    method: "GET",
    credentials: "include",
    headers: { Accept: "*/*" },
    signal,
  });
  if (!response.ok) {
    throw await parseErrorFromResponse(response);
  }
  const blob = await response.blob();
  const filename = filenameFromContentDisposition(
    response.headers.get("Content-Disposition"),
    fallbackName,
  );
  const objectUrl = URL.createObjectURL(blob);
  try {
    const anchor = document.createElement("a");
    anchor.href = objectUrl;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
}
