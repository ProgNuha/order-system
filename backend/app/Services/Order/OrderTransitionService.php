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
    ) {}

    public function markAsPaid(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanTransition($locked, OrderStatus::PAID);

            $locked->update([
                'status' => OrderStatus::PAID,
                'paid_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function markAsShipped(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanTransition($locked, OrderStatus::SHIPPED);

            $locked->update([
                'status' => OrderStatus::SHIPPED,
                'shipped_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function markAsCompleted(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanTransition($locked, OrderStatus::COMPLETED);

            $locked->update([
                'status' => OrderStatus::COMPLETED,
                'completed_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function cancel(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanTransition($locked, OrderStatus::CANCELLED);

            $locked->load('items.product');

            foreach ($locked->items as $item) {
                $product = $item->product()->lockForUpdate()->first();
                $newStock = $product->stock + $item->quantity;
                $product->update(['stock' => $newStock]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'CANCEL_RESTORE',
                    'quantity_change' => $item->quantity,
                    'stock_after' => $newStock,
                    'reference_type' => 'order',
                    'reference_id' => $locked->id,
                    'note' => "Pembatalan order {$locked->order_number}",
                    'created_by' => $locked->created_by,
                ]);
            }

            $locked->update([
                'status' => OrderStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $locked->fresh();
        });
    }
}
