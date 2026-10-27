<?php

use App\Enums\StudioRole;
use App\Models\Invitation;
use App\Models\Studio;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the owner her team first and the pending invitations, without the students', function () {
    $studio = Studio::factory()->create();
    User::factory()->memberOf($studio, StudioRole::Instructor)->create(['name' => 'Fernanda Díaz']);
    User::factory()->memberOf($studio, StudioRole::FrontDesk)->create(['name' => 'Camila Soto']);
    User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $owner = User::factory()->memberOf($studio, StudioRole::Owner)->create(['name' => 'Carla Rojas']);
    Invitation::factory()->for($studio)->create(['email' => 'diego@example.com']);
    Invitation::factory()->for($studio)->accepted()->create();

    $this->actingAs($owner)
        ->get(route('studios.team.index', $studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/team')
            ->where('members', fn ($members) => collect($members)->map(fn ($member) => [$member['name'], $member['role'], $member['removable']])->all() === [
                ['Carla Rojas', 'Dueña', false],
                ['Camila Soto', 'Recepción', true],
                ['Fernanda Díaz', 'Instructor', true],
            ])
            ->has('invitations', 1)
            ->where('invitations.0.email', 'diego@example.com')
            ->where('roles', [
                ['value' => 'front_desk', 'label' => 'Recepción'],
                ['value' => 'instructor', 'label' => 'Instructor'],
            ]));
});

it('keeps the team page away from the front desk', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->get(route('studios.team.index', $studio))
        ->assertForbidden();
});
