<?php

namespace App\Http\Requests;

use App\Models\BannedIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
        $bannedEmails = BannedIdentifier::emails()->pluck('value')->all();
        $bannedIps    = BannedIdentifier::ips()->pluck('value')->all();

        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
                Rule::notIn($bannedEmails),
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/',
                'unique:users,username',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            '_ip_check' => [
                Rule::notIn($bannedIps),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     * Inject the request IP so we can validate it using the rules array.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['_ip_check' => $this->ip()]);
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'username.regex'      => 'Username may only contain letters, numbers, and underscores.',
            'email.not_in'        => 'This email address is not allowed to register.',
            '_ip_check.not_in'    => 'Registration is not allowed from your current IP address.',
        ];
    }
}
