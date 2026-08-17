<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Announcements an administrator pushes to pharmacies.
 *
 * Reuses the existing per-pharmacy `notifications` table rather than adding a
 * broadcast mechanism of its own: an announcement is simply one notification
 * row per recipient, so it shows up in the app's existing list and unread
 * badge with no client change.
 *
 * Who sent what is not stored on the row. Accountability lives in the append
 * only admin audit log, which already records this route.
 */
class AdminNotificationController extends Controller
{
    public const TYPE = 'admin_announcement';

    /** Pharmacies that would receive a broadcast right now. */
    public function audience(Request $request): JsonResponse
    {
        $this->authorizeAnnouncements($request);

        return response()->json([
            'data' => [
                'recipients' => Pharmacy::where('status', 'approved')->count(),
                'pharmacies' => Pharmacy::where('status', 'approved')
                    ->orderBy('pharmacy_name')
                    ->get(['id', 'pharmacy_name'])
                    ->map(fn (Pharmacy $pharmacy) => [
                        'id' => $pharmacy->id,
                        'name' => $pharmacy->pharmacy_name,
                    ]),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAnnouncements($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:500'],
            'target' => ['required', 'in:all,pharmacy'],
            'pharmacy_id' => ['required_if:target,pharmacy', 'nullable', 'integer', 'exists:pharmacies,id'],
        ]);

        $recipients = $this->recipients($validated);

        if ($recipients->isEmpty()) {
            return response()->json([
                'message' => 'There are no approved pharmacies to notify.',
                'code' => 'announcement_no_recipients',
            ], 422);
        }

        // One transaction: a half-delivered announcement would be worse than
        // none, because the sender has no way to tell who missed it.
        DB::transaction(function () use ($recipients, $validated): void {
            $now = now();
            Notification::insert(
                $recipients->map(fn (int $pharmacyId) => [
                    'pharmacy_id' => $pharmacyId,
                    'title' => $validated['title'],
                    'message' => $validated['message'],
                    'type' => self::TYPE,
                    'is_read' => false,
                    'date' => $now->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        });

        return response()->json([
            'message' => 'Announcement sent to '.$recipients->count().' pharmacy(ies).',
            'code' => 'announcement_sent',
            'data' => ['recipients' => $recipients->count()],
        ], 201);
    }

    /**
     * Approved pharmacies only. A pending or rejected pharmacy cannot operate,
     * so its owner would never see the message.
     *
     * @param  array<string, mixed>  $validated
     * @return Collection<int, int>
     */
    private function recipients(array $validated): Collection
    {
        return Pharmacy::query()
            ->where('status', 'approved')
            ->when(
                $validated['target'] === 'pharmacy',
                fn ($query) => $query->where('id', $validated['pharmacy_id']),
            )
            ->pluck('id');
    }

    private function authorizeAnnouncements(Request $request): void
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);
    }
}
