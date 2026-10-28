<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTransitionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cashier = User::factory()->create(['role' => 'cashier']);
    }

    public function test_admin_can_mark_order_as_paid(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertOk()
            ->assertJson(['data' => ['status' => 'PAID']]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PAID']);
    }

    public function test_cashier_can_mark_order_as_paid(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertOk();
    }

    public function test_pay_fails_with_409_when_order_already_paid(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertStatus(409);
    }

    public function test_admin_can_ship_paid_order(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/ship")
            ->assertOk()
            ->assertJson(['data' => ['status' => 'SHIPPED']]);
    }

    public function test_cashier_cannot_ship_order(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$order->id}/ship")
            ->assertForbidden();
    }

    public function test_ship_fails_with_409_when_order_still_pending(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/ship")
            ->assertStatus(409);
    }

    public function test_admin_can_complete_shipped_order(): void
    {
        $order = Order::factory()->shipped()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/complete")
            ->assertOk()
            ->assertJson(['data' => ['status' => 'COMPLETED']]);
    }

    public function test_cashier_cannot_complete_order(): void
    {
        $order = Order::factory()->shipped()->create();

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$order->id}/complete")
            ->assertForbidden();
    }

    public function test_admin_can_cancel_pending_order_with_reason(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/cancel", ['cancel_reason' => 'Stok salah input'])
            ->assertOk()
            ->assertJson(['data' => ['status' => 'CANCELLED', 'cancel_reason' => 'Stok salah input']]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CANCELLED']);
    }

    public function test_cancel_requires_reason(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cancel_reason']);
    }

    public function test_cashier_cannot_cancel_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$order->id}/cancel", ['cancel_reason' => 'Batal'])
            ->assertForbidden();
    }

    public function test_cancel_restores_stock_and_writes_ledger(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => $product->price,
            'subtotal' => $product->price * 3,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/cancel", ['cancel_reason' => 'Batal'])
            ->assertOk();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 13]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'CANCEL_RESTORE',
            'quantity_change' => 3,
            'stock_after' => 13,
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);
    }

    public function test_cancel_fails_with_409_when_order_already_shipped(): void
    {
        $order = Order::factory()->shipped()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/cancel", ['cancel_reason' => 'Coba batalin'])
            ->assertStatus(409);
    }

    public function test_cancel_twice_does_not_restore_stock_twice(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => $product->price,
            'subtotal' => $product->price * 2,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/cancel", ['cancel_reason' => 'Batal pertama'])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/cancel", ['cancel_reason' => 'Batal kedua'])
            ->assertStatus(409); // CANCELLED -> CANCELLED ditolak

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 12]); // cuma +2 sekali
        $this->assertDatabaseCount('stock_movements', 1);
    }
}
