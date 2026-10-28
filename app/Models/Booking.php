<?php

namespace App\Models;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person's place in a session. Use BookSession and CancelBooking to
 * change it: they keep the session's booked_count in step.
 *
 * @property int $id
 * @property int $class_session_id
 * @property int $user_id
 * @property BookingKind $kind
 * @property int|null $booked_by_id
 * @property BookingStatus $status
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_id
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['class_session_id', 'user_id', 'kind', 'booked_by_id'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * The database defaults, so a booking just created knows them too.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'kind' => 'student',
        'status' => 'confirmed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BookingKind::class,
            'status' => BookingStatus::class,
            'cancelled_at' => 'datetime',
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
     * Bookings that still hold their place: not cancelled.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNotIn('status', [BookingStatus::CancelledOnTime, BookingStatus::CancelledLate]);
    }
}
