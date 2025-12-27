<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\ReturnModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReturnModelFactory extends Factory
{
    protected $model = ReturnModel::class;

    public function definition(): array
    {
        return [
            'return_number' => 'RET-' . $this->faker->unique()->numberBetween(10000, 99999),
            'customer_id' => Customer::factory(),
            'original_invoice_id' => null,
            'date' => $this->faker->date(),
            'total_amount' => $this->faker->randomFloat(2, 100, 5000),
            'status' => 'active',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
