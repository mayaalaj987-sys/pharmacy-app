<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AdminSessionController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function csrf(Request $request): JsonResponse
    {
        $request->session()->token();

        return response()->json([
            'message' => 'CSRF protection is ready.',
            'code' => 'csrf_ready',
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    public function login(AdminLoginRequest $request): JsonResponse
    {
        $admin = Admin::query()->where('email', Admin::normalizeEmail($request->string('email')->toString()))->first();
        if (! $admin || ! Hash::check($request->string('password')->toString(), $admin->password)) {
            $this->audit->record($request, $admin, 'admin.login.failed', 'failure', 'admin_authentication', reason: 'invalid_credentials');

            return $this->invalidCredentials();
        }
        if (! $admin->is_active) {
            $this->audit->record($request, $admin, 'admin.login.failed', 'denied', 'admin', $admin->id, 'account_disabled');

            return $this->invalidCredentials();
        }

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $request->session()->put('admin_auth_version', (int) $admin->auth_version);
        try {
            DB::transaction(function () use ($request, $admin): void {
                $admin->forceFill(['last_login_at' => now()])->save();
                $this->audit->record($request, $admin, 'admin.login.succeeded', 'success', 'admin', $admin->id);
            });
        } catch (Throwable $exception) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw $exception;
        }

        return response()->json([
            'message' => 'Administrator authenticated.',
            'code' => 'admin_authenticated',
            'data' => $this->sessionData($admin->fresh(), $request),
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    public function current(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Administrator session is active.',
            'code' => 'admin_session_active',
            'data' => $this->sessionData($request->user('admin'), $request),
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    public function logout(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        try {
            if ($admin instanceof Admin) {
                $this->audit->record($request, $admin, 'admin.logout', 'success', 'admin', $admin->id);
            }
        } finally {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Administrator session ended.',
            'code' => 'admin_logged_out',
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    private function invalidCredentials(): JsonResponse
    {
        return response()->json([
            'message' => 'The provided credentials are invalid.',
            'code' => 'invalid_credentials',
        ], 401)->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    private function sessionData(Admin $admin, Request $request): array
    {
        return [
            'admin' => (new AdminResource($admin))->resolve($request),
            'navigation' => [
                'review_pharmacies' => $admin->canReviewPharmacies(),
                'manage_admins' => $admin->is_active && $admin->isSuperAdmin(),
            ],
        ];
    }
}
