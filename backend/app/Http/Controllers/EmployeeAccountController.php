<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeEmployeePasswordRequest;
use App\Http\Requests\UpdateEmployeeProfileRequest;
use App\Http\Resources\SafeEmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Employee self-service account actions.
 *
 * The acting employee is always resolved from the authenticated token; no
 * employee identifier is accepted from the client. Tenant, role, salary and
 * approval fields are rejected by the Form Requests, so an employee can only
 * change their own display name, phone and password.
 */
class EmployeeAccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $employee = $request->user();

        return response()->json([
            'employee' => (new SafeEmployeeResource($employee))->resolve($request),
        ]);
    }

    public function update(UpdateEmployeeProfileRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $employee->name = trim((string) $validated['name']);
        }
        if (array_key_exists('phone', $validated)) {
            $employee->phone = $validated['phone'];
        }
        $employee->save();

        return response()->json([
            'message' => 'Profile updated.',
            'code' => 'employee_profile_updated',
            'employee' => (new SafeEmployeeResource($employee->fresh()))->resolve($request),
        ]);
    }

    public function changePassword(ChangeEmployeePasswordRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $employee->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'code' => 'current_password_incorrect',
            ], 422);
        }

        $currentTokenId = $employee->currentAccessToken()?->getKey();

        DB::transaction(function () use ($employee, $request, $currentTokenId): void {
            $employee->password = $request->string('new_password')->toString();
            $employee->save();

            // Revoke every other session; the caller keeps working.
            $tokens = $employee->tokens();
            if ($currentTokenId !== null) {
                $tokens->where('id', '!=', $currentTokenId);
            }
            $tokens->delete();
        });

        return response()->json([
            'message' => 'Password changed.',
            'code' => 'password_changed',
        ]);
    }
}
