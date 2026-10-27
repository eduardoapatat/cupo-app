<?php

use App\Enums\StudioRole;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('rejects an unknown role at the database level', function () {
    $membership = Membership::factory()->create();

    $invalid = fn () => DB::table('memberships')->where('id', $membership->id)->update(['role' => 'super_admin']);

    expect($invalid)->toThrow(function (QueryException $exception) {
        expect($exception->getCode())->toBe('23514');
    });
});

it('allows a single membership per person and studio', function () {
    $membership = Membership::factory()->create();

    $duplicate = fn () => Membership::factory()
        ->for($membership->studio)
        ->for($membership->user)
        ->role(StudioRole::Instructor)
        ->create();

    expect($duplicate)->toThrow(function (QueryException $exception) {
        expect($exception->getCode())->toBe('23505');
    });
});

it('allows a single owner per studio', function () {
    $studio = Studio::factory()->create();
    Membership::factory()->for($studio)->role(StudioRole::Owner)->create();

    $secondOwner = fn () => Membership::factory()->for($studio)->role(StudioRole::Owner)->create();

    expect($secondOwner)->toThrow(function (QueryException $exception) {
        expect($exception->getCode())->toBe('23505');
    });
});

it('lets a person hold different roles in different studios', function () {
    $ownStudio = Studio::factory()->create();
    $otherStudio = Studio::factory()->create();

    $user = User::factory()
        ->memberOf($ownStudio, StudioRole::Owner)
        ->memberOf($otherStudio, StudioRole::Student)
        ->create();

    expect($user->roleIn($ownStudio))->toBe(StudioRole::Owner)
        ->and($user->roleIn($otherStudio))->toBe(StudioRole::Student)
        ->and($user->roleIn(Studio::factory()->create()))->toBeNull();
});
