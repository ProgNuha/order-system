<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();
        $products = Product::all();
        $users = User::all();

        // distribusi status: lebih banyak COMPLETED biar laporan ada datanya
        $statusPool = array_merge(
            array_fill(0, 10, 'COMPLETED'),
            array_fill(0, 5, 'SHIPPED'),
            array_fill(0, 5, 'PAID'),
            array_fill(0, 3, 'PENDING'),
            array_fill(0, 2, 'CANCELLED'),
        );

        for ($i = 1; $i <= 150; $i++) {
            $createdAt = Carbon::now()->subDays(rand(0, 90))->subMinutes(rand(0, 1440));
            $status = $statusPool[array_rand($statusPool)];
            $customer = $customers->random();
            $creator = $users->random();

            $order = Order::create([
                'order_number' => 'INV-' . $createdAt->format('Ymd') . '-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'created_by' => $creator->id,
                'status' => 'PENDING',
                'total_amount' => 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $itemCount = rand(1, 4);
            $chosenProducts = $products->random(min($itemCount, $products->count()));
            $total = 0;
            $itemsCreated = [];

            foreach ($chosenProducts as $product) {
                $qty = rand(1, 3);
                $subtotal = $product->price * $qty;
                $total += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                ]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'ORDER_OUT',
                    'quantity_change' => -$qty,
                    'stock_after' => max(0, $product->stock - $qty),
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => 'Seeder: order placed',
                    'created_by' => $creator->id,
                ]);

                $itemsCreated[] = ['product_id' => $product->id, 'quantity' => $qty];
            }

            $paidAt = $shippedAt = $completedAt = $cancelledAt = null;
            $cancelReason = null;

            if (in_array($status, ['PAID', 'SHIPPED', 'COMPLETED'])) {
                $paidAt = $createdAt->copy()->addHours(rand(1, 12));
            }
            if (in_array($status, ['SHIPPED', 'COMPLETED'])) {
                $shippedAt = $paidAt->copy()->addHours(rand(1, 24));
            }
            if ($status === 'COMPLETED') {
                $completedAt = $shippedAt->copy()->addHours(rand(1, 48));
            }
            if ($status === 'CANCELLED') {
                $cancelledAt = $createdAt->copy()->addHours(rand(1, 6));
                $cancelReason = 'Dibatalkan oleh customer (seeder)';

                foreach ($itemsCreated as $item) {
                    $product = Product::find($item['product_id']);
                    StockMovement::create([
                        'product_id' => $item['product_id'],
                        'type' => 'CANCEL_RESTORE',
                        'quantity_change' => $item['quantity'],
                        'stock_after' => $product->stock + $item['quantity'],
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'note' => 'Seeder: order cancelled',
                        'created_by' => $creator->id,
                    ]);
                }
            }

            $order->update([
                'status' => $status,
                'total_amount' => $total,
                'paid_at' => $paidAt,
                'shipped_at' => $shippedAt,
                'completed_at' => $completedAt,
                'cancelled_at' => $cancelledAt,
                'cancel_reason' => $cancelReason,
            ]);
        }
    }
}