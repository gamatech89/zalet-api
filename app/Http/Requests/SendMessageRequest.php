<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by ConversationPolicy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,zip', 'max:10240'], // 10MB
            'reply_to_id' => ['nullable', 'uuid', 'exists:messages,id'],
        ];
    }

    /**
     * Additional validation: require at least content or media.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->filled('content') && !$this->hasFile('media')) {
                $validator->errors()->add('content', 'Either message content or a file is required.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'content.max' => 'Message cannot exceed 5000 characters.',
            'media.max' => 'File cannot exceed 10MB.',
            'media.mimes' => 'Unsupported file type.',
        ];
    }
}