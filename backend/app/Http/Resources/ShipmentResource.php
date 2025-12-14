<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier' => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ],
            'date' => $this->date->format('Y-m-d'),
            'status' => $this->status,
            'total_cost' => (float) $this->total_cost,
            'notes' => $this->notes,
            'items' => ShipmentItemResource::collection($this->whenLoaded('items')),
            'settled_at' => $this->settled_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
