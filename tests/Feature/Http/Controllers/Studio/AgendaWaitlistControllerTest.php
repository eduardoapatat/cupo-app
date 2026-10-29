<?php

use App\Enums\StudioRole;
use App\Enums\WaitlistStatus;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Monday, November 2, at noon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create(['waitlist_cutoff_hours' => 6]);
    $this->camila = User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create(['name' => 'Camila Soto']);
    $this->ana = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Ana Pérez']);
    $startsAt = CarbonImmutable::parse('2026-11-03 19:00', 'America/Santiago')->utc();
    $this->session = ClassSession::factory()->for($this->studio)->create([
        'class_type_id' => ClassType::factory()->for($this->studio),
        'instructor_id' => User::factory(),
        'capacity' => 1,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
    ]);
    $this->anasBooking = bookWithPlan($this->session, $this->ana);
});

it('puts a student in the waitlist of a full class, and nobody else', function () {
    $rosa = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    givePlan($rosa, $this->studio);
    $stranger = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Student)->create();
    $route = route('studios.agenda.waitlist.store', [$this->studio, $this->session]);

    $this->actingAs($this->camila)
        ->post($route, ['user_id' => $rosa->id])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Anotamos a Rosa Fuentes en la lista de espera, en el puesto 1.']);
    $this->actingAs($this->camila)
        ->post($route, ['user_id' => $stranger->id])
        ->assertNotFound();
    $this->actingAs($this->camila)
        ->post($route, ['user_id' => $this->camila->id])
        ->assertNotFound();

    expect($this->session->waitlistEntries()->sole())
        ->user_id->toBe($rosa->id)
        ->added_by_id->toBe($this->camila->id);
});

it('shows the line in the panel, and gives the freed spot to the first one waiting', function () {
    $rosa = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    givePlan($rosa, $this->studio);
    $this->actingAs($this->camila)->post(route('studios.agenda.waitlist.store', [$this->studio, $this->session]), ['user_id' => $rosa->id]);

    $this->actingAs($this->camila)
        ->get(route('studios.agenda.show', [$this->studio, $this->session]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.waitlistIsOpen', true)
            ->where('session.waitlist', fn ($waitlist) => collect($waitlist)->map(fn ($person) => [$person['name'], $person['position']])->all() === [['Rosa Fuentes', 1]]));

    $this->actingAs($this->camila)
        ->delete(route('studios.agenda.bookings.destroy', [$this->studio, $this->session, $this->anasBooking]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Cancelamos la reserva de Ana Pérez a tiempo. Rosa Fuentes tomó el cupo desde la lista de espera.']);

    expect($this->session->bookings()->current()->sole()->user_id)->toBe($rosa->id);
});

it('takes someone off the waitlist of this class only', function () {
    $rosa = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    givePlan($rosa, $this->studio);
    $this->actingAs($this->camila)->post(route('studios.agenda.waitlist.store', [$this->studio, $this->session]), ['user_id' => $rosa->id]);
    $entry = $this->session->waitlistEntries()->sole();
    $otherSession = ClassSession::factory()->for($this->studio)->create(['class_type_id' => $this->session->class_type_id]);

    $this->actingAs($this->camila)
        ->delete(route('studios.agenda.waitlist.destroy', [$this->studio, $otherSession, $entry]))
        ->assertNotFound();
    $this->actingAs($this->camila)
        ->delete(route('studios.agenda.waitlist.destroy', [$this->studio, $this->session, $entry]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Sacamos a Rosa Fuentes de la lista de espera.']);

    expect($entry->fresh()->status)->toBe(WaitlistStatus::Left);
});

it('keeps the waitlist of the Agenda for the front desk and the owner', function () {
    $route = route('studios.agenda.waitlist.store', [$this->studio, $this->session]);

    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::Instructor)->create())
        ->post($route, ['user_id' => $this->ana->id])
        ->assertForbidden();
    $this->actingAs($this->ana)
        ->post($route, ['user_id' => $this->ana->id])
        ->assertForbidden();
});
