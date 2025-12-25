<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreCreditNoteRequest
 * 
 * Validates credit note creation requests.
 * 
 * @package App\Http\Requests\Api
 */
class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'العميل مطلوب',
            'customer_id.exists' => 'العميل غير موجود',
            'amount.required' => 'المبلغ مطلوب',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'reason.required' => 'السبب مطلوب',
            'reason.max' => 'السبب يجب ألا يتجاوز 500 حرف',
        ];
    }
}
