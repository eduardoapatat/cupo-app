<?php

use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use App\Navigation\MenuItem;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Each role's menu as docs/roles-y-permisos.md defines it, in order.
 */
dataset('menus', [
    'Dueña' => [StudioRole::Owner, ['Inicio', 'Agenda', 'Alumnas', 'Vender plan', 'Pagos', 'Caja', 'Clases', 'Planes', 'Equipo', 'Reportes', 'Estudio']],
    'Recepción' => [StudioRole::FrontDesk, ['Inicio', 'Agenda', 'Alumnas', 'Vender plan', 'Pagos', 'Caja']],
    'Instructor' => [StudioRole::Instructor, ['Inicio', 'Mis clases']],
    'Alumna' => [StudioRole::Student, ['Inicio', 'Reservar', 'Mis reservas', 'Mi plan']],
]);

it('shows each role its menu, with every item but the home as «Pronto»', function (StudioRole $role, array $labels) {
    $studio = Studio::factory()->create(['name' => 'Estudio Flow']);

    $this->actingAs(User::factory()->memberOf($studio, $role)->create())
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('studio.name', 'Estudio Flow')
            ->where('studio.role', $role->value)
            ->where('studio.roleLabel', $role->label())
            ->where('studio.menu', fn ($menu) => collect($menu)->pluck('label')->all() === $labels)
            ->where('studio.menu.0.href', route('studios.home', $studio, absolute: false))
            ->where('studio.menu', fn ($menu) => collect($menu)->skip(1)->every(fn (array $item) => $item['href'] === null)));
})->with('menus');

it('only puts in a menu items the role is allowed to use', function (StudioRole $role) {
    foreach ($role->menu() as $item) {
        $permission = $item->permission();

        expect($permission === null || $role->allows($permission))
            ->toBeTrue("{$role->label()} tiene «{$item->label()}» en su menú sin el permiso {$permission?->value}");
    }
})->with(StudioRole::cases());

it('gives every menu item a distinct label', function () {
    $labels = array_map(fn (MenuItem $item): string => $item->label(), MenuItem::cases());

    expect($labels)->toHaveCount(count(array_unique($labels)));
});

it('keeps the current studio menu outside the studio', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('studio.slug', $studio->slug)
            ->where('studio.role', StudioRole::FrontDesk->value));
});

it('shares no studio with people who do not belong to one', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('studio', null));
});

it('never shares a studio the user does not belong to', function () {
    $ownStudio = Studio::factory()->create();
    $outsider = User::factory()->memberOf($ownStudio, StudioRole::Student)->create();

    $this->actingAs($outsider)
        ->get(route('studios.home', Studio::factory()->create()))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->where('studio.slug', $ownStudio->slug)
            ->missing('auth.user.memberships'));
});
