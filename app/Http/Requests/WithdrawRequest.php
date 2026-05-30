<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:500',      // Minimum 500 ZaletCoins
                'max:100000',   // Maximum 100,000 ZaletCoins
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'The minimum withdrawal amount is 500 ZaletCoins.',
            'amount.max' => 'The maximum withdrawal amount is 100,000 ZaletCoins.',
        ];
    }
}
