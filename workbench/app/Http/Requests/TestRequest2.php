<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestRequest2 extends FormRequest
{
    /**
     * @return array<string[]>
     */
    public function rules(): array
    {
        return [
            'just_a_string' => 'boolean', 
        ];
    }
}