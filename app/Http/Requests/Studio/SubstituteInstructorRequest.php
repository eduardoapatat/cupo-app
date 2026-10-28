<?php

namespace App\Http\Requests\Studio;

use App\Enums\StudioRole;
use App\Models\Studio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Someone else teaches one session, for example when the instructor is
 * sick. Like in the schedule, it is an instructor or the owner.
 */
class SubstituteInstructorRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $studio = $this->route('studio');

        assert($studio instanceof Studio);

        return [
            'instructor_id' => [
                'required',
                'integer',
                Rule::exists('memberships', 'user_id')->where(fn (Builder $query) => $query
                    ->where('studio_id', $studio->id)
                    ->whereIn('role', [StudioRole::Owner->value, StudioRole::Instructor->value])),
            ],
        ];
    }
}
