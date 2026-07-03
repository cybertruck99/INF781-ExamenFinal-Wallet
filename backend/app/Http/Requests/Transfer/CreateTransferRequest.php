<?php

namespace App\Http\Requests\Transfer;

use App\Http\Requests\StrictFormRequest;

class CreateTransferRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return [
            'destinatario' => ['required', 'string', 'max:180'],
            'monto' => ['required', 'numeric', 'min:1', 'max:5000', 'decimal:0,2'],
            'descripcion' => ['nullable', 'string', 'max:180'],
        ];
    }
}
