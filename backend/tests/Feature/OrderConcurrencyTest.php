<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderConcurrencyTest extends TestCase
{
    private ?Product $product = null;

    private ?Customer $customer = null;

    private ?User $admin = null;

    protected function tearDown(): void
    {
        if ($this->product) {
            StockMovement::where('product_id', $this->product->id)->delete();
        }

        if ($this->customer) {
            Order::where('customer_id', $this->customer->id)->delete(); // cascade ke order_items
            $this->customer->forceDelete();
        }

        $this->product?->forceDelete();
        $this->admin?->forceDelete();

        parent::tearDown();
    }

    public function test_exactly_five_of_twenty_parallel_orders_succeed_when_stock_is_five(): void
    {
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create(['stock' => 5]);

        $token = $this->admin->createToken('concurrency-test')->plainTextToken;
        $baseUri = rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/');
        $responses = Http::pool(fn ($pool) => collect(range(1, 20))->map(
            fn () => $pool->withToken($token)
                ->timeout(15)
                ->post("{$baseUri}/api/orders", [
                    'customer_id' => $this->customer->id,
                    'items' => [
                        ['product_id' => $this->product->id, 'quantity' => 1],
                    ],
                ])
        )->all());

        $statusCodes = collect($responses)->map(function ($response) {
            if ($response instanceof Response) {
                return $response->status();
            }

            // Koneksi gagal/timeout — kemungkinan server belum restart
            // setelah PHP_CLI_SERVER_WORKERS ditambahin, atau APP_URL salah.
            fwrite(STDERR, 'Connection failed: '.$response->getMessage()."\n");

            return 0;
        });

        $success = $statusCodes->filter(fn (int $code) => $code === 201)->count();
        $failed = $statusCodes->filter(fn (int $code) => $code === 422)->count();
        $connectionErrors = $statusCodes->filter(fn (int $code) => $code === 0)->count();

        $this->assertSame(0, $connectionErrors, 'Ada request yang gagal connect — cek APP_URL & PHP_CLI_SERVER_WORKERS.');
        $this->assertSame(5, $success, "Harusnya tepat 5 order sukses, malah {$success}");
        $this->assertSame(15, $failed, "Harusnya tepat 15 order gagal (stok abis), malah {$failed}");

        $this->product->refresh();
        $this->assertSame(0, $this->product->stock);

        $this->assertSame(5, Order::where('customer_id', $this->customer->id)->count());
    }
}
