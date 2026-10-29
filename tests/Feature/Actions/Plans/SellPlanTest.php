<?php

use App\Actions\Plans\SellPlan;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Thursday, October 29, in the afternoon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
});

it('sells a copy of the plan for its days from today, paid at the list price', function () {
    $plan = Plan::factory()->create(['name' => '8 clases al mes', 'price' => 45000, 'credits' => 8, 'validity_days' => 30, 'daily_limit' => 2]);
    $javiera = User::factory()->create();
    $camila = User::factory()->create();

    $purchase = app(SellPlan::class)->handle($plan, $javiera, $camila, PaymentMethod::Cash);
    $plan->update(['name' => 'Otro nombre', 'price' => 50000]);

    $purchase->refresh();
    expect($purchase)
        ->name->toBe('8 clases al mes')
        ->price->toBe(45000)
        ->credits->toBe(8)
        ->credits_left->toBe(8)
        ->daily_limit->toBe(2)
        ->sold_by_id->toBe($camila->id)
        ->price_reason->toBeNull()
        ->and($purchase->starts_on->toDateString())->toBe('2026-10-29')
        ->and($purchase->lastDay()->toDateString())->toBe('2026-11-27')
        ->and($purchase->expires_at->equalTo(CarbonImmutable::parse('2026-11-28 00:00', 'America/Santiago')))->toBeTrue();
    expect($purchase->payments()->sole())
        ->amount->toBe(45000)
        ->method->toBe(PaymentMethod::Cash)
        ->status->toBe(PaymentStatus::Approved)
        ->user_id->toBe($javiera->id)
        ->received_by_id->toBe($camila->id);
});

it('sells at another price with its reason, and an unlimited plan without credits', function () {
    $javiera = User::factory()->create();
    $carla = User::factory()->create();

    $gift = app(SellPlan::class)->handle(Plan::factory()->create(['price' => 45000]), $javiera, $carla, PaymentMethod::Transfer, 0, 'Regalo de cumpleaños');
    $unlimited = app(SellPlan::class)->handle(Plan::factory()->unlimited()->create(), $javiera, $carla, PaymentMethod::Card);

    expect($gift)->price->toBe(0)->price_reason->toBe('Regalo de cumpleaños')
        ->and($gift->payments()->sole()->amount)->toBe(0)
        ->and($unlimited)->credits->toBeNull()->credits_left->toBeNull()->daily_limit->toBe(1);
});
