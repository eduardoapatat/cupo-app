<?php

namespace App\Http\Requests\Studio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Where students transfer to. Empty turns off buying from the app.
 */
class TransferDetailsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transfer_details' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function transferDetails(): ?string
    {
        $details = trim($this->string('transfer_details')->toString());

        return $details === '' ? null : $details;
    }
}
