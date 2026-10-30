<?php

namespace App\Actions\Payments;

use App\Models\Studio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Keep a transfer receipt on the default disk, which is private: it holds
 * the student's bank details, and PaymentReceiptController serves it only
 * to whoever may see it.
 */
class StoreReceipt
{
    /**
     * @return string Its path on the disk.
     *
     * @throws PaymentRejected when it could not be saved.
     */
    public function handle(Studio $studio, UploadedFile $receipt): string
    {
        $path = $receipt->storeAs("receipts/{$studio->id}", Str::uuid().'.'.$receipt->extension());

        if ($path === false) {
            throw new PaymentRejected(__('We could not save the receipt. Try again.'));
        }

        return $path;
    }
}
