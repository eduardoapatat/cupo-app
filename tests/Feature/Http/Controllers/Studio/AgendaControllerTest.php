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

it('shows the week from Monday to Sunday, with each class at its local time and a late Sunday class in its day', function () {
    agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    agendaClass($this->studio, $this->fernanda, '2026-11-08 22:00');
    agendaClass($this->studio, $this->fernanda, '2026-11-09 07:00');

    $this->actingAs(frontDeskOf($this->studio))
        ->get(route('studios.agenda.index', $this->studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/agenda')
            ->where('today', ['date' => '2026-11-02', 'weekday' => 'LUN', 'dayOfMonth' => 2])
            ->where('focus', ['date' => '2026-11-02', 'label' => 'lunes 2 de noviembre'])
            ->where('week.start', '2026-11-02')
            ->where('week.label', '2 nov – 8 nov')
            ->where('week.monthLabel', 'Noviembre 2026')
            ->where('week.previous', '2026-10-26')
            ->where('week.next', '2026-11-09')
            ->where('week.sessionCount', 2)
            ->where('view', null)
            ->where('days.0.isToday', true)
            ->where('days.1.sessions.0', fn ($card) => $card['startsAt'] === '19:00'
                && $card['endsAt'] === '19:50'
                && $card['startMinutes'] === 19 * 60
                && $card['durationMinutes'] === 50)
            ->where('days.6.weekday', 'Dom')
            ->where('days.6.sessions.0.startMinutes', 22 * 60)
            ->where('session', null));
});

it('focuses the day in ?fecha=, shows its week and keeps the view asked for', function () {
    $this->actingAs(frontDeskOf($this->studio))
        ->get(route('studios.agenda.index', [$this->studio, 'fecha' => '2026-12-30', 'vista' => 'dia']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('focus.date', '2026-12-30')
            ->where('week.start', '2026-12-28')
            ->where('week.monthLabel', 'Diciembre 2026 – enero 2027')
            ->where('view', 'dia'));

    $this->actingAs(frontDeskOf($this->studio))
        ->get(route('studios.agenda.index', [$this->studio, 'fecha' => '2026-10-29']))
        ->assertInertia(fn (Assert $page) => $page->where('week.monthLabel', 'Octubre – noviembre 2026'));
});

it('falls back to today for a date or view it cannot read', function () {
    $this->actingAs(frontDeskOf($this->studio))
        ->get(route('studios.agenda.index', [$this->studio, 'fecha' => '2026-13-45x', 'vista' => 'año']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('focus.date', '2026-11-02')
            ->where('view', null));
});

it('shows the month as whole weeks from Monday, with each class in its day', function () {
    agendaClass($this->studio, $this->fernanda, '2026-10-01 07:00');
    agendaClass($this->studio, $this->fernanda, '2026-10-31 22:30');
    agendaClass($this->studio, $this->fernanda, '2026-10-20 19:00', ['status' => 'cancelled']);
    agendaClass($this->studio, $this->fernanda, '2026-09-30 19:00');

    $this->actingAs(frontDeskOf($this->studio))
        ->get(route('studios.agenda.index', [$this->studio, 'fecha' => '2026-10-15', 'vista' => 'mes']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'mes')
            ->where('month.start', '2026-10-01')
            ->where('month.label', 'Octubre 2026')
            ->where('month.rangeLabel', '1 – 31 oct')
            ->where('month.previous', '2026-09-01')
            ->where('month.next', '2026-11-01')
            ->where('month.sessionCount', 2)
            ->has('month.weeks', 5)
            ->where('month.weeks.0.0', fn ($day) => $day['date'] === '2026-09-28' && $day['inMonth'] === false)
            ->where('month.weeks.0.2.sessions.0.startsAt', '19:00')
            ->where('month.weeks.0.3', fn ($day) => $day['date'] === '2026-10-01' && $day['inMonth'] === true && count($day['sessions']) === 1)
            ->where('month.weeks.4.5', fn ($day) => $day['date'] === '2026-10-31' && $day['sessions'][0]['startsAt'] === '22:30')
            ->where('month.weeks.4.6.date', '2026-11-01'));
});

it('draws six weeks when the month needs them, and no month in the other views', function () {
    $this->actingAs(frontDeskOf($this->studio))
        ->get(route('studios.agenda.index', [$this->studio, 'fecha' => '2026-08-10', 'vista' => 'mes']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('month.weeks', 6)
            ->where('month.weeks.0.0.date', '2026-07-27')
            ->where('month.weeks.5.6.date', '2026-09-06'));

    $this->actingAs(frontDeskOf($this->studio))
        ->get(route('studios.agenda.index', [$this->studio, 'vista' => 'semana']))
        ->assertInertia(fn (Assert $page) => $page->where('month', null));
});

it('opens a class with who comes, the team to add and the instructors', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    bookWithPlan($session, $javiera);
    $camila = frontDeskOf($this->studio);

    $this->actingAs($camila)
        ->get(route('studios.agenda.show', [$this->studio, $session]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('focus.date', '2026-11-03')
            ->where('week.start', '2026-11-02')
            ->where('session.id', $session->id)
            ->where('session.date', 'martes 3 de noviembre')
            ->where('session.attendees', fn ($attendees) => collect($attendees)->pluck('name')->all() === ['Javiera Morales'])
            ->where('session.teamOptions', [['value' => (string) $camila->id, 'label' => 'Camila Soto']])
            ->where('session.teachers', [['value' => (string) $this->fernanda->id, 'label' => 'Fernanda Díaz']])
            ->where('session.can', ['returnClass' => false, 'substitute' => true, 'cancel' => true, 'markAttendance' => true]));
});

it('finds the students to book, marking who already has the class', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    User::factory()->memberOf(Studio::factory()->create(), StudioRole::Student)->create(['name' => 'Javiera Lagos']);
    bookWithPlan($session, $javiera);

    $this->actingAs(frontDeskOf($this->studio))
        ->getJson(route('studios.agenda.students', [$this->studio, $session, 'buscar' => 'javi']))
        ->assertOk()
        ->assertJsonPath('students', [['id' => $javiera->id, 'name' => 'Javiera Morales', 'phone' => $javiera->phone, 'alreadyBooked' => true, 'alreadyWaiting' => false]]);
});

it('books a student or adds someone of the team, and nobody else', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    $camila = frontDeskOf($this->studio);
    $rosa = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    givePlan($rosa, $this->studio);
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
    $booking = bookWithPlan($session, $javiera);
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
    $booking = bookWithPlan($session, $javiera);

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

it('does not make someone booked in the class its instructor', function () {
    $session = agendaClass($this->studio, $this->fernanda, '2026-11-03 19:00');
    $diego = User::factory()->memberOf($this->studio, StudioRole::Instructor)->create(['name' => 'Diego Herrera']);
    app(BookSession::class)->handle($session, $diego, BookingKind::Staff, $diego);

    $this->actingAs(frontDeskOf($this->studio))
        ->put(route('studios.agenda.instructor.update', [$this->studio, $session]), ['instructor_id' => $diego->id])
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Diego Herrera ya tiene una reserva en esta clase. Cancélala primero.']);

    expect($session->fresh()->instructor_id)->toBe($this->fernanda->id);
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
