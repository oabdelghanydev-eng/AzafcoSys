<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' ' . $this->faker->word(),
            'name_en' => $this->faker->word() . ' ' . $this->faker->word(),
            'category' => $this->faker->randomElement(['category_a', 'category_b', 'category_c']),
            'is_active' => true,
        ];
    }
}
