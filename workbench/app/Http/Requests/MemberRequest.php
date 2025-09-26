<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberRequest extends FormRequest
{
    /**
     * @return array<string[]>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
            ],
            'member_number' => [
                'required',
                'integer',
                "unique:members,member_number,{$this->member?->id}",
            ],
            'customer_type' => [
                'required',
            ],
            'sort_sequence' => [
                'nullable',
            ],
            'commission_rate' => [
                'nullable',
            ],
            'invoice_by_post' => [
                'boolean',
            ],
            'invoice_by_email' => [
                'boolean',
            ],
            'payment_method' => [
                'nullable',
            ],
            'vat_number' => [
                'integer',
                'nullable',
            ],
            'holding_number' => [
                'nullable',
            ],
            'fabbl_number' => [
                'nullable',
            ],
            'accs_number' => [
                'nullable',
            ],
            'organic_ref' => [
                'nullable',
            ],
            'other_ref' => [
                'nullable',
            ],
            'enterprise' => [
                'nullable',
            ],
            'ownership' => [
                'nullable',
            ],
            'farm_size' => [
                'nullable',
                'integer',
            ],
            'order_notes' => [
                'nullable',
            ],
            'invoice_notes' => [
                'nullable',
            ],
            'invoices_carried_forward' => [
                'boolean',
            ],
            'member_group_id' => [
                'nullable',
                'exists:member_groups',
            ],
        ];
    }
}