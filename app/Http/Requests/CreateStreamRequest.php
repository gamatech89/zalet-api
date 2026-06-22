<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateStreamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only creators and admins can create streams
        return $this->user()->isCreator();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'stream_mode' => ['sometimes', 'string', 'in:scena,moments'],
            'stream_id' => ['sometimes', 'uuid'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A stream title is required.',
            'title.max' => 'Stream title cannot exceed 100 characters.',
        ];
    }
}
