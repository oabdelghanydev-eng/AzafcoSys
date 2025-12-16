<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'code' => 'C-'.$this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->company(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'balance' => 0,
            'is_active' => true,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
