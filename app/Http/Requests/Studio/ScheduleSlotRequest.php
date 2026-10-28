<?php

namespace App\Http\Requests\Studio;

use App\Enums\StudioRole;
use App\Enums\Weekday;
use App\Models\ClassType;
use App\Models\ScheduleSlot;
use App\Models\Studio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A class of the weekly schedule. The studio has one room, so two classes
 * of the same day cannot overlap.
 */
class ScheduleSlotRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. The class type
     * must be one the studio still teaches, and the instructor someone who
     * teaches there (an instructor or the owner).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $studio = $this->studio();

        return [
            'weekday' => ['required', Rule::enum(Weekday::class)],
            'starts_at' => ['required', 'date_format:H:i'],
            'class_type_id' => [
                'required',
                'integer',
                Rule::exists('class_types', 'id')->where(fn (Builder $query) => $query->where('studio_id', $studio->id)->where('is_active', true)),
            ],
            'instructor_id' => [
                'nullable',
                'integer',
                Rule::exists('memberships', 'user_id')->where(fn (Builder $query) => $query
                    ->where('studio_id', $studio->id)
                    ->whereIn('role', [StudioRole::Owner->value, StudioRole::Instructor->value])),
            ],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $overlapping = $this->overlappingSlot();

                if ($overlapping !== null) {
                    $validator->errors()->add('starts_at', __('It overlaps with :class at :time. The studio has a single room.', [
                        'class' => $overlapping->classType->name,
                        'time' => substr($overlapping->starts_at, 0, 5),
                    ]));
                }
            },
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'class_type_id.exists' => __('Choose one of the studio\'s classes.'),
            'instructor_id.exists' => __('Choose someone who teaches in the studio.'),
        ];
    }

    /**
     * @return array{weekday: int, starts_at: string, class_type_id: int, instructor_id: int|null}
     */
    public function slotData(): array
    {
        return [
            'weekday' => $this->integer('weekday'),
            'starts_at' => $this->string('starts_at')->value().':00',
            'class_type_id' => $this->integer('class_type_id'),
            'instructor_id' => $this->filled('instructor_id') ? $this->integer('instructor_id') : null,
        ];
    }

    /**
     * Another class of the same day whose time range crosses this one.
     */
    private function overlappingSlot(): ?ScheduleSlot
    {
        $data = $this->slotData();
        $classType = ClassType::findOrFail($data['class_type_id']);
        $start = $this->minutes($data['starts_at']);
        $end = $start + $classType->duration_minutes;
        $sameDay = $this->studio()->scheduleSlots()->with('classType')->where('weekday', $data['weekday']);
        $editing = $this->route('scheduleSlot');

        if ($editing instanceof ScheduleSlot) {
            $sameDay->whereKeyNot($editing->id);
        }

        return $sameDay->get()
            ->first(function (ScheduleSlot $slot) use ($start, $end): bool {
                $otherStart = $this->minutes($slot->starts_at);

                return $otherStart < $end && $start < $otherStart + $slot->classType->duration_minutes;
            });
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    private function studio(): Studio
    {
        $studio = $this->route('studio');

        assert($studio instanceof Studio);

        return $studio;
    }
}
