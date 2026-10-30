<?php

use App\Enums\Commune;
use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the owner the studio data', function () {
    $studio = Studio::factory()->create(['name' => 'Estudio Flow', 'commune' => Commune::Nunoa]);

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->get(route('studios.settings.edit', $studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/settings')
            ->where('details', ['slug' => $studio->slug, 'name' => 'Estudio Flow', 'commune' => 'nunoa'])
            ->has('communes', count(Commune::cases()))
            ->missing('enrollmentUrl'));
});

it('saves the name and the commune and keeps the slug', function () {
    $studio = Studio::factory()->create(['name' => 'Estudio Flow', 'commune' => Commune::Nunoa]);

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->put(route('studios.settings.update', $studio), ['name' => 'Flow Pilates', 'commune' => 'providencia'])
        ->assertRedirect(route('studios.settings.edit', $studio));

    expect($studio->fresh()->only(['name', 'slug', 'commune']))->toBe([
        'name' => 'Flow Pilates',
        'slug' => $studio->slug,
        'commune' => Commune::Providencia,
    ]);
});

it('keeps the studio data away from the rest of the team', function () {
    $studio = Studio::factory()->create(['name' => 'Estudio Flow']);

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->put(route('studios.settings.update', $studio), ['name' => 'Otro nombre', 'commune' => 'nunoa'])
        ->assertForbidden();

    expect($studio->fresh()->name)->toBe('Estudio Flow');
});

it('answers 404 to the owner of another studio', function () {
    $owner = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Owner)->create();

    $this->actingAs($owner)
        ->get(route('studios.settings.edit', Studio::factory()->create()))
        ->assertNotFound();
});

it('validates the studio data', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->put(route('studios.settings.update', $studio), ['name' => '', 'commune' => 'nunoa'])
        ->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
});

it('saves where students transfer to, and empty turns off buying from the app', function () {
    $studio = Studio::factory()->create();
    $carla = User::factory()->memberOf($studio, StudioRole::Owner)->create();
    $route = route('studios.settings.transfer.update', $studio);

    $this->actingAs($carla)
        ->put($route, ['transfer_details' => "  Banco del Ejemplo\nCuenta 000123456  "])
        ->assertRedirect(route('studios.settings.edit', $studio))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Guardado. Las alumnas los ven al comprar un plan desde la app.']);

    expect($studio->fresh()->transfer_details)->toBe("Banco del Ejemplo\nCuenta 000123456");

    $this->actingAs($carla)->put($route, ['transfer_details' => ' ']);

    expect($studio->fresh()->transfer_details)->toBeNull();
    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->put($route, ['transfer_details' => 'Otra cuenta'])
        ->assertForbidden();
});
