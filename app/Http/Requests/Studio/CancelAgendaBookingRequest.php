<?php

namespace App\Http\Requests\Studio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelling someone's booking from the agenda. Returning the class after
 * the window (bookings.override) needs a reason.
 */
class CancelAgendaBookingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'return_class' => ['boolean'],
            'reason' => ['nullable', 'required_if_accepted:return_class', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_if_accepted' => __('Write why you return the class.'),
        ];
    }

    public function returnsClass(): bool
    {
        return $this->boolean('return_class');
    }

    public function reason(): ?string
    {
        $reason = trim($this->string('reason')->toString());

        return $reason === '' ? null : $reason;
    }
}
