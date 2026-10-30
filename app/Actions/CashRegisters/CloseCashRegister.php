<?php

namespace App\Actions\CashRegisters;

use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;

/**
 * Close a cash register with the cash counted in the drawer. Its totals
 * are counted with the register locked, after any sale in progress ends,
 * and frozen from then on (see
 * docs/decisions/0009-la-caja-cerrada-no-recibe-pagos.md).
 */
class CloseCashRegister
{
    /**
     * @param  string|null  $note  Why the counted cash is not the expected one; required then.
     *
     * @throws CashRegisterRejected when it is already closed or the note is missing.
     */
    public function handle(CashRegister $register, int $countedCash, ?string $note = null): CashRegister
    {
        return DB::transaction(function () use ($register, $countedCash, $note): CashRegister {
            // Waits for a sale in progress, which holds the register FOR SHARE.
            $locked = CashRegister::query()->whereKey($register->id)->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                throw new CashRegisterRejected(__('This cash register is already closed.'));
            }

            $totals = $locked->totals();
            $expected = $locked->opening_cash + $totals['cash'];

            if ($countedCash !== $expected && ($note === null || trim($note) === '')) {
                throw new CashRegisterRejected(__('The cash does not match: write why.'));
            }

            $locked->forceFill([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'cash_total' => $totals['cash'],
                'card_total' => $totals['card'],
                'transfer_total' => $totals['transfer'],
                'closing_note' => $note === null || trim($note) === '' ? null : trim($note),
            ])->save();

            return $locked;
        });
    }
}
