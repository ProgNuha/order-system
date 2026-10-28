<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cashier = User::factory()->create(['role' => 'cashier']);
        $this->customer = Customer::factory()->create();
    }

    public function test_guest_cannot_access_orders(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
        $this->postJson('/api/orders', [])->assertStatus(401);
    }

    public function test_cashier_can_create_order_with_single_item(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 15000]);

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/orders', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ])
            ->assertCreated()
            ->assertJson(['success' => true]);

        $this->assertSame('PENDING', $response->json('data.status'));
        $this->assertEqualsWithDelta(45000, (float) $response->json('data.total_amount'), 0.001);
        $this->assertCount(1, $response->json('data.items'));

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'customer_id' => $this->customer->id,
            'created_by' => $this->cashier->id,
            'status' => 'PENDING',
        ]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'ORDER_OUT',
            'quantity_change' => -3,
            'stock_after' => 7,
        ]);
    }

    public function test_admin_can_create_order_with_multiple_items(): void
    {
        $productA = Product::factory()->create(['stock' => 10, 'price' => 10000]);
        $productB = Product::factory()->create(['stock' => 5, 'price' => 20000]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['product_id' => $productA->id, 'quantity' => 2],
                    ['product_id' => $productB->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        // total = (2 * 10000) + (1 * 20000) = 40000
        $this->assertEqualsWithDelta(40000, (float) $response->json('data.total_amount'), 0.001);

        $this->assertDatabaseHas('products', ['id' => $productA->id, 'stock' => 8]);
        $this->assertDatabaseHas('products', ['id' => $productB->id, 'stock' => 4]);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_order_item_snapshots_unit_price_at_creation(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 12000]);

        $this->actingAs($this->admin)->postJson('/api/orders', [
            'customer_id' => $this->customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated();

        // Harga produk berubah SETELAH order dibuat
        $product->update(['price' => 99999]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'unit_price' => 12000, // tetep snapshot lama, bukan harga baru
            'subtotal' => 24000,
        ]);
    }

    public function test_store_requires_customer_id_and_items(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/orders', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'items']);
    }

    public function test_store_rejects_unknown_product(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'customer_id' => $this->customer->id,
                'items' => [['product_id' => 999999, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_store_rejects_soft_deleted_customer(): void
    {
        $this->customer->delete();
        $product = Product::factory()->create(['stock' => 10]);

        $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'customer_id' => $this->customer->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_rejects_insufficient_stock(): void
    {
        $product = Product::factory()->create(['stock' => 2, 'product_name' => 'Hydrococo 150ml']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'customer_id' => $this->customer->id,
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Stok tidak mencukupi',
            ]);

        $errors = $response->json('errors');
        $this->assertSame(
            ['Stok Hydrococo 150ml tersisa 2, diminta 5'],
            $errors['items.0.quantity']
        );

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]); // gak kepotong sama sekali
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_store_rejects_if_any_item_insufficient_even_if_others_fine(): void
    {
        $productOk = Product::factory()->create(['stock' => 10]);
        $productShort = Product::factory()->create(['stock' => 1]);

        $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['product_id' => $productOk->id, 'quantity' => 2],
                    ['product_id' => $productShort->id, 'quantity' => 5],
                ],
            ])
            ->assertStatus(422);

        // produk yg stoknya cukup HARUS tetep gak kepotong, karena transaksinya gagal semua (rollback)
        $this->assertDatabaseHas('products', ['id' => $productOk->id, 'stock' => 10]);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_orders_list_can_be_filtered_by_status(): void
    {
        Order::factory()->create(['customer_id' => $this->customer->id, 'created_by' => $this->admin->id]);
        Order::factory()->paid()->create(['customer_id' => $this->customer->id, 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/orders?status=PAID')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('PAID', $response->json('data.0.status'));
    }

    public function test_orders_list_can_be_filtered_by_customer(): void
    {
        $otherCustomer = Customer::factory()->create();
        Order::factory()->create(['customer_id' => $this->customer->id, 'created_by' => $this->admin->id]);
        Order::factory()->create(['customer_id' => $otherCustomer->id, 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/orders?customer_id={$this->customer->id}")
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_cashier_can_view_order_detail_with_items_and_status_history(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 10000]);

        $createResponse = $this->actingAs($this->admin)->postJson('/api/orders', [
            'customer_id' => $this->customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated();

        $orderId = $createResponse->json('data.id');

        $response = $this->actingAs($this->cashier)
            ->getJson("/api/orders/{$orderId}")
            ->assertOk();

        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame('PENDING', $response->json('data.status_history.0.status'));
    }

    public function test_viewing_unknown_order_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/orders/999999')
            ->assertNotFound();
    }
}