<?php

use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lets only the owner renew the link from «Alumnas»', function (StudioRole $role, bool $canRenew) {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, $role)->create())
        ->get(route('studios.students.index', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollmentUrl', route('enrollment.show', $studio->enrollment_token))
            ->where('canRenewEnrollmentLink', $canRenew));
})->with([
    'dueña' => [StudioRole::Owner, true],
    'recepción' => [StudioRole::FrontDesk, false],
]);

it('renews the link, and the old one stops working', function () {
    $studio = Studio::factory()->create();
    $oldToken = $studio->enrollment_token;

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->from(route('studios.students.index', $studio))
        ->put(route('studios.settings.enrollment-link.update', $studio))
        ->assertRedirect(route('studios.students.index', $studio));

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
