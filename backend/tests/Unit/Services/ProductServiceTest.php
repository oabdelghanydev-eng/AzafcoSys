<?php

namespace Tests\Unit\Services;

use App\DTOs\ProductDTO;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ProductService Unit Tests
 * 
 * Tests for product management business logic.
 */
class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductService::class);
    }

    // ==========================================
    // CREATE PRODUCT TESTS
    // ==========================================

    /** @test */
    public function it_can_create_product_with_dto(): void
    {
        $dto = new ProductDTO(
            name: 'تفاح أحمر',
            nameEn: 'Red Apple',
            category: 'فواكه',
            isActive: true
        );

        $product = $this->service->createProduct($dto);

        $this->assertDatabaseHas('products', [
            'name' => 'تفاح أحمر',
            'name_en' => 'Red Apple',
            'category' => 'فواكه',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_create_product_with_minimal_data(): void
    {
        $dto = new ProductDTO(name: 'منتج بسيط');

        $product = $this->service->createProduct($dto);

        $this->assertDatabaseHas('products', [
            'name' => 'منتج بسيط',
            'is_active' => true,
        ]);
    }

    // ==========================================
    // UPDATE PRODUCT TESTS
    // ==========================================

    /** @test */
    public function it_can_update_product(): void
    {
        $product = Product::factory()->create([
            'name' => 'Old Name',
            'category' => 'old',
        ]);

        $updatedProduct = $this->service->updateProduct($product, [
            'name' => 'New Name',
            'category' => 'new',
        ]);

        $this->assertEquals('New Name', $updatedProduct->name);
        $this->assertEquals('new', $updatedProduct->category);
    }

    // ==========================================
    // DELETE PRODUCT TESTS
    // ==========================================

    /** @test */
    public function it_can_delete_product_without_records(): void
    {
        $product = Product::factory()->create();

        $this->service->deleteProduct($product);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /** @test */
    public function it_prevents_deleting_product_with_invoice_items(): void
    {
        $product = Product::factory()->create();

        // Create invoice with item
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->deleteProduct($product);
    }

    /** @test */
    public function it_prevents_deleting_product_with_shipment_items(): void
    {
        $product = Product::factory()->create();

        // Create shipment with item
        $shipment = Shipment::factory()->create();
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->deleteProduct($product);
    }

    // ==========================================
    // ACTIVATE/DEACTIVATE TESTS
    // ==========================================

    /** @test */
    public function it_can_deactivate_product(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $deactivated = $this->service->deactivateProduct($product);

        $this->assertFalse($deactivated->is_active);
    }

    /** @test */
    public function it_can_activate_product(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $activated = $this->service->activateProduct($product);

        $this->assertTrue($activated->is_active);
    }

    // ==========================================
    // LIST PRODUCTS TESTS
    // ==========================================

    /** @test */
    public function it_lists_only_active_products_by_default(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);
        Product::factory()->count(3)->create(['is_active' => false]);

        $products = $this->service->listProducts();

        $this->assertCount(5, $products);
    }

    /** @test */
    public function it_can_list_all_products_including_inactive(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);
        Product::factory()->count(3)->create(['is_active' => false]);

        $products = $this->service->listProducts(['active' => false]);

        $this->assertCount(3, $products);
    }

    /** @test */
    public function it_can_filter_products_by_category(): void
    {
        Product::factory()->count(3)->create(['category' => 'فواكه', 'is_active' => true]);
        Product::factory()->count(2)->create(['category' => 'خضروات', 'is_active' => true]);

        $fruits = $this->service->listProducts(['category' => 'فواكه']);

        $this->assertCount(3, $fruits);
    }

    /** @test */
    public function it_can_search_products(): void
    {
        Product::factory()->create(['name' => 'تفاح أحمر', 'name_en' => 'Red Apple', 'is_active' => true]);
        Product::factory()->create(['name' => 'تفاح أخضر', 'name_en' => 'Green Apple', 'is_active' => true]);
        Product::factory()->create(['name' => 'موز', 'name_en' => 'Banana', 'is_active' => true]);

        // Search in Arabic
        $results = $this->service->listProducts(['search' => 'تفاح']);
        $this->assertCount(2, $results);

        // Search in English
        $results = $this->service->listProducts(['search' => 'Apple']);
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_can_paginate_products(): void
    {
        Product::factory()->count(25)->create(['is_active' => true]);

        $page1 = $this->service->listProducts([], perPage: 10);

        $this->assertEquals(25, $page1->total());
        $this->assertCount(10, $page1->items());
    }

    // ==========================================
    // CATEGORIES TESTS
    // ==========================================

    /** @test */
    public function it_can_get_distinct_categories(): void
    {
        Product::factory()->create(['category' => 'فواكه']);
        Product::factory()->create(['category' => 'فواكه']);
        Product::factory()->create(['category' => 'خضروات']);
        Product::factory()->create(['category' => null]);

        $categories = $this->service->getCategories();

        $this->assertCount(2, $categories);
        $this->assertContains('فواكه', $categories);
        $this->assertContains('خضروات', $categories);
    }

    // ==========================================
    // DTO TESTS
    // ==========================================

    /** @test */
    public function product_dto_from_array_works(): void
    {
        $dto = ProductDTO::fromArray([
            'name' => 'Test Product',
            'name_en' => 'Test Product EN',
            'category' => 'Test Category',
            'is_active' => true,
        ]);

        $this->assertEquals('Test Product', $dto->name);
        $this->assertEquals('Test Product EN', $dto->nameEn);
        $this->assertEquals('Test Category', $dto->category);
        $this->assertTrue($dto->isActive);
    }

    /** @test */
    public function product_dto_to_array_without_nulls_works(): void
    {
        $dto = new ProductDTO(
            name: 'Test',
            nameEn: null,
            category: 'Cat',
            isActive: true
        );

        $array = $dto->toArrayWithoutNulls();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('category', $array);
        $this->assertArrayNotHasKey('name_en', $array);
    }
}
