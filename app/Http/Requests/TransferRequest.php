<?php

namespace App\Http\Requests;

use App\Models\AppSetting;
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
                'min:' . AppSetting::get('transfer_min_amount', 10),
                'max:100000',
            ],
        ];
    }

    public function messages(): array
    {
        $min = AppSetting::get('transfer_min_amount', 10);
        return [
            'recipient_id.not_in' => 'You cannot transfer coins to yourself.',
            'recipient_id.exists' => 'The recipient user does not exist.',
            'amount.min' => "Minimalni iznos za slanje je {$min} ZC.",
            'amount.max' => 'The maximum transfer amount is 100,000 ZaletCoins.',
        ];
    }
}

