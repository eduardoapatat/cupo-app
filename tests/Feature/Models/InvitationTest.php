<?php

use App\Models\Invitation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('only invites front desk and instructors', function () {
    $invitation = Invitation::factory()->create();

    $invalid = fn () => DB::table('invitations')->where('id', $invitation->id)->update(['role' => 'owner']);

    expect($invalid)->toThrow(function (QueryException $exception) {
        expect($exception->getCode())->toBe('23514');
    });
});

it('stores the email in lowercase', function () {
    $invitation = Invitation::factory()->create(['email' => '  Diego@Example.COM ']);

    expect($invitation->fresh()->email)->toBe('diego@example.com');
});

it('allows a single pending invitation per email and studio', function () {
    $pending = Invitation::factory()->create(['email' => 'diego@example.com']);

    $duplicate = fn () => Invitation::factory()->for($pending->studio)->create(['email' => 'DIEGO@example.com']);

    expect($duplicate)->toThrow(function (QueryException $exception) {
        expect($exception->getCode())->toBe('23505');
    });
});

it('lets the same email be invited again once the invitation is accepted', function () {
    $accepted = Invitation::factory()->accepted()->create(['email' => 'diego@example.com']);

    $again = Invitation::factory()->for($accepted->studio)->create(['email' => 'diego@example.com']);

    expect($again->exists)->toBeTrue();
});
