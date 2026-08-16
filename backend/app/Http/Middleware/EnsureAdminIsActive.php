<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\AdminAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authenticated = $request->user('admin');
        $admin = $authenticated instanceof Admin ? Admin::query()->find($authenticated->id) : null;
        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthenticated.', 'code' => 'unauthenticated'], 401);
        }
        Auth::guard('admin')->setUser($admin);
        $request->setUserResolver(fn (?string $guard = null) => $guard === 'admin' || $guard === null ? $admin : Auth::guard($guard)->user());

        if (! $admin->is_active) {
            try {
                $this->audit->record($request, $admin, 'admin.session.denied', 'denied', reason: 'account_disabled');
            } finally {
                $this->endSession($request);
            }

            return response()->json([
                'message' => 'This administrator account is disabled.',
                'code' => 'account_disabled',
            ], 403);
        }

        if ((int) $request->session()->get('admin_auth_version', 0) !== (int) $admin->auth_version) {
            try {
                $this->audit->record($request, $admin, 'admin.session.denied', 'denied', reason: 'session_expired');
            } finally {
                $this->endSession($request);
            }

            return response()->json([
                'message' => 'The administrator session has expired.',
                'code' => 'session_expired',
            ], 401);
        }

        return $next($request);
    }

    private function endSession(Request $request): void
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
