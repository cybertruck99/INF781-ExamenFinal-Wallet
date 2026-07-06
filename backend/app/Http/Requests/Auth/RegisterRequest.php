<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\StrictFormRequest;
use App\Rules\Recaptcha;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends StrictFormRequest
{
    protected function prepareForValidation(): void
    {
        $captchaToken = $this->input('captcha_token')
            ?? $this->input('g-recaptcha-response');

        if ($captchaToken) {
            $this->merge(['captcha_token' => $captchaToken]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:160', 'regex:/^[\pL\pM\s\'.-]+$/u'],
            'ci' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[0-9A-Za-z-]+$/', 'unique:users,ci'],
            'email' => ['required', 'email:rfc', 'max:180', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:7', 'max:20', 'regex:/^[0-9+ -]+$/', 'unique:users,phone'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
            'captcha_token' => $this->captchaRules(),
            'g-recaptcha-response' => ['nullable', 'string', 'max:4096'],
        ];
    }

    private function captchaRules(): array
    {
        if (! (bool) config('services.recaptcha.enabled', true)) {
            return ['nullable', 'string', 'max:4096'];
        }

        return ['required', 'string', 'max:4096', new Recaptcha()];
    }
}
