<?php

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelSession;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Models\ClassSession;
use App\Models\User;

it('cancels the class and its bookings on time, and releases the spots', function () {
    $session = ClassSession::factory()->create(['instructor_id' => User::factory()]);
    $carla = User::factory()->create();
    foreach (User::factory()->count(3)->create() as $student) {
        app(BookSession::class)->handle($session, $student, BookingKind::Student, $student);
    }

    app(CancelSession::class)->handle($session, $carla, 'Corte de luz');

    $session->refresh();
    expect($session->status)->toBe(ClassSessionStatus::Cancelled)
        ->and($session->cancellation_reason)->toBe('Corte de luz')
        ->and($session->cancelled_by_id)->toBe($carla->id)
        ->and($session->booked_count)->toBe(0)
        ->and($session->bookings->pluck('status')->unique()->all())->toBe([BookingStatus::CancelledOnTime])
        ->and($session->bookings->pluck('cancellation_reason')->unique()->all())->toBe(['El estudio canceló la clase.']);
});

it('does not cancel a class twice or once it started', function () {
    $session = ClassSession::factory()->create(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);

    expect(fn () => app(CancelSession::class)->handle($session, User::factory()->create(), 'Tarde'))
        ->toThrow(BookingRejected::class, 'La clase ya empezó, así que ya no se puede cancelar.');

    $upcoming = ClassSession::factory()->create();
    app(CancelSession::class)->handle($upcoming, User::factory()->create(), 'Feriado');

    expect(fn () => app(CancelSession::class)->handle($upcoming->fresh(), User::factory()->create(), 'Otra vez'))
        ->toThrow(BookingRejected::class, 'Esta clase ya estaba cancelada.');
});
