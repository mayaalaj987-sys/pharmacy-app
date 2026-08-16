<?php

namespace App\Http\Controllers;

use App\Exceptions\AdminWorkflowException;
use App\Http\Requests\ChangeAdminRoleRequest;
use App\Http\Requests\CreateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\AdminAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminManagementController extends Controller
{
    public function __construct(private readonly AdminAccountService $accounts) {}

    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('viewAny', Admin::class);
        $admins = Admin::query()->orderBy('name')->orderBy('id')->get();

        return response()->json(['data' => AdminResource::collection($admins)->resolve($request)]);
    }

    public function store(CreateAdminRequest $request): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('create', Admin::class);

        try {
            $admin = $this->accounts->create(
                $request->user('admin'),
                $request->string('name')->toString(),
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $request->string('role')->toString(),
                $request,
            );
        } catch (AdminWorkflowException $exception) {
            return $this->workflowError($exception);
        }

        return response()->json([
            'message' => 'Administrator account created.',
            'code' => 'admin_created',
            'data' => (new AdminResource($admin))->resolve($request),
        ], 201);
    }

    public function changeRole(ChangeAdminRoleRequest $request, Admin $admin): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('manage', $admin);

        return $this->managedResponse($request, fn () => $this->accounts->changeRole(
            $request->user('admin'), $admin, $request->string('role')->toString(), $request
        ), 'admin_role_changed');
    }

    public function disable(Request $request, Admin $admin): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('manage', $admin);

        return $this->managedResponse($request, fn () => $this->accounts->setActive($request->user('admin'), $admin, false, $request), 'admin_disabled');
    }

    public function reactivate(Request $request, Admin $admin): JsonResponse
    {
        Gate::forUser($request->user('admin'))->authorize('manage', $admin);

        return $this->managedResponse($request, fn () => $this->accounts->setActive($request->user('admin'), $admin, true, $request), 'admin_reactivated');
    }

    private function managedResponse(Request $request, callable $operation, string $code): JsonResponse
    {
        try {
            $admin = $operation();
        } catch (AdminWorkflowException $exception) {
            return $this->workflowError($exception);
        }

        return response()->json([
            'message' => 'Administrator account updated.',
            'code' => $code,
            'data' => (new AdminResource($admin))->resolve($request),
        ]);
    }

    private function workflowError(AdminWorkflowException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage(), 'code' => $exception->errorCode], $exception->status);
    }
}
