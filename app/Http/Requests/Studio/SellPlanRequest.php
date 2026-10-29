<?php

namespace App\Http\Requests\Studio;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sell a plan at the counter. The controller checks the student and the
 * plan belong to the studio, and that only the owner changes the price.
 */
class SellPlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'price_reason' => ['required_with:price', 'nullable', 'string', 'max:200'],
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
            'user_id.required' => __('Choose the student.'),
            'plan_id.required' => __('Choose the plan.'),
            'price_reason.required_with' => __('Write why the price changes.'),
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return $this->enum('method', PaymentMethod::class) ?? PaymentMethod::Cash;
    }

    /**
     * The price asked for, or null for the list price.
     */
    public function price(): ?int
    {
        return $this->filled('price') ? $this->integer('price') : null;
    }

    public function priceReason(): ?string
    {
        return $this->filled('price_reason') ? trim($this->string('price_reason')->toString()) : null;
    }
}
