<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSalesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        /*
            product_id
            price
            qty
            discount
            is_discount_code
            discount_id
            status
            transaction_id
            user_id
        */
        return [
            "products" => "required|array",
            "platform"=>'sometimes',
            "auth_code" => 'required',
            'description' => 'nullable',
            'is_order'=> 'required',
            'amount' => 'sometimes',
            'payment_method' => 'sometimes',
            'customer_id' => 'sometimes',
            'part_payment_amount' => 'sometimes',
            'discount' => 'sometimes',
            'vat' => 'sometimes',
            'logistics' => 'sometimes',
            'type' => 'required',
            'is_split_payment' => "required",
            'split' => 'sometimes',
            'start_date' => 'sometimes',
            'end_date' => 'sometimes',
            'payment_type' => 'sometimes',
            'monthly_value' => 'sometimes',
            'posting_day' => 'sometimes',
        ];
    }
}
