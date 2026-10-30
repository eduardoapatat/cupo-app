<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Studio;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The receipt of a transfer, from the private disk, for whoever reviews
 * payments and for the student who sent it.
 */
class PaymentReceiptController extends Controller
{
    #[Authorize('viewReceipt', 'payment')]
    public function show(Studio $studio, Payment $payment): StreamedResponse
    {
        abort_if($payment->receipt_path === null || ! Storage::exists($payment->receipt_path), 404);

        return Storage::response($payment->receipt_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
