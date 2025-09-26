/**
 * @see App\Http\Requests\MemberRequest
 */
export namespace App.Http.Requests {
  export interface MemberRequest {
    name: any;
    member_number: number;
    customer_type: any;
    sort_sequence?: any;
    commission_rate?: any;
    invoice_by_post: boolean;
    invoice_by_email: boolean;
    payment_method?: any;
    vat_number?: number;
    holding_number?: any;
    fabbl_number?: any;
    accs_number?: any;
    organic_ref?: any;
    other_ref?: any;
    enterprise?: any;
    ownership?: any;
    farm_size?: number;
    order_notes?: any;
    invoice_notes?: any;
    invoices_carried_forward: boolean;
    member_group_id?: any;
  }
}

export {};
