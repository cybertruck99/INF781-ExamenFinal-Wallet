<?php

namespace App\Http\Requests\Transfer;

use App\Http\Requests\StrictFormRequest;

class ConfirmTransferRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'accepted'],
            'totp_code' => ['nullable', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
