<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMomentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'video' => ['required', 'file', 'mimes:mp4,mov,avi,webm', 'max:102400'], // 100MB max
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_ppv' => ['boolean'],
            'price_coins' => ['nullable', 'required_if:is_ppv,true', 'numeric', 'min:1'],
            'access_level' => ['sometimes', 'string', 'in:free,premium,vip'],
            'required_plan_level' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'video.max' => 'Video must not exceed 100MB.',
            'video.mimes' => 'Video must be MP4, MOV, AVI, or WebM format.',
            'price_coins.required_if' => 'Price in coins is required for PPV content.',
        ];
    }
}
