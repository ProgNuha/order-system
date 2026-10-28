<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderCreationService
{
    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function create(string $customerId, array $items, int $createdBy): Order
    {
        return DB::transaction(function () use ($customerId, $items, $createdBy) {
            // Lock produk yang kepake, urutin by id biar gak deadlock
            // kalau ada order lain rebutan produk yang sama bersamaan.
            $productIds = collect($items)->pluck('product_id')->unique()->sort()->values();

            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];

            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    $errors["items.{$index}.product_id"] = ['Produk tidak ditemukan.'];

                    continue;
                }

                if ($product->stock < $item['quantity']) {
                    $errors["items.{$index}.quantity"] = [
                        "Stok {$product->product_name} tersisa {$product->stock}, diminta {$item['quantity']}",
                    ];
                }
            }

            if (! empty($errors)) {
                throw new InsufficientStockException($errors);
            }

            $totalAmount = collect($items)->sum(
                fn (array $item) => (float) $products->get($item['product_id'])->price * $item['quantity']
            );

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customerId,
                'created_by' => $createdBy,
                'status' => OrderStatus::PENDING,
                'total_amount' => $totalAmount,
            ]);

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $unitPrice = (float) $product->price;
                $subtotal = $unitPrice * $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $newStock = $product->stock - $item['quantity'];
                $product->update(['stock' => $newStock]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'ORDER_OUT',
                    'quantity_change' => -$item['quantity'],
                    'stock_after' => $newStock,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => "Order {$order->order_number}",
                    'created_by' => $createdBy,
                ]);
            }

            return $order;
        });
    }

    private function generateOrderNumber(): string
    {
        return sprintf('INV-%s-%s', now()->format('Ymd'), Str::upper(Str::random(8)));
    }
}
