<?php

use App\Enums\StudioRole;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;

it('takes someone off the team, who then cannot enter the studio', function (StudioRole $role) {
    $studio = Studio::factory()->create();
    $owner = User::factory()->memberOf($studio, StudioRole::Owner)->create();
    $member = User::factory()->memberOf($studio, $role)->create();
    $membership = $member->memberships()->sole();

    $this->actingAs($owner)
        ->delete(route('studios.team.members.destroy', [$studio, $membership]))
        ->assertRedirect(route('studios.team.index', $studio));

    expect($membership->fresh())->toBeNull();
    $this->actingAs($member->fresh())->get(route('studios.home', $studio))->assertNotFound();
})->with(StudioRole::invitable());

it('does not take the owner or a student off from «Equipo»', function (StudioRole $role) {
    $studio = Studio::factory()->create();
    $owner = User::factory()->memberOf($studio, StudioRole::Owner)->create();
    $membership = $role === StudioRole::Owner
        ? $owner->memberships()->sole()
        : Membership::factory()->for($studio)->role($role)->create();

    $this->actingAs($owner)
        ->delete(route('studios.team.members.destroy', [$studio, $membership]))
        ->assertNotFound();

    expect($membership->fresh())->not->toBeNull();
})->with([StudioRole::Owner, StudioRole::Student]);

it('answers 404 for a member of another studio', function () {
    $studio = Studio::factory()->create();
    $owner = User::factory()->memberOf($studio, StudioRole::Owner)->create();
    $elsewhere = Membership::factory()->role(StudioRole::Instructor)->create();

    $this->actingAs($owner)
        ->delete(route('studios.team.members.destroy', [$studio, $elsewhere]))
        ->assertNotFound();

    expect($elsewhere->fresh())->not->toBeNull();
});

it('keeps removals in the hands of the owner', function () {
    $studio = Studio::factory()->create();
    $instructor = Membership::factory()->for($studio)->role(StudioRole::Instructor)->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->delete(route('studios.team.members.destroy', [$studio, $instructor]))
        ->assertForbidden();

    expect($instructor->fresh())->not->toBeNull();
});
