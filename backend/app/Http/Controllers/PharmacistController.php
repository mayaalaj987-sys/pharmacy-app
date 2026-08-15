<?php

namespace App\Http\Controllers;

use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Services\AuthSessionService;
use App\Services\PharmacistApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PharmacistController extends Controller
{
    public function __construct(
        private readonly AuthSessionService $sessions,
        private readonly PharmacistApprovalService $approvals,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:pharmacists,email',
            'password' => 'required|min:6',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'profile' => 'prohibited',
            'pharmacist_id' => 'prohibited',
            'pharmacy_name' => 'required|string',
            'pharmacy_address' => 'required|string',
            'certificate' => 'required|file|mimes:jpg,jpeg,png,pdf',
            'license' => 'required|file|mimes:jpg,jpeg,png,pdf',
        ]);

        $storedFiles = [];

        try {
            if ($request->hasFile('profile_image')) {
                $storedFiles['profile_image'] = $request->file('profile_image')->store('profiles', 'public');
            }
            $storedFiles['certificate'] = $request->file('certificate')->store('certificates', 'public');
            $storedFiles['license'] = $request->file('license')->store('licenses', 'public');

            [$pharmacist, $pharmacy] = DB::transaction(function () use ($validated, $storedFiles) {
                $pharmacist = Pharmacist::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'profile_image' => isset($storedFiles['profile_image'])
                        ? json_encode([$storedFiles['profile_image']])
                        : null,
                ]);

                $pharmacy = $pharmacist->pharmacies()->create([
                    'pharmacy_name' => $validated['pharmacy_name'],
                    'pharmacy_address' => $validated['pharmacy_address'],
                    'certificate' => json_encode([$storedFiles['certificate']]),
                    'license' => json_encode([$storedFiles['license']]),
                    'status' => 'pending',
                ]);

                return [$pharmacist, $pharmacy];
            });
        } catch (Throwable $exception) {
            foreach ($storedFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        $statusToken = $pharmacist->createToken(
            'registration-status-token',
            ['registration-status'],
        )->plainTextToken;

        return response()->json([
            'message' => 'Registration completed successfully. The pharmacy is awaiting approval.',
            'data' => [
                'registration_status_token' => $statusToken,
                'token_type' => 'Bearer',
                'actor' => [
                    'id' => $pharmacist->id,
                    'type' => 'pharmacist',
                    'role' => 'owner',
                    'name' => $pharmacist->name,
                    'email' => $pharmacist->email,
                ],
                'pharmacy' => [
                    'id' => $pharmacy->id,
                    'name' => $pharmacy->pharmacy_name,
                    'address' => $pharmacy->pharmacy_address,
                    'status' => $pharmacy->status,
                ],
                'registration' => $this->approvals->registrationStatus($pharmacist),
            ],
        ], 201);
    }

    public function registrationStatus(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'prohibited',
            'phone' => 'prohibited',
            'pharmacist_id' => 'prohibited',
            'pharmacy_id' => 'prohibited',
        ]);

        return response()->json([
            'data' => [
                'registration' => $this->approvals->registrationStatus($request->user()),
            ],
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $pharmacist = Pharmacist::where('email', $request->email)->first();

        if (! $pharmacist || ! Hash::check($request->password, $pharmacist->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
                'code' => 'invalid_credentials',
            ], 401);
        }

        $decision = $this->approvals->decision($pharmacist);
        if (! $decision['approved']) {
            return response()->json([
                'message' => $decision['message'],
                'code' => $decision['code'],
            ], 403);
        }

        $token = $pharmacist->createToken('pharmacist-token', ['app'])->plainTextToken;
        $request->setUserResolver(fn () => $pharmacist);

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'session' => $this->sessions->build($request, false),
            ],
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $pharmacist = $request->user();
        $pharmacist->currentAccessToken()->delete();
        $pharmacist->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    // ===== عرض البروفايل مع كل الصيدليات وعدد موظفين كل صيدلية =====
    public function getProfile(Request $request): JsonResponse
    {
        $pharmacist = $request->user();

        $pharmacies = $pharmacist->pharmacies()
            ->withCount(['employees' => function ($query) {
                $query->where('status', 'approved');
            }])
            ->get();

        return response()->json([
            'pharmacist' => $pharmacist,
            'pharmacies' => $pharmacies,
        ]);
    }

    // ===== تعديل بيانات الصيدلاني الشخصية =====
    public function updateProfile(Request $request): JsonResponse
    {
        $pharmacist = $request->user();

        $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:pharmacists,email,'.$pharmacist->id,
            'password' => 'sometimes|min:6',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png,webp',
        ]);

        if ($request->hasFile('profile_image')) {
            $profileImage = $request->file('profile_image')->store('profiles', 'public');
            $pharmacist->profile_image = json_encode([$profileImage]);
        }

        if ($request->name) {
            $pharmacist->name = $request->name;
        }
        if ($request->email) {
            $pharmacist->email = $request->email;
        }
        if ($request->password) {
            $pharmacist->password = Hash::make($request->password);
        }

        $pharmacist->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'pharmacist' => $pharmacist,
        ]);
    }

    // ===== NEW: تعديل معلومات صيدلية معينة =====
    public function updatePharmacy(Request $request, $id): JsonResponse
    {
        $pharmacist = $request->user();

        $pharmacy = Pharmacy::findOrFail($id);
        Gate::forUser($pharmacist)->authorize('update', $pharmacy);

        $request->validate([
            'pharmacy_name' => 'sometimes|string',
            'pharmacy_address' => 'sometimes|string',
        ]);

        if ($request->pharmacy_name) {
            $pharmacy->pharmacy_name = $request->pharmacy_name;
        }
        if ($request->pharmacy_address) {
            $pharmacy->pharmacy_address = $request->pharmacy_address;
        }

        $pharmacy->save();

        return response()->json([
            'message' => 'تم تعديل معلومات الصيدلية بنجاح',
            'pharmacy' => $pharmacy,
        ]);
    }

    public function addPharmacy(Request $request): JsonResponse
    {
        $request->validate([
            'pharmacy_name' => 'required|string',
            'pharmacy_address' => 'required|string',
            'certificate' => 'required|file|mimes:jpg,jpeg,png,pdf',
            'license' => 'required|file|mimes:jpg,jpeg,png,pdf',
        ]);

        $certificate = $request->file('certificate')->store('certificates', 'public');
        $license = $request->file('license')->store('licenses', 'public');

        $pharmacy = Pharmacy::create([
            'pharmacist_id' => $request->user()->id,
            'pharmacy_name' => $request->pharmacy_name,
            'pharmacy_address' => $request->pharmacy_address,
            'certificate' => json_encode([$certificate]),
            'license' => json_encode([$license]),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pharmacy added successfully, waiting for admin approval',
            'pharmacy' => $pharmacy,
        ], 201);
    }
}
