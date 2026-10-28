<?php

namespace App\Http\Requests\Studio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mark that someone missed a class, or undo it.
 */
class AttendanceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'no_show' => ['required', 'boolean'],
        ];
    }

    public function noShow(): bool
    {
        return $this->boolean('no_show');
    }
}
