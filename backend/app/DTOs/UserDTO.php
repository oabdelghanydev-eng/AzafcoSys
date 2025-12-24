<?php

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * User Data Transfer Object
 * 
 * Used for transferring user data between controllers and services.
 * 
 * @package App\DTOs
 */
class UserDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $password = null,
        public readonly bool $isAdmin = false,
        public readonly array $permissions = [],
        public readonly ?bool $isActive = null,
    ) {
    }

    public static function fromRequest(Request $request): static
    {
        return new static(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
            isAdmin: $request->boolean('is_admin', false),
            permissions: $request->input('permissions', []),
            isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'] ?? null,
            isAdmin: $data['is_admin'] ?? false,
            permissions: $data['permissions'] ?? [],
            isActive: $data['is_active'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'is_admin' => $this->isAdmin,
            'permissions' => $this->permissions,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * Get data for creating a new user (includes password hash).
     */
    public function toCreateArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => bcrypt($this->password),
            'is_admin' => $this->isAdmin,
            'permissions' => $this->permissions,
        ];
    }
}
