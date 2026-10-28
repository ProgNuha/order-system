<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Domain\Order\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class OrderTransitionService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {
    }

    public function markAsPaid(Order $order): Order
    {
        $this->stateMachine->assertCanTransition($order, OrderStatus::PAID);

        $order->update([
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);

        return $order->fresh();
    }

    public function markAsShipped(Order $order): Order
    {
        $this->stateMachine->assertCanTransition($order, OrderStatus::SHIPPED);

        $order->update([
            'status' => OrderStatus::SHIPPED,
            'shipped_at' => now(),
        ]);

        return $order->fresh();
    }

    public function markAsCompleted(Order $order): Order
    {
        $this->stateMachine->assertCanTransition($order, OrderStatus::COMPLETED);

        $order->update([
            'status' => OrderStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        return $order->fresh();
    }

    public function cancel(Order $order, string $reason): Order
    {
        $this->stateMachine->assertCanTransition($order, OrderStatus::CANCELLED);

        return DB::transaction(function () use ($order, $reason) {
            $order->load('items.product');

            foreach ($order->items as $item) {
                $product = $item->product()->lockForUpdate()->first();
                $newStock = $product->stock + $item->quantity;
                $product->update(['stock' => $newStock]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'CANCEL_RESTORE',
                    'quantity_change' => $item->quantity,
                    'stock_after' => $newStock,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => "Pembatalan order {$order->order_number}",
                    'created_by' => $order->created_by,
                ]);
            }

            $order->update([
                'status' => OrderStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $order->fresh();
        });
    }
}