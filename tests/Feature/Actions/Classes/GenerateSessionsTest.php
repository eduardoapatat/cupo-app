<?php

use App\Actions\Classes\GenerateSessions;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\ScheduleSlot;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;

function generate(Studio $studio): int
{
    return app(GenerateSessions::class)->handle($studio);
}

it('creates the sessions of the next days students can book, in UTC', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago')); // a Monday
    $studio = Studio::factory()->create(['booking_horizon_days' => 14]);
    $type = ClassType::factory()->for($studio)->create(['duration_minutes' => 50, 'capacity' => 10]);
    $fernanda = User::factory()->create();
    ScheduleSlot::factory()->for($studio)->create(['class_type_id' => $type->id, 'weekday' => 2, 'starts_at' => '19:00:00', 'instructor_id' => $fernanda->id]);

    expect(generate($studio))->toBe(2);

    $sessions = ClassSession::orderBy('starts_at')->get();
    expect($sessions->map(fn (ClassSession $session) => $session->starts_at->toIso8601ZuluString())->all())
        ->toBe(['2026-11-03T22:00:00Z', '2026-11-10T22:00:00Z'])
        ->and($sessions->first()->ends_at->toIso8601ZuluString())->toBe('2026-11-03T22:50:00Z')
        ->and($sessions->first()->capacity)->toBe(10)
        ->and($sessions->first()->instructor_id)->toBe($fernanda->id);
});

it('uses each date\'s own offset across a daylight saving change', function () {
    $this->travelTo(CarbonImmutable::parse('2026-04-01 12:00', 'America/Santiago'));
    $studio = Studio::factory()->create(['booking_horizon_days' => 10]);
    ScheduleSlot::factory()->for($studio)->create(['weekday' => 4, 'starts_at' => '07:00:00']); // Thursdays

    generate($studio);

    expect(ClassSession::orderBy('starts_at')->pluck('starts_at')->map->toIso8601ZuluString()->all())
        ->toBe(['2026-04-02T10:00:00Z', '2026-04-09T11:00:00Z']);
});

it('creates nothing new when it runs again', function () {
    $studio = Studio::factory()->create();
    ScheduleSlot::factory()->for($studio)->create(['weekday' => 3]);

    $first = generate($studio);

    expect(generate($studio))->toBe(0)
        ->and(ClassSession::count())->toBe($first);
});

it('skips the class types that are no longer taught and the times already past', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 20:00', 'America/Santiago')); // a Tuesday
    $studio = Studio::factory()->create(['booking_horizon_days' => 1]);
    ScheduleSlot::factory()->for($studio)->create(['weekday' => 2, 'starts_at' => '19:00:00']);
    ScheduleSlot::factory()->for($studio)->create(['weekday' => 2, 'starts_at' => '21:00:00', 'class_type_id' => ClassType::factory()->for($studio)->inactive()]);

    expect(generate($studio))->toBe(0);
});

it('runs for every studio from the scheduled command', function () {
    Studio::factory()->count(2)->create()->each(
        fn (Studio $studio) => ScheduleSlot::factory()->for($studio)->create(['weekday' => 1]),
    );

    $this->artisan('sessions:generate')->assertSuccessful();

    expect(ClassSession::distinct()->count('studio_id'))->toBe(2);
});
