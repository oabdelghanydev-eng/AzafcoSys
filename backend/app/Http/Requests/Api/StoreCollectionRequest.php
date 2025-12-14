<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank'],
            'distribution_method' => ['sometimes', 'in:auto,manual'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Manual allocations (optional)
            'allocations' => ['sometimes', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations', 'exists:invoices,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'العميل مطلوب',
            'amount.required' => 'المبلغ مطلوب',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'payment_method.required' => 'طريقة الدفع مطلوبة',
        ];
    }
}
