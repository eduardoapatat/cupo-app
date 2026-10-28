<?php

use App\Actions\Bookings\BookSession;
use App\Enums\BookingKind;
use App\Enums\StudioRole;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Studio;
use App\Models\User;
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

function student(Studio $studio): User
{
    return User::factory()->memberOf($studio, StudioRole::Student)->create();
}

it('shows the days of the horizon and the classes of the chosen day, with what she can do', function () {
    $studio = Studio::factory()->create(['booking_horizon_days' => 14]);
    $javiera = student($studio);
    $booked = classAt($studio, '2026-11-03 19:00');
    app(BookSession::class)->handle($booked, $javiera, BookingKind::Student, $javiera);
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
                ['07:00', 'full'],
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
