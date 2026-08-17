<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * What is waiting for an administrator right now.
 *
 * Derived from outstanding work rather than stored as an inbox. There is no
 * table, and deliberately no "mark as read": a pharmacy application that is
 * still pending is still pending whether or not somebody glanced at the bell.
 * Dismissing it would hide real work, and a stored copy could drift out of step
 * with the queue it describes.
 *
 * The count therefore always equals what the Verification and Support screens
 * will show when opened.
 */
class AdminInboxController extends Controller
{
    /** Individual entries listed in the dropdown before it collapses to counts. */
    private const PREVIEW_PER_GROUP = 5;

    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);

        $pendingPharmacies = Pharmacy::query()
            ->with('pharmacist:id,name')
            ->where('status', 'pending')
            ->latest('created_at')
            ->get();

        $openTickets = SupportTicket::query()
            ->with(['pharmacist:id,name', 'employee:id,name'])
            ->where('status', SupportTicket::STATUS_OPEN)
            ->latest('created_at')
            ->get();

        $items = $pendingPharmacies
            ->take(self::PREVIEW_PER_GROUP)
            ->map(fn (Pharmacy $pharmacy) => [
                'id' => 'pharmacy-'.$pharmacy->id,
                'kind' => 'pharmacy_application',
                'title' => $pharmacy->pharmacy_name,
                'detail' => 'Applied for verification'
                    .($pharmacy->pharmacist?->name ? ' · '.$pharmacy->pharmacist->name : ''),
                'at' => $pharmacy->created_at?->toIso8601String(),
            ])
            ->concat(
                $openTickets
                    ->take(self::PREVIEW_PER_GROUP)
                    ->map(fn (SupportTicket $ticket) => [
                        'id' => 'ticket-'.$ticket->id,
                        'kind' => 'support_ticket',
                        'title' => $ticket->subject,
                        'detail' => 'Support request · '.$ticket->senderName(),
                        'at' => $ticket->created_at?->toIso8601String(),
                    ]),
            )
            // Newest first across both kinds, so the bell reads chronologically.
            ->sortByDesc('at')
            ->values();

        return response()->json([
            'data' => [
                'total' => $pendingPharmacies->count() + $openTickets->count(),
                'groups' => [
                    'pharmacy_applications' => $pendingPharmacies->count(),
                    'support_tickets' => $openTickets->count(),
                ],
                'items' => $items,
            ],
        ]);
    }
}
