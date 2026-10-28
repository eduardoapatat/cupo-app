<?php

namespace App\Models;

use App\Enums\ClassSessionStatus;
use Database\Factories\ClassSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A concrete class, such as «pilates mat, Tuesday 4 at 19:00», usually
 * generated from a schedule slot. Times are stored in UTC.
 *
 * @property int $id
 * @property int $studio_id
 * @property int $class_type_id
 * @property int|null $schedule_slot_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $capacity
 * @property int $booked_count
 * @property int|null $instructor_id
 * @property ClassSessionStatus $status
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_id
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['class_type_id', 'schedule_slot_id', 'starts_at', 'ends_at', 'capacity', 'instructor_id'])]
class ClassSession extends Model
{
    /** @use HasFactory<ClassSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'booked_count' => 'integer',
            'status' => ClassSessionStatus::class,
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<ClassType, $this>
     */
    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    /**
     * @return BelongsTo<ScheduleSlot, $this>
     */
    public function scheduleSlot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sessions that still happen, from now on.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', ClassSessionStatus::Scheduled)->where('starts_at', '>', now());
    }
}
