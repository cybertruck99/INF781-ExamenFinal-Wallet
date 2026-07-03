<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\StrictFormRequest;
use App\Rules\Recaptcha;

class LoginRequest extends StrictFormRequest
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
            'email' => ['required', 'email', 'max:180'],
            'password' => ['required', 'string', 'max:200'],
            'captcha_token' => ['required', 'string', 'max:4096', new Recaptcha()],
            'g-recaptcha-response' => ['nullable', 'string', 'max:4096'],
        ];
    }
}
