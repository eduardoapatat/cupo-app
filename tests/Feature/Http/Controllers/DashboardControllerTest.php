<?php

use App\Enums\StudioRole;
use App\Http\Middleware\RememberCurrentStudio;
use App\Models\Studio;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('sends guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('asks unverified users to verify their email first', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

it('shows an empty state to people without a studio', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('studios/none'));
});

it('takes members to the home of their studio', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Student)->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('studios.home', $studio));
});

it('takes members to the studio they opened last', function () {
    $first = Studio::factory()->create(['name' => 'Estudio A']);
    $last = Studio::factory()->create(['name' => 'Estudio B']);
    $user = User::factory()
        ->memberOf($first, StudioRole::Owner)
        ->memberOf($last, StudioRole::Student)
        ->create();

    $this->actingAs($user)->get(route('studios.home', $last))->assertOk();

    $this->get(route('dashboard'))->assertRedirect(route('studios.home', $last));
});

it('falls back to the first studio by name when the last one is no longer theirs', function () {
    $first = Studio::factory()->create(['name' => 'Estudio A']);
    $user = User::factory()
        ->memberOf(Studio::factory()->create(['name' => 'Estudio B']), StudioRole::Student)
        ->memberOf($first, StudioRole::Instructor)
        ->create();

    $this->actingAs($user)
        ->withSession([RememberCurrentStudio::SESSION_KEY => Studio::factory()->create()->id])
        ->get(route('dashboard'))
        ->assertRedirect(route('studios.home', $first));
});
