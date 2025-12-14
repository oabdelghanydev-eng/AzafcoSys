<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.weight_per_unit' => ['required', 'numeric', 'min:0.001'],
            'items.*.weight_label' => ['nullable', 'string', 'max:50'],
            'items.*.cartons' => ['required', 'integer', 'min:1'],
            'items.*.initial_quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'المورد مطلوب',
            'date.required' => 'التاريخ مطلوب',
            'items.required' => 'يجب إضافة صنف واحد على الأقل',
            'items.*.product_id.required' => 'الصنف مطلوب',
            'items.*.cartons.required' => 'عدد الكراتين مطلوب',
            'items.*.initial_quantity.required' => 'الكمية مطلوبة',
        ];
    }
}
