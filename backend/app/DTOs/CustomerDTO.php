<?php

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * Customer Data Transfer Object
 * 
 * Used for transferring customer data between controllers and services.
 * 
 * @package App\DTOs
 */
class CustomerDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $notes = null,
        public readonly ?float $creditLimit = null,
    ) {
    }

    public static function fromRequest(Request $request): static
    {
        return new static(
            name: $request->input('name'),
            phone: $request->input('phone'),
            address: $request->input('address'),
            notes: $request->input('notes'),
            creditLimit: $request->has('credit_limit') ? (float) $request->input('credit_limit') : null,
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            name: $data['name'],
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            notes: $data['notes'] ?? null,
            creditLimit: isset($data['credit_limit']) ? (float) $data['credit_limit'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'notes' => $this->notes,
            'credit_limit' => $this->creditLimit,
        ];
    }
}
