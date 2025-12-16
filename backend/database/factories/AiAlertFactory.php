<?php

namespace Database\Factories;

use App\Models\AiAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiAlert>
 */
class AiAlertFactory extends Factory
{
    protected $model = AiAlert::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['price_anomaly', 'shipment_delay', 'overdue_customer']),
            'severity' => fake()->randomElement(['info', 'warning', 'critical']),
            'title' => fake()->sentence(3),
            'message' => fake()->paragraph(),
            'data' => [],
            'model_type' => null,
            'model_id' => null,
            'is_read' => false,
            'is_resolved' => false,
            'resolved_by' => null,
            'resolved_at' => null,
        ];
    }

    /**
     * State for critical alerts
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
        ]);
    }

    /**
     * State for resolved alerts
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);
    }

    /**
     * State for read alerts
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }
}
