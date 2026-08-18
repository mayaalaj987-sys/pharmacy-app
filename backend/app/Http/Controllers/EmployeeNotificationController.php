<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The employee's own bell.
 *
 * Separate from {@see NotificationController} because that one sits behind the
 * active-pharmacy gate, which is exactly the thing someone waiting on a job
 * does not have. Every request there resolves a pharmacy first and 403s
 * without one, so an unattached employee could not read a single notification
 * — including the ones about their own job search.
 *
 * One bell covers both kinds: messages addressed to them personally, and their
 * pharmacy's messages once they have a pharmacy. From the wearer's side those
 * are the same inbox.
 */
class EmployeeNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $notifications = $this->visibleTo($employee)->latest()->limit(100)->get();

        return response()->json([
            'unread_count' => $notifications->where('is_read', false)->count(),
            'notifications' => $notifications->map(fn (Notification $notification) => [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'is_read' => $notification->is_read,
                // Which inbox it came from, so the client can badge a personal
                // message differently from pharmacy traffic.
                'personal' => $notification->isPersonal(),
                'created_at' => $notification->created_at?->toISOString(),
            ])->all(),
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        // Scoped by the same query that decides what they can see, so there is
        // no second definition of "yours" to drift out of step with the first.
        $notification = $this->visibleTo($employee)->find($id);

        if ($notification === null) {
            return response()->json([
                'message' => 'Notification not found.',
                'code' => 'notification_not_found',
            ], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marked as read.',
            'code' => 'notification_read',
        ]);
    }

    /**
     * Everything this employee is entitled to see.
     *
     * Their own messages always; their pharmacy's only while they work there,
     * so leaving a job stops the old employer's traffic at the same moment the
     * job ends.
     */
    private function visibleTo(Employee $employee)
    {
        return Notification::query()->where(function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);

            if ($employee->isEmployed()) {
                $query->orWhere(fn ($scoped) => $scoped
                    ->where('pharmacy_id', $employee->pharmacy_id)
                    ->whereNull('employee_id'));
            }
        });
    }
}
