<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Cashbox', 'Bank']),
            'type' => fake()->randomElement(['cashbox', 'bank']),
            'balance' => fake()->randomFloat(2, 0, 100000),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Factory state for cashbox account
     */
    public function cashbox(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Cashbox',
            'type' => 'cashbox',
        ]);
    }

    /**
     * Factory state for bank account
     */
    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bank',
            'type' => 'bank',
        ]);
    }
}
