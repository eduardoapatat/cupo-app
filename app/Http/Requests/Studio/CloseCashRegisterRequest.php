<?php

namespace App\Http\Requests\Studio;

use App\Models\CashRegister;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Close a cash register with the cash counted in the drawer. When it does
 * not match the expected cash, the note says why.
 */
class CloseCashRegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'integer', 'min:0', 'max:10000000'],
            'closing_note' => ['nullable', 'string', 'max:300'],
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
            'counted_cash.required' => __('Write the cash you counted in the drawer.'),
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $register = $this->route('cashRegister');

                if ($validator->errors()->has('counted_cash') || ! $register instanceof CashRegister || $register->isClosed()) {
                    return;
                }

                if ($this->integer('counted_cash') !== $register->expectedCash() && $this->closingNote() === null) {
                    $validator->errors()->add('closing_note', __('The cash does not match: write why.'));
                }
            },
        ];
    }

    public function closingNote(): ?string
    {
        return $this->filled('closing_note') ? trim($this->string('closing_note')->toString()) : null;
    }
}
