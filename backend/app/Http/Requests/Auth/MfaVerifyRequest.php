<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\StrictFormRequest;

class MfaVerifyRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return [
            'ticket' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
