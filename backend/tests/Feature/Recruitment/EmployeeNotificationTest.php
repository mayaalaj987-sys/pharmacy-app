<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

/**
 * The channel that lets the platform tell a person something.
 *
 * `notifications.pharmacy_id` was NOT NULL, so nothing could ever be addressed
 * to an employee — least of all one who has no pharmacy, which is exactly the
 * person a job search concerns. Nothing sends these yet; this covers the pipe.
 */
class EmployeeNotificationTest extends SecurityTestCase
{
    public function test_an_unattached_applicant_can_read_their_own_bell(): void
    {
        // The pharmacy-scoped notification routes 403 without a pharmacy, so
        // before this endpoint existed a job seeker could be told nothing.
        $applicant = $this->applicant('notif-pool');
        Notification::notifyEmployee(
            $applicant->id,
            'Offer received',
            'Barada Pharmacy offered you the morning shift.',
            Notification::TYPE_OFFER_RECEIVED,
        );

        Sanctum::actingAs($applicant, ['*'], 'employee');

        $this->getJson('/api/employee/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.type', 'offer_received')
            ->assertJsonPath('notifications.0.personal', true);
    }

    public function test_a_personal_message_never_appears_in_a_pharmacys_list(): void
    {
        $owner = $this->pharmacist('notif-leak');
        $pharmacy = $this->pharmacy($owner, 'notif-leak');
        $employee = $this->employee($pharmacy, '901');

        Notification::notifyEmployee(
            $employee->id,
            'CV viewed',
            'A pharmacy read your CV.',
            Notification::TYPE_CV_VIEWED,
        );
        Notification::notify($pharmacy->id, 'Low stock', 'Panadol is running out.', 'stock');

        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        // The owner sees pharmacy traffic and nothing addressed to a person —
        // guaranteed by the null pharmacy_id, not by a filter added here.
        $this->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->getJson('/api/notifications?pharmacy_id='.$pharmacy->id)
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.type', 'stock');
    }

    public function test_an_employed_person_sees_both_their_own_and_their_pharmacys(): void
    {
        $owner = $this->pharmacist('notif-both');
        $pharmacy = $this->pharmacy($owner, 'notif-both');
        $employee = $this->employee($pharmacy, '902');

        Notification::notifyEmployee($employee->id, 'CV viewed', 'Someone read your CV.', Notification::TYPE_CV_VIEWED);
        Notification::notify($pharmacy->id, 'New task', 'Restock shelf A.', 'task');

        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/employee/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('unread_count', 2);
    }

    public function test_one_employee_cannot_read_anothers(): void
    {
        $mine = $this->applicant('notif-mine');
        $theirs = $this->applicant('notif-theirs');
        Notification::notifyEmployee($theirs->id, 'Offer received', 'Not for you.', Notification::TYPE_OFFER_RECEIVED);

        Sanctum::actingAs($mine, ['*'], 'employee');

        $this->getJson('/api/employee/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'notifications');
    }

    public function test_a_colleagues_pharmacy_traffic_stops_when_the_job_does(): void
    {
        // Someone who leaves keeps their row, so "my pharmacy's messages" has
        // to mean the pharmacy they work at now, not one they used to.
        $owner = $this->pharmacist('notif-left');
        $pharmacy = $this->pharmacy($owner, 'notif-left');
        $employee = $this->employee($pharmacy, '903');
        Notification::notify($pharmacy->id, 'New task', 'Restock shelf A.', 'task');

        Sanctum::actingAs($employee, ['*'], 'employee');
        $this->getJson('/api/employee/notifications')->assertOk()->assertJsonCount(1, 'notifications');

        $employee->forceFill(['pharmacy_id' => null, 'shift' => null, 'status' => Employee::STATUS_PENDING])->save();

        $this->getJson('/api/employee/notifications')->assertOk()->assertJsonCount(0, 'notifications');
    }

    public function test_marking_read_works_and_is_scoped(): void
    {
        $mine = $this->applicant('notif-read-mine');
        $theirs = $this->applicant('notif-read-theirs');
        $ours = Notification::notifyEmployee($mine->id, 'Offer received', 'Yours.', Notification::TYPE_OFFER_RECEIVED);
        $foreign = Notification::notifyEmployee($theirs->id, 'Offer received', 'Theirs.', Notification::TYPE_OFFER_RECEIVED);

        Sanctum::actingAs($mine, ['*'], 'employee');

        $this->postJson('/api/employee/notifications/'.$ours->id.'/read')
            ->assertOk()
            ->assertJsonPath('code', 'notification_read');
        $this->assertTrue($ours->fresh()->is_read);

        // Someone else's is not "forbidden", it simply does not exist for you.
        $this->postJson('/api/employee/notifications/'.$foreign->id.'/read')
            ->assertNotFound()
            ->assertJsonPath('code', 'notification_not_found');
        $this->assertFalse($foreign->fresh()->is_read);
    }

    public function test_a_pharmacist_cannot_reach_the_employee_bell(): void
    {
        $owner = $this->pharmacist('notif-guard');
        $this->pharmacy($owner, 'notif-guard');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson('/api/employee/notifications')->assertUnauthorized();
        $this->postJson('/api/employee/notifications/1/read')->assertUnauthorized();
    }

    public function test_deleting_a_pharmacy_still_cascades_its_notifications(): void
    {
        // The pharmacy_id foreign key had to survive being made nullable, which
        // on SQLite means surviving a full table rebuild.
        $owner = $this->pharmacist('notif-cascade');
        $pharmacy = $this->pharmacy($owner, 'notif-cascade');
        Notification::notify($pharmacy->id, 'Low stock', 'Panadol is running out.', 'stock');

        Pharmacy::whereKey($pharmacy->id)->delete();

        $this->assertSame(0, Notification::count());
    }

    private function applicant(string $suffix): Employee
    {
        return Employee::create([
            'pharmacy_id' => null,
            'name' => 'Applicant '.$suffix,
            'phone' => '0944'.substr(md5($suffix), 0, 6),
            'email' => 'applicant-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'cv' => '',
            'role' => Employee::ROLE_EMPLOYEE,
            'status' => Employee::STATUS_PENDING,
            'first_login' => true,
        ]);
    }
}
