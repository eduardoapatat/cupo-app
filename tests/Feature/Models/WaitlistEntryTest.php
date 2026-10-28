<?php

use App\Enums\WaitlistStatus;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

it('keeps one waiting entry per person and session', function () {
    $session = ClassSession::factory()->create();
    $javiera = User::factory()->create();
    WaitlistEntry::factory()->for($session)->for($javiera)->create();

    expectBookingWriteToFail(fn () => WaitlistEntry::factory()->for($session)->for($javiera)->create(), '23505');
});

it('lets a person wait again after leaving', function () {
    $session = ClassSession::factory()->create();
    $javiera = User::factory()->create();
    $left = WaitlistEntry::factory()->for($session)->for($javiera)->create();
    DB::table('waitlist_entries')->where('id', $left->id)->update(['status' => WaitlistStatus::Left->value, 'resolved_at' => now()]);

    expect(WaitlistEntry::factory()->for($session)->for($javiera)->create()->exists)->toBeTrue();
});

it('is resolved exactly when it no longer waits', function () {
    $entry = WaitlistEntry::factory()->create();

    expectBookingWriteToFail(fn () => DB::table('waitlist_entries')->where('id', $entry->id)->update(['status' => WaitlistStatus::Left->value]), '23514');
    expectBookingWriteToFail(fn () => DB::table('waitlist_entries')->where('id', $entry->id)->update(['resolved_at' => now()]), '23514');
});

it('counts its place among those still waiting', function () {
    $session = ClassSession::factory()->create();
    $first = WaitlistEntry::factory()->for($session)->create();
    $second = WaitlistEntry::factory()->for($session)->create();
    $third = WaitlistEntry::factory()->for($session)->create();
    DB::table('waitlist_entries')->where('id', $first->id)->update(['status' => WaitlistStatus::Left->value, 'resolved_at' => now()]);

    expect($second->position())->toBe(1)
        ->and($third->position())->toBe(2);
});
