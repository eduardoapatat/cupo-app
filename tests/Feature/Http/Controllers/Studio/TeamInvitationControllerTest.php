<?php

use App\Enums\StudioRole;
use App\Models\Invitation;
use App\Models\Studio;
use App\Models\User;
use App\Notifications\StudioInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

function studioOwner(Studio $studio): User
{
    return User::factory()->memberOf($studio, StudioRole::Owner)->create(['name' => 'Carla Rojas']);
}

it('invites someone by email and sends the link, queued', function () {
    $studio = Studio::factory()->create(['name' => 'Estudio Flow']);

    $this->actingAs(studioOwner($studio))
        ->post(route('studios.team.invitations.store', $studio), ['email' => ' Diego@Example.com ', 'role' => 'instructor'])
        ->assertRedirect(route('studios.team.index', $studio));

    $invitation = Invitation::sole();
    expect($invitation->email)->toBe('diego@example.com')
        ->and($invitation->role)->toBe(StudioRole::Instructor)
        ->and($invitation->invitedBy->name)->toBe('Carla Rojas')
        ->and($invitation->expires_at->toDateString())->toBe(now()->addDays(7)->toDateString());

    Notification::assertSentOnDemand(StudioInvitation::class, function (StudioInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'diego@example.com'
            && $notification->invitation->is($invitation)
            && $mail->subject === 'Te invitaron a Estudio Flow'
            && $mail->introLines[0] === 'Carla Rojas te invitó a sumarte a Estudio Flow como Instructor.'
            && str_contains($mail->actionUrl, '/invitaciones/'.$invitation->id.'?expires=')
            && str_contains($mail->actionUrl, '&signature=');
    });
    expect(new StudioInvitation($invitation))->toBeInstanceOf(ShouldQueue::class);
});

it('does not invite someone who is already part of the studio', function () {
    $studio = Studio::factory()->create();
    User::factory()->memberOf($studio, StudioRole::Student)->create(['email' => 'javiera@example.com']);

    $this->actingAs(studioOwner($studio))
        ->post(route('studios.team.invitations.store', $studio), ['email' => 'JAVIERA@example.com', 'role' => 'front_desk'])
        ->assertSessionHasErrors(['email' => 'Esa persona ya es parte del estudio.']);

    expect(Invitation::count())->toBe(0);
    Notification::assertNothingSent();
});

it('does not invite an email that a similar one would match as a pattern', function () {
    $studio = Studio::factory()->create();
    User::factory()->memberOf($studio, StudioRole::Student)->create(['email' => 'axb@example.com']);

    $this->actingAs(studioOwner($studio))
        ->post(route('studios.team.invitations.store', $studio), ['email' => 'a_b@example.com', 'role' => 'instructor'])
        ->assertSessionHasNoErrors();

    expect(Invitation::sole()->email)->toBe('a_b@example.com');
});

it('asks to resend instead of inviting the same email twice', function () {
    $studio = Studio::factory()->create();
    Invitation::factory()->for($studio)->create(['email' => 'diego@example.com']);

    $this->actingAs(studioOwner($studio))
        ->post(route('studios.team.invitations.store', $studio), ['email' => 'diego@example.com', 'role' => 'instructor'])
        ->assertSessionHasErrors(['email' => 'Ya invitaste a ese correo. Reenvía la invitación desde la lista.']);
});

it('only invites front desk and instructors', function (string $role) {
    $studio = Studio::factory()->create();

    $this->actingAs(studioOwner($studio))
        ->post(route('studios.team.invitations.store', $studio), ['email' => 'diego@example.com', 'role' => $role])
        ->assertSessionHasErrors(['role' => 'El valor seleccionado en rol no es válido.']);
})->with(['owner', 'student']);

it('resends an invitation with a fresh validity', function () {
    $studio = Studio::factory()->create();
    $invitation = Invitation::factory()->for($studio)->expired()->create();

    $this->actingAs(studioOwner($studio))
        ->post(route('studios.team.invitations.resend', [$studio, $invitation]))
        ->assertRedirect(route('studios.team.index', $studio));

    expect($invitation->fresh()->isExpired())->toBeFalse();
    Notification::assertSentOnDemandTimes(StudioInvitation::class, 1);
});

it('cancels an invitation', function () {
    $studio = Studio::factory()->create();
    $invitation = Invitation::factory()->for($studio)->create();

    $this->actingAs(studioOwner($studio))
        ->delete(route('studios.team.invitations.destroy', [$studio, $invitation]))
        ->assertRedirect(route('studios.team.index', $studio));

    expect($invitation->fresh())->toBeNull();
});

it('answers 404 for an invitation of another studio or one already accepted', function () {
    $studio = Studio::factory()->create();
    $owner = studioOwner($studio);
    $otherStudios = Invitation::factory()->create();
    $accepted = Invitation::factory()->for($studio)->accepted()->create();

    $this->actingAs($owner)
        ->delete(route('studios.team.invitations.destroy', [$studio, $otherStudios]))
        ->assertNotFound();
    $this->actingAs($owner)
        ->post(route('studios.team.invitations.resend', [$studio, $accepted]))
        ->assertNotFound();

    expect($otherStudios->fresh())->not->toBeNull();
});

it('keeps invitations in the hands of the owner', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->post(route('studios.team.invitations.store', $studio), ['email' => 'diego@example.com', 'role' => 'instructor'])
        ->assertForbidden();

    expect(Invitation::count())->toBe(0);
});
