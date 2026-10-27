<?php

use App\Actions\Studios\CreateStudio;
use App\Enums\Commune;
use App\Enums\StudioRole;
use App\Models\User;

it('creates the studio with the default rules and its owner', function () {
    config([
        'studio.defaults.cancellation_window_hours' => 12,
        'studio.defaults.booking_cutoff_minutes' => 30,
        'studio.defaults.waitlist_cutoff_hours' => 8,
        'studio.defaults.plan_expiry_warning_days' => 5,
    ]);
    $owner = User::factory()->create();

    $studio = app(CreateStudio::class)->handle($owner, 'Estudio Flow', Commune::Nunoa);

    expect($studio->only(['name', 'slug', 'commune', 'cancellation_window_hours', 'booking_cutoff_minutes', 'waitlist_cutoff_hours', 'plan_expiry_warning_days']))
        ->toBe([
            'name' => 'Estudio Flow',
            'slug' => 'estudio-flow',
            'commune' => Commune::Nunoa,
            'cancellation_window_hours' => 12,
            'booking_cutoff_minutes' => 30,
            'waitlist_cutoff_hours' => 8,
            'plan_expiry_warning_days' => 5,
        ])
        ->and($owner->roleIn($studio))->toBe(StudioRole::Owner);
});

it('never gives a studio the reserved slug of the create form', function () {
    $studio = app(CreateStudio::class)->handle(User::factory()->create(), 'Crear', Commune::Santiago);

    expect($studio->slug)->toBe('crear-2');
});
