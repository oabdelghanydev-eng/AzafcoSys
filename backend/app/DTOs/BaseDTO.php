<?php

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * Base Data Transfer Object
 * 
 * DTOs are used to transfer data between layers of the application.
 * They provide type safety and clear data contracts.
 * 
 * Benefits:
 * - Decouples controllers from services
 * - Provides validation at the object level
 * - Makes testing easier with predictable data structures
 * - Documents the data structure clearly
 * 
 * @package App\DTOs
 */
abstract class BaseDTO
{
    /**
     * Create a DTO from a validated request.
     * 
     * @param Request $request The validated request
     * @return static The DTO instance
     */
    abstract public static function fromRequest(Request $request): static;

    /**
     * Create a DTO from an array of data.
     * 
     * @param array $data The data array
     * @return static The DTO instance
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Convert the DTO to an array.
     * 
     * @return array The DTO data as an array
     */
    abstract public function toArray(): array;

    /**
     * Get only the non-null properties as an array.
     * Useful for update operations where only provided fields should be updated.
     * 
     * @return array Array of non-null properties
     */
    public function toArrayWithoutNulls(): array
    {
        return array_filter($this->toArray(), fn($value) => $value !== null);
    }
}
