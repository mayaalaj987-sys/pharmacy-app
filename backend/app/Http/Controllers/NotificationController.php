<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    public function getNotifications(Request $request): JsonResponse
    {
        $request->validate(['pharmacy_id' => 'required|exists:pharmacies,id']);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;
        // Capped. This used to fetch every notification the pharmacy had ever
        // received and count the unread ones in PHP, which grew slower every
        // month the app ran — and nobody scrolls back a thousand rows.
        $notifications = Notification::where('pharmacy_id', $pharmacyId)
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            // Counted in the database, not over the page, so the badge stays
            // right once there are more than a hundred.
            'unread_count' => Notification::where('pharmacy_id', $pharmacyId)
                ->where('is_read', false)
                ->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $this->pharmacyContext->assertMatches($request, (int) $notification->pharmacy_id);
        Gate::forUser($request->user())->authorize('update', $notification);
        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'تم تعليم الإشعار كمقروء']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->validate(['pharmacy_id' => 'required|exists:pharmacies,id']);
        $pharmacyId = $this->pharmacyContext->resolve($request)->id;
        Notification::where('pharmacy_id', $pharmacyId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'تم تعليم كل الإشعارات كمقروءة']);
    }

    public function deleteNotification(Request $request, int $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $this->pharmacyContext->assertMatches($request, (int) $notification->pharmacy_id);
        Gate::forUser($request->user())->authorize('delete', $notification);
        $notification->delete();

        return response()->json(['message' => 'تم حذف الإشعار']);
    }
}
