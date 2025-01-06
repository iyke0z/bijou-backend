<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateUserRequest extends FormRequest
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
            "fullname"  => 'required',
            "email" => 'nullable',
            "phone" => 'required',
            "address" => 'required',
            "role_id" => 'nullable',
            "password"=>'required',
            'dob'=>'required',
            'picture'=>'nullable',
            'gender'=>'required',
            'shop_id'=>'required',

        ];
    }
    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            $errors = (new ValidationException($validator))->errors();
            throw new HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'status_code' => '011',
                    'message' => 'Some required fields are missing or empty!',
                    'errors' => $errors
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }
}
