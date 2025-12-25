<?php

namespace App\Http\Requests\Api;

use App\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateUserPermissionsRequest
 * 
 * Validates user permissions update requests.
 * 
 * @package App\Http\Requests\Api
 */
class UpdateUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userService = app(UserService::class);

        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in($userService->getValidPermissions())],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.required' => 'الصلاحيات مطلوبة',
            'permissions.array' => 'الصلاحيات يجب أن تكون مصفوفة',
            'permissions.*.in' => 'صلاحية غير صالحة',
        ];
    }
}
