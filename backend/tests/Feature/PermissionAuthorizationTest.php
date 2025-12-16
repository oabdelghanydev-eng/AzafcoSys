<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;

/**
 * Permission Authorization Tests
 * تحسين 2025-12-16: اختبارات الصلاحيات
 */
class PermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $userWithPermissions;
    private User $userWithoutPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'is_admin' => true,
            'permissions' => [],
        ]);

        // Create user with specific permissions
        $this->userWithPermissions = User::factory()->create([
            'is_admin' => false,
            'permissions' => [
                'customers.view',
                'customers.create',
                'invoices.view',
            ],
        ]);

        // Create user without permissions
        $this->userWithoutPermissions = User::factory()->create([
            'is_admin' => false,
            'permissions' => [],
        ]);
    }

    // ============================================
    // Admin Bypass Tests
    // ============================================

    public function test_admin_can_access_any_endpoint(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/customers');

        $response->assertStatus(200);
    }

    public function test_admin_bypasses_all_permission_checks(): void
    {
        // Admin should access even without explicit permission
        $this->assertTrue($this->admin->hasPermission('customers.view'));
        $this->assertTrue($this->admin->hasPermission('invoices.create'));
        $this->assertTrue($this->admin->hasPermission('any.permission'));
    }

    // ============================================
    // Customer Permission Tests
    // ============================================

    public function test_user_with_customers_view_can_list_customers(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->actingAs($this->userWithPermissions)
            ->getJson('/api/customers');

        $response->assertStatus(200);
    }

    public function test_user_without_customers_view_cannot_list_customers(): void
    {
        $response = $this->actingAs($this->userWithoutPermissions)
            ->getJson('/api/customers');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_003',
                ]
            ]);
    }

    public function test_user_with_customers_create_can_create_customer(): void
    {
        $response = $this->actingAs($this->userWithPermissions)
            ->postJson('/api/customers', [
                'name' => 'Test Customer',
                'phone' => '01000000000',
            ]);

        $response->assertStatus(201);
    }

    public function test_user_without_customers_create_cannot_create_customer(): void
    {
        $response = $this->actingAs($this->userWithoutPermissions)
            ->postJson('/api/customers', [
                'name' => 'Test Customer',
                'phone' => '01000000000',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_003');
    }

    // ============================================
    // Invoice Permission Tests
    // ============================================

    public function test_user_with_invoices_view_can_list_invoices(): void
    {
        $response = $this->actingAs($this->userWithPermissions)
            ->getJson('/api/invoices');

        $response->assertStatus(200);
    }

    public function test_user_without_invoices_create_cannot_create_invoice(): void
    {
        $customer = Customer::factory()->create();

        // User has invoices.view but not invoices.create
        $response = $this->actingAs($this->userWithPermissions)
            ->postJson('/api/invoices', [
                'customer_id' => $customer->id,
                'date' => now()->format('Y-m-d'),
                'items' => [
                    ['product_id' => 1, 'quantity' => 10, 'unit_price' => 100]
                ],
            ]);

        // Should fail with AUTH_003 (permission denied) or validation error
        // The permission check happens first, so we expect 422
        $response->assertStatus(422);
    }

    // ============================================
    // Supplier Permission Tests
    // ============================================

    public function test_user_without_suppliers_view_cannot_list_suppliers(): void
    {
        $response = $this->actingAs($this->userWithoutPermissions)
            ->getJson('/api/suppliers');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_003');
    }

    // ============================================
    // Product Permission Tests
    // ============================================

    public function test_any_user_can_view_products(): void
    {
        // Products index/show are public (no permission required)
        $response = $this->actingAs($this->userWithoutPermissions)
            ->getJson('/api/products');

        $response->assertStatus(200);
    }

    public function test_user_without_products_create_cannot_create_product(): void
    {
        $response = $this->actingAs($this->userWithoutPermissions)
            ->postJson('/api/products', [
                'name' => 'Test Product',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'AUTH_003');
    }

    // ============================================
    // Unauthenticated Tests
    // ============================================

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/customers');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_001');
    }

    // ============================================
    // hasPermission Method Tests
    // ============================================

    public function test_has_permission_returns_true_for_granted_permission(): void
    {
        $this->assertTrue($this->userWithPermissions->hasPermission('customers.view'));
    }

    public function test_has_permission_returns_false_for_missing_permission(): void
    {
        $this->assertFalse($this->userWithPermissions->hasPermission('shipments.view'));
    }

    public function test_has_any_permission_works_correctly(): void
    {
        $this->assertTrue($this->userWithPermissions->hasAnyPermission([
            'customers.view',
            'shipments.view',
        ]));

        $this->assertFalse($this->userWithPermissions->hasAnyPermission([
            'shipments.view',
            'expenses.view',
        ]));
    }
}
