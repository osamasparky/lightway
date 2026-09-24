<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
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
     * @return array<string, mixed>
     */
   public function rules()
    {
        $rules = [
            'gateway' => 'required',
            'sale_type' => 'required|array',
            'gift_user' => 'array'
        ];

        foreach ($this->sale_type ?? [] as $itemId => $type) {

            if ($type === 'other') {

                $rules["gift_user.$itemId.full_name"] = 'required|string|max:255';

                $rules["gift_user.$itemId.email"] = 'required|email';

                $rules["gift_user.$itemId.password"] = 'required|min:6';
            }
        }

        return $rules;
    }
}
