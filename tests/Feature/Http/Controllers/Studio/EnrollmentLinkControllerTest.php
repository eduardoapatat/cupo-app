<?php

use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the owner the link to share', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->get(route('studios.settings.edit', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollmentUrl', route('enrollment.show', $studio->enrollment_token)));
});

it('renews the link, and the old one stops working', function () {
    $studio = Studio::factory()->create();
    $oldToken = $studio->enrollment_token;

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->put(route('studios.settings.enrollment-link.update', $studio))
        ->assertRedirect(route('studios.settings.edit', $studio));

    expect($studio->fresh()->enrollment_token)->not->toBe($oldToken);
    $this->get(route('enrollment.show', $oldToken))->assertNotFound();
});

it('keeps the link in the hands of the owner', function () {
    $studio = Studio::factory()->create();
    $token = $studio->enrollment_token;

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->put(route('studios.settings.enrollment-link.update', $studio))
        ->assertForbidden();

    expect($studio->fresh()->enrollment_token)->toBe($token);
});
