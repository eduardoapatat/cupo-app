<?php

use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;

it('seeds Estudio Flow with a demo account for every role and a student without the app', function () {
    $this->seed();

    $studio = Studio::where('slug', 'estudio-flow')->sole();
    $roles = $studio->memberships()->with('user')->orderBy('id')->get()
        ->mapWithKeys(fn ($membership) => [$membership->user->name => $membership->role]);

    expect($roles->all())->toBe([
        'Carla Rojas' => StudioRole::Owner,
        'Camila Soto' => StudioRole::FrontDesk,
        'Fernanda Díaz' => StudioRole::Instructor,
        'Diego Herrera' => StudioRole::Instructor,
        'Javiera Morales' => StudioRole::Student,
        'Rosa Fuentes' => StudioRole::Student,
    ])
        ->and(User::where('name', 'Rosa Fuentes')->sole()->usesApp())->toBeFalse();
});

it('seeds a second studio where Fernanda also teaches', function () {
    $this->seed();

    $sur = Studio::where('slug', 'estudio-sur')->sole();

    expect(User::where('email', 'fernanda@example.com')->sole()->roleIn($sur))->toBe(StudioRole::Instructor)
        ->and(User::where('email', 'paula@example.com')->sole()->roleIn($sur))->toBe(StudioRole::Owner);
});
