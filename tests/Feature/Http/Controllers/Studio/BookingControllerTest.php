<?php

use App\Enums\StudioRole;
use App\Enums\WaitlistStatus;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // A Monday at noon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));
});

/**
 * A class of the studio at the given local time, with Fernanda teaching.
 */
function classAt(Studio $studio, string $localTime, array $attributes = []): ClassSession
{
    $startsAt = CarbonImmutable::parse($localTime, 'America/Santiago')->utc();

    return ClassSession::factory()->for($studio)->create([
        'class_type_id' => $studio->classTypes()->firstWhere('name', 'Pilates mat') ?? ClassType::factory()->for($studio)->create(['name' => 'Pilates mat']),
        'instructor_id' => User::factory()->create(['name' => 'Fernanda Díaz']),
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
        ...$attributes,
    ]);
}

/**
 * A student of the studio, with a plan that pays for any of its classes.
 */
function student(Studio $studio): User
{
    $student = User::factory()->memberOf($studio, StudioRole::Student)->create();
    givePlan($student, $studio);

    return $student;
}

it('shows the days of the horizon and the classes of the chosen day, with what she can do', function () {
    $studio = Studio::factory()->create(['booking_horizon_days' => 14]);
    $javiera = student($studio);
    $booked = classAt($studio, '2026-11-03 19:00');
    bookWithPlan($booked, $javiera);
    classAt($studio, '2026-11-03 07:00', ['capacity' => 1, 'booked_count' => 1]);
    classAt($studio, '2026-11-03 09:00', ['instructor_id' => null]);
    classAt($studio, '2026-11-03 20:00');

    $this->actingAs($javiera)
        ->get(route('studios.book.index', [$studio, 'dia' => '2026-11-03']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/book')
            ->has('days', 14)
            ->where('days.0', ['date' => '2026-11-02', 'weekday' => 'Hoy', 'dayOfMonth' => 2, 'sessionCount' => 0])
            ->where('days.1', ['date' => '2026-11-03', 'weekday' => 'Mar', 'dayOfMonth' => 3, 'sessionCount' => 4])
            ->where('selectedDay', ['date' => '2026-11-03', 'label' => 'martes 3 de noviembre'])
            ->where('sessions', fn ($sessions) => collect($sessions)->map(fn ($session) => [$session['startsAt'], $session['state']])->all() === [
                ['07:00', 'waitlist'],
                ['09:00', 'no_instructor'],
                ['19:00', 'booked'],
                ['20:00', 'open'],
            ])
            ->where('sessions.2.freeCancellationUntil', 'las 13:00')
            ->where('sessions.3.spotsLeft', 10));
});

it('shows today when the day is missing or outside the horizon', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(student($studio))
        ->get(route('studios.book.index', [$studio, 'dia' => '2027-01-01']))
        ->assertInertia(fn (Assert $page) => $page->where('selectedDay.date', '2026-11-02'));
});

it('closes the classes past the cutoff', function () {
    $studio = Studio::factory()->create(['booking_cutoff_minutes' => 60]);
    classAt($studio, '2026-11-02 12:30');

    $this->actingAs(student($studio))
        ->get(route('studios.book.index', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('sessions.0.state', 'closed'));
});

it('offers the waitlist of a full class until its cutoff, and shows her place in line', function () {
    $studio = Studio::factory()->create(['waitlist_cutoff_hours' => 6]);
    $javiera = student($studio);
    $waited = classAt($studio, '2026-11-03 19:00', ['capacity' => 1, 'booked_count' => 1]);
    WaitlistEntry::factory()->for($waited)->create();
    $entry = WaitlistEntry::factory()->for($waited)->for($javiera)->create();
    classAt($studio, '2026-11-03 20:00', ['capacity' => 1, 'booked_count' => 1]);
    classAt($studio, '2026-11-02 17:30', ['capacity' => 1, 'booked_count' => 1]);

    $this->actingAs($javiera)
        ->get(route('studios.book.index', [$studio, 'dia' => '2026-11-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.state', 'waiting')
            ->where('sessions.0.waitlist', ['id' => $entry->id, 'position' => 2])
            ->where('sessions.1.state', 'waitlist')
            ->where('sessions.1.waitlist', null));
    $this->actingAs($javiera)
        ->get(route('studios.book.index', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('sessions.0.state', 'full'));
});

it('puts her in the waitlist of a full class, and lets her leave it', function () {
    $studio = Studio::factory()->create();
    $javiera = student($studio);
    $session = classAt($studio, '2026-11-03 19:00', ['capacity' => 1, 'booked_count' => 1]);

    $this->actingAs($javiera)
        ->post(route('studios.waitlist.store', [$studio, $session]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Quedaste en la lista de espera, en el puesto 1. Si se libera un lugar a tiempo, te lo reservamos.']);
    $entry = $session->waitlistEntries()->sole();

    $this->actingAs($javiera)
        ->delete(route('studios.waitlist.destroy', [$studio, $entry]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Saliste de la lista de espera.']);

    expect($entry->fresh()->status)->toBe(WaitlistStatus::Left);
});

it('does not let her leave someone else\'s place in line, nor wait as staff', function () {
    $studio = Studio::factory()->create();
    $session = classAt($studio, '2026-11-03 19:00', ['capacity' => 1, 'booked_count' => 1]);
    $rosasEntry = WaitlistEntry::factory()->for($session)->for(student($studio))->create();

    $this->actingAs(student($studio))
        ->delete(route('studios.waitlist.destroy', [$studio, $rosasEntry]))
        ->assertNotFound();
    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->post(route('studios.waitlist.store', [$studio, $session]))
        ->assertForbidden();

    expect($rosasEntry->fresh()->status)->toBe(WaitlistStatus::Waiting)
        ->and($session->waitlistEntries()->count())->toBe(1);
});

it('tells her when she has no plan for the day, and what she has left', function () {
    $studio = Studio::factory()->create();
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();
    classAt($studio, '2026-11-03 19:00');

    $this->actingAs($javiera)
        ->get(route('studios.book.index', [$studio, 'dia' => '2026-11-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.state', 'no_plan')
            ->where('planSummary', null));

    PlanPurchase::factory()->for($studio)->for($javiera)->create(['credits_left' => 6]);

    $this->actingAs($javiera)
        ->get(route('studios.book.index', [$studio, 'dia' => '2026-11-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.state', 'open')
            ->where('planSummary.classesLeft', 6));
});

it('books a class for the student herself', function () {
    $studio = Studio::factory()->create();
    $javiera = student($studio);
    $session = classAt($studio, '2026-11-03 19:00');

    $this->actingAs($javiera)
        ->from(route('studios.book.index', $studio))
        ->post(route('studios.book.store', [$studio, $session]))
        ->assertRedirect(route('studios.book.index', $studio))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Reservaste Pilates mat el martes 3 a las 19:00.']);

    expect($session->bookings()->sole()->user_id)->toBe($javiera->id)
        ->and($session->fresh()->booked_count)->toBe(1);
});

it('explains why a class cannot be booked', function () {
    $studio = Studio::factory()->create();
    $session = classAt($studio, '2026-11-03 19:00', ['capacity' => 1, 'booked_count' => 1]);

    $this->actingAs(student($studio))
        ->post(route('studios.book.store', [$studio, $session]))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Esta clase está llena.']);

    expect($session->bookings()->count())->toBe(0);
});

it('keeps booking for students and hides other studios', function () {
    $studio = Studio::factory()->create();
    $session = classAt($studio, '2026-11-03 19:00');

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->post(route('studios.book.store', [$studio, $session]))
        ->assertForbidden();

    $this->actingAs(student($other = Studio::factory()->create()))
        ->post(route('studios.book.store', [$other, $session]))
        ->assertNotFound();
});

it('counts a late class of the last day, whose UTC date is the next one', function () {
    $studio = Studio::factory()->create(['booking_horizon_days' => 14]);
    classAt($studio, '2026-11-15 22:30');

    $this->actingAs(student($studio))
        ->get(route('studios.book.index', [$studio, 'dia' => '2026-11-15']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.13.sessionCount', 1)
            ->where('sessions.0.startsAt', '22:30'));
});
