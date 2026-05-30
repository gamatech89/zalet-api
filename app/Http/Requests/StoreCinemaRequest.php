<?php

namespace App\Http\Requests;

use App\Services\EmbedService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCinemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:500', function ($attribute, $value, $fail) {
                $embedService = app(EmbedService::class);
                if (!$embedService->isValidUrl($value)) {
                    $fail('The URL must be from YouTube, Vimeo, or Dailymotion.');
                }
            }],
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
            'url.required' => 'A video URL is required.',
            'price_coins.required_if' => 'Price in coins is required for PPV content.',
        ];
    }
}
