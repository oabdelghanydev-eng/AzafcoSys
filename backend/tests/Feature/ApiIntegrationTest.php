<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Integration Tests
 * تحسين 2025-12-16: اختبارات تكامل API
 */
class ApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    // ============================================
    // Customer CRUD Tests
    // ============================================

    public function test_can_list_customers(): void
    {
        Customer::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'phone', 'balance'],
                ],
            ]);
    }

    public function test_can_create_customer(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/customers', [
                'name' => 'عميل جديد',
                'phone' => '01234567890',
                'address' => 'العنوان',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'عميل جديد');

        $this->assertDatabaseHas('customers', ['name' => 'عميل جديد']);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/customers/{$customer->id}", [
                'name' => 'اسم محدث',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'اسم محدث');
    }

    public function test_can_show_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $customer->id);
    }

    // ============================================
    // Supplier CRUD Tests
    // ============================================

    public function test_can_list_suppliers(): void
    {
        Supplier::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/suppliers');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_supplier(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/suppliers', [
                'name' => 'مورد جديد',
                'phone' => '01111111111',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('suppliers', ['name' => 'مورد جديد']);
    }

    // ============================================
    // Product CRUD Tests
    // ============================================

    public function test_can_list_products(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/products');

        $response->assertStatus(200);
    }

    public function test_can_create_product(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/products', [
                'name' => 'منتج جديد',
                'name_en' => 'New Product',
                'category' => 'fish',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'منتج جديد']);
    }

    // ============================================
    // Shipment Tests
    // ============================================

    public function test_can_list_shipments(): void
    {
        $supplier = Supplier::factory()->create();
        Shipment::factory()->count(3)->create(['supplier_id' => $supplier->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/shipments');

        $response->assertStatus(200);
    }

    public function test_can_create_shipment_with_items(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        // Note: initial_quantity is now auto-calculated = cartons × weight_per_unit
        // 100 cartons × 10.5 kg = 1050 kg
        $response = $this->actingAs($this->admin)
            ->postJson('/api/shipments', [
                'supplier_id' => $supplier->id,
                'date' => now()->format('Y-m-d'),
                'items' => [
                    [
                        'product_id' => $product->id,
                        'weight_per_unit' => 10.5,
                        'weight_label' => 'A10',
                        'cartons' => 100,
                        // initial_quantity is auto-calculated by backend
                        'unit_cost' => 50.00,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'open');

        // Verify shipment item was created with cartons
        $this->assertDatabaseHas('shipment_items', [
            'product_id' => $product->id,
            'cartons' => 100,
        ]);
    }

    // ============================================
    // Stock Tests
    // ============================================

    public function test_can_get_stock(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'cartons' => 50,
            'sold_cartons' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/shipments/stock');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['product_id', 'product_name', 'total_quantity'],
                ],
            ]);
    }

    // ============================================
    // Filter Tests
    // ============================================

    public function test_can_filter_customers_by_search(): void
    {
        Customer::factory()->create(['name' => 'أحمد محمد']);
        Customer::factory()->create(['name' => 'علي حسن']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers?search=أحمد');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_customers_by_active_status(): void
    {
        Customer::factory()->create(['is_active' => true]);
        Customer::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers?active=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ============================================
    // Pagination Tests
    // ============================================

    public function test_customers_can_be_paginated(): void
    {
        Customer::factory()->count(25)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonCount(10, 'data');
    }

    // ============================================
    // Error Response Tests
    // ============================================

    public function test_validation_errors_return_422(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/customers', [
                // Missing required 'name'
                'phone' => '01234567890',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_not_found_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers/99999');

        $response->assertStatus(404);
    }
}
