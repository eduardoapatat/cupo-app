<?php

use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Enums\CreditMovementReason;
use App\Enums\PaymentMethod;
use App\Enums\StudioRole;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\CreditMovement;
use App\Models\Payment;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Sunday, November 15, in the afternoon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-11-15 14:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create(['created_at' => CarbonImmutable::parse('2026-09-10')]);
    $this->carla = User::factory()->memberOf($this->studio, StudioRole::Owner)->create();
});

/**
 * A plan of the studio paid at a local time.
 *
 * @param  array<string, mixed>  $purchase
 * @param  array<string, mixed>  $payment
 */
function paidPlan(Studio $studio, string $at, int $amount, PaymentMethod $method, array $purchase = [], array $payment = []): Payment
{
    $paidAt = CarbonImmutable::parse($at, 'America/Santiago');

    return Payment::factory()->create([
        'plan_purchase_id' => PlanPurchase::factory()->for($studio)->create(['price' => $amount, 'list_price' => $amount, 'created_at' => $paidAt, ...$purchase]),
        'amount' => $amount,
        'method' => $method,
        'paid_at' => $paidAt->utc(),
        ...$payment,
    ]);
}

/**
 * A class of the studio at a local time, with its booked spots.
 */
function heldClass(Studio $studio, ClassType $type, string $at, int $booked, array $attributes = []): ClassSession
{
    return ClassSession::factory()->for($studio)->for($type)
        ->startingAt(CarbonImmutable::parse($at, 'America/Santiago')->utc())
        ->create(['capacity' => 10, 'booked_count' => $booked, ...$attributes]);
}

it('adds up the month\'s income by day, by method and by plan, with discounts and voids', function () {
    paidPlan($this->studio, '2026-11-03 10:00', 45000, PaymentMethod::Cash, ['name' => '8 clases al mes']);
    paidPlan($this->studio, '2026-11-03 19:00', 40000, PaymentMethod::Card, ['name' => '8 clases al mes', 'list_price' => 45000, 'price' => 40000]);
    paidPlan($this->studio, '2026-11-10 12:00', 28000, PaymentMethod::Transfer, ['name' => '4 clases al mes']);
    paidPlan($this->studio, '2026-11-11 12:00', 65000, PaymentMethod::Cash, ['name' => 'Ilimitado'], ['status' => 'voided', 'voided_at' => now(), 'status_reason' => 'Error']);
    paidPlan($this->studio, '2026-10-02 12:00', 30000, PaymentMethod::Cash);
    paidPlan(Studio::factory()->create(), '2026-11-04 12:00', 99000, PaymentMethod::Cash);

    $this->actingAs($this->carla)
        ->get(route('studios.reports.index', $this->studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/reports')
            ->where('month', ['value' => '2026-11', 'label' => 'noviembre de 2026', 'isCurrent' => true])
            ->where('months', fn ($months) => collect($months)->pluck('value')->all() === ['2026-11', '2026-10', '2026-09'])
            ->where('income.total', 113000)
            ->where('income.previous', 30000)
            ->has('income.byDay', 15)
            ->where('income.byDay.2', ['date' => '2026-11-03', 'label' => '3 nov.', 'total' => 85000])
            ->where('income.byDay.9.total', 28000)
            ->where('income.byMethod', fn ($methods) => collect($methods)->pluck('total', 'method')->all() === ['cash' => 45000, 'card' => 40000, 'transfer' => 28000, 'webpay' => 0])
            ->where('income.byPlan', [
                ['name' => '8 clases al mes', 'sold' => 2, 'total' => 85000],
                ['name' => '4 clases al mes', 'sold' => 1, 'total' => 28000],
            ])
            ->where('income.discounts', ['count' => 1, 'total' => 5000])
            ->where('income.voids', ['count' => 1, 'total' => 65000]));
});

it('shows a past month whole, against the whole month before', function () {
    paidPlan($this->studio, '2026-10-31 22:00', 45000, PaymentMethod::Cash);
    paidPlan($this->studio, '2026-09-30 10:00', 20000, PaymentMethod::Cash);
    paidPlan($this->studio, '2026-11-01 08:00', 28000, PaymentMethod::Cash);

    $this->actingAs($this->carla)
        ->get(route('studios.reports.index', [$this->studio, 'mes' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('month.isCurrent', false)
            ->where('income.total', 45000)
            ->where('income.previous', 20000)
            ->has('income.byDay', 31)
            ->where('income.byDay.30.total', 45000));
});

it('falls back to this month for a month that is malformed or still to come', function (string $month) {
    $this->actingAs($this->carla)
        ->get(route('studios.reports.index', [$this->studio, 'mes' => $month]))
        ->assertInertia(fn (Assert $page) => $page->where('month.value', '2026-11'));
})->with(['2026-13', 'noviembre', '2027-01']);

it('measures how full the classes held this month were, by time slot, and who came', function () {
    $yoga = ClassType::factory()->for($this->studio)->create(['name' => 'Yoga']);
    $pilates = ClassType::factory()->for($this->studio)->create(['name' => 'Pilates']);
    $mondayYoga = heldClass($this->studio, $yoga, '2026-11-02 19:00', 8);
    heldClass($this->studio, $yoga, '2026-11-09 19:00', 10);
    heldClass($this->studio, $pilates, '2026-11-04 09:00', 2);
    heldClass($this->studio, $pilates, '2026-11-06 09:00', 0, ['status' => ClassSessionStatus::Cancelled, 'cancelled_at' => now()]);
    heldClass($this->studio, $pilates, '2026-11-16 09:00', 10);
    heldClass($this->studio, $yoga, '2026-10-26 19:00', 10);
    foreach ([BookingStatus::Attended, BookingStatus::Attended, BookingStatus::NoShow, BookingStatus::CancelledLate, BookingStatus::Confirmed] as $status) {
        Booking::factory()->for($mondayYoga)->create(['status' => $status, 'cancelled_at' => $status === BookingStatus::CancelledLate ? now() : null]);
    }

    $this->actingAs($this->carla)
        ->get(route('studios.reports.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('classes.count', 3)
            ->where('classes.occupancy', 67)
            ->where('classes.bySlot', [
                ['label' => 'Lun 19:00 · Yoga', 'sessions' => 2, 'occupancy' => 90],
                ['label' => 'Mié 09:00 · Pilates', 'sessions' => 1, 'occupancy' => 20],
            ])
            ->where('classes.attendance', ['attended' => 2, 'noShow' => 1, 'cancelledLate' => 1, 'toMark' => 1]));
});

it('counts the students with a plan, their first plans and the classes that expired', function () {
    $rosa = User::factory()->create();
    PlanPurchase::factory()->for($this->studio)->for($rosa)->create(['created_at' => CarbonImmutable::parse('2026-09-20')]);
    PlanPurchase::factory()->for($this->studio)->for($rosa)->create(['created_at' => CarbonImmutable::parse('2026-11-05')]);
    PlanPurchase::factory()->for($this->studio)->create(['created_at' => CarbonImmutable::parse('2026-11-08')]);
    PlanPurchase::factory()->for($this->studio)->create(['created_at' => CarbonImmutable::parse('2026-11-09'), 'cancelled_at' => now()]);
    $expired = PlanPurchase::factory()->for($this->studio)->expired()->create(['created_at' => CarbonImmutable::parse('2026-10-14'), 'credits_left' => 0]);
    CreditMovement::create(['plan_purchase_id' => $expired->id, 'delta' => -3, 'reason' => CreditMovementReason::Expired, 'created_at' => now()]);

    $this->actingAs($this->carla)
        ->get(route('studios.reports.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page->where('students', ['withPlan' => 2, 'firstPlan' => 1, 'expiredClasses' => 3]));
});

it('lists whose plan ends this week and has not bought the next one', function () {
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales', 'phone' => '+56912345678']);
    $rosa = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    $ends = fn (string $lastDay) => CarbonImmutable::parse($lastDay, 'America/Santiago')->addDay()->utc();
    PlanPurchase::factory()->for($this->studio)->for($javiera)->create(['name' => '8 clases al mes', 'credits_left' => 2, 'expires_at' => $ends('2026-11-17')]);
    PlanPurchase::factory()->for($this->studio)->for($rosa)->create(['expires_at' => $ends('2026-11-16')]);
    PlanPurchase::factory()->for($this->studio)->for($rosa)->create(['expires_at' => $ends('2026-12-15')]);
    PlanPurchase::factory()->for($this->studio)->create(['expires_at' => $ends('2026-11-25')]);

    $this->actingAs($this->carla)
        ->get(route('studios.reports.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expiringSoon', 1)
            ->where('expiringSoon.0.studentName', 'Javiera Morales')
            ->where('expiringSoon.0.phone', '+56912345678')
            ->where('expiringSoon.0.membershipId', $javiera->memberships()->sole()->id)
            ->where('expiringSoon.0.lastDay', 'martes 17 de noviembre')
            ->where('expiringSoon.0.creditsLeft', 2));
});

it('keeps the reports for the owner', function (StudioRole $role) {
    $this->actingAs(User::factory()->memberOf($this->studio, $role)->create())
        ->get(route('studios.reports.index', $this->studio))
        ->assertForbidden();
})->with([StudioRole::FrontDesk, StudioRole::Instructor, StudioRole::Student]);
