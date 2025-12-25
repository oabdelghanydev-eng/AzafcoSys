<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateProductRequest
 * 
 * Validates product update requests.
 * 
 * @package App\Http\Requests\Api
 */
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission checked in controller
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('products', 'name')->ignore($productId),
            ],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'اسم المنتج يجب ألا يتجاوز 255 حرف',
            'name.unique' => 'اسم المنتج موجود بالفعل',
        ];
    }
}
