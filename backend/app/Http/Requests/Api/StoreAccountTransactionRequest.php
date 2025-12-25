<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreAccountTransactionRequest
 * 
 * Validates deposit and withdraw requests for both Cashbox and Bank.
 * 
 * @package App\Http\Requests\Api
 */
class StoreAccountTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission checked in controller
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:500'],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'المبلغ مطلوب',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'description.required' => 'الوصف مطلوب',
            'description.max' => 'الوصف يجب ألا يتجاوز 500 حرف',
        ];
    }
}
