<?php

use App\Models\User;

it('stores people who do not use the app, without email or password', function () {
    $rosa = User::factory()->withoutApp()->create(['name' => 'Rosa Fuentes']);
    $another = User::factory()->withoutApp()->create();

    expect($rosa->fresh()->usesApp())->toBeFalse()
        ->and($rosa->email)->toBeNull()
        ->and($another->exists)->toBeTrue();
});

it('finds a user by email ignoring case', function () {
    $ana = User::factory()->create(['email' => 'Ana@Example.com']);

    expect(User::withEmail(' ANA@example.com ')->sole()->is($ana))->toBeTrue();
});
