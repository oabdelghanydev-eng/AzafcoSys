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
                'name' => $this->product->name_en ?? $this->product->name,
            ],
            'weight_per_unit' => (float) $this->weight_per_unit,
            'weight_label' => $this->weight_label,
            'cartons' => $this->cartons,
            'sold_cartons' => $this->sold_cartons,
            'carryover_in_cartons' => $this->carryover_in_cartons,
            'carryover_out_cartons' => $this->carryover_out_cartons,
            'remaining_cartons' => $this->remaining_cartons,  // computed accessor
            'expected_weight' => $this->expected_weight,      // computed accessor
            'wastage_quantity' => (float) $this->wastage_quantity,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
        ];
    }
}

