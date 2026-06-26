<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
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
                'min:10',
                'max:500000',   // Maximum 500,000 RSD
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'The minimum deposit amount is 10 RSD.',
            'amount.max' => 'The maximum deposit amount is 500,000 RSD.',
        ];
    }
}
