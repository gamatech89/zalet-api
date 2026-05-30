<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:500'],
            'hometown_city' => ['nullable', 'string', 'max:100'],
            'hometown_country' => ['nullable', 'string', 'max:100'],
            'current_city' => ['nullable', 'string', 'max:100'],
            'current_country' => ['nullable', 'string', 'max:100'],
            'hometown_place_id' => ['nullable', 'uuid', 'exists:places,id'],
            'current_place_id' => ['nullable', 'uuid', 'exists:places,id'],
            'coordinates' => ['nullable', 'array'],
            'coordinates.lat' => ['required_with:coordinates', 'numeric', 'between:-90,90'],
            'coordinates.lng' => ['required_with:coordinates', 'numeric', 'between:-180,180'],
            'interests' => ['nullable', 'array', 'max:10'],
            'interests.*' => ['string', 'max:50'],
        ];
    }
}