<?php

namespace App\Http\Requests\Studio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The studio cancels a class, always saying why.
 */
class CancelSessionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:200'],
        ];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
