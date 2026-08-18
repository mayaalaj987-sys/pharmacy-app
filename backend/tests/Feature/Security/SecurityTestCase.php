<?php

namespace Tests\Feature\Security;

use App\Models\Employee;
use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

abstract class SecurityTestCase extends TestCase
{
    use RefreshDatabase;

    protected function pharmacist(string $suffix): Pharmacist
    {
        return Pharmacist::create([
            'name' => 'Pharmacist '.$suffix,
            'email' => 'pharmacist-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    protected function pharmacy(Pharmacist $pharmacist, string $suffix, string $status = 'approved'): Pharmacy
    {
        return Pharmacy::create([
            'pharmacist_id' => $pharmacist->id,
            'pharmacy_name' => 'Pharmacy '.$suffix,
            'pharmacy_address' => 'Address '.$suffix,
            'certificate' => 'certificate.pdf',
            'license' => 'license.pdf',
            'status' => $status,
        ]);
    }

    /**
     * An employed member of staff.
     *
     * The shift defaults to the first one still free at this pharmacy, so a
     * test that hires two people gets morning and evening without saying so,
     * and a test that tries to hire a third fails — which is the real rule.
     */
    protected function employee(Pharmacy $pharmacy, string $suffix, ?string $shift = null): Employee
    {
        return Employee::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Employee '.$suffix,
            'phone' => '0999000'.$suffix,
            'email' => 'employee-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'cv' => 'cv.pdf',
            'role' => Employee::ROLE_EMPLOYEE,
            'shift' => $shift ?? ($pharmacy->fresh()->freeShifts()[0] ?? null),
            'status' => Employee::STATUS_APPROVED,
            'first_login' => false,
        ]);
    }
}
