<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'date' => $this->date->format('Y-m-d'),
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'distribution_method' => $this->distribution_method,
            'allocated_amount' => (float) $this->allocated_amount,
            'unallocated_amount' => (float) $this->unallocated_amount,
            'notes' => $this->notes,
            'allocations' => $this->whenLoaded(
                'allocations',
                fn () => $this->allocations->map(fn ($a) => [
                    'invoice_id' => $a->invoice_id,
                    'invoice_number' => $a->invoice->invoice_number,
                    'amount' => (float) $a->amount,
                ])
            ),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
