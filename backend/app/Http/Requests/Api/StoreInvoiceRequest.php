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
            'date' => ['required', 'date'],
            'type' => ['sometimes', Rule::in(['sale', 'wastage'])],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.cartons' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Configure the validator instance.
     * تصحيح 2025-12-13: التحقق من أن الخصم لا يتجاوز subtotal
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $discount = (float) $this->input('discount', 0);
            $items = $this->input('items', []);

            // Calculate subtotal from items
            $subtotal = collect($items)->sum(function ($item) {
                return (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
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
            'items.*.quantity.required' => 'الكمية مطلوبة',
            'items.*.unit_price.required' => 'السعر مطلوب',
        ];
    }
}
