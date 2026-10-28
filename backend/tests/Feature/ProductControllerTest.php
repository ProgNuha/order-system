<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
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

    public function test_guest_cannot_access_products(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
    }

    public function test_admin_and_cashier_can_list_products(): void
    {
        Product::factory()->count(15)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success', 'message', 'data', 'meta' => ['page', 'per_page', 'total', 'total_pages'],
            ]);

        $this->actingAs($this->cashier)
            ->getJson('/api/products')
            ->assertOk();
    }

    public function test_product_list_is_paginated(): void
    {
        Product::factory()->count(25)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/products?per_page=10')
            ->assertOk();

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(25, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.total_pages'));
    }

    public function test_product_list_can_be_searched(): void
    {
        Product::factory()->create(['product_name' => 'Hydrococo 150ml', 'product_code' => 'P0001']);
        Product::factory()->create(['product_name' => 'Teh Pucuk 350ml', 'product_code' => 'P0002']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/products?search=Hydrococo')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('P0001', $response->json('data.0.product_code'));
    }

    public function test_product_list_can_be_sorted(): void
    {
        Product::factory()->create(['product_name' => 'Zebra', 'price' => 1000]);
        Product::factory()->create(['product_name' => 'Apple', 'price' => 5000]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/products?sort=product_name&direction=asc')
            ->assertOk();

        $this->assertSame('Apple', $response->json('data.0.product_name'));
        $this->assertSame('Zebra', $response->json('data.1.product_name'));
    }

    public function test_admin_can_create_product(): void
    {
        $payload = [
            'product_code' => 'P0099',
            'product_name' => 'Produk Baru',
            'stock' => 20,
            'price' => 15000,
            'low_stock_threshold' => 5,
        ];

        $this->actingAs($this->admin)
            ->postJson('/api/products', $payload)
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => ['product_code' => 'P0099', 'product_name' => 'Produk Baru'],
            ]);

        $this->assertDatabaseHas('products', ['product_code' => 'P0099']);
    }

    public function test_cashier_cannot_create_product(): void
    {
        $payload = [
            'product_code' => 'P0099',
            'product_name' => 'Produk Baru',
            'stock' => 20,
            'price' => 15000,
        ];

        $this->actingAs($this->cashier)
            ->postJson('/api/products', $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('products', ['product_code' => 'P0099']);
    }

    public function test_store_requires_valid_payload(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_code', 'product_name', 'stock', 'price']);
    }

    public function test_store_rejects_duplicate_product_code(): void
    {
        Product::factory()->create(['product_code' => 'P0001']);

        $this->actingAs($this->admin)
            ->postJson('/api/products', [
                'product_code' => 'P0001',
                'product_name' => 'Duplikat',
                'stock' => 10,
                'price' => 1000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_code']);
    }

    public function test_store_rejects_negative_stock_and_price(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/products', [
                'product_code' => 'P0099',
                'product_name' => 'Produk Baru',
                'stock' => -1,
                'price' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock', 'price']);
    }

    public function test_any_authenticated_user_can_view_a_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->cashier)
            ->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJson(['data' => ['id' => $product->id]]);
    }

    public function test_viewing_unknown_product_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/products/999999')
            ->assertNotFound();
    }

    public function test_admin_can_update_product(): void
    {
        $product = Product::factory()->create(['product_name' => 'Lama']);

        $this->actingAs($this->admin)
            ->putJson("/api/products/{$product->id}", ['product_name' => 'Baru'])
            ->assertOk()
            ->assertJson(['data' => ['product_name' => 'Baru']]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'product_name' => 'Baru']);
    }

    public function test_cashier_cannot_update_product(): void
    {
        $product = Product::factory()->create(['product_name' => 'Lama']);

        $this->actingAs($this->cashier)
            ->putJson("/api/products/{$product->id}", ['product_name' => 'Baru'])
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'product_name' => 'Lama']);
    }

    public function test_update_rejects_duplicate_product_code(): void
    {
        $existing = Product::factory()->create(['product_code' => 'P0001']);
        $product = Product::factory()->create(['product_code' => 'P0002']);

        $this->actingAs($this->admin)
            ->putJson("/api/products/{$product->id}", ['product_code' => $existing->product_code])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_code']);
    }

    public function test_admin_can_delete_product_and_it_is_soft_deleted(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_cashier_cannot_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->cashier)
            ->deleteJson("/api/products/{$product->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_deleted_product_does_not_appear_in_list(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/products')
            ->assertOk();

        $this->assertDatabaseCount('products', 1);
        $this->assertEmpty($response->json('data'));
    }
}
