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
            "platform"=>'nullable',
            "auth_code" => 'required',
            'description' => 'nullable',
            'amount' => 'required'
        ];
    }
}
