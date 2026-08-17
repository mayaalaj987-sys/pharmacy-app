<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The administrator side of support: read the queue, answer a ticket.
 *
 * Answering is a one-shot resolution rather than a conversation thread, which
 * keeps the contract small; a sender who needs more raises a new ticket.
 */
class AdminSupportTicketController extends Controller
{
    /** Open tickets first and oldest-first within that, so nobody is forgotten. */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSupport($request);

        $paginator = SupportTicket::query()
            ->with(['pharmacist:id,name,email', 'employee:id,name,email', 'pharmacy:id,pharmacy_name', 'responder:id,name'])
            ->when(
                in_array($request->query('status'), [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_RESOLVED], true),
                fn ($query) => $query->where('status', $request->query('status')),
            )
            // 'open' sorts before 'resolved' alphabetically, which is the order
            // we want; spelled out rather than relied upon.
            ->orderByRaw("case when status = '".SupportTicket::STATUS_OPEN."' then 0 else 1 end")
            ->orderBy('created_at')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (SupportTicket $t) => $this->present($t))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'open_total' => SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count(),
            ],
        ]);
    }

    public function respond(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeSupport($request);

        $validated = $request->validate([
            'response' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $admin = $request->user('admin');

        $updated = DB::transaction(function () use ($ticket, $validated, $admin): ?SupportTicket {
            $locked = SupportTicket::query()->lockForUpdate()->find($ticket->id);

            // Two administrators can open the same ticket; the second one must
            // not overwrite the first one's answer.
            if ($locked === null || ! $locked->isOpen()) {
                return null;
            }

            $locked->update([
                'admin_response' => $validated['response'],
                'status' => SupportTicket::STATUS_RESOLVED,
                'responded_by_admin_id' => $admin->getKey(),
                'responded_at' => now(),
            ]);

            return $locked;
        });

        if ($updated === null) {
            return response()->json([
                'message' => 'This ticket has already been answered.',
                'code' => 'support_ticket_already_resolved',
            ], 409);
        }

        return response()->json([
            'message' => 'Response sent.',
            'code' => 'support_ticket_resolved',
            'data' => $this->present($updated->fresh([
                'pharmacist:id,name,email', 'employee:id,name,email', 'pharmacy:id,pharmacy_name', 'responder:id,name',
            ])),
        ]);
    }

    private function authorizeSupport(Request $request): void
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);
    }

    /** @return array<string, mixed> */
    private function present(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'status' => $ticket->status,
            'sender' => [
                'name' => $ticket->senderName(),
                'role' => $ticket->senderRole(),
                'email' => $ticket->pharmacist?->email ?? $ticket->employee?->email,
            ],
            'pharmacy' => $ticket->pharmacy?->pharmacy_name,
            'admin_response' => $ticket->admin_response,
            'responded_by' => $ticket->responder?->name,
            'responded_at' => $ticket->responded_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }
}
