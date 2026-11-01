<?php

use App\Actions\Bookings\CancelBooking;
use App\Enums\StudioRole;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));
});

function taughtClass(Studio $studio, ?User $instructor, string $localTime): ClassSession
{
    $startsAt = CarbonImmutable::parse($localTime, 'America/Santiago')->utc();

    return ClassSession::factory()->for($studio)->create([
        'class_type_id' => ClassType::factory()->for($studio),
        'instructor_id' => $instructor?->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
    ]);
}

it('shows her days from yesterday to the horizon, her classes of the chosen day and who comes to one', function () {
    $studio = Studio::factory()->create(['booking_horizon_days' => 14]);
    $fernanda = User::factory()->memberOf($studio, StudioRole::Instructor)->create();
    $diego = User::factory()->memberOf($studio, StudioRole::Instructor)->create();
    $earlierToday = taughtClass($studio, $fernanda, '2026-11-02 07:00');
    $tomorrow = taughtClass($studio, $fernanda, '2026-11-03 19:00');
    taughtClass($studio, $fernanda, '2026-10-31 19:00');
    $diegos = taughtClass($studio, $diego, '2026-11-03 07:00');
    $rosa = User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes', 'phone' => '+56912345678']);
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $ana = User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Ana Pérez']);
    foreach ([$rosa, $javiera, $ana] as $student) {
        $booking = bookWithPlan($tomorrow, $student);
    }
    app(CancelBooking::class)->handle($booking, $ana);

    $this->actingAs($fernanda)
        ->get(route('studios.my-sessions.index', $studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/my-sessions')
            ->has('days', 15)
            ->where('days.0', fn ($day) => $day['date'] === '2026-11-01' && $day['weekday'] === 'Ayer' && $day['sessions'] === [])
            ->where('days.1.isToday', true)
            ->where('days.1.sessions', [$earlierToday->id])
            ->where('selectedDate', '2026-11-02')
            ->where('sessions', fn ($sessions) => collect($sessions)->map(fn ($session) => [$session['id'], $session['state']])->all() === [[$earlierToday->id, 'empty']]));

    $this->actingAs($fernanda)
        ->get(route('studios.my-sessions.index', [$studio, 'dia' => '2026-11-03', 'clase' => $tomorrow->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dayLabel', 'martes 3 de noviembre')
            ->where('sessions', fn ($sessions) => collect($sessions)->pluck('id')->all() === [$tomorrow->id])
            ->where('selected.id', $tomorrow->id)
            ->where('selected.state', 'upcoming')
            ->where('selected.bookedCount', 2)
            ->where('selected.attendanceOpensAt', '18:45')
            ->where('selected.attendees', fn ($attendees) => collect($attendees)->pluck('name')->all() === ['Javiera Morales', 'Rosa Fuentes']));

    // Another instructor's class is never shown, even if asked for.
    $this->actingAs($fernanda)
        ->get(route('studios.my-sessions.index', [$studio, 'dia' => '2026-11-03', 'clase' => $diegos->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.id', $tomorrow->id));
});

it('opens on yesterday while a list is still to take there, and says when it was taken', function () {
    $studio = Studio::factory()->create();
    $fernanda = User::factory()->memberOf($studio, StudioRole::Instructor)->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', 'America/Santiago'));
    $yesterday = taughtClass($studio, $fernanda, '2026-11-01 19:00');
    $booking = bookWithPlan($yesterday, User::factory()->memberOf($studio, StudioRole::Student)->create());
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));

    $this->actingAs($fernanda)
        ->get(route('studios.my-sessions.index', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedDate', '2026-11-01')
            ->where('selected.state', 'to_mark')
            ->where('selected.attendanceIsOpen', true)
            ->where('selected.attendanceClosesLabel', 'hoy a las 23:59'));

    $this->actingAs($fernanda)->put(route('studios.attendance.update', [$studio, $yesterday]), ['attended' => []]);

    $this->actingAs($fernanda)
        ->get(route('studios.my-sessions.index', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('selectedDate', '2026-11-02'));
    $this->actingAs($fernanda)
        ->get(route('studios.my-sessions.index', [$studio, 'dia' => '2026-11-01']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.state', 'taken')
            ->where('selected.takenAt', 'lunes 2 a las 12:00'));
    expect($booking->fresh()->status->value)->toBe('confirmed');
});

it('is for whoever teaches, not for students', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Student)->create())
        ->get(route('studios.my-sessions.index', $studio))
        ->assertForbidden();
});

it('opens the attendance of her next class 15 minutes before it starts', function () {
    $studio = Studio::factory()->create();
    $fernanda = User::factory()->memberOf($studio, StudioRole::Instructor)->create();
    $soon = taughtClass($studio, $fernanda, '2026-11-02 12:10');
    $later = taughtClass($studio, $fernanda, '2026-11-02 13:20');
    foreach ([$soon, $later] as $session) {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', 'America/Santiago'));
        bookWithPlan($session, User::factory()->memberOf($studio, StudioRole::Student)->create());
    }
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));

    $this->actingAs($fernanda)
        ->get(route('studios.my-sessions.index', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions', fn ($sessions) => collect($sessions)->pluck('state')->all() === ['to_mark', 'upcoming'])
            ->where('selected.id', $soon->id)
            ->where('selected.attendanceIsOpen', true));
});
