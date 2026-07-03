<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Throwable;

class Recaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('Completa el CAPTCHA.');
            return;
        }

        $value = trim($value);
        $testToken = config('services.recaptcha.test_token');
        if (app()->environment(['local', 'testing'])
            && is_string($testToken)
            && $testToken !== ''
            && hash_equals($testToken, $value)
        ) {
            return;
        }

        $secret = config('services.recaptcha.secret_key');
        if (blank($secret)) {
            $fail('reCAPTCHA no esta configurado en el servidor.');
            return;
        }

        $connectTimeout = max(1, (int) config('services.recaptcha.connect_timeout', 2));
        $timeout = max($connectTimeout + 1, (int) config('services.recaptcha.timeout', 4));

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->withOptions(['verify' => $this->tlsVerifyOption()])
                ->post(config('services.recaptcha.verify_url'), [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);
        } catch (Throwable) {
            $fail('No se pudo validar reCAPTCHA. Intenta nuevamente.');
            return;
        }

        if (! $response->ok() || ! ($response->json('success') ?? false)) {
            $fail('La verificacion CAPTCHA fallo. Marca la casilla nuevamente.');
        }
    }

    private function tlsVerifyOption(): bool|string
    {
        if (! (bool) config('services.recaptcha.ssl_verify', true)) {
            return false;
        }

        $caBundle = config('services.recaptcha.ca_bundle');
        if (! is_string($caBundle) || trim($caBundle) === '') {
            return true;
        }

        $caBundle = trim($caBundle);
        $caPath = preg_match('/^[A-Za-z]:[\\\\\\/]/', $caBundle) || str_starts_with($caBundle, DIRECTORY_SEPARATOR)
            ? $caBundle
            : base_path($caBundle);

        return is_file($caPath) ? $caPath : true;
    }
}
