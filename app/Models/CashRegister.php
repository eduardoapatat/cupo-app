<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CashRegisterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The cash register of one person of the team on one day. Use
 * OpenCashRegister and CloseCashRegister to change it; once closed, its
 * totals are frozen and never change.
 *
 * @property int $id
 * @property int $studio_id
 * @property int|null $user_id
 * @property CarbonImmutable $business_date The day it belongs to, in the studio's time zone
 * @property int $opening_cash
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $expected_cash
 * @property int|null $counted_cash
 * @property int|null $cash_total
 * @property int|null $card_total
 * @property int|null $transfer_total
 * @property string|null $closing_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['studio_id', 'user_id', 'business_date', 'opening_cash', 'opened_at'])]
class CashRegister extends Model
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opening_cash' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
            'cash_total' => 'integer',
            'card_total' => 'integer',
            'transfer_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Studio, $this>
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Whether it is still the register of today, in the studio's time zone.
     */
    public function isToday(): bool
    {
        return $this->business_date->toDateString() === now(config()->string('studio.timezone'))->toDateString();
    }

    /**
     * Registers still open.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    /**
     * Registers of today, in the studio's time zone.
     *
     * @param  Builder<self>  $query
     */
    public function scopeToday(Builder $query): void
    {
        $query->whereDate('business_date', now(config()->string('studio.timezone'))->toDateString());
    }

    /**
     * What it took in per method: frozen when it was closed, counted from
     * its approved payments while it is open.
     *
     * @return array{cash: int, card: int, transfer: int}
     */
    public function totals(): array
    {
        if ($this->isClosed()) {
            return [
                'cash' => (int) $this->cash_total,
                'card' => (int) $this->card_total,
                'transfer' => (int) $this->transfer_total,
            ];
        }

        $sums = $this->payments()
            ->where('status', PaymentStatus::Approved)
            ->groupBy('method')
            ->selectRaw('method, sum(amount) as total')
            ->pluck('total', 'method');

        return [
            'cash' => (int) ($sums[PaymentMethod::Cash->value] ?? 0),
            'card' => (int) ($sums[PaymentMethod::Card->value] ?? 0),
            'transfer' => (int) ($sums[PaymentMethod::Transfer->value] ?? 0),
        ];
    }

    /**
     * The cash that should be in the drawer: what it started with plus the
     * approved cash payments.
     */
    public function expectedCash(): int
    {
        return $this->isClosed()
            ? (int) $this->expected_cash
            : $this->opening_cash + $this->totals()['cash'];
    }

    /**
     * Counted minus expected: negative when cash is missing. Null while open.
     */
    public function difference(): ?int
    {
        return $this->isClosed() ? (int) $this->counted_cash - (int) $this->expected_cash : null;
    }
}
