<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money a student paid for a plan, in CLP as an integer.
 *
 * @property int $id
 * @property int $studio_id
 * @property int $plan_purchase_id
 * @property int|null $cash_register_id The register it went into, when taken at the counter
 * @property int $user_id
 * @property int $amount
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property int|null $received_by_id
 * @property int|null $voided_by_id
 * @property CarbonImmutable|null $voided_at
 * @property string|null $status_reason Why it was voided
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['studio_id', 'plan_purchase_id', 'cash_register_id', 'user_id', 'amount', 'method', 'received_by_id'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * The database default, so a payment just created knows it too.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'approved',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'voided_at' => 'datetime',
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
     * @return BelongsTo<PlanPurchase, $this>
     */
    public function planPurchase(): BelongsTo
    {
        return $this->belongsTo(PlanPurchase::class);
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_id');
    }

    /**
     * Whether it went into a register that is already closed.
     */
    public function isInAClosedRegister(): bool
    {
        return $this->cashRegister?->isClosed() ?? false;
    }
}
