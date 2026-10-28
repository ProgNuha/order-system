<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
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

    public function test_guest_cannot_access_customers(): void
    {
        $this->getJson('/api/customers')->assertStatus(401);
    }

    public function test_admin_and_cashier_can_list_customers(): void
    {
        Customer::factory()->count(15)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success', 'message', 'data', 'meta' => ['page', 'per_page', 'total', 'total_pages'],
            ]);

        $this->actingAs($this->cashier)
            ->getJson('/api/customers')
            ->assertOk();
    }

    public function test_customer_list_is_paginated(): void
    {
        Customer::factory()->count(25)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers?per_page=10')
            ->assertOk();

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(25, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.total_pages'));
    }

    public function test_customer_list_can_be_searched(): void
    {
        Customer::factory()->create(['customer_name' => 'Budi Santoso']);
        Customer::factory()->create(['customer_name' => 'Siti Aminah']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers?search=Budi')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Budi Santoso', $response->json('data.0.customer_name'));
    }

    public function test_customer_list_can_be_sorted(): void
    {
        Customer::factory()->create(['customer_name' => 'Zainal']);
        Customer::factory()->create(['customer_name' => 'Amir']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers?sort=customer_name&direction=asc')
            ->assertOk();

        $this->assertSame('Amir', $response->json('data.0.customer_name'));
        $this->assertSame('Zainal', $response->json('data.1.customer_name'));
    }

    public function test_admin_can_create_customer(): void
    {
        $payload = [
            'customer_name' => 'Budi Santoso',
            'customer_address' => 'Jakarta',
            'gender' => 'Pria',
            'birth_date' => '1995-02-10',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
        ];

        $this->actingAs($this->admin)
            ->postJson('/api/customers', $payload)
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => ['customer_name' => 'Budi Santoso'],
            ]);

        $this->assertDatabaseHas('customers', ['customer_name' => 'Budi Santoso']);
    }

    public function test_cashier_can_create_customer(): void
    {
        $payload = ['customer_name' => 'Pelanggan Baru'];

        $this->actingAs($this->cashier)
            ->postJson('/api/customers', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('customers', ['customer_name' => 'Pelanggan Baru']);
    }

    public function test_store_requires_customer_name(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/customers', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_name']);
    }

    public function test_store_rejects_invalid_gender(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/customers', [
                'customer_name' => 'Budi',
                'gender' => 'Lainnya',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);
    }

    public function test_any_authenticated_user_can_view_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->cashier)
            ->getJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJson(['data' => ['id' => $customer->id]]);
    }

    public function test_viewing_unknown_customer_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/customers/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_admin_can_update_customer(): void
    {
        $customer = Customer::factory()->create(['customer_name' => 'Lama']);

        $this->actingAs($this->admin)
            ->putJson("/api/customers/{$customer->id}", ['customer_name' => 'Baru'])
            ->assertOk()
            ->assertJson(['data' => ['customer_name' => 'Baru']]);

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'customer_name' => 'Baru']);
    }

    public function test_cashier_can_update_customer(): void
    {
        $customer = Customer::factory()->create(['customer_name' => 'Lama']);

        $this->actingAs($this->cashier)
            ->putJson("/api/customers/{$customer->id}", ['customer_name' => 'Baru'])
            ->assertOk();

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'customer_name' => 'Baru']);
    }

    public function test_admin_can_delete_customer_and_it_is_soft_deleted(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_cashier_cannot_delete_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->cashier)
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
    }

    public function test_deleted_customer_does_not_appear_in_list(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers')
            ->assertOk();

        $this->assertDatabaseCount('customers', 1);
        $this->assertEmpty($response->json('data'));
    }
}
