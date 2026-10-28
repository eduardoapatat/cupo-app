<?php

use App\Actions\Bookings\BookSession;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Enums\StudioRole;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Monday, November 2, at noon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create(['cancellation_window_hours' => 6]);
    $this->fernanda = User::factory()->memberOf($this->studio, StudioRole::Instructor)->create(['name' => 'Fernanda Díaz']);
});

/**
 * A class of the studio at the given local time, taught by Fernanda.
 */
function agendaClass(Studio $studio, User $instructor, string $localTime, array $attributes = []): ClassSession
{
    $startsAt = CarbonImmutable::parse($localTime, 'America/Santiago')->utc();

    return ClassSession::factory()->for($studio)->create([
        'class_type_id' => $studio->classTypes()->first() ?? ClassType::factory()->for($studio)->create(['name' => 'Pilates mat']),
        'instructor_id' => $instructor->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
        ...$attributes,
    ]);
}

function frontDeskOf(Studio $studio): User
{
    return User::factory()->memberOf($studio, StudioRole::FrontDesk)->create(['name' => 'Camila Soto']);
}

function ownerOf(Studio $studio): User
{
    return User::factory()->memberOf($studio, StudioRole::Owner)->create(['name' => 'Carla Rojas']);
}

it('shows the week from Monday to Sunday, with a late Sunday class in its day', function () {
    agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    agendaClass($this->studio, $this->fernanda, '2026-11-08 22:00');
    agendaClass($this->studio, $this->fernanda, '2026-11-09 07:00');

    $this->actingAs(frontDeskOf($this->studio))
        ->get(route('studios.agenda.index', $this->studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/agenda')
            ->where('week.start', '2026-11-02')
            ->where('week.previous', '2026-10-26')
            ->where('week.next', '2026-11-09')
            ->where('week.isCurrent', true)
            ->where('days.0.isToday', true)
            ->where('days.1.sessions.0.startsAt', '19:00')
            ->where('days.6.label', 'Dom 8')
            ->where('days.6.sessions.0.startsAt', '22:00')
            ->where('session', null));
});

it('opens a class with who comes, the team to add and the instructors', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    app(BookSession::class)->handle($session, $javiera, BookingKind::Student, $javiera);
    $camila = frontDeskOf($this->studio);

    $this->actingAs($camila)
        ->get(route('studios.agenda.show', [$this->studio, $session]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('week.start', '2026-11-02')
            ->where('session.id', $session->id)
            ->where('session.date', 'martes 3 de noviembre')
            ->where('session.attendees', fn ($attendees) => collect($attendees)->pluck('name')->all() === ['Javiera Morales'])
            ->where('session.teamOptions', [['value' => (string) $camila->id, 'label' => 'Camila Soto']])
            ->where('session.teachers', [['value' => (string) $this->fernanda->id, 'label' => 'Fernanda Díaz']])
            ->where('session.can', ['returnClass' => false, 'substitute' => true, 'cancel' => true]));
});

it('finds the students to book, marking who already has the class', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    User::factory()->memberOf(Studio::factory()->create(), StudioRole::Student)->create(['name' => 'Javiera Lagos']);
    app(BookSession::class)->handle($session, $javiera, BookingKind::Student, $javiera);

    $this->actingAs(frontDeskOf($this->studio))
        ->getJson(route('studios.agenda.students', [$this->studio, $session, 'buscar' => 'javi']))
        ->assertOk()
        ->assertJsonPath('students', [['id' => $javiera->id, 'name' => 'Javiera Morales', 'phone' => $javiera->phone, 'alreadyBooked' => true]]);
});

it('books a student or adds someone of the team, and nobody else', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    $camila = frontDeskOf($this->studio);
    $rosa = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    $stranger = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Student)->create();

    $this->actingAs($camila)
        ->post(route('studios.agenda.bookings.store', [$this->studio, $session]), ['user_id' => $rosa->id, 'kind' => 'student'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Anotamos a Rosa Fuentes en la clase.']);
    $this->actingAs($camila)
        ->post(route('studios.agenda.bookings.store', [$this->studio, $session]), ['user_id' => $camila->id, 'kind' => 'staff']);
    $this->actingAs($camila)
        ->post(route('studios.agenda.bookings.store', [$this->studio, $session]), ['user_id' => $stranger->id, 'kind' => 'student'])
        ->assertNotFound();
    $this->actingAs($camila)
        ->post(route('studios.agenda.bookings.store', [$this->studio, $session]), ['user_id' => $rosa->id, 'kind' => 'staff'])
        ->assertNotFound();

    expect($session->bookings()->orderBy('id')->get()->map(fn ($booking) => [$booking->user_id, $booking->kind, $booking->booked_by_id])->all())->toBe([
        [$rosa->id, BookingKind::Student, $camila->id],
        [$camila->id, BookingKind::Staff, $camila->id],
    ])->and($session->fresh()->booked_count)->toBe(2);
});

it('lets only the owner return a class cancelled late, with a reason', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-02 16:00');
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $booking = app(BookSession::class)->handle($session, $javiera, BookingKind::Student, $javiera);
    $route = route('studios.agenda.bookings.destroy', [$this->studio, $session, $booking]);

    $this->actingAs(frontDeskOf($this->studio))
        ->delete($route, ['return_class' => true, 'reason' => 'Se enfermó'])
        ->assertForbidden();
    $this->actingAs($carla = ownerOf($this->studio))
        ->delete($route, ['return_class' => true, 'reason' => ''])
        ->assertSessionHasErrors(['reason' => 'Escribe por qué le devuelves la clase.']);
    $this->actingAs($carla)
        ->delete($route, ['return_class' => true, 'reason' => 'Se enfermó'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Cancelamos la reserva de Javiera Morales a tiempo.']);

    expect($booking->fresh())
        ->status->toBe(BookingStatus::CancelledOnTime)
        ->cancellation_reason->toBe('Se enfermó')
        ->cancelled_by_id->toBe($carla->id);
});

it('cancels late from the front desk, and the class counts as used', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-02 16:00');
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $booking = app(BookSession::class)->handle($session, $javiera, BookingKind::Student, $javiera);

    $this->actingAs(frontDeskOf($this->studio))
        ->delete(route('studios.agenda.bookings.destroy', [$this->studio, $session, $booking]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Cancelamos la reserva de Javiera Morales. Fue después del plazo de cancelación gratis, así que la clase cuenta como usada.']);

    expect($booking->fresh()->status)->toBe(BookingStatus::CancelledLate);
});

it('puts a substitute instructor in one class', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    $diego = User::factory()->memberOf($this->studio, StudioRole::Instructor)->create(['name' => 'Diego Herrera']);
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create();

    $this->actingAs(frontDeskOf($this->studio))
        ->put(route('studios.agenda.instructor.update', [$this->studio, $session]), ['instructor_id' => $diego->id])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Ahora Diego Herrera da esta clase.']);
    $this->actingAs(frontDeskOf($this->studio))
        ->put(route('studios.agenda.instructor.update', [$this->studio, $session]), ['instructor_id' => $javiera->id])
        ->assertSessionHasErrors('instructor_id');

    expect($session->fresh()->instructor_id)->toBe($diego->id);
});

it('cancels a class with its reason', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');

    $this->actingAs(frontDeskOf($this->studio))
        ->post(route('studios.agenda.cancel', [$this->studio, $session]), ['reason' => ''])
        ->assertSessionHasErrors('reason');
    $this->actingAs(frontDeskOf($this->studio))
        ->post(route('studios.agenda.cancel', [$this->studio, $session]), ['reason' => 'Corte de luz'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Cancelamos la clase. Sus reservas quedaron canceladas a tiempo.']);

    expect($session->fresh())->status->toBe(ClassSessionStatus::Cancelled)->cancellation_reason->toBe('Corte de luz');
});

it('keeps the agenda for the front desk and the owner, and hides other studios', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');

    $this->actingAs($this->fernanda)->get(route('studios.agenda.index', $this->studio))->assertForbidden();
    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::Student)->create())
        ->post(route('studios.agenda.cancel', [$this->studio, $session]), ['reason' => 'No'])
        ->assertForbidden();

    $other = Studio::factory()->create();
    $this->actingAs(frontDeskOf($other))->get(route('studios.agenda.show', [$other, $session]))->assertNotFound();
});
