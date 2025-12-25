<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateSettingRequest
 * 
 * Validates setting update requests.
 * 
 * @package App\Http\Requests\Api
 */
class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'settings.required' => 'الإعدادات مطلوبة',
            'settings.array' => 'الإعدادات يجب أن تكون مصفوفة',
        ];
    }
}
