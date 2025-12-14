<?php

namespace Database\Factories;

use App\Models\ShipmentItem;
use App\Models\Shipment;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentItemFactory extends Factory
{
    protected $model = ShipmentItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(100, 1000);
        $unitCost = $this->faker->randomFloat(2, 10, 100);

        return [
            'shipment_id' => Shipment::factory(),
            'product_id' => Product::factory(),
            'weight_per_unit' => $this->faker->randomFloat(2, 0.5, 5),
            'weight_label' => $this->faker->optional()->word(),
            'cartons' => $this->faker->numberBetween(10, 100),
            'initial_quantity' => $quantity,
            'sold_quantity' => 0,
            'remaining_quantity' => $quantity,
            'wastage_quantity' => 0,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
        ];
    }

    public function withSales(int $soldQuantity): static
    {
        return $this->state(fn(array $attributes) => [
            'sold_quantity' => $soldQuantity,
            'remaining_quantity' => $attributes['initial_quantity'] - $soldQuantity,
        ]);
    }
}
