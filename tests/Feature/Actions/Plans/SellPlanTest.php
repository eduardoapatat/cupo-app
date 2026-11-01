<?php

use App\Actions\Bookings\BookSession;
use App\Actions\CashRegisters\CashRegisterRejected;
use App\Actions\Plans\SellPlan;
use App\Enums\BookingKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CashRegister;
use App\Models\ClassSession;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Thursday, October 29, in the afternoon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
});

/**
 * Today's open register of someone of the team, in the plan's studio.
 */
function registerFor(Plan $plan, ?User $person = null): CashRegister
{
    return CashRegister::factory()->create([
        'studio_id' => $plan->studio_id,
        'user_id' => $person ?? User::factory(),
    ]);
}

it('sells a copy of the plan for its days from today, paid at the list price into the register', function () {
    $plan = Plan::factory()->create(['name' => '8 clases al mes', 'price' => 45000, 'credits' => 8, 'validity_days' => 30, 'daily_limit' => 2]);
    $javiera = User::factory()->create();
    $camila = User::factory()->create();
    $register = registerFor($plan, $camila);

    $purchase = app(SellPlan::class)->handle($plan, $javiera, $register, PaymentMethod::Cash);
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
        ->cash_register_id->toBe($register->id)
        ->received_by_id->toBe($camila->id)
        ->paid_at->toEqual(now())
        ->and($register->totals())->toBe(['cash' => 45000, 'card' => 0, 'transfer' => 0]);
});

it('sells at another price with its reason, and an unlimited plan without credits', function () {
    $javiera = User::factory()->create();
    $plan = Plan::factory()->create(['price' => 45000]);
    $unlimitedPlan = Plan::factory()->unlimited()->create();

    $gift = app(SellPlan::class)->handle($plan, $javiera, registerFor($plan), PaymentMethod::Transfer, 0, 'Regalo de cumpleaños');
    $unlimited = app(SellPlan::class)->handle($unlimitedPlan, $javiera, registerFor($unlimitedPlan), PaymentMethod::Card);

    expect($gift)->price->toBe(0)->price_reason->toBe('Regalo de cumpleaños')
        ->and($gift->payments()->sole()->amount)->toBe(0)
        ->and($unlimited)->credits->toBeNull()->credits_left->toBeNull()->daily_limit->toBe(1);
});

it('sells nothing into a register that was closed meanwhile', function () {
    $plan = Plan::factory()->create();
    $register = registerFor($plan);
    $stale = $register->replicate();
    $stale->id = $register->id;
    $register->forceFill(['closed_at' => now(), 'expected_cash' => 20000, 'counted_cash' => 20000, 'cash_total' => 0, 'card_total' => 0, 'transfer_total' => 0])->save();

    expect(fn () => app(SellPlan::class)->handle($plan, User::factory()->create(), $stale, PaymentMethod::Cash))
        ->toThrow(CashRegisterRejected::class, 'Tu caja se cerró, así que no se cobró nada.');
    expect($plan->purchases()->count())->toBe(0);
});

/**
 * Sell her the plan and say on which day it starts and its last day.
 *
 * @return array{0: string, 1: string}
 */
function soldDays(object $test): array
{
    $purchase = app(SellPlan::class)->handle($test->plan, $test->rosa, $test->register, PaymentMethod::Cash);

    return [$purchase->starts_on->toDateString(), $purchase->lastDay()->toDateString()];
}

describe('one plan at a time', function () {
    beforeEach(function () {
        $this->plan = Plan::factory()->create(['name' => '8 clases al mes', 'price' => 45000, 'credits' => 8, 'validity_days' => 30]);
        $this->rosa = User::factory()->create();
        $this->register = registerFor($this->plan);
    });

    it('starts today when she has no plan', function () {
        expect(soldDays($this))->toBe(['2026-10-29', '2026-11-27']);
    });

    it('waits until her current plan ends while it has classes, one after the other', function () {
        soldDays($this);

        $second = app(SellPlan::class)->handle($this->plan, $this->rosa, $this->register, PaymentMethod::Cash);
        $third = app(SellPlan::class)->handle($this->plan, $this->rosa, $this->register, PaymentMethod::Cash);

        expect($second->starts_on->toDateString())->toBe('2026-11-28')
            ->and($second->lastDay()->toDateString())->toBe('2026-12-27')
            ->and($second->isQueued())->toBeTrue()
            ->and($second->isValid())->toBeFalse()
            ->and($third->starts_on->toDateString())->toBe('2026-12-28')
            ->and($this->rosa->planPurchases()->valid()->count())->toBe(1)
            ->and($this->rosa->planPurchases()->queued()->count())->toBe(2);
    });

    it('waits after an unlimited plan too', function () {
        app(SellPlan::class)->handle(Plan::factory()->for($this->plan->studio)->unlimited()->create(['validity_days' => 30]), $this->rosa, $this->register, PaymentMethod::Cash);

        expect(soldDays($this)[0])->toBe('2026-11-28');
    });

    it('starts today when her current plan has no classes left', function () {
        $used = app(SellPlan::class)->handle($this->plan, $this->rosa, $this->register, PaymentMethod::Cash);
        $used->update(['credits_left' => 0]);

        expect(soldDays($this)[0])->toBe('2026-10-29');
    });

    it('counts the plans of each studio apart', function () {
        PlanPurchase::factory()->for($this->rosa)->create();

        expect(soldDays($this)[0])->toBe('2026-10-29');
    });

    it('pays each class with the plan running on its day', function () {
        $current = app(SellPlan::class)->handle($this->plan, $this->rosa, $this->register, PaymentMethod::Cash);
        $next = app(SellPlan::class)->handle($this->plan, $this->rosa, $this->register, PaymentMethod::Cash);
        $class = fn (string $at) => ClassSession::factory()->for($this->plan->studio)->create([
            'instructor_id' => User::factory(),
            'starts_at' => CarbonImmutable::parse($at, 'America/Santiago')->utc(),
            'ends_at' => CarbonImmutable::parse($at, 'America/Santiago')->addMinutes(50)->utc(),
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'America/Santiago'));

        $before = app(BookSession::class)->handle($class('2026-11-27 19:00'), $this->rosa, BookingKind::Student, $this->rosa);
        $after = app(BookSession::class)->handle($class('2026-11-28 19:00'), $this->rosa, BookingKind::Student, $this->rosa);

        expect($before->plan_purchase_id)->toBe($current->id)
            ->and($after->plan_purchase_id)->toBe($next->id);
    });
});
