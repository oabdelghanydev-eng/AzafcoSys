<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Http\Resources\SupplierResource;
use App\Http\Requests\Api\StoreSupplierRequest;
use App\Http\Requests\Api\UpdateSupplierRequest;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @tags Supplier
 */
class SupplierController extends Controller
{
    use \App\Traits\ApiResponse;

    private NumberGeneratorService $numberGenerator;

    public function __construct(NumberGeneratorService $numberGenerator)
    {
        $this->numberGenerator = $numberGenerator;
    }

    /**
     * List suppliers with filters
     * Permission: suppliers.view
     */
    public function index(Request $request)
    {
        $this->checkPermission('suppliers.view');

        $query = Supplier::query()
            ->when($request->search, fn($q, $s) => $q->where(function ($query) use ($s) {
                $query->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            }))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('with_balance'), fn($q) => $q->where('balance', '!=', 0))
            ->orderBy('name');

        $suppliers = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return SupplierResource::collection($suppliers);
    }

    /**
     * Create new supplier
     * Permission: suppliers.create
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->checkPermission('suppliers.create');

        $validated = $request->validated();
        $validated['code'] = $this->numberGenerator->generate('supplier');

        $supplier = Supplier::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المورد بنجاح',
            'data' => new SupplierResource($supplier)
        ], 201);
    }

    /**
     * Show single supplier
     * Permission: suppliers.view
     */
    public function show(Supplier $supplier): SupplierResource
    {
        $this->checkPermission('suppliers.view');

        return new SupplierResource(
            $supplier->loadCount(['shipments', 'expenses'])
        );
    }

    /**
     * Update supplier
     * Permission: suppliers.edit
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->checkPermission('suppliers.edit');

        $supplier->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المورد بنجاح',
            'data' => new SupplierResource($supplier->fresh())
        ]);
    }

    /**
     * Delete supplier (soft check for relations)
     * Permission: suppliers.delete
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->checkPermission('suppliers.delete');

        // Check if supplier has shipments
        if ($supplier->shipments()->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUP_001',
                    'message' => 'لا يمكن حذف مورد له شحنات مرتبطة',
                    'message_en' => 'Cannot delete supplier with related shipments'
                ]
            ], 422);
        }

        // Check if supplier has expenses
        if ($supplier->expenses()->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUP_002',
                    'message' => 'لا يمكن حذف مورد له مصروفات مرتبطة',
                    'message_en' => 'Cannot delete supplier with related expenses'
                ]
            ], 422);
        }

        // Check if supplier has balance
        if ($supplier->balance != 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUP_003',
                    'message' => 'لا يمكن حذف مورد له رصيد غير صفري',
                    'message_en' => 'Cannot delete supplier with non-zero balance'
                ]
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المورد بنجاح'
        ]);
    }
}
