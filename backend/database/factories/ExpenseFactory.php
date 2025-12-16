<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'expense_number' => 'EXP-' . fake()->unique()->numberBetween(10000, 99999),
            'type' => 'company', // Default to company to avoid needing supplier_id
            'supplier_id' => null,
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'description' => fake()->sentence(),
            'payment_method' => fake()->randomElement(['cash', 'bank']),
            'category' => fake()->randomElement(['transport', 'labor', 'other']),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Factory state for company expense
     */
    public function company(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'company',
            'supplier_id' => null,
        ]);
    }

    /**
     * Factory state for supplier expense
     */
    public function supplier(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'supplier',
            'supplier_id' => Supplier::factory(),
        ]);
    }

    /**
     * Factory state for cash payment
     */
    public function cash(): static
    {
        return $this->state(fn(array $attributes) => [
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Factory state for bank payment
     */
    public function bank(): static
    {
        return $this->state(fn(array $attributes) => [
            'payment_method' => 'bank',
        ]);
    }
}
