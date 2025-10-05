<?php

namespace App\Http\Requests\NestedRequest;

use Illuminate\Foundation\Http\FormRequest;

class BasicNestedRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'parent' => [
              'array|required'
            ],
            'parent.child' => 'string|required',
            'parent.child2' => 'integer|nullable',
            'parent.children' => 'array',
            'parent.children.*' => 'string',
            'second_parent' => 'array|required',
            'second_parent.child' => 'string|required',
            'third_parent' => 'integer'
        ];
    }
}
