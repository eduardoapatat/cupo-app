<?php

use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Plans\SellPlan;
use App\Enums\BookingKind;
use App\Enums\StudioRole;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create();
    $this->javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create();
});

it('shows her valid plans, the classes taken and returned, and her past plans', function () {
    $plan = PlanPurchase::factory()->for($this->studio)->for($this->javiera)->create(['name' => '8 clases al mes']);
    PlanPurchase::factory()->for($this->studio)->for($this->javiera)->expired()->create(['name' => 'Viejo']);
    PlanPurchase::factory()->for(Studio::factory()->create())->for($this->javiera)->create(['name' => 'De otro estudio']);
    $startsAt = CarbonImmutable::parse('2026-11-03 19:00', 'America/Santiago')->utc();
    $session = ClassSession::factory()->for($this->studio)->create([
        'class_type_id' => ClassType::factory()->for($this->studio)->create(['name' => 'Yoga']),
        'instructor_id' => User::factory(),
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHour(),
    ]);
    $booking = app(BookSession::class)->handle($session, $this->javiera, BookingKind::Student, $this->javiera);
    app(CancelBooking::class)->handle($booking, $this->javiera);

    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.index', $this->studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/my-plan')
            ->where('summary.classesLeft', 8)
            ->where('valid', fn ($valid) => collect($valid)->pluck('name')->all() === ['8 clases al mes'])
            ->where('valid.0.lastDay', 'martes 1 de diciembre')
            ->where('past', fn ($past) => collect($past)->pluck('name')->all() === ['Viejo'])
            ->where('movements', fn ($movements) => collect($movements)->map(fn ($movement) => [$movement['reason'], $movement['delta'], $movement['className']])->all() === [
                ['Cancelada a tiempo', 1, 'Yoga'],
                ['Reserva', -1, 'Yoga'],
            ]));

    expect($plan->fresh()->credits_left)->toBe(8);
});

it('warns her from the studio\'s warning days on, and shows the classes that expired unused', function () {
    $this->studio->update(['plan_expiry_warning_days' => 3]);
    $plan = PlanPurchase::factory()->for($this->studio)->for($this->javiera)->create(['validity_days' => 4, 'credits_left' => 2]);
    $expired = PlanPurchase::factory()->for($this->studio)->for($this->javiera)->expired()->create(['credits' => 8, 'credits_left' => 3]);
    $this->artisan('plans:expire');
    $page = fn () => $this->actingAs($this->javiera)->get(route('studios.my-plan.index', $this->studio));

    $page()->assertInertia(fn (Assert $page) => $page
        ->where('summary.expiresSoon', false)
        ->where('past.0.expiredCredits', 3)
        ->where('movements.0.reason', 'Vencieron sin usar')
        ->where('movements.0.delta', -3));

    $plan->update(['expires_at' => CarbonImmutable::parse('2026-11-05', 'America/Santiago')->utc()]);

    $page()->assertInertia(fn (Assert $page) => $page
        ->where('summary.daysLeft', 3)
        ->where('summary.expiresSoon', true));
    expect($expired->refresh()->credits_left)->toBe(0);
});

it('shows the plan she bought ahead as waiting, without the warning to renew', function () {
    $this->studio->update(['plan_expiry_warning_days' => 3]);
    $plan = Plan::factory()->for($this->studio)->create(['name' => '8 clases al mes', 'credits' => 8, 'validity_days' => 30]);
    PlanPurchase::factory()->for($this->studio)->for($this->javiera)->create(['credits_left' => 4, 'expires_at' => CarbonImmutable::parse('2026-11-04', 'America/Santiago')->utc()]);
    app(SellPlan::class)->newPurchase($plan, $this->javiera, null, $plan->price);

    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->has('valid', 1)
            ->where('queued.0.name', '8 clases al mes')
            ->where('queued.0.startsOn', 'miércoles 4 de noviembre')
            ->where('queued.0.lastDay', 'jueves 3 de diciembre')
            ->has('past', 0)
            ->where('summary.classesLeft', 4)
            ->where('summary.expiresSoon', false)
            ->where('summary.next', ['name' => '8 clases al mes', 'startsOn' => 'miércoles 4 de noviembre']));
});

it('is only for students', function () {
    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create())
        ->get(route('studios.my-plan.index', $this->studio))
        ->assertForbidden();
});
