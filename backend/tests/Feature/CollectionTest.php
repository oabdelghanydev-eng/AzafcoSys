<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature Tests for Collection Endpoints
 * Epic 6: Collections System
 */
class CollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Invoice $invoice1;

    private Invoice $invoice2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'permissions' => ['collections.view', 'collections.create', 'collections.delete'],
        ]);

        $this->customer = Customer::factory()->create([
            'balance' => 1000,
        ]);

        // Create two unpaid invoices
        $this->invoice1 = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'date' => now()->subDays(5)->toDateString(),
            'total' => 400,
            'balance' => 400,
            'paid_amount' => 0,
            'status' => 'active',
        ]);

        $this->invoice2 = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'date' => now()->subDays(3)->toDateString(),
            'total' => 600,
            'balance' => 600,
            'paid_amount' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * Helper to bypass EnsureWorkingDay middleware
     */
    private function collectionRequest(string $method, string $uri, array $data = [])
    {
        return $this->actingAs($this->user)
            ->withoutMiddleware(\App\Http\Middleware\EnsureWorkingDay::class)
            ->{$method}($uri, $data);
    }

    // ============================================
    // Collection Creation Tests
    // ============================================

    public function test_can_create_collection(): void
    {
        $response = $this->collectionRequest('postJson', '/api/collections', [
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'cash',
            'distribution_method' => 'manual',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('collections', [
            'customer_id' => $this->customer->id,
            'amount' => 500,
        ]);
    }

    public function test_can_list_collections(): void
    {
        Collection::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->collectionRequest('getJson', '/api/collections');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_get_unpaid_invoices(): void
    {
        $response = $this->collectionRequest(
            'getJson',
            '/api/collections/unpaid-invoices?customer_id='.$this->customer->id
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // ============================================
    // Deletion Prevention Tests
    // ============================================

    public function test_cannot_delete_collection(): void
    {
        $collection = Collection::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        // Trying to delete should throw exception
        $this->expectException(\App\Exceptions\BusinessException::class);
        $collection->delete();
    }

    // ============================================
    // Validation Tests
    // ============================================

    public function test_collection_requires_customer(): void
    {
        $response = $this->collectionRequest('postJson', '/api/collections', [
            'date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_id');
    }

    public function test_collection_requires_amount(): void
    {
        $response = $this->collectionRequest('postJson', '/api/collections', [
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }
}
