<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:supplier,company',
            'payment_method' => 'required|in:cash,bank',
            'supplier_id' => 'required_if:type,supplier|nullable|exists:suppliers,id',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'المبلغ مطلوب',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'type.required' => 'نوع المصروف مطلوب',
            'supplier_id.required_if' => 'المورد مطلوب لمصروفات الموردين',
        ];
    }
}
