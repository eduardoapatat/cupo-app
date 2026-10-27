<?php

use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a guest the form to create an account and join', function () {
    $studio = Studio::factory()->create(['name' => 'Estudio Flow']);

    $this->get(route('enrollment.show', $studio->enrollment_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('enrollment/show')
            ->where('state', 'register')
            ->where('studio.name', 'Estudio Flow'))
        ->assertSessionHas('url.intended', route('enrollment.show', $studio->enrollment_token));
});

it('creates the account of a guest and makes her a student, with the verification email', function () {
    Notification::fake();
    $studio = Studio::factory()->create();

    $this->post(route('enrollment.store', $studio->enrollment_token), [
        'name' => 'Javiera Morales',
        'email' => 'javiera@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'adult' => 'on',
    ])->assertRedirect(route('studios.home', $studio));

    $javiera = User::where('email', 'javiera@example.com')->sole();
    $this->assertAuthenticatedAs($javiera);
    expect($javiera->roleIn($studio))->toBe(StudioRole::Student)
        ->and($javiera->adult_declared_at)->not->toBeNull();
    Notification::assertSentTo($javiera, VerifyEmail::class);
});

it('validates the account like the registration does', function () {
    $studio = Studio::factory()->create();

    $this->post(route('enrollment.store', $studio->enrollment_token), ['name' => 'Javiera'])
        ->assertSessionHasErrors([
            'email' => 'El campo correo es obligatorio.',
            'adult' => 'Debes declarar que eres mayor de 18 años.',
        ]);

    expect(User::count())->toBe(0);
});

it('lets someone signed in join with her account', function () {
    $studio = Studio::factory()->create();
    $instructorElsewhere = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Instructor)->create();

    $this->actingAs($instructorElsewhere)
        ->get(route('enrollment.show', $studio->enrollment_token))
        ->assertInertia(fn (Assert $page) => $page->where('state', 'join')->where('needsAdultDeclaration', false));

    $this->actingAs($instructorElsewhere)
        ->post(route('enrollment.store', $studio->enrollment_token))
        ->assertRedirect(route('studios.home', $studio));

    expect($instructorElsewhere->fresh()->roleIn($studio))->toBe(StudioRole::Student);
});

it('asks an older account to declare being 18 before joining', function () {
    $studio = Studio::factory()->create();
    $user = User::factory()->create(['adult_declared_at' => null]);

    $this->actingAs($user)
        ->post(route('enrollment.store', $studio->enrollment_token))
        ->assertSessionHasErrors(['adult' => 'Debes declarar que eres mayor de 18 años.']);

    expect($user->fresh()->roleIn($studio))->toBeNull();
});

it('keeps the role of someone who is already a member', function () {
    $studio = Studio::factory()->create();
    $camila = User::factory()->memberOf($studio, StudioRole::FrontDesk)->create();

    $this->actingAs($camila)
        ->get(route('enrollment.show', $studio->enrollment_token))
        ->assertInertia(fn (Assert $page) => $page->where('state', 'member'));

    $this->actingAs($camila)
        ->post(route('enrollment.store', $studio->enrollment_token))
        ->assertRedirect(route('studios.home', $studio));

    expect($camila->fresh()->roleIn($studio))->toBe(StudioRole::FrontDesk);
});

it('answers 404 to a link that no longer exists', function () {
    $this->get(route('enrollment.show', 'enlace-viejo'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('enrollment/unavailable'));

    $this->post(route('enrollment.store', 'enlace-viejo'), [])->assertNotFound();
});

it('starts a new session when it signs a new student in', function () {
    $studio = Studio::factory()->create();
    $this->get(route('enrollment.show', $studio->enrollment_token));
    $sessionId = session()->getId();

    $this->post(route('enrollment.store', $studio->enrollment_token), [
        'name' => 'Javiera Morales',
        'email' => 'javiera@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'adult' => 'on',
    ]);

    expect(session()->getId())->not->toBe($sessionId);
});
