<?php

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CancelSession;
use App\Actions\Bookings\JoinWaitlist;
use App\Enums\BookingKind;
use App\Enums\CreditMovementReason;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Monday, November 2, at noon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create(['cancellation_window_hours' => 6]);
    $this->javiera = User::factory()->create(['name' => 'Javiera']);
});

/**
 * A class of the studio at the given local time, with an instructor.
 */
function planClass(Studio $studio, string $localTime, array $attributes = []): ClassSession
{
    $startsAt = CarbonImmutable::parse($localTime, 'America/Santiago')->utc();

    return ClassSession::factory()->for($studio)->create([
        'instructor_id' => User::factory(),
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
        ...$attributes,
    ]);
}

/**
 * A plan bought today that lasts the given days (its last day included).
 *
 * @param  array<string, mixed>  $attributes
 */
function boughtPlan(Studio $studio, User $student, int $days = 30, array $attributes = []): PlanPurchase
{
    $today = now('America/Santiago')->startOfDay();

    return PlanPurchase::factory()->for($studio)->for($student)->create([
        'validity_days' => $days,
        'starts_on' => $today->toDateString(),
        'expires_at' => $today->addDays($days)->utc(),
        ...$attributes,
    ]);
}

function bookOwn(ClassSession $session, User $student): Booking
{
    return app(BookSession::class)->handle($session, $student, BookingKind::Student, $student);
}

it('takes one class from the plan that expires first, and notes it', function () {
    $later = boughtPlan($this->studio, $this->javiera, 30);
    $sooner = boughtPlan($this->studio, $this->javiera, 10, ['credits' => 4, 'credits_left' => 2]);

    $booking = bookOwn(planClass($this->studio, '2026-11-03 19:00'), $this->javiera);

    expect($booking->plan_purchase_id)->toBe($sooner->id)
        ->and($sooner->fresh()->credits_left)->toBe(1)
        ->and($later->fresh()->credits_left)->toBe(8)
        ->and($sooner->creditMovements()->sole())
        ->delta->toBe(-1)
        ->reason->toBe(CreditMovementReason::Booking)
        ->booking_id->toBe($booking->id);
});

it('only uses a plan still valid on the day of the class', function () {
    boughtPlan($this->studio, $this->javiera, 3);
    PlanPurchase::factory()->for(Studio::factory()->create())->for($this->javiera)->create();

    expect(bookOwn(planClass($this->studio, '2026-11-04 22:00'), $this->javiera)->exists)->toBeTrue();
    expect(fn () => bookOwn(planClass($this->studio, '2026-11-05 07:00'), $this->javiera))
        ->toThrow(BookingRejected::class, 'No tienes un plan vigente con clases para el jueves 5. Cómpralo en el mesón.');
});

it('says when her classes ran out, and names her when the front desk books', function () {
    boughtPlan($this->studio, $this->javiera, 30, ['credits' => 1, 'credits_left' => 1]);
    bookOwn(planClass($this->studio, '2026-11-03 19:00'), $this->javiera);
    $camila = User::factory()->create();

    expect(fn () => bookOwn(planClass($this->studio, '2026-11-04 19:00'), $this->javiera))
        ->toThrow(BookingRejected::class, 'No tienes un plan vigente con clases para el miércoles 4.');
    expect(fn () => app(BookSession::class)->handle(planClass($this->studio, '2026-11-05 19:00'), $this->javiera, BookingKind::Student, $camila))
        ->toThrow(BookingRejected::class, 'Javiera no tiene un plan vigente con clases para el jueves 5.');
});

it('keeps the daily limit of the plan, and moves on to another plan', function () {
    $unlimited = boughtPlan($this->studio, $this->javiera, 10, ['credits' => null, 'credits_left' => null, 'daily_limit' => 1]);
    bookOwn(planClass($this->studio, '2026-11-03 09:00'), $this->javiera);

    expect(fn () => bookOwn(planClass($this->studio, '2026-11-03 19:00'), $this->javiera))
        ->toThrow(BookingRejected::class, 'Tu plan permite 1 clase al día, y ya reservaste ese día.');
    expect(bookOwn(planClass($this->studio, '2026-11-04 09:00'), $this->javiera)->plan_purchase_id)->toBe($unlimited->id)
        ->and($unlimited->creditMovements()->count())->toBe(0);

    $pack = boughtPlan($this->studio, $this->javiera, 30);
    expect(bookOwn(planClass($this->studio, '2026-11-03 20:00'), $this->javiera)->plan_purchase_id)->toBe($pack->id);
});

it('gives the class back when she cancels in time, not later', function () {
    $plan = boughtPlan($this->studio, $this->javiera);
    $onTime = bookOwn(planClass($this->studio, '2026-11-03 19:00'), $this->javiera);
    $late = bookOwn(planClass($this->studio, '2026-11-02 15:00'), $this->javiera);

    app(CancelBooking::class)->handle($onTime, $this->javiera);
    app(CancelBooking::class)->handle($late, $this->javiera);

    expect($plan->fresh()->credits_left)->toBe(7)
        ->and($plan->creditMovements()->where('delta', 1)->sole()->reason)->toBe(CreditMovementReason::CancelledOnTime);
});

it('gives the class back when the owner returns it or the studio cancels the class', function () {
    $plan = boughtPlan($this->studio, $this->javiera);
    $late = bookOwn(planClass($this->studio, '2026-11-02 15:00'), $this->javiera);
    $session = planClass($this->studio, '2026-11-03 19:00');
    bookOwn($session, $this->javiera);
    $carla = User::factory()->create();

    app(CancelBooking::class)->handle($late, $carla, returnClass: true, reason: 'Se enfermó');
    app(CancelSession::class)->handle($session, $carla, 'Feriado');

    expect($plan->fresh()->credits_left)->toBe(8)
        ->and($plan->creditMovements()->where('delta', 1)->orderBy('id')->pluck('reason')->all())
        ->toBe([CreditMovementReason::Returned, CreditMovementReason::SessionCancelled]);
});

it('lets the team take a spot without a plan', function () {
    $booking = app(BookSession::class)->handle(planClass($this->studio, '2026-11-03 19:00'), $this->javiera, BookingKind::Staff, $this->javiera);

    expect($booking->plan_purchase_id)->toBeNull();
});

it('takes no one into the waitlist without a plan, and skips whoever ran out of classes', function () {
    $session = planClass($this->studio, '2026-11-03 19:00', ['capacity' => 1]);
    $ana = User::factory()->create();
    givePlan($ana, $this->studio);
    $anasBooking = bookOwn($session, $ana);

    expect(fn () => app(JoinWaitlist::class)->handle($session->refresh(), $this->javiera, $this->javiera))
        ->toThrow(BookingRejected::class, 'No tienes un plan vigente con clases para el martes 3.');

    $plan = boughtPlan($this->studio, $this->javiera, 30, ['credits' => 1, 'credits_left' => 1]);
    $rosa = User::factory()->create();
    givePlan($rosa, $this->studio);
    $javierasEntry = app(JoinWaitlist::class)->handle($session->refresh(), $this->javiera, $this->javiera);
    app(JoinWaitlist::class)->handle($session->refresh(), $rosa, $rosa);
    bookOwn(planClass($this->studio, '2026-11-04 19:00'), $this->javiera);

    $cancellation = app(CancelBooking::class)->handle($anasBooking, $ana);

    expect($cancellation->promoted?->is($rosa))->toBeTrue()
        ->and($javierasEntry->refresh()->status)->toBe(WaitlistStatus::Left)
        ->and($plan->fresh()->credits_left)->toBe(0);
});
