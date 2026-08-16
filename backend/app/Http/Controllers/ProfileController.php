<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Pharmacist;
use App\Services\AuthSessionService;
use App\Services\ProfileImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuthSessionService $sessions,
        private readonly ProfileImageService $images,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->sessionResponse($request);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Pharmacist $pharmacist */
        $pharmacist = $request->user();
        $validated = $request->validated();
        $oldImage = $pharmacist->profile_image;
        $newImage = null;

        if ($request->hasFile('profile_image')) {
            $newImage = $this->images->store($request->file('profile_image'));
            $pharmacist->profile_image = $newImage;
        }

        if (array_key_exists('name', $validated)) {
            $pharmacist->name = $validated['name'];
        }

        if (array_key_exists('phone', $validated)) {
            $pharmacist->phone = $validated['phone'];
        }

        try {
            $pharmacist->save();
        } catch (Throwable $exception) {
            if ($newImage !== null) {
                $this->images->deleteOwned($newImage);
            }

            throw $exception;
        }

        if ($newImage !== null && $oldImage !== $newImage) {
            $this->images->deleteOwned($oldImage);
        }

        return $this->sessionResponse($request, 'Profile updated successfully.', 'profile_updated');
    }

    private function sessionResponse(
        Request $request,
        ?string $message = null,
        ?string $code = null,
    ): JsonResponse {
        $payload = [
            'data' => [
                'session' => $this->sessions->build($request),
            ],
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($code !== null) {
            $payload['code'] = $code;
        }

        return response()->json($payload);
    }
}
