<?php

namespace Tests\Feature\Notifications;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Admin\AdminTestCase;

class NotificationEventsTest extends AdminTestCase
{
    public function test_pharmacy_approval_creates_an_english_notification_for_the_owner(): void
    {
        $admin = $this->admin('notify-approve');
        $pharmacy = $this->pendingPharmacy('notify-approve')->refresh();

        $this->asAdmin($admin)
            ->postJson('/api/admin/review/applications/'.$pharmacy->id.'/approve', [
                'review_version' => $pharmacy->review_version,
            ])
            ->assertOk();

        $notification = Notification::where('pharmacy_id', $pharmacy->id)
            ->where('type', 'pharmacy_approved')
            ->first();

        $this->assertNotNull($notification);
        $this->assertFalse($notification->is_read);
        $this->assertSame('Pharmacy approved', $notification->title);
        $this->assertTrue($this->isAscii($notification->message));
    }

    public function test_pharmacy_rejection_creates_a_notification_including_the_reason(): void
    {
        $admin = $this->admin('notify-reject');
        $pharmacy = $this->pendingPharmacy('notify-reject')->refresh();

        $this->asAdmin($admin)
            ->postJson('/api/admin/review/applications/'.$pharmacy->id.'/reject', [
                'review_version' => $pharmacy->review_version,
                'reason' => 'Submitted licence is unreadable.',
            ])
            ->assertOk();

        $notification = Notification::where('pharmacy_id', $pharmacy->id)
            ->where('type', 'pharmacy_rejected')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('Pharmacy rejected', $notification->title);
        $this->assertStringContainsString('Submitted licence is unreadable.', $notification->message);
        $this->assertTrue($this->isAscii($notification->message));
    }

    public function test_a_duplicate_decision_does_not_create_a_second_notification(): void
    {
        $admin = $this->admin('notify-duplicate');
        $pharmacy = $this->pendingPharmacy('notify-duplicate')->refresh();

        $this->asAdmin($admin)
            ->postJson('/api/admin/review/applications/'.$pharmacy->id.'/approve', [
                'review_version' => $pharmacy->review_version,
            ])
            ->assertOk();

        $fresh = Pharmacy::find($pharmacy->id);
        $this->asAdmin($admin)
            ->postJson('/api/admin/review/applications/'.$pharmacy->id.'/approve', [
                'review_version' => $fresh->review_version,
            ])
            ->assertOk();

        $this->assertSame(
            1,
            Notification::where('pharmacy_id', $pharmacy->id)
                ->where('type', 'pharmacy_approved')
                ->count(),
        );
    }

    public function test_accepting_an_offer_notifies_the_hiring_pharmacy(): void
    {
        $owner = Pharmacist::create([
            'name' => 'Owner notify-emp',
            'email' => 'owner-notify-emp@example.test',
            'password' => 'Strong!Password123',
        ]);
        $pharmacy = Pharmacy::create([
            'pharmacist_id' => $owner->id,
            'pharmacy_name' => 'Pharmacy notify-emp',
            'pharmacy_address' => 'Address notify-emp',
            'certificate' => 'certificate.pdf',
            'license' => 'license.pdf',
            'status' => 'approved',
        ]);
        $applicant = Employee::create([
            'pharmacy_id' => null,
            'name' => 'Applicant One',
            'phone' => '0999000301',
            'email' => 'applicant-notify@example.test',
            'password' => 'Strong!Password123',
            'cv' => 'cv.pdf',
            'role' => 'employee',
            'status' => 'pending',
            'first_login' => true,
        ]);

        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $offerId = $this->postJson('/api/recruitment/offers', [
            'employee_id' => $applicant->id,
            'shift' => 'morning',
            'salary' => 500,
        ])->assertCreated()->json('offer.id');

        // Sending is not a hiring event: nothing has happened to the pharmacy
        // yet, and announcing it would tell the owner their own action back.
        $this->assertSame(0, Notification::where('pharmacy_id', $pharmacy->id)->count());

        Sanctum::actingAs($applicant, ['*'], 'employee');
        $this->postJson('/api/employee/offers/'.$offerId.'/accept')->assertOk();

        $notification = Notification::where('pharmacy_id', $pharmacy->id)
            ->where('type', 'employee_offer_accepted')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('Offer accepted', $notification->title);
        $this->assertStringContainsString('Applicant One', $notification->message);
        $this->assertTrue($this->isAscii($notification->message));
    }

    public function test_a_pharmacy_left_waiting_is_told_the_applicant_went_elsewhere(): void
    {
        // Otherwise it sits on an offer that can never be accepted, with no way
        // of knowing why. The offer itself is deliberately left pending.
        $applicant = Employee::create([
            'pharmacy_id' => null,
            'name' => 'Applicant Two',
            'phone' => '0999000302',
            'email' => 'applicant-elsewhere@example.test',
            'password' => 'Strong!Password123',
            'cv' => 'cv.pdf',
            'role' => 'employee',
            'status' => 'pending',
            'first_login' => true,
        ]);

        $rivals = [];
        foreach (['rival-a', 'rival-b'] as $suffix) {
            $owner = Pharmacist::create([
                'name' => 'Owner '.$suffix,
                'email' => 'owner-'.$suffix.'@example.test',
                'password' => 'Strong!Password123',
            ]);
            $pharmacy = Pharmacy::create([
                'pharmacist_id' => $owner->id,
                'pharmacy_name' => 'Pharmacy '.$suffix,
                'pharmacy_address' => 'Address '.$suffix,
                'certificate' => 'certificate.pdf',
                'license' => 'license.pdf',
                'status' => 'approved',
            ]);

            Sanctum::actingAs($owner, ['*'], 'pharmacist');
            $rivals[$suffix] = [
                'pharmacy' => $pharmacy,
                'offer' => $this->postJson('/api/recruitment/offers', [
                    'employee_id' => $applicant->id,
                    'shift' => 'morning',
                ])->assertCreated()->json('offer.id'),
            ];
        }

        Sanctum::actingAs($applicant, ['*'], 'employee');
        $this->postJson('/api/employee/offers/'.$rivals['rival-a']['offer'].'/accept')->assertOk();

        $told = Notification::where('pharmacy_id', $rivals['rival-b']['pharmacy']->id)
            ->where('type', 'employee_hired_elsewhere')
            ->first();

        $this->assertNotNull($told);
        $this->assertTrue($this->isAscii($told->message));

        // The losing offer is untouched, so it becomes live again by itself if
        // the job they took does not last.
        $this->assertDatabaseHas('job_offers', [
            'id' => $rivals['rival-b']['offer'],
            'status' => 'pending',
        ]);
    }

    private function isAscii(string $value): bool
    {
        return mb_check_encoding($value, 'ASCII');
    }
}
