<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterEmployeeRequest;
use App\Http\Resources\SafeEmployeeResource;
use App\Models\Employee;
use App\Models\Notification;
use App\Services\AuthSessionService;
use App\Services\DocumentVersionService;
use App\Services\PharmacyContextResolver;
use App\Services\PrivateDocumentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly PharmacyContextResolver $pharmacyContext,
        private readonly AuthSessionService $sessions,
        private readonly PrivateDocumentService $documents,
        private readonly DocumentVersionService $documentVersions,
    ) {}

    // ===== تسجيل الموظف — بدون اختيار صيدلية، الطلب يروح لكل الصيدليات =====
    public function register(RegisterEmployeeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $cv = null;
        $experienceProof = null;
        try {
            $cv = $this->documents->storeUpload($request->file('cv'), 'employee-documents', 'cv');
            if ($request->hasFile('experience_proof')) {
                $experienceProof = $this->documents->storeUpload(
                    $request->file('experience_proof'),
                    'employee-documents',
                    'experience_proof',
                );
            }

            $employee = DB::transaction(function () use ($validated, $cv, $experienceProof) {
                $employee = Employee::create([
                    'pharmacy_id' => null,
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'cv' => '',
                    'experience_proof' => null,
                    'role' => $validated['role'],
                    'status' => Employee::STATUS_PENDING,
                    'first_login' => true,
                ]);
                $this->documentVersions->createEmployeeVersion($employee, 'cv', $cv, $employee);
                if ($experienceProof !== null) {
                    $this->documentVersions->createEmployeeVersion($employee, 'experience_proof', $experienceProof, $employee);
                }

                return $employee;
            });
        } catch (Throwable $exception) {
            foreach ([$cv, $experienceProof] as $stored) {
                if ($stored !== null) {
                    $this->documents->delete($stored->storageKey);
                }
            }
            throw $exception;
        }

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
        if ($employee->status === Employee::STATUS_APPROVED && $employee->first_login) {
            $welcomeMessage = 'Welcome '.$employee->name.'! Your account has been approved.';
            $employee->update(['first_login' => false]);
        }

        $token = $employee->createToken('employee-token', ['app'])->plainTextToken;
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
            ->where('status', Employee::STATUS_PENDING)
            ->get();

        return response()->json([
            'count' => $employees->count(),
            'employees' => SafeEmployeeResource::collection($employees)->resolve($request),
        ]);
    }

    // ===== UPDATED: الصيدلاني يوافق على موظف ويوظفه بصيدلية معينة =====
    public function approveEmployee(Request $request, $id): JsonResponse
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'salary' => 'nullable|numeric',
            // Optional: a client that does not know about shifts yet gets the
            // first free one, which is what the old counted cap did in effect.
            'shift' => ['nullable', Rule::in(Employee::SHIFTS)],
        ]);
        $pharmacy = $this->pharmacyContext->resolve($request);

        DB::beginTransaction();

        try {
            $employee = Employee::lockForUpdate()->find($id);

            if (! $employee) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Applicant not found.',
                    'code' => 'employee_not_found',
                ], 404);
            }

            if ($employee->status !== Employee::STATUS_PENDING || $employee->isEmployed()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'This application has already been processed.',
                    'code' => 'employee_not_available',
                ], 400);
            }

            $free = $pharmacy->freeShifts();
            $shift = $request->input('shift') ?? ($free[0] ?? null);

            if ($shift === null || ! in_array($shift, $free, true)) {
                DB::rollBack();

                return response()->json([
                    'message' => $free === []
                        ? 'Every shift at this pharmacy is already covered.'
                        : 'The '.$shift.' shift is already covered at this pharmacy.',
                    'code' => 'shift_taken',
                    'free_shifts' => $free,
                ], 400);
            }

            $employee->pharmacy_id = $pharmacy->id;
            $employee->shift = $shift;
            $employee->status = Employee::STATUS_APPROVED;
            // Salary is the pharmacist's call for trainees too. It used to be
            // forced to null for them, silently discarding what was typed.
            $employee->salary = $request->salary;
            $employee->save();

            Notification::create([
                'pharmacy_id' => $pharmacy->id,
                'title' => 'Employee approved',
                'message' => $employee->name.' has been approved and added to your pharmacy.',
                'type' => 'employee_approved',
                'is_read' => false,
                'date' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => $employee->name.' now covers the '.$shift.' shift.',
                'code' => 'employee_approved',
                'employee' => (new SafeEmployeeResource($employee))->resolve($request),
            ]);
        } catch (QueryException $exception) {
            DB::rollBack();

            // The unique index caught a second hire into the same shift that
            // the check above could not see. On SQLite there is no row locking
            // to prevent the interleave, so this is the guarantee, not a
            // formality — report it as the same refusal.
            return response()->json([
                'message' => 'That shift was taken while you were deciding.',
                'code' => 'shift_taken',
                'free_shifts' => $pharmacy->fresh()->freeShifts(),
            ], 409);
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            return response()->json([
                'message' => 'The application could not be approved.',
                'code' => 'employee_approval_failed',
            ], 500);
        }
    }

    // ===== NEW: الصيدلاني يحذف موظف من صيدليته كلياً =====
    public function dismissEmployee(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->pharmacyContext->assertMatches($request, (int) $employee->pharmacy_id);
        Gate::forUser($request->user())->authorize('delete', $employee);

        if ($employee->status !== Employee::STATUS_APPROVED) {
            return response()->json([
                'message' => 'هذا الموظف ليس موظفاً نشطاً',
            ], 400);
        }

        if ($employee->documentVersions()->exists()) {
            return response()->json([
                'message' => 'This employee cannot be dismissed until the recruitment-document retention policy is defined.',
                'code' => 'employee_document_retention_required',
            ], 409);
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
            ->where('status', Employee::STATUS_APPROVED)
            ->orderByRaw("case shift when 'morning' then 0 when 'evening' then 1 else 2 end")
            ->get();

        return response()->json(['employees' => SafeEmployeeResource::collection($employees)->resolve($request)]);
    }
}
