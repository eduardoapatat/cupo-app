<?php

use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the studio home to every member', function (StudioRole $role) {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, $role)->create())
        ->get(route('studios.home', $studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('studios/home'));
})->with(StudioRole::cases());

it('answers 404 to people outside the studio', function () {
    $outsider = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Owner)->create();

    $this->actingAs($outsider)
        ->get(route('studios.home', Studio::factory()->create()))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404));
});

it('answers 404 for a studio that does not exist', function () {
    $this->actingAs(User::factory()->create())
        ->get('/estudios/no-existe')
        ->assertNotFound();
});

it('sends guests to the login page', function () {
    $this->get(route('studios.home', Studio::factory()->create()))
        ->assertRedirect(route('login'));
});
