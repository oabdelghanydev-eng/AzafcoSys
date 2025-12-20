<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentItemFactory extends Factory
{
    protected $model = ShipmentItem::class;

    public function definition(): array
    {
        $cartons = $this->faker->numberBetween(10, 100);
        $unitCost = $this->faker->randomFloat(2, 10, 100);

        return [
            'shipment_id' => Shipment::factory(),
            'product_id' => Product::factory(),
            'weight_per_unit' => $this->faker->randomFloat(2, 0.5, 5),
            'weight_label' => $this->faker->optional()->word(),
            'cartons' => $cartons,
            'sold_cartons' => 0,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'wastage_quantity' => 0,
            'unit_cost' => $unitCost,
            'total_cost' => $cartons * $unitCost,
        ];
    }

    public function withSales(int $soldCartons): static
    {
        return $this->state(fn(array $attributes) => [
            'sold_cartons' => $soldCartons,
        ]);
    }

    public function withCarryoverIn(int $cartons): static
    {
        return $this->state(fn(array $attributes) => [
            'carryover_in_cartons' => $cartons,
        ]);
    }
}

