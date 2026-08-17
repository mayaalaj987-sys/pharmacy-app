import { AnimatePresence, motion } from "framer-motion";
import { Check, X, FileText, Image as ImageIcon, Eye, Download, AlertCircle } from "lucide-react";
import { useRef, useState } from "react";
import { StatusBadge } from "@/components/ui-custom/StatusBadge";
import { DocumentPreviewModal } from "@/components/review/DocumentPreviewModal";
import { AdminApiError } from "@/lib/adminApi";
import { downloadAdminDocument } from "@/lib/download";
import { approveApplication, fetchApplication, rejectApplication } from "@/lib/reviewApi";
import type { PharmacyApplication, PharmacyDocument } from "@/lib/types";
import { exactTime } from "@/lib/relativeTime";

type ToastPush = (t: {
  variant: "success" | "error" | "info";
  title: string;
  description?: string;
}) => void;

interface Props {
  application: PharmacyApplication | null;
  onClose: () => void;
  onDecided: (updated: PharmacyApplication) => void;
  onToast: ToastPush;
}

function statusLabel(status: PharmacyApplication["status"]): "Pending" | "Active" | "Blocked" {
  if (status === "approved") return "Active";
  if (status === "rejected") return "Blocked";
  return "Pending";
}

function decisionErrorMessage(error: unknown): string {
  if (error instanceof AdminApiError) {
    switch (error.code) {
      case "review_version_conflict":
        return "This application changed since you opened it. Refreshed with the latest state — please review and try again.";
      case "review_already_finalized":
        return "This application was already finalized with a different decision.";
      case "legal_documents_required":
        return "Valid pending certificate and license documents are required before approval.";
      case "rejection_reason_invalid":
        return "Enter a rejection reason between 5 and 500 characters.";
      case "forbidden":
        return "You are not authorized to perform this action.";
      default:
        return error.message || "The decision could not be applied.";
    }
  }
  return "The decision could not be applied.";
}

export function ReviewDrawer({ application, onClose, onDecided, onToast }: Props) {
  const [rejecting, setRejecting] = useState(false);
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState<"approve" | "reject" | null>(null);
  const [previewDoc, setPreviewDoc] = useState<PharmacyDocument | null>(null);
  // Synchronous guard: `busy` is React state and can't prevent two clicks in the same tick.
  const busyRef = useRef(false);

  if (!application) return null;

  const refreshAfterConflict = async () => {
    try {
      const res = await fetchApplication(application.id);
      if (res.data) onDecided(res.data);
    } catch {
      // Best-effort refresh; the list query will still resync on next view.
    }
  };

  const approve = async () => {
    if (busyRef.current) return;
    busyRef.current = true;
    setBusy("approve");
    try {
      const res = await approveApplication(application.id, application.review_version);
      if (res.data) onDecided(res.data);
      onToast({
        variant: "success",
        title: res.code === "review_already_applied" ? "Already approved" : "Application approved",
        description: `${application.name} ${res.code === "review_already_applied" ? "was already approved." : "has been approved."}`,
      });
      if (res.code !== "review_already_applied") onClose();
    } catch (error) {
      onToast({
        variant: "error",
        title: "Approval failed",
        description: decisionErrorMessage(error),
      });
      if (
        error instanceof AdminApiError &&
        (error.code === "review_version_conflict" || error.code === "review_already_finalized")
      ) {
        await refreshAfterConflict();
      }
    } finally {
      busyRef.current = false;
      setBusy(null);
    }
  };

  const reject = async () => {
    if (busyRef.current) return;
    const normalized = reason.trim();
    if (normalized.length < 5 || normalized.length > 500) {
      onToast({
        variant: "error",
        title: "Invalid reason",
        description: "Enter a rejection reason between 5 and 500 characters.",
      });
      return;
    }
    busyRef.current = true;
    setBusy("reject");
    try {
      const res = await rejectApplication(application.id, application.review_version, normalized);
      if (res.data) onDecided(res.data);
      onToast({
        variant: "success",
        title: res.code === "review_already_applied" ? "Already rejected" : "Application rejected",
        description: `${application.name} ${res.code === "review_already_applied" ? "was already rejected." : "has been rejected."}`,
      });
      if (res.code !== "review_already_applied") {
        setRejecting(false);
        setReason("");
        onClose();
      }
    } catch (error) {
      onToast({
        variant: "error",
        title: "Rejection failed",
        description: decisionErrorMessage(error),
      });
      if (
        error instanceof AdminApiError &&
        (error.code === "review_version_conflict" || error.code === "review_already_finalized")
      ) {
        await refreshAfterConflict();
      }
    } finally {
      busyRef.current = false;
      setBusy(null);
    }
  };

  const documents = application.documents ?? [];
  const isFinalized = application.status !== "pending";

  return (
    <AnimatePresence>
      <motion.div
        key="overlay"
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        className="fixed inset-0 z-40 bg-brand-ink/40 backdrop-blur-[2px]"
        onClick={onClose}
      />
      <motion.aside
        key="drawer"
        initial={{ x: "100%" }}
        animate={{ x: 0 }}
        exit={{ x: "100%" }}
        transition={{ type: "spring", stiffness: 340, damping: 34 }}
        className="fixed right-0 top-0 h-full w-full max-w-[520px] bg-card border-l z-50 flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-label={`Review ${application.name}`}
      >
        <div className="p-5 border-b flex items-start justify-between">
          <div>
            <p className="label-caption text-muted-foreground">#{application.id}</p>
            <h3 className="text-lg font-semibold tracking-tight mt-1">{application.name}</h3>
            <div className="mt-2">
              <StatusBadge status={statusLabel(application.status)} />
            </div>
          </div>
          <button
            onClick={onClose}
            aria-label="Close"
            className="w-8 h-8 rounded-lg hover:bg-accent grid place-items-center transition"
          >
            <X size={16} strokeWidth={1.75} />
          </button>
        </div>

        <div className="flex-1 overflow-auto scrollbar-thin p-5 space-y-6">
          <div>
            <p className="label-caption text-muted-foreground mb-3">Submitted details</p>
            <dl className="space-y-2 text-sm">
              {[
                ["Owner", application.owner?.name ?? "—"],
                ["Contact email", application.owner?.email ?? "—"],
                ["Contact phone", application.owner?.phone ?? "—"],
                ["Address", application.address],
                ["Submitted", application.submitted_at ? exactTime(application.submitted_at) : "—"],
              ].map(([k, v]) => (
                <div key={k} className="flex justify-between gap-4 py-2 border-b last:border-b-0">
                  <dt className="text-muted-foreground">{k}</dt>
                  <dd className="font-medium text-right">{v}</dd>
                </div>
              ))}
            </dl>
          </div>

          <div>
            <p className="label-caption text-muted-foreground mb-3">Legal documents</p>
            {documents.length === 0 ? (
              <p className="text-sm text-muted-foreground">No documents on file.</p>
            ) : (
              <div className="grid grid-cols-2 gap-3">
                {documents.map((d) => {
                  const Icon = d.mime_category === "pdf" ? FileText : ImageIcon;
                  return (
                    <div key={d.id} className="border rounded-xl p-3 hover:bg-accent/40 transition">
                      <div
                        className={`w-9 h-9 rounded-lg bg-muted grid place-items-center ${d.mime_category === "pdf" ? "text-red-500" : "text-blue-500"}`}
                      >
                        <Icon size={16} strokeWidth={1.75} />
                      </div>
                      <p className="text-xs font-medium mt-2 capitalize">{d.type}</p>
                      <p className="text-[11px] text-muted-foreground">
                        {Math.round(d.size_bytes / 1024)} KB · {d.review_status}
                      </p>
                      <div className="flex items-center gap-1 mt-2 text-muted-foreground">
                        <button
                          onClick={() => setPreviewDoc(d)}
                          aria-label={`Preview ${d.type}`}
                          className="w-7 h-7 rounded-md hover:bg-accent grid place-items-center"
                        >
                          <Eye size={13} strokeWidth={1.75} />
                        </button>
                        <button
                          onClick={() =>
                            void downloadAdminDocument(d.download_url, `${d.type}-${d.id}`)
                          }
                          aria-label={`Download ${d.type}`}
                          className="w-7 h-7 rounded-md hover:bg-accent grid place-items-center"
                        >
                          <Download size={13} strokeWidth={1.75} />
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {isFinalized && (
            <div className="flex items-center gap-2 text-xs text-muted-foreground bg-muted/40 rounded-lg p-3">
              <AlertCircle size={14} /> This application was already {application.status}
              {application.reviewed_at ? ` on ${exactTime(application.reviewed_at)}` : ""}.
            </div>
          )}

          <AnimatePresence>
            {rejecting && !isFinalized && (
              <motion.div
                initial={{ height: 0, opacity: 0 }}
                animate={{ height: "auto", opacity: 1 }}
                exit={{ height: 0, opacity: 0 }}
                className="overflow-hidden"
              >
                <label
                  htmlFor="reject-reason"
                  className="label-caption text-muted-foreground mb-1.5 block"
                >
                  Rejection reason <span className="text-red-500">*</span>
                </label>
                <textarea
                  id="reject-reason"
                  value={reason}
                  onChange={(e) => setReason(e.target.value.slice(0, 500))}
                  rows={3}
                  placeholder="Explain what's missing or invalid…"
                  className="w-full rounded-xl border bg-card p-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-red-500/30"
                />
                <div className="flex justify-between text-[11px] text-muted-foreground mt-1">
                  <span>5–500 characters.</span>
                  <span>{reason.length} / 500</span>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        {!isFinalized && (
          <div className="p-5 border-t bg-surface-elevated flex gap-2">
            {!rejecting ? (
              <>
                <button
                  disabled={busy !== null}
                  onClick={() => setRejecting(true)}
                  className="flex-1 h-10 rounded-xl border text-sm font-medium hover:bg-red-500/5 hover:text-red-600 hover:border-red-500/40 transition inline-flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                  <X size={14} strokeWidth={1.75} /> Reject
                </button>
                <button
                  disabled={busy !== null}
                  onClick={() => void approve()}
                  className="flex-1 h-10 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 text-white text-sm font-medium hover:shadow-glow-emerald transition inline-flex items-center justify-center gap-1.5 disabled:opacity-50"
                >
                  <Check size={14} strokeWidth={2} />{" "}
                  {busy === "approve" ? "Approving…" : "Approve"}
                </button>
              </>
            ) : (
              <>
                <button
                  disabled={busy !== null}
                  onClick={() => {
                    setRejecting(false);
                    setReason("");
                  }}
                  className="flex-1 h-10 rounded-xl border text-sm font-medium hover:bg-accent transition disabled:opacity-50"
                >
                  Cancel
                </button>
                <button
                  disabled={busy !== null || reason.trim().length < 5}
                  onClick={() => void reject()}
                  className="flex-1 h-10 rounded-xl bg-red-500 text-white text-sm font-medium hover:bg-red-600 transition disabled:opacity-50"
                >
                  {busy === "reject" ? "Rejecting…" : "Confirm rejection"}
                </button>
              </>
            )}
          </div>
        )}
      </motion.aside>
      {previewDoc && (
        <DocumentPreviewModal
          key="preview"
          document={previewDoc}
          onClose={() => setPreviewDoc(null)}
        />
      )}
    </AnimatePresence>
  );
}
