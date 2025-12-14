<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 100, 5000);

        return [
            'receipt_number' => 'REC-' . $this->faker->unique()->numberBetween(10000, 99999),
            'customer_id' => Customer::factory(),
            'date' => $this->faker->date(),
            'amount' => $amount,
            'payment_method' => $this->faker->randomElement(['cash', 'bank']),
            // Use 'auto' which maps to 'oldest_first' in business logic
            'distribution_method' => 'auto',
            'allocated_amount' => 0,
            'unallocated_amount' => $amount,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function manual(): static
    {
        return $this->state(fn(array $attributes) => [
            'distribution_method' => 'manual',
        ]);
    }
}
