<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessDetailsRequest extends FormRequest
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
            "name" => "required",
            "logo" => "sometimes:mimes:png,jpg",
            "website" => "sometimes",
            "phone_one" => "required",
            "phone_two" => "nullable",
            "motto" => "nullable",
            "vat" => "required",
            "status"=>"sometimes",
            "expiry_date"=>'sometimes',
            "times" => "sometimes",
            "is_negative_stock" => "sometimes",
            "owner_equity" => "sometimes",
            "logistics_fee" => "sometimes"
        ];
    }
}
