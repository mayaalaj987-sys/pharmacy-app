<?php

namespace Tests\Feature\Security;

use App\Models\Employee;
use App\Models\Pharmacist;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Brute-force protection on the four unauthenticated write endpoints.
 *
 * The admin console has had a login limiter since it was built. The mobile API
 * never got one, even though it covers every pharmacist and employee account —
 * an attacker had unlimited guesses at any of them. Registration was equally
 * open, and an unlimited supply of applicants is a spam surface now that every
 * one of them is visible to recruiters.
 */
class PublicEndpointThrottleTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_pharmacist_login_stops_accepting_guesses(): void
    {
        Pharmacist::create([
            'name' => 'Maya Alhaj',
            'email' => 'guessed@example.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/login', [
                'email' => 'guessed@example.test',
                'password' => 'wrong-guess',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => 'guessed@example.test',
            'password' => 'wrong-guess',
        ])->assertStatus(429);

        // The lockout must not be bypassable by suddenly guessing right.
        $this->postJson('/api/login', [
            'email' => 'guessed@example.test',
            'password' => 'correct-horse-battery',
        ])->assertStatus(429);
    }

    public function test_employee_login_stops_accepting_guesses(): void
    {
        Employee::create([
            'name' => 'Rana Saeed',
            'phone' => '0999000111',
            'email' => 'guessed-employee@example.test',
            'password' => Hash::make('correct-horse-battery'),
            'cv' => '',
            'role' => Employee::ROLE_EMPLOYEE,
            'status' => Employee::STATUS_PENDING,
        ]);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/employee/login', [
                'email' => 'guessed-employee@example.test',
                'password' => 'wrong-guess',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/employee/login', [
            'email' => 'guessed-employee@example.test',
            'password' => 'wrong-guess',
        ])->assertStatus(429);
    }

    public function test_one_locked_account_does_not_lock_out_another(): void
    {
        // Keyed on email as well as IP, so a shared address — a pharmacy's wifi,
        // a mobile carrier NAT — cannot be used to lock a colleague out.
        $victim = $this->pharmacist('throttle-victim');
        $this->pharmacy($victim, 'throttle-victim');

        foreach (range(1, 6) as $ignored) {
            $this->postJson('/api/login', [
                'email' => 'attacked@example.test',
                'password' => 'wrong-guess',
            ]);
        }

        $this->postJson('/api/login', [
            'email' => $victim->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_registration_is_capped_per_address(): void
    {
        foreach (range(1, 10) as $index) {
            $this->postJson('/api/employee/register', $this->application($index))
                ->assertCreated();
        }

        $this->postJson('/api/employee/register', $this->application(11))
            ->assertStatus(429);

        $this->assertSame(10, Employee::count());
    }

    public function test_a_short_password_is_refused_at_registration(): void
    {
        // Changing a password already required eight characters; registering
        // required six, so the floor was lowest exactly where accounts are born.
        $this->postJson('/api/employee/register', [
            ...$this->application(99),
            'password' => 'short12',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertSame(0, Employee::count());
    }

    private function application(int $index): array
    {
        return [
            'name' => 'Applicant '.$index,
            'phone' => '09990001'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'email' => 'applicant-'.$index.'@example.test',
            'password' => 'password123',
            'role' => Employee::ROLE_TRAINEE,
            'cv' => $this->validPdfUpload('cv.pdf'),
        ];
    }
}
