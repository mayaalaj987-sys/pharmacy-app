<?php

namespace App\Http\Controllers;

use App\Models\Pharmacist;
use App\Models\Pharmacy;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PharmacistController extends Controller
{





    public function register(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name'          => 'required|string',
            'email'         => 'required|email|unique:pharmacists,email',
            'password'      => 'required|min:6',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp',
        ]);

        $profileImage = null;
        if ($request->hasFile('profile_image')) {
            $profileImage = $request->file('profile_image')->store('profiles', 'public');
        }

        $pharmacist = Pharmacist::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'profile_image' => $profileImage ? json_encode([$profileImage]) : null,
        ]);

        return response()->json([
            'message'       => 'Pharmacist registered successfully',
            'pharmacist_id' => $pharmacist->id,
        ], 201);
    }

    public function registerPharmacy(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'pharmacist_id'    => 'required|exists:pharmacists,id',
            'pharmacy_name'    => 'required|string',
            'pharmacy_address' => 'required|string',
            'certificate'      => 'required|file|mimes:jpg,jpeg,png,pdf',
            'license'          => 'required|file|mimes:jpg,jpeg,png,pdf',
        ]);

        $certificate = $request->file('certificate')->store('certificates', 'public');
        $license     = $request->file('license')->store('licenses', 'public');

        $pharmacy = Pharmacy::create([
            'pharmacist_id'    => $request->pharmacist_id,
            'pharmacy_name'    => $request->pharmacy_name,
            'pharmacy_address' => $request->pharmacy_address,
            'certificate'      => json_encode([$certificate]),
            'license'          => json_encode([$license]),
            'status'           => 'pending',
        ]);

        return response()->json([
            'message'  => 'Pharmacy registered successfully, waiting for admin approval',
            'pharmacy' => $pharmacy,
        ], 201);
    }

    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $pharmacist = Pharmacist::where('email', $request->email)->first();

        if (!$pharmacist || !Hash::check($request->password, $pharmacist->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $pharmacy = $pharmacist->pharmacies()->first();

        if (!$pharmacy) {
            return response()->json(['message' => 'No pharmacy found for this pharmacist'], 404);
        }

        if ($pharmacy->status === 'pending') {
            return response()->json(['message' => 'Your account is pending admin approval'], 403);
        }

        if ($pharmacy->status === 'rejected') {
            return response()->json(['message' => 'Your account has been rejected'], 403);
        }

        $token = $pharmacist->createToken('pharmacist-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
        ]);
    }

    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function deleteAccount(Request $request): \Illuminate\Http\JsonResponse
    {
        $pharmacist = $request->user();
        $pharmacist->currentAccessToken()->delete();
        $pharmacist->delete();
        return response()->json(['message' => 'Account deleted successfully']);
    }

    // ===== عرض البروفايل مع كل الصيدليات وعدد موظفين كل صيدلية =====
    public function getProfile(Request $request): \Illuminate\Http\JsonResponse
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
    public function updateProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        $pharmacist = $request->user();

        $request->validate([
            'name'          => 'sometimes|string',
            'email'         => 'sometimes|email|unique:pharmacists,email,' . $pharmacist->id,
            'password'      => 'sometimes|min:6',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png,webp',
        ]);

        if ($request->hasFile('profile_image')) {
            $profileImage = $request->file('profile_image')->store('profiles', 'public');
            $pharmacist->profile_image = json_encode([$profileImage]);
        }

        if ($request->name)     $pharmacist->name     = $request->name;
        if ($request->email)    $pharmacist->email    = $request->email;
        if ($request->password) $pharmacist->password = Hash::make($request->password);

        $pharmacist->save();

        return response()->json([
            'message'    => 'Profile updated successfully',
            'pharmacist' => $pharmacist,
        ]);
    }

    // ===== NEW: تعديل معلومات صيدلية معينة =====
    public function updatePharmacy(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $pharmacist = $request->user();

        // التحقق إن الصيدلية تابعة للصيدلاني الحالي
        $pharmacy = Pharmacy::where('id', $id)
            ->where('pharmacist_id', $pharmacist->id)
            ->first();

        if (!$pharmacy) {
            return response()->json([
                'message' => 'الصيدلية غير موجودة أو لا تملك صلاحية تعديلها',
            ], 404);
        }

        $request->validate([
            'pharmacy_name'    => 'sometimes|string',
            'pharmacy_address' => 'sometimes|string',
        ]);

        if ($request->pharmacy_name)    $pharmacy->pharmacy_name    = $request->pharmacy_name;
        if ($request->pharmacy_address) $pharmacy->pharmacy_address = $request->pharmacy_address;

        $pharmacy->save();

        return response()->json([
            'message'  => 'تم تعديل معلومات الصيدلية بنجاح',
            'pharmacy' => $pharmacy,
        ]);
    }

    public function addPharmacy(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'pharmacy_name'    => 'required|string',
            'pharmacy_address' => 'required|string',
            'certificate'      => 'required|file|mimes:jpg,jpeg,png,pdf',
            'license'          => 'required|file|mimes:jpg,jpeg,png,pdf',
        ]);

        $certificate = $request->file('certificate')->store('certificates', 'public');
        $license     = $request->file('license')->store('licenses', 'public');

        $pharmacy = Pharmacy::create([
            'pharmacist_id'    => $request->user()->id,
            'pharmacy_name'    => $request->pharmacy_name,
            'pharmacy_address' => $request->pharmacy_address,
            'certificate'      => json_encode([$certificate]),
            'license'          => json_encode([$license]),
            'status'           => 'pending',
        ]);

        return response()->json([
            'message'  => 'Pharmacy added successfully, waiting for admin approval',
            'pharmacy' => $pharmacy,
        ], 201);
    }

    // ===== ADMIN =====

    public function getAllPharmacies(): \Illuminate\Http\JsonResponse
    {
        $pharmacies = Pharmacy::with('pharmacist')->get();
        return response()->json(['pharmacies' => $pharmacies]);
    }

    public function getPendingPharmacies(): \Illuminate\Http\JsonResponse
    {
        $pharmacies = Pharmacy::where('status', 'pending')->with('pharmacist')->get();
        return response()->json(['pharmacies' => $pharmacies]);
    }

    public function approvePharmacy($id): \Illuminate\Http\JsonResponse
    {
        try {

            $pharmacy = Pharmacy::find($id);

            if (!$pharmacy) {
                return response()->json([
                    'message' => 'Pharmacy not found'
                ], 404);
            }

            if ($pharmacy->status !== 'pending') {
                return response()->json([
                    'message' => 'This pharmacy is already ' . $pharmacy->status
                ], 400);
            }

            $pharmacy->status = 'approved';
            $pharmacy->save();

            return response()->json([
                'message' => 'Pharmacy approved successfully',
                'pharmacy' => $pharmacy
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function rejectPharmacy($id): \Illuminate\Http\JsonResponse
    {
        try {

            $pharmacy = Pharmacy::find($id);

            if (!$pharmacy) {
                return response()->json([
                    'message' => 'Pharmacy not found'
                ], 404);
            }

            if ($pharmacy->status !== 'pending') {
                return response()->json([
                    'message' => 'This pharmacy is already ' . $pharmacy->status
                ], 400);
            }

            $pharmacy->status = 'rejected';
            $pharmacy->save();

            return response()->json([
                'message' => 'Pharmacy rejected successfully',
                'pharmacy' => $pharmacy
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
