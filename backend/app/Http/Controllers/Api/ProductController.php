<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ProductDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProductRequest;
use App\Http\Requests\Api\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProductController
 *
 * Handles product management.
 * Delegates business logic to ProductService.
 */
/**
 * @tags Product
 */
class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductService $productService
    ) {
    }

    /**
     * List all active products
     */
    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->listProducts(
            filters: [
                'search' => $request->search,
                'active' => $request->has('active') ? $request->boolean('active') : null,
                'category' => $request->category,
            ],
            perPage: $request->per_page
        );

        return $this->success($products);
    }

    /**
     * Show single product
     */
    public function show(Product $product): JsonResponse
    {
        return $this->success($product);
    }

    /**
     * Create new product
     * Permission: products.create
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->checkPermission('products.create');

        $dto = ProductDTO::fromRequest($request);
        $product = $this->productService->createProduct($dto);

        return $this->success($product, 'تم إنشاء المنتج بنجاح', 201);
    }

    /**
     * Update product
     * Permission: products.edit
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->checkPermission('products.edit');

        $product = $this->productService->updateProduct($product, $request->validated());

        return $this->success($product, 'تم تحديث المنتج بنجاح');
    }

    /**
     * Delete product
     * Permission: products.delete
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->checkPermission('products.delete');

        $this->productService->deleteProduct($product);

        return $this->success(null, 'تم حذف المنتج بنجاح');
    }

    /**
     * Get product categories
     */
    public function categories(): JsonResponse
    {
        $categories = $this->productService->getCategories();

        return $this->success(['categories' => $categories]);
    }
}
