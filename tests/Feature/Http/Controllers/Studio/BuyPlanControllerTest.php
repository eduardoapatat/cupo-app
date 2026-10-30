<?php

use App\Actions\Payments\ReviewPayment;
use App\Actions\Payments\SubmitTransferReceipt;
use App\Enums\PaymentStatus;
use App\Enums\StudioRole;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
    Storage::fake();
    $this->studio = Studio::factory()->create(['transfer_details' => "Banco del Ejemplo\nCuenta 000123456"]);
    $this->javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create();
    $this->camila = User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create(['name' => 'Camila Soto']);
    $this->plan = Plan::factory()->for($this->studio)->create(['name' => '8 clases al mes', 'price' => 45000, 'validity_days' => 30]);
});

it('shows the plans on sale and where to transfer', function () {
    Plan::factory()->for($this->studio)->archived()->create();

    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.buy', $this->studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/buy-plan')
            ->has('plans', 1)
            ->where('plans.0.lastDay', 'viernes 27 de noviembre')
            ->where('transferDetails', "Banco del Ejemplo\nCuenta 000123456"));
});

it('takes her receipt and tells her the plan starts once it is checked', function () {
    $this->actingAs($this->javiera)
        ->post(route('studios.my-plan.buy.store', $this->studio), [
            'plan_id' => $this->plan->id,
            'receipt' => UploadedFile::fake()->create('comprobante.pdf', 300, 'application/pdf'),
        ])
        ->assertRedirect(route('studios.my-plan.index', $this->studio))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Recibimos tu comprobante. Tu plan parte cuando el estudio lo valide.']);

    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::ToReview);
    Storage::assertExists($payment->receipt_path);

    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingPayment.planName', '8 clases al mes')
            ->where('pendingPayment.amount', 45000)
            ->where('rejectedPayment', null)
            ->where('canBuy', false));
});

it('accepts only a photo or a PDF of up to 5 MB', function (Closure $receipt, string $message) {
    $this->actingAs($this->javiera)
        ->post(route('studios.my-plan.buy.store', $this->studio), ['plan_id' => $this->plan->id, 'receipt' => $receipt()])
        ->assertSessionHasErrors(['receipt' => $message]);

    expect(Payment::count())->toBe(0);
})->with([
    'a text file' => [fn () => UploadedFile::fake()->create('comprobante.txt', 10, 'text/plain'), 'El comprobante debe ser una foto (JPG o PNG) o un PDF.'],
    'too heavy' => [fn () => UploadedFile::fake()->image('comprobante.png')->size(6000), 'El comprobante puede pesar hasta 5 MB.'],
    'missing' => [fn () => null, 'Sube el comprobante de tu transferencia.'],
]);

it('shows her why it was rejected, so she can send another one', function () {
    $payment = app(SubmitTransferReceipt::class)->handle($this->plan, $this->javiera, UploadedFile::fake()->image('comprobante.png'));
    app(ReviewPayment::class)->reject($payment, $this->camila, 'El monto no coincide');

    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingPayment', null)
            ->where('rejectedPayment', ['planName' => '8 clases al mes', 'reason' => 'El monto no coincide'])
            ->where('canBuy', true));
});

it('offers no purchase from the app while the studio has no transfer details', function () {
    $this->studio->update(['transfer_details' => null]);

    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page->where('canBuy', false));
    $this->actingAs($this->javiera)
        ->post(route('studios.my-plan.buy.store', $this->studio), ['plan_id' => $this->plan->id, 'receipt' => UploadedFile::fake()->image('comprobante.png')])
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'El estudio todavía no recibe transferencias desde la app.']);

    expect(Payment::count())->toBe(0);
});

it('lets only students buy, and only plans of their studio', function () {
    $this->actingAs($this->camila)
        ->get(route('studios.my-plan.buy', $this->studio))
        ->assertForbidden();
    $this->actingAs($this->javiera)
        ->post(route('studios.my-plan.buy.store', $this->studio), ['plan_id' => Plan::factory()->create()->id, 'receipt' => UploadedFile::fake()->image('comprobante.png')])
        ->assertNotFound();
});
