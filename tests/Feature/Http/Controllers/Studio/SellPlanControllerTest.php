<?php

use App\Actions\Plans\SellPlan;
use App\Enums\PaymentMethod;
use App\Enums\StudioRole;
use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create();
    $this->camila = User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create();
    $this->javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $this->plan = Plan::factory()->for($this->studio)->create(['name' => '8 clases al mes', 'price' => 45000]);
    $this->register = CashRegister::factory()->for($this->studio)->for($this->camila)->create();
});

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function saleForm(int $userId, int $planId, array $changes = []): array
{
    return ['user_id' => $userId, 'plan_id' => $planId, 'method' => 'cash', ...$changes];
}

it('shows the plans on sale, with the last day if sold today, and the student asked for', function () {
    Plan::factory()->for($this->studio)->archived()->create();
    PlanPurchase::factory()->for($this->studio)->for($this->javiera)->create(['name' => '4 clases', 'credits' => 4, 'credits_left' => 2]);

    $this->actingAs($this->camila)
        ->get(route('studios.sell.create', [$this->studio, 'alumna' => $this->javiera->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/sell-plan')
            ->has('plans', 1)
            ->where('plans.0.lastDay', 'viernes 27 de noviembre')
            ->where('student.name', 'Javiera Morales')
            ->where('student.plans', [['name' => '4 clases', 'creditsLeft' => 2, 'lastDay' => '27 de noviembre']])
            ->where('canChangePrice', false)
            ->where('register', ['state' => 'open', 'day' => null, 'openedAt' => '16:00'])
            ->has('methods', 3));
});

it('finds the students of the studio to sell to', function () {
    User::factory()->memberOf(Studio::factory()->create(), StudioRole::Student)->create(['name' => 'Javiera Lagos']);

    $this->actingAs($this->camila)
        ->getJson(route('studios.sell.students', [$this->studio, 'buscar' => 'javi']))
        ->assertOk()
        ->assertJsonPath('students.0.name', 'Javiera Morales')
        ->assertJsonCount(1, 'students');
});

it('sells at the list price and opens her profile', function () {
    $response = $this->actingAs($this->camila)
        ->post(route('studios.sell.store', $this->studio), saleForm($this->javiera->id, $this->plan->id, ['method' => 'transfer']));

    $purchase = $this->javiera->planPurchases()->sole();
    $response->assertRedirect(route('studios.students.show', [$this->studio, $this->javiera->memberships()->sole(), 'pestana' => 'plan']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Vendimos 8 clases al mes a Javiera Morales. Vale hasta el viernes 27 de noviembre.']);
    expect($purchase->price)->toBe(45000)
        ->and($purchase->payments()->sole())
        ->method->toBe(PaymentMethod::Transfer)
        ->cash_register_id->toBe($this->register->id);
});

it('sells only into her open register of today', function (Closure $register, string $state, ?string $day) {
    $this->register->delete();
    $register($this->studio, $this->camila);

    $this->actingAs($this->camila)
        ->get(route('studios.sell.create', $this->studio))
        ->assertInertia(fn (Assert $page) => $page->where('register', ['state' => $state, 'day' => $day, 'openedAt' => null]));
    $this->actingAs($this->camila)
        ->post(route('studios.sell.store', $this->studio), saleForm($this->javiera->id, $this->plan->id))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Abre tu caja para vender.']);

    expect(PlanPurchase::count())->toBe(0);
})->with([
    'without one' => [fn () => null, 'none', null],
    'closed today' => [fn (Studio $studio, User $person) => CashRegister::factory()->for($studio)->for($person)->closed()->create(), 'closed', null],
    'left open yesterday' => [fn (Studio $studio, User $person) => CashRegister::factory()->for($studio)->for($person)->onDaysAgo(1)->create(), 'stale', 'miércoles 28 de octubre'],
]);

it('lets only the owner change the price, always with a reason', function () {
    $route = route('studios.sell.store', $this->studio);

    $this->actingAs($this->camila)
        ->post($route, saleForm($this->javiera->id, $this->plan->id, ['price' => 40000, 'price_reason' => 'Amiga']))
        ->assertForbidden();
    $this->actingAs($this->camila)
        ->post($route, saleForm($this->javiera->id, $this->plan->id, ['price' => 45000, 'price_reason' => 'Igual']))
        ->assertRedirect();

    $carla = User::factory()->memberOf($this->studio, StudioRole::Owner)->create();
    CashRegister::factory()->for($this->studio)->for($carla)->create();
    $this->actingAs($carla)
        ->post($route, saleForm($this->javiera->id, $this->plan->id, ['price' => 0]))
        ->assertSessionHasErrors(['price_reason' => 'Escribe por qué cambia el precio.']);
    $this->actingAs($carla)
        ->post($route, saleForm($this->javiera->id, $this->plan->id, ['price' => 0, 'price_reason' => 'Regalo']))
        ->assertRedirect();

    expect($this->javiera->planPurchases()->orderBy('id')->pluck('price')->all())->toBe([45000, 0]);
});

it('sells only plans on sale to students of the studio', function () {
    $route = route('studios.sell.store', $this->studio);
    $archived = Plan::factory()->for($this->studio)->archived()->create();
    $elsewhere = Plan::factory()->create();
    $stranger = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Student)->create();

    $this->actingAs($this->camila)->post($route, saleForm($this->javiera->id, $archived->id))->assertNotFound();
    $this->actingAs($this->camila)->post($route, saleForm($this->javiera->id, $elsewhere->id))->assertNotFound();
    $this->actingAs($this->camila)->post($route, saleForm($stranger->id, $this->plan->id))->assertNotFound();
    $this->actingAs($this->camila)->post($route, saleForm($this->camila->id, $this->plan->id))->assertNotFound();
    $this->actingAs($this->camila)->post($route, saleForm($this->javiera->id, $this->plan->id, ['method' => 'webpay']))->assertSessionHasErrors('method');

    expect(PlanPurchase::count())->toBe(0);
});

it('keeps the sale for the front desk and the owner', function (StudioRole $role) {
    $this->actingAs(User::factory()->memberOf($this->studio, $role)->create())
        ->get(route('studios.sell.create', $this->studio))
        ->assertForbidden();
})->with([StudioRole::Instructor, StudioRole::Student]);

it('shows her plans and payments in her profile', function () {
    app(SellPlan::class)->handle($this->plan, $this->javiera, $this->register, PaymentMethod::Card);
    PlanPurchase::factory()->for($this->studio)->for($this->javiera)->expired()->create(['name' => 'Viejo', 'credits' => 8, 'credits_left' => 2]);
    $this->artisan('plans:expire');

    $this->actingAs($this->camila)
        ->get(route('studios.students.show', [$this->studio, $this->javiera->memberships()->sole(), 'pestana' => 'plan']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSellPlan', true)
            ->where('student.userId', $this->javiera->id)
            ->where('student.purchases', fn ($purchases) => collect($purchases)->map(fn ($purchase) => [$purchase['name'], $purchase['isValid']])->all() === [
                ['8 clases al mes', true],
                ['Viejo', false],
            ])
            ->where('student.purchases.0.payments.0', fn ($payment) => $payment['amount'] === 45000 && $payment['method'] === 'Tarjeta' && $payment['status'] === 'approved')
            ->where('student.purchases.0.listPrice', 45000)
            ->where('student.purchases.0.isCancelled', false)
            ->where('student.purchases.1.expiredCredits', 2));
});

it('keeps the receipt of a transfer at the counter, only for a transfer', function () {
    Storage::fake();
    $route = route('studios.sell.store', $this->studio);

    $this->actingAs($this->camila)
        ->post($route, saleForm($this->javiera->id, $this->plan->id, ['method' => 'transfer', 'receipt' => UploadedFile::fake()->image('comprobante.png')]))
        ->assertRedirect();
    $this->actingAs($this->camila)
        ->post($route, saleForm($this->javiera->id, $this->plan->id, ['method' => 'cash', 'receipt' => UploadedFile::fake()->image('comprobante.png')]))
        ->assertRedirect();

    [$transfer, $cash] = Payment::query()->orderBy('id')->get()->all();
    Storage::assertExists($transfer->receipt_path);
    expect($cash->receipt_path)->toBeNull()
        ->and(Storage::allFiles())->toHaveCount(1);
});
