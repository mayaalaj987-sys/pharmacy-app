<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Pharmacy;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    // ===== تسجيل الموظف — بدون اختيار صيدلية، الطلب يروح لكل الصيدليات =====
    public function register(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name'             => 'required|string',
            'phone'            => 'required|string',
            'email'            => 'required|email|unique:employees,email',
            'password'         => 'required|min:6',
            'cv'               => 'required|file|mimes:jpg,jpeg,png,pdf',   // ✅ CHANGE: certificate → cv
            'experience_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'role'             => 'required|in:employee,trainee',
        ]);

        // الموظف يجب يرفع experience_proof إذا كان employee
        if ($request->role === 'employee' && !$request->hasFile('experience_proof')) {
            return response()->json([
                'message' => 'يجب رفع ملف الخبرة إذا كنت موظفاً',
            ], 400);
        }

        // رفع الملفات
        $cv = $request->file('cv')->store('cvs', 'public');
        $experienceProof = null;
        if ($request->hasFile('experience_proof')) {
            $experienceProof = $request->file('experience_proof')->store('experience', 'public');
        }

        // ✅ NEW: pharmacy_id = null لأنه ما اختار صيدلية، الطلب مفتوح لكل الصيدليات
        $employee = Employee::create([
            'pharmacy_id'      => null,
            'name'             => $request->name,
            'phone'            => $request->phone,
            'email'            => $request->email,
            'password'         => Hash::make($request->password),
            'cv'      => $cv,
            'experience_proof' => $experienceProof,
            'role'             => $request->role,
            'status'           => 'pending',
            'first_login'      => true,
        ]);

        return response()->json([
            'message'  => 'تم التسجيل بنجاح، طلبك مرئي لجميع الصيدليات',
            'employee' => $employee,
        ], 201);
    }

    // ===== تسجيل الدخول =====
    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $employee = Employee::where('email', $request->email)->first();

        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return response()->json(['message' => 'البيانات غير صحيحة'], 401);
        }

        if ($employee->status === 'pending') {
            return response()->json(['message' => 'حسابك قيد المراجعة'], 403);
        }

        if ($employee->status === 'rejected') {
            return response()->json(['message' => 'تم رفض حسابك'], 403);
        }

        $welcomeMessage = null;
        if ($employee->first_login) {
            $welcomeMessage = 'أهلاً وسهلاً ' . $employee->name . '! تم قبول حسابك بنجاح 🎉';
            $employee->update(['first_login' => false]);
        }

        $token = $employee->createToken('employee-token')->plainTextToken;

        return response()->json([
            'message'  => $welcomeMessage ?? 'تم تسجيل الدخول بنجاح',
            'token'    => $token,
            'role'     => $employee->role,
            'employee' => $employee,
        ]);
    }

    // ===== تسجيل الخروج =====
    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    // ===== NEW: الصيدلاني يشوف كل طلبات التوظيف المفتوحة (بغض النظر عن الصيدلية) =====
    // الصيدلاني بيدخل على صيدلية معينة ويشوف الطلبات — لما يوافق بيوظفه بهي الصيدلية
    public function getAllPendingEmployees(Request $request): \Illuminate\Http\JsonResponse
    {
        // يرجع كل الموظفين اللي حالتهم pending وما عندهم صيدلية بعد
        $employees = Employee::whereNull('pharmacy_id')
            ->where('status', 'pending')
            ->get();

        return response()->json([
            'count'     => $employees->count(),
            'employees' => $employees,
        ]);
    }

    // ===== UPDATED: الصيدلاني يوافق على موظف ويوظفه بصيدلية معينة =====
    public function approveEmployee(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        try {

            $pharmacist = $request->user();

            $request->validate([
                'pharmacy_id' => 'required|exists:pharmacies,id',
                'salary'      => 'nullable|numeric',
            ]);

            $pharmacy = Pharmacy::where('id', $request->pharmacy_id)
                ->where('pharmacist_id', $pharmacist->id)
                ->first();

            if (!$pharmacy) {
                return response()->json([
                    'message' => 'الصيدلية غير موجودة أو لا تملك صلاحية عليها',
                ], 403);
            }

            $employee = Employee::find($id);

            if (!$employee) {
                return response()->json([
                    'message' => 'الموظف غير موجود',
                ], 404);
            }

            if ($employee->status !== 'pending') {
                return response()->json([
                    'message' => 'هذا الموظف تمت معالجة طلبه مسبقاً',
                ], 400);
            }

            $employeeCount = Employee::where('pharmacy_id', $request->pharmacy_id)
                ->where('status', 'approved')
                ->count();

            if ($employeeCount >= 2) {
                return response()->json([
                    'message' => 'هذه الصيدلية وصلت للحد الأقصى (2)',
                ], 400);
            }

            $employee->pharmacy_id = $request->pharmacy_id;
            $employee->status = 'approved';
            $employee->salary = $employee->role === 'employee' ? $request->salary : null;
            $employee->save();

            return response()->json([
                'message'  => 'تم القبول بنجاح',
                'employee' => $employee,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== رفض موظف =====
    public function rejectEmployee($id): \Illuminate\Http\JsonResponse
    {
        $employee = Employee::findOrFail($id);

        if ($employee->status !== 'pending') {
            return response()->json([
                'message' => 'هذا الموظف تمت معالجة طلبه مسبقاً',
            ], 400);
        }

        $employee->update(['status' => 'rejected']);
        return response()->json(['message' => 'تم رفض الموظف']);
    }

    // ===== NEW: الصيدلاني يحذف موظف من صيدليته كلياً =====
    public function dismissEmployee(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $pharmacist = $request->user();

        $employee = Employee::findOrFail($id);

        // التحقق إن الموظف تابع لصيدلية من صيدليات الصيدلاني الحالي
        $pharmacy = Pharmacy::where('id', $employee->pharmacy_id)
            ->where('pharmacist_id', $pharmacist->id)
            ->first();

        if (!$pharmacy) {
            return response()->json([
                'message' => 'لا تملك صلاحية إزالة هذا الموظف',
            ], 403);
        }

        if ($employee->status !== 'approved') {
            return response()->json([
                'message' => 'هذا الموظف ليس موظفاً نشطاً',
            ], 400);
        }

        $employeeName = $employee->name;
        $pharmacyId   = $employee->pharmacy_id;

        // ✅ حذف الموظف كلياً من النظام
        $employee->tokens()->delete(); // نحذف tokens أولاً لو كان مسجل دخول
        $employee->delete();

        Notification::create([
            'pharmacy_id' => $pharmacyId,
            'title'       => 'تم إنهاء خدمة موظف',
            'message'     => 'تم إنهاء خدمة الموظف ' . $employeeName . ' من الصيدلية',
            'type'        => 'employee',
            'is_read'     => false,
            'date'        => now(),
        ]);

        return response()->json([
            'message' => 'تم حذف الموظف من النظام بنجاح',
        ]);
    }

    // ===== الصيدلاني: كل موظفي صيدلية معينة =====
    public function getEmployees(Request $request, $pharmacy_id): \Illuminate\Http\JsonResponse
    {
        $pharmacist = $request->user();

        $pharmacy = Pharmacy::where('id', $pharmacy_id)
            ->where('pharmacist_id', $pharmacist->id)
            ->firstOrFail();

        $employees = Employee::where('pharmacy_id', $pharmacy->id)
            ->where('status', 'approved')
            ->get();

        return response()->json(['employees' => $employees]);
    }
}
