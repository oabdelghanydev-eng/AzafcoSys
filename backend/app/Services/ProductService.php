<?php

namespace App\Services;

use App\DTOs\ProductDTO;
use App\Exceptions\BusinessException;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * ProductService
 * 
 * Handles product management business logic.
 * 
 * @package App\Services
 */
class ProductService extends BaseService
{
    /**
     * List products with filters.
     *
     * @param array $filters Optional filters (search, active, category)
     * @param int|null $perPage Items per page (null = all)
     * @return LengthAwarePaginator|Collection
     */
    public function listProducts(array $filters = [], ?int $perPage = null): LengthAwarePaginator|Collection
    {
        $query = Product::query()
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where(function ($query) use ($s) {
                $query->where('name', 'like', "%{$s}%")
                    ->orWhere('name_en', 'like', "%{$s}%");
            }))
            ->when(isset($filters['active']), fn($q) => $q->where('is_active', $filters['active']))
            ->when($filters['category'] ?? null, fn($q, $c) => $q->where('category', $c))
            ->orderBy('id');

        // By default only show active products (unless explicitly filtered)
        if (!isset($filters['active'])) {
            $query->where('is_active', true);
        }

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Get a single product.
     *
     * @param Product $product
     * @return Product
     */
    public function getProduct(Product $product): Product
    {
        return $product;
    }

    /**
     * Create a new product.
     *
     * @param ProductDTO $dto Product data
     * @return Product
     */
    public function createProduct(ProductDTO $dto): Product
    {
        return $this->transactionWithLog('Create product', function () use ($dto) {
            return Product::create($dto->toArrayWithoutNulls());
        }, ['name' => $dto->name]);
    }

    /**
     * Update a product.
     *
     * @param Product $product Product to update
     * @param array $data Update data
     * @return Product
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);

        $this->log('Product updated', ['product_id' => $product->id]);

        return $product->fresh();
    }

    /**
     * Delete a product.
     *
     * @param Product $product Product to delete
     * @throws BusinessException If product has records
     */
    public function deleteProduct(Product $product): void
    {
        $this->validateCanDelete($product);

        $this->transactionWithLog('Delete product', function () use ($product) {
            $product->delete();
        }, ['product_id' => $product->id]);
    }

    /**
     * Deactivate a product (soft delete alternative).
     *
     * @param Product $product Product to deactivate
     * @return Product
     */
    public function deactivateProduct(Product $product): Product
    {
        $product->update(['is_active' => false]);

        $this->log('Product deactivated', ['product_id' => $product->id]);

        return $product->fresh();
    }

    /**
     * Activate a product.
     *
     * @param Product $product Product to activate
     * @return Product
     */
    public function activateProduct(Product $product): Product
    {
        $product->update(['is_active' => true]);

        $this->log('Product activated', ['product_id' => $product->id]);

        return $product->fresh();
    }

    /**
     * Validate that a product can be deleted.
     *
     * @throws BusinessException If product has invoice or shipment records
     */
    protected function validateCanDelete(Product $product): void
    {
        $hasInvoiceItems = $product->invoiceItems()->exists();
        $hasShipmentItems = $product->shipmentItems()->exists();

        if ($hasInvoiceItems || $hasShipmentItems) {
            $this->throwBusinessError(
                'PRD_001',
                'لا يمكن حذف منتج له سجلات. يمكنك تعطيله بدلاً من ذلك.',
                'Cannot delete product with records. Deactivate instead.'
            );
        }
    }

    /**
     * Get all product categories.
     *
     * @return array
     */
    public function getCategories(): array
    {
        return Product::distinct()
            ->whereNotNull('category')
            ->pluck('category')
            ->toArray();
    }

    protected function getServiceName(): string
    {
        return 'ProductService';
    }
}
