<?php

use App\Actions\Bookings\BookSession;
use App\Enums\BookingKind;
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
    $this->booking = app(BookSession::class)->handle($this->session, $javiera, BookingKind::Student, $javiera);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Santiago'));
});

function markAttendanceRoute(Studio $studio, ClassSession $session, Booking $booking): string
{
    return route('studios.attendance.update', [$studio, $session, $booking]);
}

it('lets the instructor of the class mark who came, and undo it', function () {
    $route = markAttendanceRoute($this->studio, $this->session, $this->booking);

    $this->actingAs($this->fernanda)
        ->put($route, ['attended' => true])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Marcamos que Javiera Morales vino.']);
    expect($this->booking->fresh()->status)->toBe(BookingStatus::Attended);

    $this->actingAs($this->fernanda)
        ->put($route, ['attended' => false])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Marcamos que Javiera Morales no vino.']);
    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('lets the front desk and the owner mark any class of the studio', function (StudioRole $role) {
    $this->actingAs(User::factory()->memberOf($this->studio, $role)->create())
        ->put(markAttendanceRoute($this->studio, $this->session, $this->booking), ['attended' => true])
        ->assertRedirect();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Attended);
})->with([StudioRole::FrontDesk, StudioRole::Owner]);

it('does not let another instructor or a student mark it', function (StudioRole $role) {
    $this->actingAs(User::factory()->memberOf($this->studio, $role)->create())
        ->put(markAttendanceRoute($this->studio, $this->session, $this->booking), ['attended' => true])
        ->assertForbidden();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
})->with([StudioRole::Instructor, StudioRole::Student]);

it('hides the classes of other studios and the bookings of other classes', function () {
    $other = Studio::factory()->create();
    $this->fernanda->memberships()->create(['studio_id' => $other->id, 'role' => StudioRole::Instructor]);
    $otherBooking = Booking::factory()->for(ClassSession::factory()->for($this->studio)->create(['class_type_id' => $this->session->class_type_id]))->create();

    $this->actingAs($this->fernanda)
        ->put(markAttendanceRoute($other, $this->session, $this->booking), ['attended' => true])
        ->assertNotFound();
    $this->actingAs($this->fernanda)
        ->put(markAttendanceRoute($this->studio, $this->session, $otherBooking), ['attended' => true])
        ->assertNotFound();
});

it('explains when the attendance is closed, except to the owner, who corrects it', function (StudioRole $role, bool $corrects) {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'America/Santiago'));
    $person = $role === StudioRole::Instructor ? $this->fernanda : User::factory()->memberOf($this->studio, $role)->create();

    $response = $this->actingAs($person)
        ->put(markAttendanceRoute($this->studio, $this->session, $this->booking), ['attended' => true]);

    if ($corrects) {
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Marcamos que Javiera Morales vino.']);
    } else {
        $response->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'La asistencia de esta clase no se puede marcar ahora.']);
    }

    expect($this->booking->fresh()->status)->toBe($corrects ? BookingStatus::Attended : BookingStatus::Confirmed);
})->with([
    'the instructor' => [StudioRole::Instructor, false],
    'the front desk' => [StudioRole::FrontDesk, false],
    'the owner' => [StudioRole::Owner, true],
]);
