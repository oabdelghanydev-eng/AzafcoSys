<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'balance' => (float) $this->balance,
            'formatted_balance' => $this->formatted_balance,
            'notes' => $this->notes,
            'is_active' => $this->is_active,

            // Counts (when loaded)
            'shipments_count' => $this->whenCounted('shipments'),
            'expenses_count' => $this->whenCounted('expenses'),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
