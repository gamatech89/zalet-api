<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:100'],
            'is_group' => ['boolean'],
            'is_public' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'At least one user is required to start a conversation.',
            'user_ids.min' => 'At least one user is required to start a conversation.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
        ];
    }
}
