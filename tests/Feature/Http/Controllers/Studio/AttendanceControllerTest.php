<?php

use App\Enums\BookingStatus;
use App\Enums\StudioRole;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Monday at 19:00 a class starts; it was booked on Sunday.
    $this->studio = Studio::factory()->create();
    $this->fernanda = User::factory()->memberOf($this->studio, StudioRole::Instructor)->create();
    $startsAt = CarbonImmutable::parse('2026-11-02 19:00', 'America/Santiago')->utc();
    $this->travelTo($startsAt->subDay());
    $this->session = ClassSession::factory()->for($this->studio)->create([
        'class_type_id' => ClassType::factory()->for($this->studio),
        'instructor_id' => $this->fernanda->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
    ]);
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $this->booking = bookWithPlan($this->session, $javiera);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Santiago'));
});

/**
 * The attendance of the whole class, saved at once.
 */
function attendanceRoute(Studio $studio, ClassSession $session): string
{
    return route('studios.attendance.update', [$studio, $session]);
}

it('saves who came to the class at once, and who did not', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 19:00', 'America/Santiago'));
    $rosa = bookWithPlan($this->session, User::factory()->memberOf($this->studio, StudioRole::Student)->create());
    $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Santiago'));
    $route = attendanceRoute($this->studio, $this->session);

    $this->actingAs($this->fernanda)
        ->put($route, ['attended' => [$this->booking->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Guardamos la asistencia: vinieron 1 de 2.']);
    expect($this->booking->fresh()->status)->toBe(BookingStatus::Attended)
        ->and($rosa->fresh()->status)->toBe(BookingStatus::Confirmed);

    $this->actingAs($this->fernanda)
        ->put($route, ['attended' => [$rosa->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Guardamos la asistencia: vinieron 1 de 2.']);
    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($rosa->fresh()->status)->toBe(BookingStatus::Attended);
});

it('lets the front desk and the owner save any class of the studio', function (StudioRole $role) {
    $this->actingAs(User::factory()->memberOf($this->studio, $role)->create())
        ->put(attendanceRoute($this->studio, $this->session), ['attended' => [$this->booking->id]])
        ->assertRedirect();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Attended);
})->with([StudioRole::FrontDesk, StudioRole::Owner]);

it('does not let another instructor or a student save it', function (StudioRole $role) {
    $this->actingAs(User::factory()->memberOf($this->studio, $role)->create())
        ->put(attendanceRoute($this->studio, $this->session), ['attended' => [$this->booking->id]])
        ->assertForbidden();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
})->with([StudioRole::Instructor, StudioRole::Student]);

it('hides the classes of other studios and refuses bookings of other classes', function () {
    $other = Studio::factory()->create();
    $this->fernanda->memberships()->create(['studio_id' => $other->id, 'role' => StudioRole::Instructor]);
    $otherBooking = Booking::factory()->for(ClassSession::factory()->for($this->studio)->create(['class_type_id' => $this->session->class_type_id]))->create();

    $this->actingAs($this->fernanda)
        ->put(attendanceRoute($other, $this->session), ['attended' => [$this->booking->id]])
        ->assertNotFound();
    $this->actingAs($this->fernanda)
        ->put(attendanceRoute($this->studio, $this->session), ['attended' => [$otherBooking->id]])
        ->assertSessionHasErrors(['attended.0' => 'Alguien de la lista no está en esta clase.']);

    expect($otherBooking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('explains when the attendance is closed, except to the owner, who corrects it', function (StudioRole $role, bool $corrects) {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'America/Santiago'));
    $person = $role === StudioRole::Instructor ? $this->fernanda : User::factory()->memberOf($this->studio, $role)->create();

    $response = $this->actingAs($person)
        ->put(attendanceRoute($this->studio, $this->session), ['attended' => [$this->booking->id]]);

    if ($corrects) {
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Guardamos la asistencia: vinieron 1 de 1.']);
    } else {
        $response->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'La asistencia de esta clase no se puede marcar ahora.']);
    }

    expect($this->booking->fresh()->status)->toBe($corrects ? BookingStatus::Attended : BookingStatus::Confirmed);
})->with([
    'the instructor' => [StudioRole::Instructor, false],
    'the front desk' => [StudioRole::FrontDesk, false],
    'the owner' => [StudioRole::Owner, true],
]);
