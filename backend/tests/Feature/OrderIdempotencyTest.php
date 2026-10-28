<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush(); // biar gak ada lock/record nyangkut dari test sebelumnya

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create(['stock' => 10, 'price' => 10000]);
    }

    private function payload(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ];
    }

    public function test_request_without_idempotency_key_creates_order_normally(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/orders', $this->payload())
            ->assertCreated();

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_retry_with_same_key_and_payload_returns_same_response_without_new_order(): void
    {
        $key = (string) Str::uuid();

        $first = $this->actingAs($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders', $this->payload())
            ->assertCreated();

        $this->assertDatabaseCount('orders', 1);

        $second = $this->actingAs($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders', $this->payload())
            ->assertCreated();

        // Order ID-nya harus SAMA, bukan order baru
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('orders', 1); // tetep cuma 1 row

        // Stok cuma kepotong sekali (bukti order kedua gak diproses ulang)
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'stock' => 8]);
    }

    public function test_retry_with_same_key_but_different_payload_returns_409(): void
    {
        $key = (string) Str::uuid();

        $this->actingAs($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders', $this->payload())
            ->assertCreated();

        $otherProduct = Product::factory()->create(['stock' => 10, 'price' => 5000]);

        $this->actingAs($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders', [
                'customer_id' => $this->customer->id,
                'items' => [['product_id' => $otherProduct->id, 'quantity' => 1]],
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 1); // request kedua gak bikin order baru
    }

    public function test_invalid_idempotency_key_format_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->withHeader('Idempotency-Key', 'bukan-uuid')
            ->postJson('/api/orders', $this->payload())
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_concurrent_request_with_same_key_while_processing_returns_409(): void
    {
        $key = (string) Str::uuid();

        // Simulasiin request pertama LAGI diproses (lock-nya diklaim manual,
        // belum sempet nulis hasil finalnya)
        Cache::add("idempotency:order:{$key}:lock", true, 30);

        $this->actingAs($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders', $this->payload())
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_failed_order_creation_is_also_replayed_on_retry(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 999], // stok gak cukup
            ],
        ];

        $first = $this->actingAs($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders', $payload)
            ->assertStatus(422);

        $second = $this->actingAs($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/orders', $payload)
            ->assertStatus(422);

        $this->assertSame($first->json('message'), $second->json('message'));
        $this->assertDatabaseCount('orders', 0);
    }
}
