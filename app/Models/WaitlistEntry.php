<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use Carbon\CarbonImmutable;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person waiting for a spot in a full session. Use JoinWaitlist and
 * LeaveWaitlist to change it; CancelBooking promotes the first one.
 *
 * @property int $id
 * @property int $class_session_id
 * @property int $user_id
 * @property int|null $added_by_id
 * @property WaitlistStatus $status
 * @property int|null $booking_id
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['class_session_id', 'user_id', 'added_by_id'])]
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory;

    /**
     * The database default, so an entry just created knows it too.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'waiting',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WaitlistStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Entries still waiting, first come first served.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWaiting(Builder $query): void
    {
        $query->where('status', WaitlistStatus::Waiting)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Her place in line: 1 for the first one waiting.
     */
    public function position(): int
    {
        return self::query()
            ->where('class_session_id', $this->class_session_id)
            ->where('status', WaitlistStatus::Waiting)
            ->where(fn (Builder $query) => $query
                ->where('created_at', '<', $this->created_at)
                ->orWhere(fn (Builder $query) => $query->where('created_at', $this->created_at)->where('id', '<', $this->id)))
            ->count() + 1;
    }
}
