<?php

use App\Actions\Payments\PaymentRejected;
use App\Actions\Payments\ReviewPayment;
use App\Actions\Payments\SubmitTransferReceipt;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Plan;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Thursday, October 29, in the afternoon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
    Storage::fake();
    $this->studio = Studio::factory()->create(['transfer_details' => 'Banco del Ejemplo, cuenta 000123456']);
    $this->plan = Plan::factory()->for($this->studio)->create(['name' => '8 clases al mes', 'price' => 45000, 'credits' => 8, 'validity_days' => 30]);
    $this->rosa = User::factory()->create();
    $this->camila = User::factory()->create(['name' => 'Camila']);
});

function receiptFile(): UploadedFile
{
    return UploadedFile::fake()->image('comprobante.png');
}

it('keeps the receipt and waits to be reviewed, with no plan yet', function () {
    $payment = app(SubmitTransferReceipt::class)->handle($this->plan, $this->rosa, receiptFile());

    expect($payment->refresh())
        ->status->toBe(PaymentStatus::ToReview)
        ->method->toBe(PaymentMethod::Transfer)
        ->amount->toBe(45000)
        ->plan_id->toBe($this->plan->id)
        ->plan_purchase_id->toBeNull()
        ->cash_register_id->toBeNull()
        ->paid_at->toBeNull()
        ->and($this->rosa->planPurchases()->exists())->toBeFalse();
    Storage::assertExists($payment->receipt_path);
});

it('takes one receipt at a time, only for a plan on sale, in a studio that takes transfers', function () {
    app(SubmitTransferReceipt::class)->handle($this->plan, $this->rosa, receiptFile());

    expect(fn () => app(SubmitTransferReceipt::class)->handle($this->plan, $this->rosa, receiptFile()))
        ->toThrow(PaymentRejected::class, 'Ya tienes un comprobante por validar.');
    expect(fn () => app(SubmitTransferReceipt::class)->handle(Plan::factory()->for($this->studio)->archived()->create(), User::factory()->create(), receiptFile()))
        ->toThrow(PaymentRejected::class, 'Este plan ya no está a la venta.');
    expect(fn () => app(SubmitTransferReceipt::class)->handle(Plan::factory()->create(), User::factory()->create(), receiptFile()))
        ->toThrow(PaymentRejected::class, 'El estudio todavía no recibe transferencias desde la app.');
});

it('starts her plan from the day it is approved, at the price she paid', function () {
    $payment = app(SubmitTransferReceipt::class)->handle($this->plan, $this->rosa, receiptFile());
    $this->plan->update(['price' => 50000]);
    $this->travelTo(CarbonImmutable::parse('2026-10-30 09:30', 'America/Santiago'));

    app(ReviewPayment::class)->approve($payment, $this->camila);

    $purchase = $this->rosa->planPurchases()->sole();
    expect($payment->refresh())
        ->status->toBe(PaymentStatus::Approved)
        ->plan_purchase_id->toBe($purchase->id)
        ->reviewed_by_id->toBe($this->camila->id)
        ->received_by_id->toBe($this->camila->id)
        ->paid_at->toEqual(now())
        ->and($purchase)
        ->price->toBe(45000)
        ->list_price->toBe(50000)
        ->credits_left->toBe(8)
        ->price_reason->toBe('El precio de cuando envió el comprobante.')
        ->and($purchase->starts_on->toDateString())->toBe('2026-10-30')
        ->and($purchase->lastDay()->toDateString())->toBe('2026-11-28');
});

it('rejects saying why, lets her send another, and reviews each receipt once', function () {
    $payment = app(SubmitTransferReceipt::class)->handle($this->plan, $this->rosa, receiptFile());

    app(ReviewPayment::class)->reject($payment, $this->camila, 'El monto no coincide');

    expect($payment->refresh())
        ->status->toBe(PaymentStatus::Rejected)
        ->status_reason->toBe('El monto no coincide')
        ->and($this->rosa->planPurchases()->exists())->toBeFalse();
    expect(fn () => app(ReviewPayment::class)->approve($payment, User::factory()->create()))
        ->toThrow(PaymentRejected::class, 'Camila ya lo revisó.');
    expect(app(SubmitTransferReceipt::class)->handle($this->plan, $this->rosa, receiptFile())->status)
        ->toBe(PaymentStatus::ToReview);
});
