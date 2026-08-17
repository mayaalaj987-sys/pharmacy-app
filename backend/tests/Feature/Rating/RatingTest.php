<?php

namespace Tests\Feature\Rating;

use App\Models\Rating;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

class RatingTest extends SecurityTestCase
{
    public function test_pharmacist_submits_an_app_rating_once(): void
    {
        $owner = $this->pharmacist('rate-once');
        $this->pharmacy($owner, 'rate-once');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/rating', [
            'pharmacist_id' => $owner->id,
            'stars' => 5,
        ])->assertCreated();

        $this->assertDatabaseHas('ratings', [
            'pharmacist_id' => $owner->id,
            'stars' => 5,
        ]);

        $this->postJson('/api/rating', [
            'pharmacist_id' => $owner->id,
            'stars' => 3,
        ])->assertStatus(400);

        $this->assertSame(1, Rating::where('pharmacist_id', $owner->id)->count());
    }

    public function test_my_rating_reports_state_and_average(): void
    {
        $owner = $this->pharmacist('rate-state');
        $this->pharmacy($owner, 'rate-state');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->getJson('/api/rating')
            ->assertOk()
            ->assertJsonPath('has_rated', false)
            ->assertJsonPath('rating', null);

        $this->postJson('/api/rating', [
            'pharmacist_id' => $owner->id,
            'stars' => 4,
        ])->assertCreated();

        $this->getJson('/api/rating')
            ->assertOk()
            ->assertJsonPath('has_rated', true)
            ->assertJsonPath('rating.stars', 4)
            ->assertJsonPath('ratings_count', 1);
    }

    public function test_stars_outside_one_to_five_are_rejected(): void
    {
        $owner = $this->pharmacist('rate-range');
        $this->pharmacy($owner, 'rate-range');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        foreach ([0, 6, -1] as $stars) {
            $this->postJson('/api/rating', [
                'pharmacist_id' => $owner->id,
                'stars' => $stars,
            ])->assertUnprocessable()->assertJsonValidationErrors('stars');
        }

        $this->assertSame(0, Rating::count());
    }

    public function test_a_pharmacist_cannot_rate_on_behalf_of_another(): void
    {
        $owner = $this->pharmacist('rate-actor');
        $other = $this->pharmacist('rate-actor-other');
        $this->pharmacy($owner, 'rate-actor');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/rating', [
            'pharmacist_id' => $other->id,
            'stars' => 5,
        ])->assertForbidden();

        $this->assertSame(0, Rating::count());
    }

    public function test_employees_cannot_rate_the_application(): void
    {
        $owner = $this->pharmacist('rate-employee');
        $pharmacy = $this->pharmacy($owner, 'rate-employee');
        $employee = $this->employee($pharmacy, '401');
        Sanctum::actingAs($employee, ['*'], 'employee');

        $this->getJson('/api/rating')->assertUnauthorized();
        $this->postJson('/api/rating', [
            'pharmacist_id' => $owner->id,
            'stars' => 5,
        ])->assertUnauthorized();
    }
}
