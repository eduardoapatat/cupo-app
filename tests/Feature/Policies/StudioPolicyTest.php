<?php

use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets every member enter their studio', function (StudioRole $role) {
    $studio = Studio::factory()->create();
    $member = User::factory()->memberOf($studio, $role)->create();

    expect(Gate::forUser($member)->allows('view', $studio))->toBeTrue();
})->with(StudioRole::cases());

it('answers 404 to people outside the studio', function () {
    $outsider = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Owner)->create();

    $response = Gate::forUser($outsider)->inspect('view', Studio::factory()->create());

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});
