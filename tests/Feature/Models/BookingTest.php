<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('keeps one current booking per person and session', function () {
    $session = ClassSession::factory()->create();
    $javiera = User::factory()->create();
    Booking::factory()->for($session)->for($javiera)->create();

    expectSqlState(fn () => Booking::factory()->for($session)->for($javiera)->create(), '23505');
});

it('lets a person book again after cancelling', function () {
    $session = ClassSession::factory()->create();
    $javiera = User::factory()->create();
    Booking::factory()->for($session)->for($javiera)->cancelled()->create();

    expect(Booking::factory()->for($session)->for($javiera)->create()->exists)->toBeTrue();
});

it('marks a booking as cancelled exactly when it has its cancellation time', function () {
    $booking = Booking::factory()->create();

    expectSqlState(fn () => DB::table('bookings')->where('id', $booking->id)->update(['status' => BookingStatus::CancelledLate->value]), '23514');
    expectSqlState(fn () => DB::table('bookings')->where('id', $booking->id)->update(['cancelled_at' => now()]), '23514');
});
