<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\SupportTicket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Support tickets from the pharmacist and employee apps.
 *
 * The sender is taken from the authenticated token and never from the request
 * body, so a ticket cannot be filed in someone else's name. A sender only ever
 * sees their own tickets.
 */
class SupportTicketController extends Controller
{
    /** Tickets raised by the caller, newest first. */
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where($this->senderColumn($request), $request->user()->getKey())
            ->latest()
            ->get()
            ->map(fn (SupportTicket $ticket) => $this->present($ticket));

        return response()->json([
            'tickets' => $tickets,
            'open_count' => $tickets->where('status', SupportTicket::STATUS_OPEN)->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            // Identity and lifecycle belong to the server.
            'pharmacist_id' => ['prohibited'],
            'employee_id' => ['prohibited'],
            'status' => ['prohibited'],
            'admin_response' => ['prohibited'],
        ]);

        $user = $request->user();

        $ticket = SupportTicket::create([
            $this->senderColumn($request) => $user->getKey(),
            'pharmacy_id' => $this->pharmacyIdFor($user),
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        return response()->json([
            'message' => 'Your message was sent to support.',
            'code' => 'support_ticket_created',
            'ticket' => $this->present($ticket),
        ], 201);
    }

    /**
     * Which foreign key identifies this caller.
     *
     * @throws AuthorizationException
     */
    private function senderColumn(Request $request): string
    {
        return match (true) {
            $request->user() instanceof Pharmacist => 'pharmacist_id',
            $request->user() instanceof Employee => 'employee_id',
            default => throw new AuthorizationException('Unauthenticated.'),
        };
    }

    /**
     * Best-effort pharmacy context, purely so an administrator can see where
     * the message came from. A pharmacist with several pharmacies is not asked
     * to choose: support is about the account, not one branch.
     */
    private function pharmacyIdFor(Pharmacist|Employee $user): ?int
    {
        if ($user instanceof Employee) {
            return $user->pharmacy_id;
        }

        return $user->pharmacies()->where('status', 'approved')->orderBy('id')->value('id');
    }

    /** @return array<string, mixed> */
    private function present(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'status' => $ticket->status,
            'admin_response' => $ticket->admin_response,
            'responded_at' => $ticket->responded_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }
}
