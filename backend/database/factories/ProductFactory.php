<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_code' => 'P' . str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'product_name' => ucfirst($this->faker->words(2, true)),
            'stock' => $this->faker->numberBetween(10, 200),
            'price' => $this->faker->numberBetween(5, 500) * 1000,
        ];
    }
}