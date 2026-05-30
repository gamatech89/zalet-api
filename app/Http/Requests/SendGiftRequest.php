<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_id' => [
                'required',
                'uuid',
                'exists:users,id',
                Rule::notIn([$this->user()->id]),
            ],
            'gift_id' => [
                'required',
                'integer',
                'exists:gift_catalog,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_id.not_in' => 'You cannot send a gift to yourself.',
            'recipient_id.exists' => 'The recipient user does not exist.',
            'gift_id.exists' => 'The selected gift does not exist.',
        ];
    }
}

