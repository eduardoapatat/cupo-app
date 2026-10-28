<?php

use App\Actions\Classes\GenerateSessions;
use App\Enums\StudioRole;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\ScheduleSlot;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago')); // a Monday
    $this->studio = Studio::factory()->create(['booking_horizon_days' => 14]);
    $this->owner = User::factory()->memberOf($this->studio, StudioRole::Owner)->create();
    $this->pilates = ClassType::factory()->for($this->studio)->create(['name' => 'Pilates mat', 'duration_minutes' => 50]);
});

it('shows the owner the class types, the schedule and who can teach', function () {
    $fernanda = User::factory()->memberOf($this->studio, StudioRole::Instructor)->create(['name' => 'Fernanda Díaz']);
    User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create();
    ScheduleSlot::factory()->for($this->studio)->create(['class_type_id' => $this->pilates->id, 'weekday' => 2, 'starts_at' => '19:00:00', 'instructor_id' => $fernanda->id]);

    $this->actingAs($this->owner)
        ->get(route('studios.classes.index', $this->studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/classes')
            ->where('classTypes.0.name', 'Pilates mat')
            ->where('slots.0.startsAt', '19:00')
            ->where('slots.0.instructorName', 'Fernanda Díaz')
            ->has('teachers', 2)
            ->where('bookingHorizonDays', 14));
});

it('adds a class type, with a unique name per studio', function () {
    $this->actingAs($this->owner)
        ->post(route('studios.classes.types.store', $this->studio), ['name' => 'Yoga', 'duration_minutes' => 60, 'capacity' => 8])
        ->assertRedirect(route('studios.classes.index', $this->studio));

    expect($this->studio->classTypes()->where('name', 'Yoga')->sole()->capacity)->toBe(8);

    $this->actingAs($this->owner)
        ->post(route('studios.classes.types.store', $this->studio), ['name' => 'PILATES MAT', 'duration_minutes' => 50, 'capacity' => 10])
        ->assertSessionHasErrors(['name' => 'El estudio ya tiene una clase con ese nombre.']);
});

it('adds a class to the schedule and generates its sessions', function () {
    $this->actingAs($this->owner)
        ->post(route('studios.classes.slots.store', $this->studio), ['weekday' => 2, 'starts_at' => '19:00', 'class_type_id' => $this->pilates->id, 'instructor_id' => ''])
        ->assertRedirect(route('studios.classes.index', $this->studio));

    $slot = $this->studio->scheduleSlots()->sole();
    expect($slot->starts_at)->toBe('19:00:00')
        ->and($slot->instructor_id)->toBeNull()
        ->and($slot->classSessions()->count())->toBe(2);
});

it('does not let two classes of the same day cross, the studio has one room', function () {
    ScheduleSlot::factory()->for($this->studio)->create(['class_type_id' => $this->pilates->id, 'weekday' => 2, 'starts_at' => '19:00:00']);

    $this->actingAs($this->owner)
        ->post(route('studios.classes.slots.store', $this->studio), ['weekday' => 2, 'starts_at' => '19:30', 'class_type_id' => $this->pilates->id])
        ->assertSessionHasErrors(['starts_at' => 'Se cruza con Pilates mat a las 19:00. El estudio tiene una sola sala.']);

    $this->actingAs($this->owner)
        ->post(route('studios.classes.slots.store', $this->studio), ['weekday' => 2, 'starts_at' => '19:50', 'class_type_id' => $this->pilates->id])
        ->assertSessionHasNoErrors();
});

it('only takes the studio\'s own active classes and its teachers', function () {
    $otherStudiosType = ClassType::factory()->create();
    $frontDesk = User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create();

    $this->actingAs($this->owner)
        ->post(route('studios.classes.slots.store', $this->studio), ['weekday' => 2, 'starts_at' => '19:00', 'class_type_id' => $otherStudiosType->id, 'instructor_id' => $frontDesk->id])
        ->assertSessionHasErrors([
            'class_type_id' => 'Elige una de las clases del estudio.',
            'instructor_id' => 'Elige a alguien que dé clases en el estudio.',
        ]);
});

it('moves the sessions nobody booked and keeps the ones with bookings', function () {
    $slot = ScheduleSlot::factory()->for($this->studio)->create(['class_type_id' => $this->pilates->id, 'weekday' => 2, 'starts_at' => '19:00:00']);
    app(GenerateSessions::class)->handle($this->studio);
    $booked = $slot->classSessions()->orderBy('starts_at')->first();
    $booked->forceFill(['booked_count' => 6])->save();

    $this->actingAs($this->owner)
        ->put(route('studios.classes.slots.update', [$this->studio, $slot]), ['weekday' => 2, 'starts_at' => '19:30', 'class_type_id' => $this->pilates->id])
        ->assertRedirect(route('studios.classes.index', $this->studio));

    $times = $slot->classSessions()->orderBy('starts_at')->get()
        ->map(fn (ClassSession $session) => $session->starts_at->timezone('America/Santiago')->format('d H:i'))->all();
    expect($times)->toBe(['03 19:00', '10 19:30']);
});

it('takes a class off the schedule and keeps only its sessions with bookings', function () {
    $slot = ScheduleSlot::factory()->for($this->studio)->create(['class_type_id' => $this->pilates->id, 'weekday' => 2, 'starts_at' => '19:00:00']);
    app(GenerateSessions::class)->handle($this->studio);
    $booked = $slot->classSessions()->orderBy('starts_at')->first();
    $booked->forceFill(['booked_count' => 3])->save();

    $this->actingAs($this->owner)
        ->delete(route('studios.classes.slots.destroy', [$this->studio, $slot]))
        ->assertRedirect(route('studios.classes.index', $this->studio));

    expect($slot->fresh())->toBeNull()
        ->and(ClassSession::sole()->is($booked))->toBeTrue()
        ->and($booked->fresh()->schedule_slot_id)->toBeNull();
});

it('stops generating the sessions of a class type that is no longer taught', function () {
    $slot = ScheduleSlot::factory()->for($this->studio)->create(['class_type_id' => $this->pilates->id, 'weekday' => 2, 'starts_at' => '19:00:00']);
    app(GenerateSessions::class)->handle($this->studio);

    $this->actingAs($this->owner)
        ->put(route('studios.classes.types.update', [$this->studio, $this->pilates]), ['name' => 'Pilates mat', 'duration_minutes' => 50, 'capacity' => 10, 'is_active' => '0'])
        ->assertRedirect(route('studios.classes.index', $this->studio));

    expect($this->pilates->fresh()->is_active)->toBeFalse()
        ->and($slot->classSessions()->count())->toBe(0);
});

it('keeps «Clases» in the hands of the owner and inside her studio', function () {
    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create())
        ->get(route('studios.classes.index', $this->studio))
        ->assertForbidden();

    $elsewhere = ScheduleSlot::factory()->create();
    $this->actingAs($this->owner)
        ->delete(route('studios.classes.slots.destroy', [$this->studio, $elsewhere]))
        ->assertNotFound();
});

it('keeps each class inside its day', function () {
    $this->actingAs($this->owner)
        ->post(route('studios.classes.slots.store', $this->studio), ['weekday' => 2, 'starts_at' => '23:30', 'class_type_id' => $this->pilates->id])
        ->assertSessionHasErrors(['starts_at' => 'La clase tiene que terminar antes de la medianoche.']);
});

it('lets a slot keep a class type that is no longer taught while its time changes', function () {
    $slot = ScheduleSlot::factory()->for($this->studio)->create(['class_type_id' => $this->pilates->id, 'weekday' => 2, 'starts_at' => '19:00:00']);
    $this->pilates->update(['is_active' => false]);

    $this->actingAs($this->owner)
        ->put(route('studios.classes.slots.update', [$this->studio, $slot]), ['weekday' => 2, 'starts_at' => '20:00', 'class_type_id' => $this->pilates->id])
        ->assertSessionHasNoErrors();

    expect($slot->fresh()->starts_at)->toBe('20:00:00');

    $this->actingAs($this->owner)
        ->post(route('studios.classes.slots.store', $this->studio), ['weekday' => 3, 'starts_at' => '19:00', 'class_type_id' => $this->pilates->id])
        ->assertSessionHasErrors('class_type_id');
});
