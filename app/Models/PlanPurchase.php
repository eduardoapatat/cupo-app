<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PlanPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A plan a student bought, with a copy of the plan as it was sold. Use
 * SellPlan to create it and BookSession to take its classes.
 *
 * @property int $id
 * @property int $studio_id
 * @property int $user_id
 * @property int|null $plan_id
 * @property string $name
 * @property int $price
 * @property int|null $credits
 * @property int|null $credits_left
 * @property int $validity_days
 * @property int|null $daily_limit
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $expires_at The start of the day after its last day, in the studio's time zone
 * @property int|null $sold_by_id
 * @property string|null $price_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['studio_id', 'user_id', 'plan_id', 'name', 'price', 'credits', 'credits_left', 'validity_days', 'daily_limit', 'starts_on', 'expires_at', 'sold_by_id', 'price_reason'])]
class PlanPurchase extends Model
{
    /** @use HasFactory<PlanPurchaseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'credits' => 'integer',
            'credits_left' => 'integer',
            'validity_days' => 'integer',
            'daily_limit' => 'integer',
            'starts_on' => 'date',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isUnlimited(): bool
    {
        return $this->credits === null;
    }

    /**
     * Its last valid day, in the studio's time zone, for showing it.
     */
    public function lastDay(): CarbonImmutable
    {
        return $this->expires_at->timezone(config()->string('studio.timezone'))->subDay()->startOfDay();
    }

    /**
     * Purchases still valid now.
     *
     * @param  Builder<self>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }
}
