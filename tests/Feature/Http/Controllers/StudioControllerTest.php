<?php

use App\Actions\Studios\CreateStudio;
use App\Enums\Commune;
use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the form with the communes to people without a studio of their own', function () {
    $this->actingAs(User::factory()->memberOf(Studio::factory()->create(), StudioRole::Instructor)->create())
        ->get(route('studios.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/create')
            ->has('communes', count(Commune::cases()))
            ->where('communes.0', ['value' => 'alhue', 'label' => 'Alhué']));
});

it('creates the studio and takes its new owner there', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('studios.store'), [
        'name' => '  Estudio Flow ',
        'commune' => 'nunoa',
    ]);

    $studio = Studio::sole();
    $response->assertRedirect(route('studios.home', $studio));
    expect($studio->name)->toBe('Estudio Flow')
        ->and($studio->commune)->toBe(Commune::Nunoa)
        ->and($user->roleIn($studio))->toBe(StudioRole::Owner);
});

it('refuses a second studio to someone who already owns one', function () {
    $owner = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Owner)->create();

    $this->actingAs($owner)->get(route('studios.create'))->assertForbidden();
    $this->actingAs($owner)
        ->post(route('studios.store'), ['name' => 'Estudio Sur', 'commune' => 'nunoa'])
        ->assertForbidden();

    expect(Studio::count())->toBe(1);
});

it('takes a double submit to the studio the first request created', function () {
    $this->mock(CreateStudio::class)
        ->shouldReceive('handle')
        ->andThrow(new UniqueConstraintViolationException('pgsql', 'insert into memberships', [], new PDOException));

    $this->actingAs(User::factory()->create())
        ->post(route('studios.store'), ['name' => 'Estudio Flow', 'commune' => 'nunoa'])
        ->assertRedirect(route('dashboard'));
});

it('validates the studio data in Spanish', function (array $data, string $field, string $message) {
    $this->actingAs(User::factory()->create())
        ->post(route('studios.store'), $data)
        ->assertSessionHasErrors([$field => $message]);

    expect(Studio::count())->toBe(0);
})->with([
    'sin nombre' => [['commune' => 'nunoa'], 'name', 'El campo nombre es obligatorio.'],
    'nombre sin letras' => [['name' => '---', 'commune' => 'nunoa'], 'name', 'El nombre debe tener letras o números.'],
    'comuna fuera de la RM' => [['name' => 'Estudio Flow', 'commune' => 'valparaiso'], 'commune', 'El valor seleccionado en comuna no es válido.'],
]);

it('sends guests to the login page', function () {
    $this->get(route('studios.create'))->assertRedirect(route('login'));
});
