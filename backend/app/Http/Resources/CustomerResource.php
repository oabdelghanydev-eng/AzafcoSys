<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
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
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'invoices_count' => $this->whenCounted('invoices'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
