<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreProductRequest
 * 
 * Validates product creation requests.
 * 
 * @package App\Http\Requests\Api
 */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission checked in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:products'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المنتج مطلوب',
            'name.max' => 'اسم المنتج يجب ألا يتجاوز 255 حرف',
            'name.unique' => 'اسم المنتج موجود بالفعل',
            'name_en.max' => 'الاسم الإنجليزي يجب ألا يتجاوز 255 حرف',
            'category.max' => 'التصنيف يجب ألا يتجاوز 100 حرف',
        ];
    }
}
