<?php

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * Product Data Transfer Object
 * 
 * Used for transferring product data between controllers and services.
 * 
 * @package App\DTOs
 */
class ProductDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $nameEn = null,
        public readonly ?string $description = null,
        public readonly ?string $category = null,
        public readonly ?string $unit = null,
        public readonly bool $isActive = true,
    ) {
    }

    public static function fromRequest(Request $request): static
    {
        return new static(
            name: $request->input('name'),
            nameEn: $request->input('name_en'),
            description: $request->input('description'),
            category: $request->input('category'),
            unit: $request->input('unit'),
            isActive: $request->boolean('is_active', true),
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            name: $data['name'],
            nameEn: $data['name_en'] ?? null,
            description: $data['description'] ?? null,
            category: $data['category'] ?? null,
            unit: $data['unit'] ?? null,
            isActive: $data['is_active'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'name_en' => $this->nameEn,
            'description' => $this->description,
            'category' => $this->category,
            'unit' => $this->unit,
            'is_active' => $this->isActive,
        ];
    }
}
