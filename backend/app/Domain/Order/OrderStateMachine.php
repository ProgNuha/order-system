<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;

class OrderStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'PENDING' => ['PAID', 'CANCELLED'],
        'PAID' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED' => ['COMPLETED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function assertCanTransition(Order $order, OrderStatus $to): void
    {
        if (! $this->canTransition($order->status, $to)) {
            throw new InvalidOrderTransitionException(
                "Transisi status dari {$order->status->value} ke {$to->value} tidak diizinkan."
            );
        }
    }
}