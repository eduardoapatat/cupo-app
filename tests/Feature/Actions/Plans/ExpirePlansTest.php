<?php

use App\Enums\CreditMovementReason;
use App\Models\CreditMovement;
use App\Models\PlanPurchase;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Monday, November 2, just after midnight in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 00:20', 'America/Santiago'));
});

it('takes the classes left in an expired plan, with one movement that says how many', function () {
    $expired = PlanPurchase::factory()->expired()->create(['credits' => 8, 'credits_left' => 3]);
    $valid = PlanPurchase::factory()->create(['credits' => 8, 'credits_left' => 5]);

    $this->artisan('plans:expire')->expectsOutput('Classes expired: 3.')->assertSuccessful();

    expect($expired->refresh()->credits_left)->toBe(0)
        ->and($valid->refresh()->credits_left)->toBe(5)
        ->and($expired->creditMovements()->sole())
        ->delta->toBe(-3)
        ->reason->toBe(CreditMovementReason::Expired)
        ->booking_id->toBeNull()
        ->and(PlanPurchase::query()->whereKey($expired->id)->withExpiredCredits()->sole()->expiredCredits())->toBe(3);
});

it('expires each class once, however many times it runs', function () {
    $expired = PlanPurchase::factory()->expired()->create(['credits' => 8, 'credits_left' => 3]);

    $this->artisan('plans:expire')->assertSuccessful();
    $this->artisan('plans:expire')->expectsOutput('Classes expired: 0.')->assertSuccessful();

    expect($expired->creditMovements()->count())->toBe(1);
});

it('leaves alone unlimited plans, voided ones and those with nothing left', function () {
    $unlimited = PlanPurchase::factory()->expired()->unlimited()->create();
    $voided = PlanPurchase::factory()->expired()->create(['credits_left' => 4, 'cancelled_at' => now()->subDays(5)]);
    $usedUp = PlanPurchase::factory()->expired()->create(['credits' => 8, 'credits_left' => 0]);

    $this->artisan('plans:expire')->expectsOutput('Classes expired: 0.')->assertSuccessful();

    expect($voided->refresh()->credits_left)->toBe(4)
        ->and(CreditMovement::count())->toBe(0)
        ->and($unlimited->refresh()->credits_left)->toBeNull()
        ->and($usedUp->refresh()->credits_left)->toBe(0);
});

it('lets only an expiry take more than one class at a time', function () {
    $purchase = PlanPurchase::factory()->create();

    $movement = fn (int $delta, CreditMovementReason $reason): Closure => fn () => CreditMovement::create([
        'plan_purchase_id' => $purchase->id,
        'delta' => $delta,
        'reason' => $reason,
        'created_at' => now(),
    ]);

    expectSqlState($movement(-3, CreditMovementReason::Booking), '23514');
    expectSqlState($movement(2, CreditMovementReason::Expired), '23514');
});
