<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ],
            'weight_per_unit' => (float) $this->weight_per_unit,
            'weight_label' => $this->weight_label,
            'cartons' => $this->cartons,
            'initial_quantity' => (float) $this->initial_quantity,
            'sold_quantity' => (float) $this->sold_quantity,
            'remaining_quantity' => (float) $this->remaining_quantity,
            'wastage_quantity' => (float) $this->wastage_quantity,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
        ];
    }
}
