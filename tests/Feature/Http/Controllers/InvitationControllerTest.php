<?php

use App\Enums\StudioRole;
use App\Models\Invitation;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

function invitationLink(Invitation $invitation, string $route = 'invitations.show'): string
{
    return URL::temporarySignedRoute($route, $invitation->expires_at, ['invitation' => $invitation->id]);
}

describe('opening the link', function () {
    it('shows a guest without an account the form to create one', function () {
        $invitation = Invitation::factory()
            ->for(Studio::factory()->create(['name' => 'Estudio Flow']))
            ->create(['email' => 'diego@example.com', 'role' => StudioRole::Instructor]);

        $this->get(invitationLink($invitation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invitations/show')
                ->where('state', 'register')
                ->where('invitation.studio', 'Estudio Flow')
                ->where('invitation.role', 'Instructor')
                ->where('invitation.email', 'diego@example.com'));
    });

    it('asks a guest who already has an account to sign in, and brings them back', function () {
        $invitation = Invitation::factory()->create(['email' => 'diego@example.com']);
        User::factory()->create(['email' => 'diego@example.com']);

        $this->get($link = invitationLink($invitation))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'login'))
            ->assertSessionHas('url.intended', $link);
    });

    it('recognizes an account whose email differs only in case', function () {
        $invitation = Invitation::factory()->create(['email' => 'ana@example.com']);
        User::factory()->create(['email' => 'Ana@Example.com']);

        $this->get(invitationLink($invitation))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'login'));

        $this->post(invitationLink($invitation, 'invitations.accept'), [
            'name' => 'Ana',
            'password' => 'password',
            'password_confirmation' => 'password',
            'adult' => 'on',
        ])->assertSessionHasErrors('email');

        expect(User::count())->toBe(1);
    });

    it('tells a signed-in person when the invitation is for another email', function () {
        $invitation = Invitation::factory()->create(['email' => 'diego@example.com']);

        $this->actingAs(User::factory()->create(['email' => 'otra@example.com']))
            ->get(invitationLink($invitation))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'other_account'));
    });

    it('tells a member that there is nothing to accept', function () {
        $invitation = Invitation::factory()->create(['email' => 'diego@example.com']);
        $member = User::factory()
            ->memberOf($invitation->studio, StudioRole::Student)
            ->create(['email' => 'diego@example.com']);

        $this->actingAs($member)
            ->get(invitationLink($invitation))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'member'));
    });

    it('answers 404 to a tampered link, like to a missing invitation', function () {
        $invitation = Invitation::factory()->create();

        $this->get(invitationLink($invitation).'x')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('invitations/unavailable')->where('reason', 'invalid'));

        $this->get(route('invitations.show', ['invitation' => $invitation->id + 1]))
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->where('reason', 'invalid'));
    });

    it('explains when the invitation was already used', function () {
        $invitation = Invitation::factory()->accepted()->create();

        $this->get(invitationLink($invitation))
            ->assertStatus(410)
            ->assertInertia(fn (Assert $page) => $page->where('reason', 'accepted'));
    });

    it('explains when the link expired, even after a resend extended the invitation', function () {
        $invitation = Invitation::factory()->create();
        $oldLink = invitationLink($invitation);
        $this->travel(config('studio.invitation_days') + 1)->days();
        $invitation->update(['expires_at' => now()->addDays(7)]);

        $this->get($oldLink)
            ->assertStatus(410)
            ->assertInertia(fn (Assert $page) => $page->where('reason', 'expired'));
    });
});

describe('accepting', function () {
    it('creates the account of a guest, already verified, and adds them to the team', function () {
        $invitation = Invitation::factory()->create(['email' => 'diego@example.com', 'role' => StudioRole::Instructor]);

        $this->post(invitationLink($invitation, 'invitations.accept'), [
            'name' => 'Diego Herrera',
            'password' => 'password',
            'password_confirmation' => 'password',
            'adult' => 'on',
        ])->assertRedirect(route('studios.home', $invitation->studio));

        $diego = User::where('email', 'diego@example.com')->sole();
        $this->assertAuthenticatedAs($diego);
        expect($diego->email_verified_at)->not->toBeNull()
            ->and($diego->adult_declared_at)->not->toBeNull()
            ->and($diego->roleIn($invitation->studio))->toBe(StudioRole::Instructor)
            ->and($invitation->fresh()->isAccepted())->toBeTrue();
    });

    it('starts a new session when it signs the new account in', function () {
        $invitation = Invitation::factory()->create(['email' => 'diego@example.com']);
        $this->get(invitationLink($invitation));
        $sessionId = session()->getId();

        $this->post(invitationLink($invitation, 'invitations.accept'), [
            'name' => 'Diego Herrera',
            'password' => 'password',
            'password_confirmation' => 'password',
            'adult' => 'on',
        ]);

        expect(session()->getId())->not->toBe($sessionId);
    });

    it('asks a guest for the account data in Spanish', function () {
        $invitation = Invitation::factory()->create();

        $this->post(invitationLink($invitation, 'invitations.accept'), [])
            ->assertSessionHasErrors([
                'name' => 'El campo nombre es obligatorio.',
                'adult' => 'Debes declarar que eres mayor de 18 años.',
            ]);

        expect($invitation->fresh()->isAccepted())->toBeFalse();
    });

    it('does not let a guest create an account over an existing one', function () {
        $invitation = Invitation::factory()->create(['email' => 'diego@example.com']);
        User::factory()->create(['email' => 'diego@example.com']);

        $this->post(invitationLink($invitation, 'invitations.accept'), [
            'name' => 'Otra persona',
            'password' => 'password',
            'password_confirmation' => 'password',
            'adult' => 'on',
        ])->assertSessionHasErrors(['email' => 'Ya tienes una cuenta con este correo. Inicia sesión para aceptar la invitación.']);

        $this->assertGuest();
        expect(User::count())->toBe(1);
    });

    it('adds a signed-in invitee to the team and verifies their email', function () {
        $invitation = Invitation::factory()->create(['email' => 'camila@example.com', 'role' => StudioRole::FrontDesk]);
        $camila = User::factory()->unverified()->create(['email' => 'Camila@Example.com']);

        $this->actingAs($camila)
            ->post(invitationLink($invitation, 'invitations.accept'))
            ->assertRedirect(route('studios.home', $invitation->studio));

        expect($camila->fresh()->roleIn($invitation->studio))->toBe(StudioRole::FrontDesk)
            ->and($camila->fresh()->email_verified_at)->not->toBeNull();
    });

    it('refuses someone signed in with another email', function () {
        $invitation = Invitation::factory()->create(['email' => 'diego@example.com']);

        $this->actingAs(User::factory()->create(['email' => 'otra@example.com']))
            ->post(invitationLink($invitation, 'invitations.accept'))
            ->assertForbidden();

        expect($invitation->fresh()->isAccepted())->toBeFalse();
    });

    it('keeps the role of someone who is already a member', function () {
        $invitation = Invitation::factory()->create(['email' => 'javiera@example.com', 'role' => StudioRole::FrontDesk]);
        $javiera = User::factory()->memberOf($invitation->studio, StudioRole::Student)->create(['email' => 'javiera@example.com']);

        $this->actingAs($javiera)
            ->post(invitationLink($invitation, 'invitations.accept'))
            ->assertRedirect(route('studios.home', $invitation->studio));

        expect($javiera->fresh()->roleIn($invitation->studio))->toBe(StudioRole::Student);
    });

    it('cannot use a link twice', function () {
        $invitation = Invitation::factory()->accepted()->create(['email' => 'diego@example.com']);

        $this->actingAs(User::factory()->create(['email' => 'diego@example.com']))
            ->post(invitationLink($invitation, 'invitations.accept'))
            ->assertStatus(410);
    });
});
