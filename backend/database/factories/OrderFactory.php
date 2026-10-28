<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'INV-'.now()->format('Ymd').'-'.$this->faker->unique()->numerify('####'),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'status' => OrderStatus::PENDING,
            'total_amount' => $this->faker->randomFloat(2, 10000, 500000),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::SHIPPED,
            'paid_at' => now()->subHour(),
            'shipped_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::COMPLETED,
            'paid_at' => now()->subHours(2),
            'shipped_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }
}
