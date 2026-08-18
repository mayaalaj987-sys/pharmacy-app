<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

class TaskWorkflowTest extends SecurityTestCase
{
    public function test_pharmacist_assigns_a_task_to_their_own_employee(): void
    {
        $owner = $this->pharmacist('task-assign');
        $pharmacy = $this->pharmacy($owner, 'task-assign');
        $employee = $this->employee($pharmacy, '201');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/tasks', [
            'employee_id' => $employee->id,
            'title' => 'Restock shelf A',
            'description' => 'Refill painkillers',
        ])->assertCreated();

        $this->assertDatabaseHas('tasks', [
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Restock shelf A',
            'status' => 'pending',
        ]);
    }

    public function test_pharmacist_cannot_assign_a_task_to_another_pharmacys_employee(): void
    {
        $owner = $this->pharmacist('task-cross-owner');
        $otherOwner = $this->pharmacist('task-cross-other');
        $this->pharmacy($owner, 'task-cross-owner');
        $otherPharmacy = $this->pharmacy($otherOwner, 'task-cross-other');
        $foreignEmployee = $this->employee($otherPharmacy, '202');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/tasks', [
            'employee_id' => $foreignEmployee->id,
            'title' => 'Should not be created',
        ])->assertNotFound();

        $this->assertDatabaseMissing('tasks', ['employee_id' => $foreignEmployee->id]);
    }

    public function test_task_creation_requires_a_title(): void
    {
        $owner = $this->pharmacist('task-validation');
        $pharmacy = $this->pharmacy($owner, 'task-validation');
        $employee = $this->employee($pharmacy, '203');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/tasks', ['employee_id' => $employee->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_employee_sees_only_their_own_tasks(): void
    {
        $owner = $this->pharmacist('task-scope');
        $pharmacy = $this->pharmacy($owner, 'task-scope');
        $mine = $this->employee($pharmacy, '204');
        $colleague = $this->employee($pharmacy, '205');

        $ownTask = Task::create([
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $mine->id,
            'title' => 'My task',
            'status' => 'pending',
        ]);
        Task::create([
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $colleague->id,
            'title' => 'Colleague task',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($mine, ['*'], 'employee');

        $response = $this->getJson('/api/tasks')->assertOk();
        $response->assertJsonPath('pending_count', 1);
        $response->assertJsonCount(1, 'tasks');
        $response->assertJsonPath('tasks.0.id', $ownTask->id);
        $response->assertJsonMissing(['title' => 'Colleague task']);
    }

    public function test_employee_marks_their_task_done_and_pharmacist_sees_the_new_status(): void
    {
        $owner = $this->pharmacist('task-done');
        $pharmacy = $this->pharmacy($owner, 'task-done');
        $employee = $this->employee($pharmacy, '206');
        $task = Task::create([
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Count inventory',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee, ['*'], 'employee');
        $this->postJson('/api/tasks/'.$task->id.'/done')->assertOk();
        $this->assertSame('done', $task->fresh()->status);

        // The pharmacist list reflects the transition performed by the employee.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner, ['*'], 'pharmacist');
        $this->getJson('/api/tasks/pharmacy')
            ->assertOk()
            ->assertJsonPath('done_count', 1)
            ->assertJsonPath('pending_count', 0)
            ->assertJsonPath('tasks.0.status', 'done')
            ->assertJsonPath('tasks.0.employee.name', $employee->name);
    }

    public function test_completing_an_already_completed_task_is_rejected(): void
    {
        $owner = $this->pharmacist('task-twice');
        $pharmacy = $this->pharmacy($owner, 'task-twice');
        $employee = $this->employee($pharmacy, '207');
        $task = Task::create([
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Already done',
            'status' => 'done',
        ]);
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->postJson('/api/tasks/'.$task->id.'/done')->assertStatus(400);
    }

    public function test_employee_cannot_complete_a_colleagues_task(): void
    {
        $owner = $this->pharmacist('task-foreign-done');
        $pharmacy = $this->pharmacy($owner, 'task-foreign-done');
        $mine = $this->employee($pharmacy, '208');
        $colleague = $this->employee($pharmacy, '209');
        $task = Task::create([
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $colleague->id,
            'title' => 'Not mine',
            'status' => 'pending',
        ]);
        Sanctum::actingAs($mine, ['*'], 'employee');

        $this->postJson('/api/tasks/'.$task->id.'/done')->assertNotFound();
        $this->assertSame('pending', $task->fresh()->status);
    }

    public function test_pharmacist_task_list_is_scoped_to_their_pharmacy(): void
    {
        $owner = $this->pharmacist('task-list-owner');
        $otherOwner = $this->pharmacist('task-list-other');
        $pharmacy = $this->pharmacy($owner, 'task-list-owner');
        $otherPharmacy = $this->pharmacy($otherOwner, 'task-list-other');
        $employee = $this->employee($pharmacy, '210');
        $foreignEmployee = $this->employee($otherPharmacy, '211');

        Task::create([
            'pharmacy_id' => $pharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Mine',
            'status' => 'pending',
        ]);
        Task::create([
            'pharmacy_id' => $otherPharmacy->id,
            'employee_id' => $foreignEmployee->id,
            'title' => 'Theirs',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson('/api/tasks/pharmacy')
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.title', 'Mine')
            ->assertJsonMissing(['title' => 'Theirs']);
    }

    public function test_an_employees_own_task_list_is_scoped_to_their_current_pharmacy(): void
    {
        // An employee row outlives any one job: they can be moved between
        // pharmacies. Filtering their task list on employee_id alone would show
        // them work assigned by a pharmacy they no longer belong to.
        $formerOwner = $this->pharmacist('task-scope-former');
        $formerPharmacy = $this->pharmacy($formerOwner, 'task-scope-former');
        $currentOwner = $this->pharmacist('task-scope-current');
        $currentPharmacy = $this->pharmacy($currentOwner, 'task-scope-current');

        $employee = $this->employee($currentPharmacy, '208');

        Task::create([
            'pharmacy_id' => $formerPharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Left behind at the old job',
            'status' => 'pending',
        ]);
        Task::create([
            'pharmacy_id' => $currentPharmacy->id,
            'employee_id' => $employee->id,
            'title' => 'Restock shelf A',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.title', 'Restock shelf A')
            ->assertJsonPath('pending_count', 1)
            ->assertJsonMissing(['title' => 'Left behind at the old job']);
    }
}
