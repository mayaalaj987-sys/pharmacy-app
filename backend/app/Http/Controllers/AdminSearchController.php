<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Console-wide search behind the command palette.
 *
 * Searches only what an administrator acts on: pharmacies and support tickets.
 * Deliberately not medicines, sales or stock — those belong to a pharmacy and
 * an administrator has no business browsing them.
 */
class AdminSearchController extends Controller
{
    private const PER_GROUP = 5;

    private const MIN_QUERY = 2;

    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);

        $query = trim((string) $request->query('q', ''));

        // A single character matches almost everything and is never a search.
        if (mb_strlen($query) < self::MIN_QUERY) {
            return response()->json([
                'data' => ['query' => $query, 'pharmacies' => [], 'tickets' => []],
            ]);
        }

        $like = '%'.$query.'%';

        $pharmacies = Pharmacy::query()
            ->with('pharmacist:id,name')
            ->where(fn ($inner) => $inner
                ->where('pharmacy_name', 'like', $like)
                ->orWhere('pharmacy_address', 'like', $like))
            ->orderBy('pharmacy_name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Pharmacy $pharmacy) => [
                'id' => $pharmacy->id,
                'title' => $pharmacy->pharmacy_name,
                'detail' => trim(($pharmacy->pharmacist?->name ?? 'Unknown owner').' · '.$pharmacy->pharmacy_address),
                'status' => $pharmacy->isBlocked() ? 'blocked' : $pharmacy->status,
            ]);

        $tickets = SupportTicket::query()
            ->with(['pharmacist:id,name', 'employee:id,name'])
            ->where(fn ($inner) => $inner
                ->where('subject', 'like', $like)
                ->orWhere('message', 'like', $like))
            ->orderByRaw("case when status = '".SupportTicket::STATUS_OPEN."' then 0 else 1 end")
            ->latest('created_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (SupportTicket $ticket) => [
                'id' => $ticket->id,
                'title' => $ticket->subject,
                'detail' => $ticket->senderName(),
                'status' => $ticket->status,
            ]);

        return response()->json([
            'data' => [
                'query' => $query,
                'pharmacies' => $pharmacies,
                'tickets' => $tickets,
            ],
        ]);
    }
}
