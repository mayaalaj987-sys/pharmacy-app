<?php

namespace App\Http\Controllers;

use App\Services\AuthSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(private readonly AuthSessionService $sessions) {}

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'session' => $this->sessions->build($request),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
