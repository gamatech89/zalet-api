<?php

namespace App\Http\Requests;

use App\Models\BannedIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/',
                'unique:users,username',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['email'])) {
                    return;
                }

                $email  = strtolower(trim($this->input('email', '')));
                $domain = substr($email, strpos($email, '@') + 1);
                $ip     = $this->ip();

                $blocked = BannedIdentifier::where(function ($q) use ($email, $domain, $ip) {
                    $q->where(fn ($s) => $s->where('type', 'email')->where('value', $email))
                      ->orWhere(fn ($s) => $s->where('type', 'email_domain')->where('value', $domain))
                      ->orWhere(fn ($s) => $s->where('type', 'ip')->where('value', $ip));
                })->exists();

                if ($blocked) {
                    $validator->errors()->add('email', 'Registracija nije moguća.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username may only contain letters, numbers, and underscores.',
        ];
    }
}
