<?php

namespace App\Http\Requests\NestedRequest\NestedNestedRequest;

use Illuminate\Foundation\Http\FormRequest;

class BasicNestedNestedRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'outer' => 'array|required',
            'outer.inner' => 'array|required',
            'outer.inner.value' => 'string|required',
            'outer.inner.values' => 'array',
            'outer.inner.values.*' => 'integer',
        ];
    }
}
