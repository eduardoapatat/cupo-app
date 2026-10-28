<?php

use App\Enums\ClassSessionStatus;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\ScheduleSlot;
use App\Models\Studio;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Expect the write to fail with the given SQLSTATE. It runs in a savepoint,
 * so the test's transaction survives the error and the next write can run.
 */
function expectSqlState(Closure $write, string $state): void
{
    expect(fn () => DB::transaction($write))->toThrow(function (QueryException $exception) use ($state) {
        expect($exception->getCode())->toBe($state);
    });
}

it('never books more people than the capacity', function () {
    $session = ClassSession::factory()->create(['capacity' => 10]);

    expectSqlState(fn () => DB::table('class_sessions')->where('id', $session->id)->update(['booked_count' => 11]), '23514');
    expectSqlState(fn () => DB::table('class_sessions')->where('id', $session->id)->update(['booked_count' => -1]), '23514');
});

it('never overlaps two active sessions of the same studio, its only room', function () {
    $studio = Studio::factory()->create();
    $first = ClassSession::factory()->for($studio)->startingAt(now()->addDay()->setTime(19, 0))->create();

    expectSqlState(
        fn () => ClassSession::factory()->for($studio)->startingAt(now()->addDay()->setTime(19, 30))->create(),
        '23P01',
    );

    $first->forceFill(['status' => ClassSessionStatus::Cancelled])->save();
    $replacement = ClassSession::factory()->for($studio)->startingAt(now()->addDay()->setTime(19, 30))->create();

    expect($replacement->exists)->toBeTrue();
});

it('lets two studios hold classes at the same time', function () {
    $startsAt = now()->addDay()->setTime(19, 0);
    ClassSession::factory()->startingAt($startsAt)->create();

    expect(ClassSession::factory()->startingAt($startsAt)->create()->exists)->toBeTrue();
});

it('lets back-to-back sessions share their edge', function () {
    $studio = Studio::factory()->create();
    $start = now()->addDay()->setTime(19, 0);
    ClassSession::factory()->for($studio)->create(['starts_at' => $start, 'ends_at' => $start->addHour()]);

    $next = ClassSession::factory()->for($studio)->create(['starts_at' => $start->addHour(), 'ends_at' => $start->addHours(2)]);

    expect($next->exists)->toBeTrue();
});

it('rejects impossible class types and schedules', function () {
    $classType = ClassType::factory()->create();
    $slot = ScheduleSlot::factory()->create();

    expectSqlState(fn () => DB::table('class_types')->where('id', $classType->id)->update(['capacity' => 0]), '23514');
    expectSqlState(fn () => DB::table('class_types')->where('id', $classType->id)->update(['duration_minutes' => 0]), '23514');
    expectSqlState(fn () => DB::table('schedule_slots')->where('id', $slot->id)->update(['weekday' => 8]), '23514');
});

it('keeps class type names unique per studio, ignoring case', function () {
    $studio = Studio::factory()->create();
    ClassType::factory()->for($studio)->create(['name' => 'Pilates mat']);

    expectSqlState(fn () => ClassType::factory()->for($studio)->create(['name' => 'PILATES MAT']), '23505');
});
