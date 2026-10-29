<?php

use App\Actions\Bookings\CancelSession;
use App\Enums\StudioRole;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Studio;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A class of the studio at the given local time.
 */
function homeClass(Studio $studio, ?User $instructor, string $localTime, array $attributes = []): ClassSession
{
    $startsAt = CarbonImmutable::parse($localTime, 'America/Santiago')->utc();

    return ClassSession::factory()->for($studio)->create([
        'class_type_id' => $studio->classTypes()->first() ?? ClassType::factory()->for($studio)->create(['name' => 'Pilates mat']),
        'instructor_id' => $instructor?->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
        ...$attributes,
    ]);
}

/**
 * The booking of a student, made on Monday morning so every class of the
 * day can still be booked.
 */
function homeBooking(ClassSession $session, User $student): void
{
    test()->travelTo(CarbonImmutable::parse('2026-11-02 06:00', 'America/Santiago'));
    bookWithPlan($session, $student);
}

it('shows the studio home to every member', function (StudioRole $role) {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, $role)->create())
        ->get(route('studios.home', $studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('studios/home'));
})->with(StudioRole::cases());

it('gives each role its blocks in order, with the ones of phase 3 still to come', function (StudioRole $role, array $blocks) {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, $role)->create())
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('blocks', $blocks));
})->with([
    'owner' => [StudioRole::Owner, [
        ['key' => 'today-occupancy', 'isBuilt' => true],
        ['key' => 'alerts', 'isBuilt' => false],
        ['key' => 'month-income', 'isBuilt' => false],
    ]],
    'front desk' => [StudioRole::FrontDesk, [
        ['key' => 'today-sessions', 'isBuilt' => true],
        ['key' => 'arrivals', 'isBuilt' => true],
        ['key' => 'receipts-to-review', 'isBuilt' => false],
        ['key' => 'cash-register', 'isBuilt' => false],
    ]],
    'instructor' => [StudioRole::Instructor, [['key' => 'teaching-today', 'isBuilt' => true]]],
    'student' => [StudioRole::Student, [
        ['key' => 'next-booking', 'isBuilt' => true],
        ['key' => 'classes-left', 'isBuilt' => false],
        ['key' => 'plan-expiry', 'isBuilt' => false],
    ]],
]);

it('gives every role only blocks whose data it is allowed to see', function (StudioRole $role) {
    foreach ($role->homeBlocks() as $block) {
        expect($role->allows($block->permission()))->toBeTrue("{$role->value} sees {$block->value}");
    }
})->with(StudioRole::cases());

it('shows the front desk today\'s classes and who arrives to the one on now', function () {
    $studio = Studio::factory()->create();
    $fernanda = User::factory()->memberOf($studio, StudioRole::Instructor)->create(['name' => 'Fernanda Díaz']);
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $morning = homeClass($studio, $fernanda, '2026-11-02 09:00');
    $noon = homeClass($studio, $fernanda, '2026-11-02 12:00');
    $cancelled = homeClass($studio, $fernanda, '2026-11-02 19:00');
    homeClass($studio, $fernanda, '2026-11-03 09:00');
    homeBooking($noon, $javiera);
    app(CancelSession::class)->handle($cancelled, User::factory()->create(), 'Feriado');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:20', 'America/Santiago'));

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('todaySessions', fn ($sessions) => collect($sessions)->map(fn ($session) => [$session['id'], $session['startsAt'], $session['bookedCount'], $session['isCancelled']])->all() === [
                [$morning->id, '09:00', 0, false],
                [$noon->id, '12:00', 1, false],
                [$cancelled->id, '19:00', 0, true],
            ])
            ->where('arrivals.id', $noon->id)
            ->where('arrivals.isOn', true)
            ->where('arrivals.attendees.0.name', 'Javiera Morales')
            ->where('teachingToday', null)
            ->where('nextBooking', null));
});

it('tells the front desk when no classes are left today', function () {
    $studio = Studio::factory()->create();
    homeClass($studio, User::factory()->create(), '2026-11-02 09:00');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 20:00', 'America/Santiago'));

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('arrivals', null)->has('todaySessions', 1));
});

it('shows the instructor only her own classes of today, with their attendance', function () {
    $studio = Studio::factory()->create();
    $fernanda = User::factory()->memberOf($studio, StudioRole::Instructor)->create();
    $hers = homeClass($studio, $fernanda, '2026-11-02 09:00');
    homeClass($studio, User::factory()->create(), '2026-11-02 11:00');
    homeClass($studio, $fernanda, '2026-11-03 09:00');
    homeBooking($hers, User::factory()->memberOf($studio, StudioRole::Student)->create());
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'America/Santiago'));

    $this->actingAs($fernanda)
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->has('teachingToday', 1)
            ->where('teachingToday.0.id', $hers->id)
            ->where('teachingToday.0.attendanceIsOpen', true)
            ->has('teachingToday.0.attendees', 1)
            ->where('todaySessions', null)
            ->where('arrivals', null));
});

it('shows the student her next class in this studio and the waitlists she is in', function () {
    $studio = Studio::factory()->create();
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();
    $teacher = User::factory()->create(['name' => 'Diego Herrera']);
    $later = homeClass($studio, $teacher, '2026-11-04 19:00');
    $sooner = homeClass($studio, $teacher, '2026-11-03 19:00');
    homeBooking($later, $javiera);
    homeBooking($sooner, $javiera);
    homeBooking(homeClass(Studio::factory()->create(), $teacher, '2026-11-02 20:00'), $javiera);
    WaitlistEntry::factory()->for(homeClass($studio, $teacher, '2026-11-05 19:00'))->for($javiera)->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));

    $this->actingAs($javiera)
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nextBooking.id', $sooner->id)
            ->where('nextBooking.date', 'martes 3 de noviembre')
            ->where('nextBooking.isToday', false)
            ->where('nextBooking.instructorName', 'Diego Herrera')
            ->where('nextBooking.freeCancellationUntil', 'las 13:00')
            ->where('waitingCount', 1)
            ->where('todaySessions', null));
});

it('answers 404 to people outside the studio', function () {
    $outsider = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Owner)->create();

    $this->actingAs($outsider)
        ->get(route('studios.home', Studio::factory()->create()))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404));
});

it('answers 404 for a studio that does not exist', function () {
    $this->actingAs(User::factory()->create())
        ->get('/estudios/no-existe')
        ->assertNotFound();
});

it('sends guests to the login page', function () {
    $this->get(route('studios.home', Studio::factory()->create()))
        ->assertRedirect(route('login'));
});
