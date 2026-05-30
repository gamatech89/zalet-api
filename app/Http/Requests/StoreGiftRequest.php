<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin middleware handles auth
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'coin_price' => 'required|integer|min:1',
            'category_id' => 'nullable|exists:gift_categories,id',
            'icon_2d' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'icon_3d' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }
}