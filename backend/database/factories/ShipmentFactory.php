<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'number' => 'SHP-' . $this->faker->unique()->numberBetween(10000, 99999),
            'supplier_id' => Supplier::factory(),
            'date' => $this->faker->date(),
            'status' => 'open',
            'total_cost' => $this->faker->randomFloat(2, 1000, 50000),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'closed',
        ]);
    }

    public function settled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'settled',
            'settled_at' => now(),
        ]);
    }
}
