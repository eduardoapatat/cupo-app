<?php

use App\Enums\StudioRole;
use App\Models\ScheduleSlot;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the owner the studio rules', function () {
    $studio = Studio::factory()->create(['cancellation_window_hours' => 6, 'booking_horizon_days' => 14]);

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->get(route('studios.settings.edit', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rules.cancellation_window_hours', 6)
            ->where('rules.booking_horizon_days', 14));
});

it('saves the rules and generates the sessions of a longer horizon at once', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago')); // a Monday
    $studio = Studio::factory()->create(['booking_horizon_days' => 7]);
    $slot = ScheduleSlot::factory()->for($studio)->create(['weekday' => 2, 'starts_at' => '19:00:00']);

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->put(route('studios.settings.rules.update', $studio), [
            'cancellation_window_hours' => 12,
            'booking_cutoff_minutes' => 30,
            'waitlist_cutoff_hours' => 8,
            'booking_horizon_days' => 21,
        ])
        ->assertRedirect(route('studios.settings.edit', $studio));

    expect($studio->fresh()->only(['cancellation_window_hours', 'booking_cutoff_minutes', 'waitlist_cutoff_hours', 'booking_horizon_days']))
        ->toBe(['cancellation_window_hours' => 12, 'booking_cutoff_minutes' => 30, 'waitlist_cutoff_hours' => 8, 'booking_horizon_days' => 21])
        ->and($slot->classSessions()->count())->toBe(3);
});

it('validates the rules', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->put(route('studios.settings.rules.update', $studio), [
            'cancellation_window_hours' => -1,
            'booking_cutoff_minutes' => 30,
            'waitlist_cutoff_hours' => 8,
            'booking_horizon_days' => 0,
        ])
        ->assertSessionHasErrors([
            'cancellation_window_hours' => 'El campo ventana de cancelación debe ser al menos 0.',
            'booking_horizon_days' => 'El campo anticipación debe ser al menos 1.',
        ]);
});

it('keeps the rules in the hands of the owner', function () {
    $studio = Studio::factory()->create(['cancellation_window_hours' => 6]);

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->put(route('studios.settings.rules.update', $studio), [
            'cancellation_window_hours' => 0,
            'booking_cutoff_minutes' => 0,
            'waitlist_cutoff_hours' => 0,
            'booking_horizon_days' => 30,
        ])
        ->assertForbidden();

    expect($studio->fresh()->cancellation_window_hours)->toBe(6);
});
