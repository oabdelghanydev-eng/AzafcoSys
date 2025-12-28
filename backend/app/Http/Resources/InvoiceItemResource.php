<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name_en ?? $this->product->name,
            ],
            'shipment_item_id' => $this->shipment_item_id,
            'cartons' => $this->cartons,
            'cartons_returned' => (int) ($this->cartons_returned ?? 0),
            'quantity' => (float) $this->quantity,
            'quantity_returned' => (float) ($this->quantity_returned ?? 0),
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
