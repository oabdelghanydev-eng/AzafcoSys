<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Http\Resources\CustomerResource;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Http\Requests\Api\UpdateCustomerRequest;
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
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();
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
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

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
