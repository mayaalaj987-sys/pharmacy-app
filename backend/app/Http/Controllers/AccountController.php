<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\DeactivateAccountRequest;
use App\Models\Pharmacist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Pharmacist $pharmacist */
        $pharmacist = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $pharmacist->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'code' => 'current_password_incorrect',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        $currentTokenId = $pharmacist->currentAccessToken()?->getKey();

        DB::transaction(function () use ($request, $pharmacist, $currentTokenId): void {
            $pharmacist->password = Hash::make($request->string('new_password')->toString());
            $pharmacist->save();

            $tokens = $pharmacist->tokens();
            if ($currentTokenId !== null) {
                $tokens->where('id', '!=', $currentTokenId);
            }
            $tokens->delete();
        });

        return response()->json([
            'message' => 'Password changed successfully.',
            'code' => 'password_changed',
        ]);
    }

    public function deactivate(DeactivateAccountRequest $request): JsonResponse
    {
        /** @var Pharmacist $pharmacist */
        $pharmacist = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $pharmacist->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'code' => 'current_password_incorrect',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($pharmacist): void {
            $pharmacist->deactivated_at = now();
            $pharmacist->save();
            $pharmacist->tokens()->delete();
        });

        return response()->json([
            'message' => 'This account has been deactivated.',
            'code' => 'account_deactivated',
        ]);
    }
}
