<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Recent administrator activity, read out of the append-only audit log.
 *
 * The log records every admin request, including reads. Those are noise in a
 * feed, so only actions that changed something are surfaced — plus anything
 * that was denied, which is worth seeing precisely because it failed.
 *
 * Nothing here can be edited or removed: database triggers make the underlying
 * table append-only.
 */
class AdminActivityController extends Controller
{
    /** Route names that only read; they never belong in an activity feed. */
    private const READ_ONLY_SUFFIXES = [
        '.index', '.show', '.current', '.audience', '.preview', '.csrf',
    ];

    /** Human phrasing for the actions worth reporting. */
    private const LABELS = [
        'pharmacy.review.approved' => 'approved a pharmacy',
        'pharmacy.review.rejected' => 'rejected a pharmacy',
        'pharmacy.document.downloaded' => 'downloaded a document',
        'admin.login.succeeded' => 'signed in',
        'admin.login.failed' => 'failed to sign in',
        'admin.logout' => 'signed out',
        'admin.password.reset' => 'reset an administrator password',
        'admin.accounts.store' => 'created an administrator',
        'admin.accounts.role' => 'changed an administrator role',
        'admin.accounts.disable' => 'disabled an administrator',
        'admin.accounts.reactivate' => 'reactivated an administrator',
        'admin.announcements.store' => 'sent an announcement',
        'admin.support.tickets.respond' => 'answered a support ticket',
        'admin.review.applications.approve' => 'approved an application',
        'admin.review.applications.reject' => 'rejected an application',
    ];

    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('viewAnyReview', Pharmacy::class);

        $limit = min(50, max(1, (int) $request->integer('limit', 15)));

        $entries = AdminAuditLog::query()
            ->with('admin:id,name')
            ->where(fn ($query) => $query
                // Anything that changed something…
                ->where(fn ($inner) => collect(self::READ_ONLY_SUFFIXES)
                    ->each(fn (string $suffix) => $inner->where('action', 'not like', '%'.$suffix)))
                // …or anything that was refused.
                ->orWhere('outcome', '!=', 'success'))
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $entries->map(fn (AdminAuditLog $entry) => [
                'id' => $entry->id,
                'actor' => $entry->admin?->name ?? 'System',
                'action' => $entry->action,
                'label' => $this->label($entry),
                'outcome' => $entry->outcome,
                'target' => $this->target($entry),
                'reason' => $entry->reason,
                'logged_at' => $entry->logged_at?->toIso8601String(),
            ]),
        ]);
    }

    private function label(AdminAuditLog $entry): string
    {
        $label = self::LABELS[$entry->action] ?? $this->humanise($entry->action);

        return $entry->outcome === 'success' ? $label : $label.' (denied)';
    }

    /**
     * Fallback for an action with no phrasing yet: turn
     * `admin.something.happened` into `something happened`.
     */
    private function humanise(string $action): string
    {
        $parts = explode('.', $action);
        if ($parts[0] === 'admin' || $parts[0] === 'pharmacy') {
            array_shift($parts);
        }

        return str_replace('_', ' ', implode(' ', $parts));
    }

    private function target(AdminAuditLog $entry): ?string
    {
        if ($entry->target_type === null) {
            return null;
        }

        $type = class_basename($entry->target_type);

        return $entry->target_id === null ? $type : $type.' #'.$entry->target_id;
    }
}
