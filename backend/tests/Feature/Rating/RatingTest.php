<?php

namespace Tests\Feature\Rating;

use App\Models\Rating;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Security\SecurityTestCase;

class RatingTest extends SecurityTestCase
{
    public function test_a_pharmacist_holds_one_rating_and_may_revise_it(): void
    {
        // It used to refuse a second attempt outright. Holding somebody to one
        // bad afternoon forever is not feedback — and it also put the note out
        // of reach of everyone who had already left a star.
        $owner = $this->pharmacist('rate-once');
        $this->pharmacy($owner, 'rate-once');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/rating', [
            'pharmacist_id' => $owner->id,
            'stars' => 5,
        ])->assertCreated()->assertJsonPath('code', 'rating_recorded');

        $this->postJson('/api/rating', [
            'pharmacist_id' => $owner->id,
            'stars' => 3,
        ])->assertOk()->assertJsonPath('code', 'rating_updated');

        $this->assertSame(1, Rating::where('pharmacist_id', $owner->id)->count());
        $this->assertSame(3, (int) Rating::sole()->stars);
    }

    public function test_a_rating_can_carry_the_reason_behind_it(): void
    {
        // A star records that somebody was unhappy without recording why, which
        // is the one thing feedback has to do.
        $owner = $this->pharmacist('rate-note');
        $this->pharmacy($owner, 'rate-note');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/rating', [
            'pharmacist_id' => $owner->id,
            'stars' => 2,
            'note' => 'The purchase cart is good but the till is slow to search.',
        ])->assertCreated();

        $this->assertSame(
            'The purchase cart is good but the till is slow to search.',
            Rating::sole()->note,
        );

        $this->getJson('/api/rating')
            ->assertOk()
            ->assertJsonPath('rating.note', 'The purchase cart is good but the till is slow to search.');
    }

    public function test_a_note_longer_than_the_column_is_refused(): void
    {
        $owner = $this->pharmacist('rate-long');
        $this->pharmacy($owner, 'rate-long');
        Sanctum::actingAs($owner, ['*'], 'pharmacist');

        $this->postJson('/api/rating', [
            'pharmacist_id' => $owner->id,
            'stars' => 4,
            'note' => str_repeat('a', 1001),
        ])->assertUnprocessable()->assertJsonValidationErrors('note');

        $this->assertSame(0, Rating::count());
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
