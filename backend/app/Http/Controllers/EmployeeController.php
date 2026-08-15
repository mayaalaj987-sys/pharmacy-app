<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Notification;
use App\Services\AuthSessionService;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly PharmacyContextResolver $pharmacyContext,
        private readonly AuthSessionService $sessions,
    ) {}

    // ===== تسجيل الموظف — بدون اختيار صيدلية، الطلب يروح لكل الصيدليات =====
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'required|email|unique:employees,email',
            'password' => 'required|min:6',
            'cv' => 'required|file|mimes:jpg,jpeg,png,pdf',   // ✅ CHANGE: certificate → cv
            'experience_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'role' => 'required|in:employee,trainee',
            'pharmacy_id' => 'prohibited',
            'status' => 'prohibited',
            'salary' => 'prohibited',
            'first_login' => 'prohibited',
        ]);

        // الموظف يجب يرفع experience_proof إذا كان employee
        if ($request->role === 'employee' && ! $request->hasFile('experience_proof')) {
            return response()->json([
                'message' => 'Experience proof is required for employees.',
                'code' => 'experience_proof_required',
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
            'pharmacy_id' => null,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'cv' => $cv,
            'experience_proof' => $experienceProof,
            'role' => $request->role,
            'status' => 'pending',
            'first_login' => true,
        ]);

        return response()->json([
            'message' => 'Registration completed successfully. Your application is awaiting approval.',
            'data' => [
                'actor' => [
                    'id' => $employee->id,
                    'type' => 'employee',
                    'role' => $employee->role,
                    'status' => $employee->status,
                    'name' => $employee->name,
                    'email' => $employee->email,
                ],
            ],
        ], 201);
    }

    // ===== تسجيل الدخول =====
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $employee = Employee::where('email', $request->email)->first();

        if (! $employee || ! Hash::check($request->password, $employee->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
                'code' => 'invalid_credentials',
            ], 401);
        }

        $welcomeMessage = null;
        if ($employee->status === 'approved' && $employee->first_login) {
            $welcomeMessage = 'Welcome '.$employee->name.'! Your account has been approved.';
            $employee->update(['first_login' => false]);
        }

        $token = $employee->createToken('employee-token')->plainTextToken;
        $request->setUserResolver(fn () => $employee);

        return response()->json([
            'message' => $welcomeMessage ?? 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'session' => $this->sessions->build($request, false),
            ],
        ]);
    }

    // ===== NEW: الصيدلاني يشوف كل طلبات التوظيف المفتوحة (بغض النظر عن الصيدلية) =====
    // الصيدلاني بيدخل على صيدلية معينة ويشوف الطلبات — لما يوافق بيوظفه بهي الصيدلية
    public function getAllPendingEmployees(Request $request): JsonResponse
    {
        // يرجع كل الموظفين اللي حالتهم pending وما عندهم صيدلية بعد
        $employees = Employee::whereNull('pharmacy_id')
            ->where('status', 'pending')
            ->get();

        return response()->json([
            'count' => $employees->count(),
            'employees' => $employees,
        ]);
    }

    // ===== UPDATED: الصيدلاني يوافق على موظف ويوظفه بصيدلية معينة =====
    public function approveEmployee(Request $request, $id): JsonResponse
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'salary' => 'nullable|numeric',
        ]);
        $pharmacy = $this->pharmacyContext->resolve($request);

        DB::beginTransaction();

        try {
            $employee = Employee::lockForUpdate()->find($id);

            if (! $employee) {
                DB::rollBack();

                return response()->json([
                    'message' => 'الموظف غير موجود',
                ], 404);
            }

            if ($employee->status !== 'pending' || $employee->pharmacy_id !== null) {
                DB::rollBack();

                return response()->json([
                    'message' => 'هذا الموظف تمت معالجة طلبه مسبقاً',
                ], 400);
            }

            $employeeCount = Employee::where('pharmacy_id', $pharmacy->id)
                ->where('status', 'approved')
                ->count();

            if ($employeeCount >= 2) {
                DB::rollBack();

                return response()->json([
                    'message' => 'هذه الصيدلية وصلت للحد الأقصى (2)',
                ], 400);
            }

            $employee->pharmacy_id = $pharmacy->id;
            $employee->status = 'approved';
            $employee->salary = $employee->role === 'employee' ? $request->salary : null;
            $employee->save();
            DB::commit();

            return response()->json([
                'message' => 'تم القبول بنجاح',
                'employee' => $employee,
            ]);
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            return response()->json(['message' => 'تعذر قبول الموظف'], 500);
        }
    }

    // ===== NEW: الصيدلاني يحذف موظف من صيدليته كلياً =====
    public function dismissEmployee(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->pharmacyContext->assertMatches($request, (int) $employee->pharmacy_id);
        Gate::forUser($request->user())->authorize('delete', $employee);

        if ($employee->status !== 'approved') {
            return response()->json([
                'message' => 'هذا الموظف ليس موظفاً نشطاً',
            ], 400);
        }

        $employeeName = $employee->name;
        $pharmacyId = $employee->pharmacy_id;

        // ✅ حذف الموظف كلياً من النظام
        $employee->tokens()->delete(); // نحذف tokens أولاً لو كان مسجل دخول
        $employee->delete();

        Notification::create([
            'pharmacy_id' => $pharmacyId,
            'title' => 'تم إنهاء خدمة موظف',
            'message' => 'تم إنهاء خدمة الموظف '.$employeeName.' من الصيدلية',
            'type' => 'employee',
            'is_read' => false,
            'date' => now(),
        ]);

        return response()->json([
            'message' => 'تم حذف الموظف من النظام بنجاح',
        ]);
    }

    // ===== الصيدلاني: كل موظفي صيدلية معينة =====
    public function getEmployees(Request $request, $pharmacy_id): JsonResponse
    {
        $pharmacy = $this->pharmacyContext->owned($request, (int) $pharmacy_id);

        $employees = Employee::where('pharmacy_id', $pharmacy->id)
            ->where('status', 'approved')
            ->get();

        return response()->json(['employees' => $employees]);
    }
}
