<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait ValidatesQueryParameters
{
    protected function validateQuery(Request $request, array $rules): array
    {
        $validator = Validator::make($request->query(), $rules);
        $validator->after(function ($validator) use ($request, $rules): void {
            $allowed = array_values(array_unique(array_map(
                fn ($field) => explode('.', $field)[0],
                array_keys($rules)
            )));

            foreach (array_keys($request->query()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'Campo no permitido.');
                }
            }
        });

        return $validator->validate();
    }
}
