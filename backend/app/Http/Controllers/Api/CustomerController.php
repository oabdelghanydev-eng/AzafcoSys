<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Http\Resources\CustomerResource;
use App\Services\NumberGeneratorService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @tags Customer
 */
class CustomerController extends Controller
{
    use ApiResponse;

    private NumberGeneratorService $numberGenerator;

    public function __construct(NumberGeneratorService $numberGenerator)
    {
        $this->numberGenerator = $numberGenerator;
    }

    /**
     * List customers
     */
    public function index(Request $request)
    {
        $query = Customer::query()
            ->when($request->search, fn($q, $s) => $q->where(function ($query) use ($s) {
                $query->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            }))
            ->when($request->has('active'), fn($q) => $q->where('is_active', $request->active))
            ->when($request->with_debt, fn($q) => $q->where('balance', '>', 0))
            ->withCount('invoices')
            ->orderBy('name');

        $customers = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return CustomerResource::collection($customers);
    }

    /**
     * Create customer
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        // Generate unique code
        $validated['code'] = $this->numberGenerator->generate('customer');

        $customer = Customer::create($validated);

        return $this->success(
            new CustomerResource($customer),
            'تم إنشاء العميل بنجاح',
            201
        );
    }

    /**
     * Show customer
     */
    public function show(Customer $customer)
    {
        return new CustomerResource($customer->loadCount('invoices'));
    }

    /**
     * Update customer
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $customer->update($validated);

        return $this->success(
            new CustomerResource($customer),
            'تم تحديث العميل بنجاح'
        );
    }

    /**
     * Delete customer
     */
    public function destroy(Customer $customer): JsonResponse
    {
        // Check if customer has invoices
        if ($customer->invoices()->exists()) {
            return $this->error(
                'CUS_001',
                'لا يمكن حذف عميل له فواتير مرتبطة',
                'Cannot delete customer with related invoices',
                422
            );
        }

        // Check if customer has collections
        if ($customer->collections()->exists()) {
            return $this->error(
                'CUS_002',
                'لا يمكن حذف عميل له تحصيلات مرتبطة',
                'Cannot delete customer with related collections',
                422
            );
        }

        // Check if customer has balance
        if ($customer->balance != 0) {
            return $this->error(
                'CUS_003',
                'لا يمكن حذف عميل له رصيد غير صفري',
                'Cannot delete customer with non-zero balance',
                422
            );
        }

        $customer->delete();

        return $this->success(null, 'تم حذف العميل بنجاح');
    }
}
