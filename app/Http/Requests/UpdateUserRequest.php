<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by admin middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::in(['user', 'creator', 'admin'])],
            'is_active' => ['sometimes', 'boolean'],
            'is_legacy_founder' => ['sometimes', 'boolean'],
            'storage_limit_mb' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
