<?php

namespace App\Http\Requests\Studio;

/**
 * A transfer receipt: a photo (JPG or PNG) or a PDF of up to 5 MB, for
 * the app and for the counter.
 */
class ReceiptRules
{
    /**
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'receipt.file' => __('The receipt must be a photo or a PDF.'),
            'receipt.mimes' => __('The receipt must be a photo (JPG or PNG) or a PDF.'),
            'receipt.max' => __('The receipt can weigh up to 5 MB.'),
        ];
    }
}
