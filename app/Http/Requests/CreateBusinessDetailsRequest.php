<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBusinessDetailsRequest extends FormRequest
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
            "logo" => "nullable",
            "email" => "nullable",
            "website" => "nullable",
            "phone_one" => "required",
            "phone_two" => "nullable",
            "motto" => "nullable",
            "vat" => "required",
            "status"=>"required",
            "expiry_date"=>'nullable'
        ];
    }
}
