<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestRequest extends FormRequest
{
    /**
     * @return array<string[]>
     */
    public function rules(): array
    {
        return [
            'just_a_string' => 'boolean',
            'array_of_rules_as_seperator' => 'nullable|array',
            'array_of_rules_as_array' => [
                'string',
                'email',
            ],
            'sometimes_required' => 'sometimes|required|string',
            'required_rule' => 'required|string',
            'nullable_rule' => 'nullable|string', 
            'rule_array' => 'array',
            'rule_array.*' => 'string',
            'rule_array.id' => 'integer',
        ];
    }
}