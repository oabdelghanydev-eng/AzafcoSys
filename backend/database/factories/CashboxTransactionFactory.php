<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\CashboxTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashboxTransaction>
 */
class CashboxTransactionFactory extends Factory
{
    protected $model = CashboxTransaction::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory()->state(['type' => 'cashbox']),
            'type' => $this->faker->randomElement(['in', 'out']),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'balance_after' => $this->faker->randomFloat(2, 0, 50000),
            'description' => $this->faker->sentence(),
            'reference_type' => null,
            'reference_id' => null,
            'created_by' => User::factory(),
        ];
    }

    public function deposit(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'in',
        ]);
    }

    public function withdrawal(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'out',
        ]);
    }
}
