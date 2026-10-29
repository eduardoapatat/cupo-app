<?php

use App\Enums\StudioRole;
use App\Models\Plan;
use App\Models\Studio;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->studio = Studio::factory()->create();
    $this->carla = User::factory()->memberOf($this->studio, StudioRole::Owner)->create();
});

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function planForm(array $changes = []): array
{
    return [
        'name' => '8 clases al mes',
        'price' => 45000,
        'unlimited' => false,
        'credits' => 8,
        'validity_days' => 30,
        'daily_limit' => null,
        ...$changes,
    ];
}

it('lists the plans on sale first, by price, and the archived ones last', function () {
    Plan::factory()->for($this->studio)->archived()->create(['name' => 'Clase suelta', 'price' => 8000]);
    $unlimited = Plan::factory()->for($this->studio)->unlimited()->create(['name' => 'Ilimitado', 'price' => 65000]);
    Plan::factory()->for($this->studio)->create(['name' => '4 clases', 'price' => 28000, 'credits' => 4]);
    Plan::factory()->for(Studio::factory()->create())->create(['name' => 'De otro estudio']);

    $this->actingAs($this->carla)
        ->get(route('studios.plans.show', [$this->studio, $unlimited]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/plans')
            ->where('plans', fn ($plans) => collect($plans)->pluck('name')->all() === ['4 clases', 'Ilimitado', 'Clase suelta'])
            ->where('plan', [
                'id' => $unlimited->id,
                'name' => 'Ilimitado',
                'price' => 65000,
                'credits' => null,
                'validityDays' => 30,
                'dailyLimit' => 1,
                'isArchived' => false,
            ])
            ->where('defaultDailyLimit', 1));
});

it('adds a pack plan and an unlimited one', function () {
    $this->actingAs($this->carla)
        ->post(route('studios.plans.store', $this->studio), planForm(['daily_limit' => 2]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Agregamos el plan 8 clases al mes.']);
    $this->actingAs($this->carla)
        ->post(route('studios.plans.store', $this->studio), planForm(['name' => 'Ilimitado', 'unlimited' => true, 'credits' => 99, 'daily_limit' => 1]));

    expect($this->studio->plans()->orderBy('id')->get()->map(fn (Plan $plan) => [$plan->name, $plan->credits, $plan->daily_limit])->all())->toBe([
        ['8 clases al mes', 8, 2],
        ['Ilimitado', null, 1],
    ]);
});

it('validates the plan, and keeps one name per plan on sale', function () {
    Plan::factory()->for($this->studio)->create(['name' => '8 clases al mes']);
    Plan::factory()->for($this->studio)->archived()->create(['name' => 'Clase suelta']);

    $this->actingAs($this->carla)
        ->post(route('studios.plans.store', $this->studio), planForm(['name' => '8 CLASES al mes', 'price' => -1, 'credits' => 0, 'validity_days' => 0]))
        ->assertSessionHasErrors([
            'name' => 'El estudio ya vende un plan con ese nombre.',
            'price' => 'El campo precio debe ser al menos 0.',
            'credits' => 'El campo clases debe ser al menos 1.',
            'validity_days' => 'El campo vigencia debe ser al menos 1.',
        ]);
    $this->actingAs($this->carla)
        ->post(route('studios.plans.store', $this->studio), planForm(['name' => 'Clase suelta']))
        ->assertSessionHasNoErrors();
});

it('saves a change to a plan', function () {
    $plan = Plan::factory()->for($this->studio)->create(['name' => '8 clases al mes']);

    $this->actingAs($this->carla)
        ->put(route('studios.plans.update', [$this->studio, $plan]), planForm(['price' => 48000]))
        ->assertRedirect(route('studios.plans.show', [$this->studio, $plan]));

    expect($plan->fresh()->price)->toBe(48000);
});

it('archives a plan and puts it back on sale, unless its name is taken', function () {
    $plan = Plan::factory()->for($this->studio)->create(['name' => 'Clase suelta']);

    $this->actingAs($this->carla)->post(route('studios.plans.archive.store', [$this->studio, $plan]));
    expect($plan->fresh()->isArchived())->toBeTrue();

    $this->actingAs($this->carla)->delete(route('studios.plans.archive.destroy', [$this->studio, $plan]));
    expect($plan->fresh()->isArchived())->toBeFalse();

    $this->actingAs($this->carla)->post(route('studios.plans.archive.store', [$this->studio, $plan]));
    Plan::factory()->for($this->studio)->create(['name' => 'Clase suelta']);
    $this->actingAs($this->carla)
        ->delete(route('studios.plans.archive.destroy', [$this->studio, $plan]))
        ->assertSessionHasErrors(['name' => 'El estudio ya vende un plan con ese nombre.']);
    expect($plan->fresh()->isArchived())->toBeTrue();
});

it('keeps plans for the owner and hides the plans of other studios', function () {
    $plan = Plan::factory()->for($this->studio)->create();

    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create())
        ->get(route('studios.plans.index', $this->studio))
        ->assertForbidden();
    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create())
        ->post(route('studios.plans.store', $this->studio), planForm())
        ->assertForbidden();

    $other = Studio::factory()->create();
    $this->actingAs(User::factory()->memberOf($other, StudioRole::Owner)->create())
        ->put(route('studios.plans.update', [$other, $plan]), planForm())
        ->assertNotFound();
});
