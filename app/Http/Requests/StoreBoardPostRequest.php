<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoardPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:2000',
            'category' => 'required|in:apartment,job,roommate,ride,advice,general',
            'type' => 'sometimes|in:need,offer,question',
            'images' => 'sometimes|array|max:10',
            'images.*' => 'string|url|max:500',
            'location_label' => 'sometimes|string|max:200',
        ];
    }
}