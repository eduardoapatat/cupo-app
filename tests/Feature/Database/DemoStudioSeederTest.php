<?php

use App\Enums\StudioRole;
use App\Models\Studio;

it('seeds Estudio Flow with a demo account for every role', function () {
    $this->seed();

    $studio = Studio::where('slug', 'estudio-flow')->sole();
    $roles = $studio->memberships()->with('user')->orderBy('id')->get()
        ->mapWithKeys(fn ($membership) => [$membership->user->email => $membership->role]);

    expect($roles->all())->toBe([
        'carla@example.com' => StudioRole::Owner,
        'camila@example.com' => StudioRole::FrontDesk,
        'fernanda@example.com' => StudioRole::Instructor,
        'diego@example.com' => StudioRole::Instructor,
        'javiera@example.com' => StudioRole::Student,
    ]);
});
