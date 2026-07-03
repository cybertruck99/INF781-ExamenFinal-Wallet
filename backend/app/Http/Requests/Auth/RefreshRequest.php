<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\StrictFormRequest;

class RefreshRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return ['refresh_token' => ['required', 'string', 'max:300']];
    }
}
