<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderCancelConcurrencyTest extends TestCase
{
    private ?Product $product = null;

    private ?Customer $customer = null;

    private ?Order $order = null;

    private ?User $admin = null;

    protected function tearDown(): void
    {
        if ($this->order) {
            StockMovement::where('reference_type', 'order')->where('reference_id', $this->order->id)->delete();
            OrderItem::where('order_id', $this->order->id)->delete();
            $this->order->forceDelete();
        }

        $this->customer?->forceDelete();
        $this->product?->forceDelete();
        $this->admin?->forceDelete();

        parent::tearDown();
    }

    public function test_exactly_one_of_ten_parallel_cancels_succeeds_on_the_same_order(): void
    {
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create(['stock' => 10]);

        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->admin->id,
            'status' => OrderStatus::PENDING,
            'total_amount' => $this->product->price * 3,
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_price' => $this->product->price,
            'subtotal' => $this->product->price * 3,
        ]);

        $token = $this->admin->createToken('cancel-concurrency-test')->plainTextToken;
        $baseUri = rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/');

        $responses = Http::pool(fn ($pool) => collect(range(1, 10))->map(
            fn (int $i) => $pool->withToken($token)
                ->timeout(15)
                ->post("{$baseUri}/api/orders/{$this->order->id}/cancel", [
                    'cancel_reason' => "Percobaan cancel paralel #{$i}",
                ])
        )->all());

        $statusCodes = collect($responses)->map(function ($response) {
            if ($response instanceof Response) {
                return $response->status();
            }

            fwrite(STDERR, 'Connection failed: '.$response->getMessage()."\n");

            return 0;
        });

        $success = $statusCodes->filter(fn (int $code) => $code === 200)->count();
        $conflict = $statusCodes->filter(fn (int $code) => $code === 409)->count();
        $connectionErrors = $statusCodes->filter(fn (int $code) => $code === 0)->count();

        $this->assertSame(0, $connectionErrors, 'Ada request yang gagal connect — cek APP_URL & PHP_CLI_SERVER_WORKERS.');
        $this->assertSame(1, $success, "Harusnya tepat 1 cancel sukses, malah {$success}");
        $this->assertSame(9, $conflict, "Harusnya 9 sisanya ditolak 409, malah {$conflict}");

        $this->product->refresh();
        $this->assertSame(13, $this->product->stock, 'Stok cuma boleh kembali sekali (10 + 3), bukan berkali-kali');

        $this->assertSame(
            1,
            StockMovement::where('reference_type', 'order')
                ->where('reference_id', $this->order->id)
                ->where('type', 'CANCEL_RESTORE')
                ->count(),
            'Ledger CANCEL_RESTORE cuma boleh ke-insert sekali'
        );

        $this->assertSame(OrderStatus::CANCELLED, $this->order->fresh()->status);
    }
}
