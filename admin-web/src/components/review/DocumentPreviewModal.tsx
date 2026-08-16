import { useEffect, useState } from "react";
import { X, AlertTriangle, Loader2 } from "lucide-react";
import { fetchAdminDocumentBlob, AdminApiError } from "@/lib/adminApi";
import type { PharmacyDocument } from "@/lib/types";

interface Props {
  document: PharmacyDocument;
  onClose: () => void;
}

/**
 * Fetches the private document bytes through the authenticated endpoint and renders them
 * from a short-lived object URL. The URL is revoked on close/unmount so bytes are never
 * left addressable after the viewer closes.
 */
export function DocumentPreviewModal({ document, onClose }: Props) {
  const [objectUrl, setObjectUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    let url: string | null = null;
    fetchAdminDocumentBlob(document.preview_url, controller.signal)
      .then((blob) => {
        url = URL.createObjectURL(blob);
        setObjectUrl(url);
      })
      .catch((err) => {
        if (err instanceof AdminApiError) {
          setError(
            err.code === "document_unavailable"
              ? "This document is no longer available."
              : err.message,
          );
        } else if (!(err instanceof DOMException && err.name === "AbortError")) {
          setError("Could not load the document preview.");
        }
      });
    return () => {
      controller.abort();
      if (url) URL.revokeObjectURL(url);
    };
  }, [document.preview_url]);

  return (
    <div
      className="fixed inset-0 z-[95] bg-black/85 backdrop-blur-sm grid place-items-center p-8"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
    >
      <div
        className="max-w-3xl w-full max-h-[85vh] rounded-2xl bg-card border overflow-hidden relative flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between p-4 border-b">
          <p className="text-sm font-medium capitalize">{document.type} preview</p>
          <button
            onClick={onClose}
            aria-label="Close preview"
            className="w-8 h-8 rounded-lg hover:bg-accent grid place-items-center transition"
          >
            <X size={16} strokeWidth={1.75} />
          </button>
        </div>
        <div className="flex-1 overflow-auto grid place-items-center p-4 min-h-[300px]">
          {error && (
            <div className="text-center text-sm text-red-500 flex flex-col items-center gap-2">
              <AlertTriangle size={20} /> {error}
            </div>
          )}
          {!error && !objectUrl && (
            <div className="text-center text-sm text-muted-foreground flex flex-col items-center gap-2">
              <Loader2 size={20} className="animate-spin" /> Loading preview…
            </div>
          )}
          {!error && objectUrl && document.mime_category === "pdf" && (
            <iframe
              title={`${document.type} preview`}
              src={objectUrl}
              className="w-full h-[70vh] rounded-lg border"
              sandbox=""
            />
          )}
          {!error && objectUrl && document.mime_category === "image" && (
            <img
              src={objectUrl}
              alt={`${document.type} document`}
              className="max-w-full max-h-[70vh] rounded-lg"
            />
          )}
        </div>
      </div>
    </div>
  );
}
