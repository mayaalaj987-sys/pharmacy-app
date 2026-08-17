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
 * The log records every admin request, including every page load. Those are
 * noise in a feed, so this is an allowlist: an action appears only if it is
 * listed in {@see self::LABELS}, which is by definition the set of things an
 * administrator deliberately *did*.
 *
 * An allowlist rather than a denylist of read suffixes, because a denylist
 * silently leaks: `admin.analytics.overview` ends in none of the usual read
 * suffixes and flooded the feed with page visits until this was tightened.
 *
 * Denied attempts at these same actions are kept — a refused approval is worth
 * seeing precisely because it failed.
 *
 * The audit log itself still records everything. This only decides what the
 * feed shows; nothing is edited or removed, and database triggers make the
 * underlying table append-only.
 */
class AdminActivityController extends Controller
{
    /**
     * The only actions the feed reports, with their phrasing.
     *
     * Adding a route does not add it here: a new entry is a deliberate decision
     * that the action is worth an administrator's attention.
     */
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
            ->whereIn('action', array_keys(self::LABELS))
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
        $label = self::LABELS[$entry->action];

        return $entry->outcome === 'success' ? $label : $label.' (denied)';
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
