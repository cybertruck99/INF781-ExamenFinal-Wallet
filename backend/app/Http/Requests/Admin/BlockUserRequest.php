<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\StrictFormRequest;

class BlockUserRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return [
            'blocked' => ['required', 'boolean'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ];
    }
}
