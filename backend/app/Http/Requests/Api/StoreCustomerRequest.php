<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'opening_balance' => 'nullable|numeric|min:0',  // رصيد أول المدة
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم العميل مطلوب',
            'name.max' => 'اسم العميل يجب ألا يتجاوز 255 حرف',
            'opening_balance.numeric' => 'رصيد أول المدة يجب أن يكون رقم',
            'opening_balance.min' => 'رصيد أول المدة يجب أن يكون 0 أو أكثر',
        ];
    }
}

