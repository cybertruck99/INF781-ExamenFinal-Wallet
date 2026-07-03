<?php

namespace App\Http\Requests\Wallet;

use App\Http\Requests\StrictFormRequest;

class TopupRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'min:1', 'max:5000', 'decimal:0,2'],
            'descripcion' => ['nullable', 'string', 'max:180'],
        ];
    }
}
