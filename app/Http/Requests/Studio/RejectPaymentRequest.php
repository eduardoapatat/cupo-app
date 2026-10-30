<?php

namespace App\Http\Requests\Studio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reject a transfer receipt: always saying why, since the student reads it.
 */
class RejectPaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:200', 'regex:/[\pL\pN]/u'],
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
            'reason.required' => __('Write why you reject it: she will read it.'),
            'reason.regex' => __('Write why you reject it: she will read it.'),
        ];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
