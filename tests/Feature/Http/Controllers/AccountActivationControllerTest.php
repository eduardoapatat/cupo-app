<?php

use App\Models\User;
use App\Notifications\ActivateAccount;
use Inertia\Testing\AssertableInertia as Assert;

function studentWithoutApp(string $email = 'rosa@example.com'): User
{
    return User::factory()->withoutApp()->create(['name' => 'Rosa Fuentes', 'email' => $email]);
}

it('shows the student the form to create her password', function () {
    $rosa = studentWithoutApp();

    $this->get(ActivateAccount::url($rosa))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('activation/show')
            ->where('name', 'Rosa Fuentes')
            ->where('email', 'rosa@example.com'));
});

it('saves the password, verifies the email and signs her in', function () {
    $rosa = studentWithoutApp();
    $link = ActivateAccount::url($rosa);
    $activateUrl = $this->get($link)->viewData('page')['props']['activateUrl'];

    $this->post($activateUrl, ['password' => 'password', 'password_confirmation' => 'password'])
        ->assertRedirect(route('dashboard'));

    $rosa->refresh();
    $this->assertAuthenticatedAs($rosa);
    expect($rosa->usesApp())->toBeTrue()
        ->and($rosa->email_verified_at)->not->toBeNull();
});

it('explains that the link no longer works once the account is active', function () {
    $rosa = studentWithoutApp();
    $link = ActivateAccount::url($rosa);
    $rosa->forceFill(['password' => 'password'])->save();

    $this->get($link)
        ->assertStatus(410)
        ->assertInertia(fn (Assert $page) => $page->component('activation/unavailable')->where('reason', 'active'));
});

it('stops a link sent to an email that was corrected afterwards', function () {
    $rosa = studentWithoutApp('rosa.antiguo@example.com');
    $oldLink = ActivateAccount::url($rosa);
    $rosa->update(['email' => 'rosa@example.com']);

    $this->get($oldLink)
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->where('reason', 'invalid'));
});

it('explains when the link expired', function () {
    $link = ActivateAccount::url(studentWithoutApp());
    $this->travel(config('studio.activation_days') + 1)->days();

    $this->get($link)
        ->assertStatus(410)
        ->assertInertia(fn (Assert $page) => $page->where('reason', 'expired'));
});

it('answers 404 to a tampered link', function () {
    $this->get(ActivateAccount::url(studentWithoutApp()).'x')->assertNotFound();
});

it('validates the password', function () {
    $rosa = studentWithoutApp();
    $activateUrl = $this->get(ActivateAccount::url($rosa))->viewData('page')['props']['activateUrl'];

    $this->post($activateUrl, ['password' => 'password', 'password_confirmation' => 'otra'])
        ->assertSessionHasErrors('password');

    expect($rosa->fresh()->usesApp())->toBeFalse();
});

it('starts a new session when it signs her in', function () {
    $rosa = studentWithoutApp();
    $activateUrl = $this->get(ActivateAccount::url($rosa))->viewData('page')['props']['activateUrl'];
    $sessionId = session()->getId();

    $this->post($activateUrl, ['password' => 'password', 'password_confirmation' => 'password']);

    expect(session()->getId())->not->toBe($sessionId);
});
