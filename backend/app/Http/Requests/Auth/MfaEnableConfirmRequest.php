<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\StrictFormRequest;

class MfaEnableConfirmRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'regex:/^\d{6}$/']];
    }
}
