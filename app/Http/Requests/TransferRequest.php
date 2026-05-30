<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferRequest extends FormRequest
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
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:100000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_id.not_in' => 'You cannot transfer coins to yourself.',
            'recipient_id.exists' => 'The recipient user does not exist.',
            'amount.min' => 'The minimum transfer amount is 1 ZaletCoin.',
            'amount.max' => 'The maximum transfer amount is 100,000 ZaletCoins.',
        ];
    }
}

