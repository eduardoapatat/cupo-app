<?php

namespace App\Http\Requests\Studio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * A student buys a plan by transfer: the plan and a photo or PDF of the
 * receipt. The controller checks the plan belongs to the studio.
 */
class BuyPlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer'],
            'receipt' => ['required', ...ReceiptRules::rules()],
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
            'plan_id.required' => __('Choose the plan.'),
            'receipt.required' => __('Upload the receipt of your transfer.'),
            ...ReceiptRules::messages(),
        ];
    }

    public function receipt(): UploadedFile
    {
        $receipt = $this->file('receipt');

        return $receipt instanceof UploadedFile ? $receipt : abort(422);
    }
}
