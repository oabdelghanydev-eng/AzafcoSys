<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Will be handled by middleware
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            // date is optional - will use open daily report date
            'date' => ['nullable', 'date'],
            'type' => ['sometimes', Rule::in(['sale', 'wastage'])],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Items - Updated field names for clarity
            // cartons = عدد الكراتين المباعة
            // total_weight = الوزن الفعلي من الميزان (kg)
            // price = سعر الكيلو
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.cartons' => ['required', 'integer', 'min:1'],
            'items.*.total_weight' => ['required', 'numeric', 'min:0.001'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Configure the validator instance.
     * التحقق من وجود يومية مفتوحة والتحقق من الخصم
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for ANY open daily report
            $dailyReport = \App\Models\DailyReport::where('status', 'open')->first();

            if (!$dailyReport) {
                $validator->errors()->add(
                    'daily_report',
                    'يجب فتح يومية أولاً قبل تسجيل الفواتير.'
                );
                return; // Stop further validation if no daily report
            }

            $discount = (float) $this->input('discount', 0);
            $items = $this->input('items', []);

            // Calculate subtotal from items (total_weight × price)
            $subtotal = collect($items)->sum(function ($item) {
                return (float) ($item['total_weight'] ?? 0) * (float) ($item['price'] ?? 0);
            });

            if ($discount > $subtotal) {
                $validator->errors()->add(
                    'discount',
                    "الخصم ({$discount}) أكبر من إجمالي الأصناف ({$subtotal})"
                );
            }

            // Ensure total is not zero (unless wastage)
            if ($subtotal - $discount <= 0 && $this->input('type') !== 'wastage') {
                $validator->errors()->add(
                    'total',
                    'إجمالي الفاتورة يجب أن يكون أكبر من صفر'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'العميل مطلوب',
            'customer_id.exists' => 'العميل غير موجود',
            'date.required' => 'التاريخ مطلوب',
            'items.required' => 'يجب إضافة صنف واحد على الأقل',
            'items.min' => 'يجب إضافة صنف واحد على الأقل',
            'items.*.product_id.required' => 'الصنف مطلوب',
            'items.*.cartons.required' => 'عدد الكراتين مطلوب',
            'items.*.cartons.min' => 'عدد الكراتين يجب أن يكون 1 على الأقل',
            'items.*.total_weight.required' => 'الوزن الفعلي مطلوب',
            'items.*.total_weight.min' => 'الوزن يجب أن يكون أكبر من صفر',
            'items.*.price.required' => 'سعر الكيلو مطلوب',
        ];
    }
}
