<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendStreamGiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authentication handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'gift_id' => ['required', 'integer', 'exists:gift_catalog,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'gift_id.required' => 'Please select a gift to send.',
            'gift_id.exists' => 'The selected gift does not exist.',
        ];
    }
}
