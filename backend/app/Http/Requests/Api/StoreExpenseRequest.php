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
            'type' => 'required|in:supplier,company,supplier_payment',
            'payment_method' => 'required|in:cash,bank',
            'supplier_id' => 'required_if:type,supplier|required_if:type,supplier_payment|nullable|exists:suppliers,id',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Configure the validator instance.
     * تصحيح 2025-12-23: التحقق من وجود يومية مفتوحة
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $date = $this->input('date');
            $dailyReport = \App\Models\DailyReport::where('date', $date)
                ->where('status', 'open')
                ->first();

            if (!$dailyReport) {
                $validator->errors()->add(
                    'date',
                    "لا توجد يومية مفتوحة لهذا التاريخ ({$date}). يجب فتح اليومية أولاً."
                );
            }
        });
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
