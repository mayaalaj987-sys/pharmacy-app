<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterEmployeeRequest;
use App\Http\Resources\SafeEmployeeResource;
use App\Models\Employee;
use App\Services\AuthSessionService;
use App\Services\DocumentVersionService;
use App\Services\PharmacyContextResolver;
use App\Services\PrivateDocumentService;
use App\Services\RecruitmentService;
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
        private readonly PrivateDocumentService $documents,
        private readonly DocumentVersionService $documentVersions,
        private readonly RecruitmentService $recruitment,
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

    // ===== NEW: الصيدلاني يحذف موظف من صيدليته كلياً =====
    public function dismissEmployee(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->pharmacyContext->assertMatches($request, (int) $employee->pharmacy_id);
        Gate::forUser($request->user())->authorize('delete', $employee);

        if (! $employee->isEmployed()) {
            return response()->json([
                'message' => 'This person does not work at your pharmacy.',
                'code' => 'employee_not_active',
            ], 400);
        }

        // No document check any more. It refused every real employee, because
        // registering always creates a CV — and it existed to stop a hard
        // delete that would have taken their tasks and sales with it. Nothing
        // is deleted now.
        $this->recruitment->endEmployment($employee, 'pharmacy');

        return response()->json([
            'message' => $employee->name.' no longer works at your pharmacy.',
            'code' => 'employee_detached',
            'employee_id' => $employee->id,
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
