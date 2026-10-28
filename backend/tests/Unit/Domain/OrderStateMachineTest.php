<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Order\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderStateMachineTest extends TestCase
{
    private OrderStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateMachine = new OrderStateMachine;
    }

    public static function validTransitions(): array
    {
        return [
            'PENDING -> PAID' => [OrderStatus::PENDING, OrderStatus::PAID],
            'PENDING -> CANCELLED' => [OrderStatus::PENDING, OrderStatus::CANCELLED],
            'PAID -> SHIPPED' => [OrderStatus::PAID, OrderStatus::SHIPPED],
            'PAID -> CANCELLED' => [OrderStatus::PAID, OrderStatus::CANCELLED],
            'SHIPPED -> COMPLETED' => [OrderStatus::SHIPPED, OrderStatus::COMPLETED],
        ];
    }

    public static function invalidTransitions(): array
    {
        return [
            'PENDING -> SHIPPED (skip PAID)' => [OrderStatus::PENDING, OrderStatus::SHIPPED],
            'PENDING -> COMPLETED' => [OrderStatus::PENDING, OrderStatus::COMPLETED],
            'SHIPPED -> CANCELLED' => [OrderStatus::SHIPPED, OrderStatus::CANCELLED],
            'COMPLETED -> CANCELLED' => [OrderStatus::COMPLETED, OrderStatus::CANCELLED],
            'CANCELLED -> CANCELLED' => [OrderStatus::CANCELLED, OrderStatus::CANCELLED],
            'COMPLETED -> PAID (mundur)' => [OrderStatus::COMPLETED, OrderStatus::PAID],
            'CANCELLED -> PAID' => [OrderStatus::CANCELLED, OrderStatus::PAID],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_valid_transition_is_allowed(OrderStatus $from, OrderStatus $to): void
    {
        $this->assertTrue($this->stateMachine->canTransition($from, $to));
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transition_is_rejected(OrderStatus $from, OrderStatus $to): void
    {
        $this->assertFalse($this->stateMachine->canTransition($from, $to));
    }

    #[DataProvider('invalidTransitions')]
    public function test_assert_can_transition_throws_on_invalid_transition(OrderStatus $from, OrderStatus $to): void
    {
        $order = new Order(['status' => $from]);

        $this->expectException(InvalidOrderTransitionException::class);

        $this->stateMachine->assertCanTransition($order, $to);
    }

    #[DataProvider('validTransitions')]
    public function test_assert_can_transition_does_not_throw_on_valid_transition(OrderStatus $from, OrderStatus $to): void
    {
        $order = new Order(['status' => $from]);

        $this->stateMachine->assertCanTransition($order, $to);

        $this->addToAssertionCount(1); // gak exception = lolos
    }
}
