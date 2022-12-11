<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayRequest extends FormRequest
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
        return [
            "ref" => 'required',
            "amount" => 'required',
            "customer_id" => "nullable",
            "payment_method" => 'required',
            "bank_id" => 'nullable',
            "auth_code" => 'required',
            "split" => 'nullable'
        ];
    }
}
