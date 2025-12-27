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
                    'You must open a daily report first before recording returns.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required',
            'items.required' => 'At least one item is required',
            'items.*.product_id.required' => 'Product is required',
            'items.*.quantity.required' => 'Quantity is required',
            'items.*.unit_price.required' => 'Price is required',
        ];
    }
}
