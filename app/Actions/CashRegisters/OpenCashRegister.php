<?php

namespace App\Actions\CashRegisters;

use App\Models\CashRegister;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Open today's cash register of a person of the team, with the cash she
 * starts with. One per person and day: a register left open on another day
 * is closed first, counting its cash.
 */
class OpenCashRegister
{
    /**
     * @throws CashRegisterRejected when she already has one open or already closed today's.
     */
    public function handle(Studio $studio, User $person, int $openingCash): CashRegister
    {
        $this->ensureCanOpen($studio, $person);

        try {
            return CashRegister::create([
                'studio_id' => $studio->id,
                'user_id' => $person->id,
                'business_date' => now(config()->string('studio.timezone'))->toDateString(),
                'opening_cash' => $openingCash,
                'opened_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Opened from two tabs at once: the other one won.
            throw new CashRegisterRejected(__('Your cash register is already open.'));
        }
    }

    private function ensureCanOpen(Studio $studio, User $person): void
    {
        $open = $person->cashRegisters()->where('studio_id', $studio->id)->open()->first();

        if ($open?->isToday()) {
            throw new CashRegisterRejected(__('Your cash register is already open.'));
        }

        if ($open !== null) {
            throw new CashRegisterRejected(__('Close your cash register of :day first.', [
                'day' => $open->business_date->translatedFormat('l j \d\e F'),
            ]));
        }

        $today = now(config()->string('studio.timezone'))->toDateString();

        if ($person->cashRegisters()->where('studio_id', $studio->id)->whereDate('business_date', $today)->exists()) {
            throw new CashRegisterRejected(__('You already closed your cash register today.'));
        }
    }
}
