import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useRef, useState } from "react";
import { Inbox, MailOpen, User } from "lucide-react";
import { AdminApiError } from "@/lib/adminApi";
import { fetchTickets, respondToTicket } from "@/lib/supportApi";
import type { SupportTicket } from "@/lib/types";
import { StatusBadge } from "@/components/ui-custom/StatusBadge";
import { useToast } from "@/components/ui-custom/Toast";

const PER_PAGE = 25;

type Filter = "all" | "open" | "resolved";

/**
 * Support queue: pick a ticket on the left, answer it on the right.
 *
 * Answering resolves the ticket. A 409 means another administrator got there
 * first, which is reported plainly rather than retried.
 */
export function TicketsView() {
  const [filter, setFilter] = useState<Filter>("open");
  const [page, setPage] = useState(1);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const queryClient = useQueryClient();
  const { push } = useToast();

  const query = useQuery({
    queryKey: ["support", "tickets", filter, page],
    queryFn: ({ signal }) => fetchTickets(page, PER_PAGE, filter, signal),
  });

  if (query.isPending) {
    return (
      <div role="status" className="text-sm text-muted-foreground">
        Loading tickets…
      </div>
    );
  }

  if (query.isError) {
    const message =
      query.error instanceof AdminApiError ? query.error.message : "Unable to load tickets.";
    return (
      <div role="alert" className="text-sm text-red-600">
        {message}
        <button
          onClick={() => void query.refetch()}
          className="ml-3 h-8 px-3 rounded-lg border text-foreground hover:bg-accent transition"
        >
          Retry
        </button>
      </div>
    );
  }

  const tickets = query.data.data;
  const selected = tickets.find((t) => t.id === selectedId) ?? null;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h2 className="text-lg font-semibold">Support</h2>
          <p className="text-xs text-muted-foreground mt-0.5">
            {query.data.meta.open_total} open of {query.data.meta.total} total
          </p>
        </div>
        <div className="flex items-center gap-1 bg-card border rounded-xl p-1 shadow-soft">
          {(["open", "resolved", "all"] as const).map((value) => (
            <button
              key={value}
              onClick={() => {
                setFilter(value);
                setPage(1);
                setSelectedId(null);
              }}
              className={`h-8 px-3 rounded-lg text-xs font-medium capitalize transition ${
                filter === value
                  ? "bg-primary text-primary-foreground"
                  : "text-muted-foreground hover:text-foreground"
              }`}
            >
              {value}
            </button>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,22rem)_1fr] gap-4">
        <section className="bg-card rounded-2xl border shadow-soft overflow-hidden">
          <h3 className="px-4 py-3 border-b text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Queue
          </h3>
          {tickets.length === 0 ? (
            <p className="p-8 text-center text-xs text-muted-foreground">
              {query.data.meta.total === 0
                ? "No one has contacted support yet. Messages sent from the mobile app land here."
                : filter === "open"
                  ? "Every ticket has been answered."
                  : "No tickets match this filter."}
            </p>
          ) : (
            <ul className="divide-y max-h-[32rem] overflow-y-auto">
              {tickets.map((ticket) => (
                <li key={ticket.id}>
                  <button
                    onClick={() => setSelectedId(ticket.id)}
                    aria-current={selectedId === ticket.id}
                    className={`w-full text-left px-4 py-3 transition hover:bg-accent ${
                      selectedId === ticket.id ? "bg-accent" : ""
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <span className="text-sm font-medium truncate">{ticket.subject}</span>
                      <StatusBadge status={ticket.status === "open" ? "Pending" : "Active"} />
                    </div>
                    <p className="text-[11px] text-muted-foreground mt-1 truncate">
                      Opened by {ticket.sender.name} · {ticket.sender.role}
                    </p>
                  </button>
                </li>
              ))}
            </ul>
          )}

          {query.data.meta.last_page > 1 && (
            <div className="flex items-center justify-between px-4 py-3 border-t text-xs">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
                className="h-8 px-3 rounded-lg border disabled:opacity-40 hover:bg-accent transition"
              >
                Previous
              </button>
              <span className="text-muted-foreground">
                {query.data.meta.current_page} / {query.data.meta.last_page}
              </span>
              <button
                disabled={page >= query.data.meta.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="h-8 px-3 rounded-lg border disabled:opacity-40 hover:bg-accent transition"
              >
                Next
              </button>
            </div>
          )}
        </section>

        <section className="bg-card rounded-2xl border shadow-soft p-6">
          {selected ? (
            <TicketDetail
              ticket={selected}
              onResolved={() => {
                void queryClient.invalidateQueries({
                  queryKey: ["support", "tickets"],
                });
              }}
              push={push}
            />
          ) : (
            <div className="h-full min-h-[18rem] grid place-items-center text-center">
              <div>
                <Inbox size={32} strokeWidth={1.5} className="mx-auto text-muted-foreground" />
                <p className="mt-3 text-sm text-muted-foreground">Select a ticket to view</p>
              </div>
            </div>
          )}
        </section>
      </div>
    </div>
  );
}

function TicketDetail({
  ticket,
  onResolved,
  push,
}: {
  ticket: SupportTicket;
  onResolved: () => void;
  push: ReturnType<typeof useToast>["push"];
}) {
  const [response, setResponse] = useState("");
  const [submitting, setSubmitting] = useState(false);
  // A ref, not state: two clicks in one tick must not both get through.
  const inFlight = useRef(false);

  async function submit() {
    const trimmed = response.trim();
    if (trimmed.length < 5) {
      push({
        title: "Response too short",
        description: "Write at least 5 characters.",
        variant: "error",
      });
      return;
    }
    if (inFlight.current) return;
    inFlight.current = true;
    setSubmitting(true);

    try {
      await respondToTicket(ticket.id, trimmed);
      push({
        title: "Response sent",
        description: "The ticket is resolved.",
        variant: "success",
      });
      setResponse("");
      onResolved();
    } catch (error) {
      const message =
        error instanceof AdminApiError && error.code === "support_ticket_already_resolved"
          ? "Another administrator already answered this ticket."
          : error instanceof AdminApiError
            ? error.message
            : "Unable to send the response.";
      push({ title: "Not sent", description: message, variant: "error" });
      onResolved();
    } finally {
      inFlight.current = false;
      setSubmitting(false);
    }
  }

  return (
    <div className="space-y-5">
      <header>
        <div className="flex items-start justify-between gap-3">
          <h3 className="text-base font-semibold">{ticket.subject}</h3>
          <StatusBadge status={ticket.status === "open" ? "Pending" : "Active"} />
        </div>
        <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
          <span className="inline-flex items-center gap-1">
            <User size={12} /> {ticket.sender.name} ({ticket.sender.role})
          </span>
          {ticket.sender.email && <span>{ticket.sender.email}</span>}
          {ticket.pharmacy && <span>{ticket.pharmacy}</span>}
        </p>
      </header>

      <div className="rounded-xl bg-muted/50 p-4">
        <p className="text-sm whitespace-pre-wrap">{ticket.message}</p>
      </div>

      {ticket.status === "resolved" ? (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
          <p className="text-xs font-semibold text-emerald-700 flex items-center gap-1.5">
            <MailOpen size={13} /> Answered
            {ticket.responded_by ? ` by ${ticket.responded_by}` : ""}
          </p>
          <p className="mt-2 text-sm whitespace-pre-wrap">{ticket.admin_response}</p>
        </div>
      ) : (
        <div>
          <label htmlFor="ticket-response" className="text-xs font-medium block mb-1.5">
            Your response <span className="text-red-500">*</span>
          </label>
          <textarea
            id="ticket-response"
            value={response}
            onChange={(event) => setResponse(event.target.value)}
            rows={5}
            maxLength={2000}
            placeholder="Answer the pharmacist or employee…"
            className="w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-1 focus:ring-ring"
          />
          <div className="mt-3 flex items-center justify-between">
            <span className="text-[11px] text-muted-foreground">
              Sending marks this ticket resolved.
            </span>
            <button
              onClick={() => void submit()}
              disabled={submitting}
              className="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-sm font-medium disabled:opacity-50 hover:opacity-90 transition"
            >
              {submitting ? "Sending…" : "Send response"}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
