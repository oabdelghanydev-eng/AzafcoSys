<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
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
            'opening_balance' => 'nullable|numeric',  // رصيد أول المدة (يمكن أن يكون سالب أو موجب)
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المورد مطلوب',
            'opening_balance.numeric' => 'رصيد أول المدة يجب أن يكون رقم',
        ];
    }
}

