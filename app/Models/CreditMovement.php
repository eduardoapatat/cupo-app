<?php

namespace App\Models;

use App\Enums\CreditMovementReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One class taken from (-1) or returned to (+1) a plan.
 *
 * @property int $id
 * @property int $plan_purchase_id
 * @property int|null $booking_id
 * @property int $delta
 * @property CreditMovementReason $reason
 * @property int|null $created_by_id
 * @property CarbonImmutable $created_at
 */
#[Fillable(['plan_purchase_id', 'booking_id', 'delta', 'reason', 'created_by_id', 'created_at'])]
class CreditMovement extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'reason' => CreditMovementReason::class,
        ];
    }

    /**
     * @return BelongsTo<PlanPurchase, $this>
     */
    public function planPurchase(): BelongsTo
    {
        return $this->belongsTo(PlanPurchase::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
