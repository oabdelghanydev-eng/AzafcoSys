<?php

namespace Database\Factories;

use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyReportFactory extends Factory
{
    protected $model = DailyReport::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->date(),
            'cashbox_opening' => $this->faker->randomFloat(2, 0, 10000),
            'bank_opening' => $this->faker->randomFloat(2, 0, 50000),
            'total_sales' => 0,
            'total_collections_cash' => 0,
            'total_collections_bank' => 0,
            'total_expenses_cash' => 0,
            'total_expenses_bank' => 0,
            'total_wastage' => 0,
            'total_transfers_in' => 0,
            'total_transfers_out' => 0,
            'cashbox_closing' => 0,
            'bank_closing' => 0,
            'cashbox_difference' => 0,
            'net_day' => 0,
            'status' => 'open',
            'opened_by' => User::factory(),
        ];
    }

    /**
     * Closed daily report state
     */
    public function closed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => User::factory(),
        ]);
    }

    /**
     * Report with calculated totals
     */
    public function withTotals(float $sales = 1000, float $collections = 500): static
    {
        return $this->state(fn(array $attributes) => [
            'total_sales' => $sales,
            'total_collections_cash' => $collections,
            'net_day' => $sales - $collections,
        ]);
    }
}
