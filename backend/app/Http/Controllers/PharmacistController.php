<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPharmacyRequest;
use App\Http\Requests\RegisterPharmacistRequest;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Services\AuthSessionService;
use App\Services\DocumentVersionService;
use App\Services\PharmacistApprovalService;
use App\Services\PrivateDocumentService;
use App\Services\ProfileImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class PharmacistController extends Controller
{
    public function __construct(
        private readonly AuthSessionService $sessions,
        private readonly PharmacistApprovalService $approvals,
        private readonly ProfileImageService $profileImages,
        private readonly PrivateDocumentService $documents,
        private readonly DocumentVersionService $documentVersions,
    ) {}

    public function register(RegisterPharmacistRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $coordinates = $request->pharmacyCoordinates();
        $profileImage = null;
        $certificate = null;
        $license = null;

        try {
            if ($request->hasFile('profile_image')) {
                $profileImage = $this->profileImages->store($request->file('profile_image'));
            }
            $certificate = $this->documents->storeUpload($request->file('certificate'), 'pharmacy-documents', 'certificate');
            $license = $this->documents->storeUpload($request->file('license'), 'pharmacy-documents', 'license');

            [$pharmacist, $pharmacy] = DB::transaction(function () use ($validated, $coordinates, $profileImage, $certificate, $license) {
                $pharmacist = Pharmacist::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($validated['password']),
                    'profile_image' => $profileImage,
                ]);

                $pharmacy = $pharmacist->pharmacies()->create([
                    'pharmacy_name' => $validated['pharmacy_name'],
                    'pharmacy_address' => $validated['pharmacy_address'],
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude'],
                    'certificate' => '',
                    'license' => '',
                    'status' => 'pending',
                ]);
                $this->documentVersions->createPharmacyVersion($pharmacy, 'certificate', $certificate, $pharmacist);
                $this->documentVersions->createPharmacyVersion($pharmacy, 'license', $license, $pharmacist);

                return [$pharmacist, $pharmacy];
            });
        } catch (Throwable $exception) {
            if ($profileImage !== null) {
                $this->profileImages->deleteOwned($profileImage);
            }
            foreach ([$certificate, $license] as $stored) {
                if ($stored !== null) {
                    $this->documents->delete($stored->storageKey);
                }
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
                    'latitude' => $pharmacy->latitude,
                    'longitude' => $pharmacy->longitude,
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

        if ($pharmacist->deactivated_at !== null) {
            return response()->json([
                'message' => 'This account has been deactivated.',
                'code' => 'account_deactivated',
            ], 403);
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

    public function addPharmacy(AddPharmacyRequest $request): JsonResponse
    {
        $certificate = null;
        $license = null;
        try {
            $certificate = $this->documents->storeUpload($request->file('certificate'), 'pharmacy-documents', 'certificate');
            $license = $this->documents->storeUpload($request->file('license'), 'pharmacy-documents', 'license');
            $coordinates = $request->pharmacyCoordinates();
            $pharmacy = DB::transaction(function () use ($request, $coordinates, $certificate, $license) {
                $pharmacy = Pharmacy::create([
                    'pharmacist_id' => $request->user()->id,
                    'pharmacy_name' => $request->validated('pharmacy_name'),
                    'pharmacy_address' => $request->validated('pharmacy_address'),
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude'],
                    'certificate' => '',
                    'license' => '',
                    'status' => 'pending',
                ]);
                $this->documentVersions->createPharmacyVersion($pharmacy, 'certificate', $certificate, $request->user());
                $this->documentVersions->createPharmacyVersion($pharmacy, 'license', $license, $request->user());

                return $pharmacy;
            });
        } catch (Throwable $exception) {
            foreach ([$certificate, $license] as $stored) {
                if ($stored !== null) {
                    $this->documents->delete($stored->storageKey);
                }
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'Pharmacy added successfully, waiting for admin approval',
            'pharmacy' => [
                'id' => $pharmacy->id,
                'pharmacy_name' => $pharmacy->pharmacy_name,
                'pharmacy_address' => $pharmacy->pharmacy_address,
                'latitude' => $pharmacy->latitude,
                'longitude' => $pharmacy->longitude,
                'status' => $pharmacy->status,
                'certificate_on_file' => true,
                'license_on_file' => true,
            ],
        ], 201);
    }
}
