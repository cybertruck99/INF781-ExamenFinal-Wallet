<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\StrictFormRequest;

class LogoutRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return ['refresh_token' => ['nullable', 'string', 'max:300']];
    }
}
