<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Shipment Request
 *
 * Validates shipment update data.
 * Rules:
 * - Only open shipments can be updated (validated in controller)
 * - Cannot reduce cartons below sold amount (validated in controller)
 */
class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'sometimes|array',
            'items.*.id' => 'required|exists:shipment_items,id',
            'items.*.weight_per_unit' => 'sometimes|numeric|min:0.001',
            'items.*.cartons' => 'sometimes|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.id.required' => 'معرف البند مطلوب',
            'items.*.id.exists' => 'البند غير موجود',
            'items.*.weight_per_unit.min' => 'الوزن يجب أن يكون أكبر من صفر',
            'items.*.cartons.min' => 'عدد الكراتين يجب أن يكون على الأقل 1',
        ];
    }
}

