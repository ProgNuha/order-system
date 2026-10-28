<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_name' => $this->faker->name(),
            'customer_address' => $this->faker->address(),
            'gender' => $this->faker->randomElement(['Pria', 'Wanita']),
            'birth_date' => $this->faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'phone' => $this->faker->numerify('08##########'),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}