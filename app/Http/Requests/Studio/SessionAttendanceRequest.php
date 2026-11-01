<?php

namespace App\Http\Requests\Studio;

use App\Models\ClassSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The bookings of a class that came: only bookings of that class that still
 * hold their place.
 */
class SessionAttendanceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $session = $this->route('classSession');
        $sessionId = $session instanceof ClassSession ? $session->id : 0;

        return [
            'attended' => ['present', 'array'],
            'attended.*' => [
                'integer',
                'distinct',
                Rule::exists('bookings', 'id')
                    ->where('class_session_id', $sessionId)
                    ->whereNotIn('status', ['cancelled_on_time', 'cancelled_late']),
            ],
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
            'attended.*.exists' => __('Someone in the list is not in this class.'),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function attendedIds(): array
    {
        return array_map('intval', $this->array('attended'));
    }
}
