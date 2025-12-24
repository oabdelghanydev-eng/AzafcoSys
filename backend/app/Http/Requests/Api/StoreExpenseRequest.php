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
            // date is optional - will use open daily report date
            'date' => 'nullable|date',
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
     * التحقق من وجود يومية مفتوحة (استخدام تاريخها تلقائياً)
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for ANY open daily report
            $dailyReport = \App\Models\DailyReport::where('status', 'open')->first();

            if (!$dailyReport) {
                $validator->errors()->add(
                    'daily_report',
                    'يجب فتح يومية أولاً قبل تسجيل المصروفات.'
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
