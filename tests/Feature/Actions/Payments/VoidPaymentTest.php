<?php

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\JoinWaitlist;
use App\Actions\CashRegisters\CloseCashRegister;
use App\Actions\Payments\PaymentRejected;
use App\Actions\Payments\VoidPayment;
use App\Actions\Plans\SellPlan;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\CashRegister;
use App\Models\ClassSession;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Thursday, October 29, in the afternoon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create();
    $this->camila = User::factory()->create();
    $this->rosa = User::factory()->create(['name' => 'Rosa']);
    $this->register = CashRegister::factory()->for($this->studio)->for($this->camila)->create(['opening_cash' => 20000]);
    $plan = Plan::factory()->for($this->studio)->create(['price' => 45000, 'credits' => 8]);
    $this->purchase = app(SellPlan::class)->handle($plan, $this->rosa, $this->register, PaymentMethod::Cash);
    $this->payment = $this->purchase->payments()->sole();
});

/**
 * A class of the studio some hours from now (negative: already given).
 */
function classInHours(Studio $studio, int $hours, int $capacity = 10): ClassSession
{
    return ClassSession::factory()->for($studio)->create([
        'instructor_id' => User::factory(),
        'capacity' => $capacity,
        'starts_at' => now()->addHours($hours),
        'ends_at' => now()->addHours($hours)->addMinutes(50),
    ]);
}

it('voids the payment and its plan, saying who and why', function () {
    app(VoidPayment::class)->handle($this->payment, $this->camila, 'Se vendió el plan equivocado');

    expect($this->payment->refresh())
        ->status->toBe(PaymentStatus::Voided)
        ->voided_by_id->toBe($this->camila->id)
        ->status_reason->toBe('Se vendió el plan equivocado')
        ->and($this->payment->voided_at)->not->toBeNull()
        ->and($this->purchase->refresh()->isCancelled())->toBeTrue()
        ->and($this->purchase->isValid())->toBeFalse()
        ->and($this->register->totals()['cash'])->toBe(0)
        ->and($this->register->expectedCash())->toBe(20000);
});

it('cancels her bookings still to come without giving classes back, and keeps the ones taken', function () {
    $past = classInHours($this->studio, -30);
    $taken = Booking::factory()->for($past)->for($this->rosa)->create(['plan_purchase_id' => $this->purchase->id, 'status' => BookingStatus::Attended]);
    $full = classInHours($this->studio, 30, capacity: 1);
    $upcoming = bookWithPlan($full, $this->rosa);
    $javiera = User::factory()->create();
    givePlan($javiera, $full);
    $entry = app(JoinWaitlist::class)->handle($full->refresh(), $javiera, $javiera);
    $creditsLeft = $this->purchase->refresh()->credits_left;

    app(VoidPayment::class)->handle($this->payment, $this->camila, 'Error');

    expect($upcoming->refresh())
        ->status->toBe(BookingStatus::CancelledOnTime)
        ->cancellation_reason->toBe('Se anuló su plan.')
        ->and($taken->refresh()->status)->toBe(BookingStatus::Attended)
        ->and($entry->refresh()->status)->toBe(WaitlistStatus::Promoted)
        ->and($this->purchase->refresh()->credits_left)->toBe($creditsLeft)
        ->and($this->purchase->creditMovements()->where('delta', 1)->exists())->toBeFalse();
});

it('takes no more classes from a voided plan', function () {
    app(VoidPayment::class)->handle($this->payment, $this->camila, 'Error');

    expect(fn () => app(BookSession::class)->handle(classInHours($this->studio, 30), $this->rosa, BookingKind::Student, $this->rosa))
        ->toThrow(BookingRejected::class);
});

it('voids once, and leaves the totals of a register already closed', function () {
    app(CloseCashRegister::class)->handle($this->register, 65000);

    app(VoidPayment::class)->handle($this->payment, User::factory()->create(), 'Error');

    expect(fn () => app(VoidPayment::class)->handle($this->payment, $this->camila, 'Otra vez'))
        ->toThrow(PaymentRejected::class, 'Este pago ya está anulado.')
        ->and($this->register->refresh()->totals()['cash'])->toBe(45000)
        ->and($this->register->expectedCash())->toBe(65000)
        ->and(PlanPurchase::query()->whereNull('cancelled_at')->exists())->toBeFalse();
});
