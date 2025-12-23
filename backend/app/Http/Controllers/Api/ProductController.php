<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Product
 */
class ProductController extends Controller
{
    use ApiResponse;

    /**
     * List all active products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->when($request->search, fn($q, $s) => $q->where(function ($query) use ($s) {
                $query->where('name', 'like', "%{$s}%")
                    ->orWhere('name_en', 'like', "%{$s}%");
            }))
            ->when($request->has('active'), fn($q) => $q->where('is_active', $request->active))
            ->when($request->category, fn($q, $c) => $q->where('category', $c))
            ->orderBy('id');

        // By default only show active products
        if (!$request->has('active')) {
            $query->where('is_active', true);
        }

        $products = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

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
    public function store(Request $request): JsonResponse
    {
        $this->checkPermission('products.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:products',
            'name_en' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($validated);

        return $this->success($product, 'تم إنشاء المنتج بنجاح', 201);
    }

    /**
     * Update product
     * Permission: products.edit
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $this->checkPermission('products.edit');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:products,name,' . $product->id,
            'name_en' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return $this->success($product, 'تم تحديث المنتج بنجاح');
    }

    /**
     * Delete product
     * Permission: products.delete
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->checkPermission('products.delete');

        // Check if product has been used in any invoices or shipments
        $hasInvoiceItems = $product->invoiceItems()->exists();
        $hasShipmentItems = $product->shipmentItems()->exists();

        if ($hasInvoiceItems || $hasShipmentItems) {
            return $this->error(
                'PRD_001',
                'لا يمكن حذف منتج له سجلات. يمكنك تعطيله بدلاً من ذلك.',
                'Cannot delete product with records. Deactivate instead.',
                422
            );
        }

        $product->delete();

        return $this->success(null, 'تم حذف المنتج بنجاح');
    }
}
