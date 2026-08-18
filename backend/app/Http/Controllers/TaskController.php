<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Task;
use App\Services\PharmacyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function __construct(private readonly PharmacyContextResolver $pharmacyContext) {}

    // ===== الصيدلاني: إضافة مهمة لموظف =====
    public function createTask(Request $request): JsonResponse
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'employee_id' => 'required|exists:employees,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $pharmacy = $this->pharmacyContext->resolve($request);

        $employee = Employee::where('id', $request->employee_id)
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', Employee::STATUS_APPROVED)
            ->first();

        if (! $employee) {
            return response()->json([
                'message' => 'الموظف غير موجود في هذه الصيدلية',
            ], 404);
        }

        $task = Task::create([
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $request->employee_id,
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        // إشعار للصيدلية إن مهمة انضافت
        Notification::create([
            'pharmacy_id' => $pharmacy->id,
            'title' => 'مهمة جديدة',
            'message' => 'تم تعيين مهمة "'.$task->title.'" للموظف '.$employee->name,
            'type' => 'task',
            'is_read' => false,
            'date' => now(),
        ]);

        return response()->json([
            'message' => 'تم إضافة المهمة بنجاح',
            'task' => $task,
        ], 201);
    }

    // ===== الصيدلاني: عرض كل مهام صيدلية =====
    public function getPharmacyTasks(Request $request): JsonResponse
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $pharmacyId = $this->pharmacyContext->resolve($request)->id;

        $tasks = Task::where('pharmacy_id', $pharmacyId)
            ->with('employee:id,name,role')
            ->latest()
            ->get();

        return response()->json([
            'pending_count' => $tasks->where('status', 'pending')->count(),
            'done_count' => $tasks->where('status', 'done')->count(),
            'tasks' => $tasks,
        ]);
    }

    // ===== الصيدلاني: حذف مهمة =====
    public function deleteTask(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $this->pharmacyContext->assertMatches($request, (int) $task->pharmacy_id);
        Gate::forUser($request->user())->authorize('delete', $task);
        $task->delete();

        return response()->json([
            'message' => 'تم حذف المهمة',
        ]);
    }

    // ===== الموظف: عرض مهامه =====
    public function getMyTasks(Request $request): JsonResponse
    {
        $employee = $request->user(); // من الـ token
        $pharmacy = $this->pharmacyContext->resolve($request);

        // Scoped to the current pharmacy as well as the person. An employee who
        // leaves one pharmacy and joins another keeps their row, so filtering on
        // employee_id alone would show them their old employer's tasks.
        $tasks = Task::where('employee_id', $employee->id)
            ->where('pharmacy_id', $pharmacy->id)
            ->latest()
            ->get();

        return response()->json([
            'pending_count' => $tasks->where('status', 'pending')->count(),
            'done_count' => $tasks->where('status', 'done')->count(),
            'tasks' => $tasks,
        ]);
    }

    // ===== الموظف: يعلم على المهمة "تم" =====
    public function markAsDone(Request $request, $id): JsonResponse
    {
        $employee = $request->user();

        $task = Task::where('id', $id)
            ->where('employee_id', $employee->id) // يتأكد إن المهمة إلو هو
            ->firstOrFail();
        $this->pharmacyContext->assertMatches($request, (int) $task->pharmacy_id);
        Gate::forUser($employee)->authorize('update', $task);

        if ($task->status === 'done') {
            return response()->json([
                'message' => 'المهمة منجزة مسبقاً',
            ], 400);
        }

        $task->update(['status' => 'done']);

        // إشعار للصيدلية إن الموظف أنجز المهمة
        Notification::create([
            'pharmacy_id' => $task->pharmacy_id,
            'title' => 'مهمة منجزة ✅',
            'message' => 'أنجز الموظف '.$employee->name.' المهمة: "'.$task->title.'"',
            'type' => 'task',
            'is_read' => false,
            'date' => now(),
        ]);

        return response()->json([
            'message' => 'تم تعليم المهمة كمنجزة',
            'task' => $task,
        ]);
    }
}
