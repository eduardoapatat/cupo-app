<?php

use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Enums\BookingKind;
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

it('shows the instructor her own classes from today, with the names of who comes', function () {
    $studio = Studio::factory()->create();
    $fernanda = User::factory()->memberOf($studio, StudioRole::Instructor)->create();
    $diego = User::factory()->memberOf($studio, StudioRole::Instructor)->create();
    $earlierToday = taughtClass($studio, $fernanda, '2026-11-02 07:00');
    $tomorrow = taughtClass($studio, $fernanda, '2026-11-03 19:00');
    taughtClass($studio, $fernanda, '2026-11-01 19:00');
    taughtClass($studio, $diego, '2026-11-03 07:00');
    $rosa = User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes', 'phone' => '+56912345678']);
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $ana = User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Ana Pérez']);
    foreach ([$rosa, $javiera, $ana] as $student) {
        $booking = app(BookSession::class)->handle($tomorrow, $student, BookingKind::Student, $student);
    }
    app(CancelBooking::class)->handle($booking, $ana);

    $this->actingAs($fernanda)
        ->get(route('studios.my-sessions.index', $studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/my-sessions')
            ->where('days', fn ($days) => collect($days)->map(fn ($day) => [$day['date'], $day['isToday'], collect($day['sessions'])->pluck('id')->all()])->all() === [
                ['2026-11-02', true, [$earlierToday->id]],
                ['2026-11-03', false, [$tomorrow->id]],
            ])
            ->where('days.1.label', 'martes 3 de noviembre')
            ->where('days.1.sessions.0.attendees', ['Javiera Morales', 'Rosa Fuentes'])
            ->where('days.1.sessions.0.bookedCount', 2));
});

it('is for whoever teaches, not for students', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Student)->create())
        ->get(route('studios.my-sessions.index', $studio))
        ->assertForbidden();
});
