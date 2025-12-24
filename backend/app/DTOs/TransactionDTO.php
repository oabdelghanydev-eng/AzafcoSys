<?php

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * Transaction Data Transfer Object
 * 
 * Used for Cashbox and Bank transactions (deposits, withdrawals).
 * 
 * @package App\DTOs
 */
class TransactionDTO extends BaseDTO
{
    public function __construct(
        public readonly float $amount,
        public readonly string $type, // 'deposit' or 'withdraw'
        public readonly string $description,
        public readonly ?string $referenceType = null, // 'collection', 'expense', etc.
        public readonly ?int $referenceId = null,
        public readonly ?string $notes = null,
    ) {
    }

    public static function fromRequest(Request $request): static
    {
        return new static(
            amount: (float) $request->input('amount'),
            type: $request->input('type', 'deposit'),
            description: $request->input('description'),
            referenceType: $request->input('reference_type'),
            referenceId: $request->input('reference_id'),
            notes: $request->input('notes'),
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            amount: (float) $data['amount'],
            type: $data['type'] ?? 'deposit',
            description: $data['description'],
            referenceType: $data['reference_type'] ?? null,
            referenceId: $data['reference_id'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * Create a deposit DTO.
     */
    public static function deposit(float $amount, string $description, ?string $referenceType = null, ?int $referenceId = null): static
    {
        return new static(
            amount: $amount,
            type: 'deposit',
            description: $description,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );
    }

    /**
     * Create a withdrawal DTO.
     */
    public static function withdraw(float $amount, string $description, ?string $referenceType = null, ?int $referenceId = null): static
    {
        return new static(
            amount: $amount,
            type: 'withdraw',
            description: $description,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'type' => $this->type,
            'description' => $this->description,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'notes' => $this->notes,
        ];
    }

    public function isDeposit(): bool
    {
        return $this->type === 'deposit';
    }

    public function isWithdraw(): bool
    {
        return $this->type === 'withdraw';
    }
}
