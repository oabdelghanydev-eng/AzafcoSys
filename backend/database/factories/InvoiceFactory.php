<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $total = $this->faker->randomFloat(2, 100, 10000);
        $discount = $this->faker->randomFloat(2, 0, $total * 0.1);

        return [
            'invoice_number' => 'INV-'.$this->faker->unique()->numberBetween(10000, 99999),
            'customer_id' => Customer::factory(),
            'date' => $this->faker->date(),
            'type' => 'sale',
            'status' => 'active',
            'subtotal' => $total,
            'discount' => $discount,
            'total' => $total - $discount,
            'paid_amount' => 0,
            'balance' => $total - $discount,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_amount' => $attributes['total'],
            'balance' => 0,
        ]);
    }
}
