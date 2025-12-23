<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'original_invoice_id' => ['nullable', 'exists:invoices,id'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.shipment_item_id' => ['nullable', 'exists:shipment_items,id'],
        ];
    }

    /**
     * Configure the validator instance.
     * تصحيح 2025-12-23: التحقق من وجود يومية مفتوحة
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Returns use today's date if not specified
            $date = $this->input('date', now()->toDateString());
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
            'customer_id.required' => 'العميل مطلوب',
            'items.required' => 'يجب إضافة صنف واحد على الأقل',
            'items.*.product_id.required' => 'الصنف مطلوب',
            'items.*.quantity.required' => 'الكمية مطلوبة',
            'items.*.unit_price.required' => 'السعر مطلوب',
        ];
    }
}
