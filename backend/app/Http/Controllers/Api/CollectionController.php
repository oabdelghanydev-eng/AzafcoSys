<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCollectionRequest;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use App\Services\CollectionDistributorService;
use App\Services\NumberGeneratorService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags Collection
 */
class CollectionController extends Controller
{
    use ApiResponse;

    private NumberGeneratorService $numberGenerator;

    private CollectionDistributorService $distributorService;

    public function __construct(
        NumberGeneratorService $numberGenerator,
        CollectionDistributorService $distributorService
    ) {
        $this->numberGenerator = $numberGenerator;
        $this->distributorService = $distributorService;
    }

    /**
     * List collections with filters.
     * Permission: collections.view
     */
    public function index(Request $request)
    {
        $this->checkPermission('collections.view');

        $query = Collection::with(['customer'])
            ->when($request->customer_id, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->payment_method, fn ($q, $m) => $q->where('payment_method', $m))
            ->orderByDesc('date')
            ->orderByDesc('id');

        $collections = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return CollectionResource::collection($collections);
    }

    /**
     * Create payment collection.
     * Permission: collections.create
     */
    public function store(StoreCollectionRequest $request)
    {
        $this->checkPermission('collections.create');

        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $collection = Collection::create([
                'receipt_number' => $this->numberGenerator->generate('collection'),
                'customer_id' => $validated['customer_id'],
                'date' => $validated['date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'distribution_method' => $validated['distribution_method'] ?? 'auto',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Manual distribution if provided
            if (! empty($validated['allocations'])) {
                $allocations = collect($validated['allocations'])
                    ->pluck('amount', 'invoice_id')
                    ->toArray();
                $this->distributorService->distributeManual($collection, $allocations);
            }

            return $this->success(
                new CollectionResource($collection->load('allocations.invoice')),
                'تم إنشاء التحصيل بنجاح',
                201
            );
        });
    }

    /**
     * Show collection details.
     * Permission: collections.view
     */
    public function show(Collection $collection)
    {
        $this->checkPermission('collections.view');

        return new CollectionResource($collection->load(['customer', 'allocations.invoice']));
    }

    /**
     * Delete collection.
     * Permission: collections.delete
     */
    public function destroy(Collection $collection)
    {
        $this->checkPermission('collections.delete');

        $collection->delete();

        return $this->success(null, 'تم حذف التحصيل بنجاح');
    }

    /**
     * Get unpaid invoices for a customer.
     *
     * Returns list of invoices with balance > 0 for manual allocation.
     * Used by the collection form UI for manual distribution.
     */
    public function getUnpaidInvoices(Request $request)
    {
        $request->validate(['customer_id' => 'required|exists:customers,id']);

        $invoices = $this->distributorService->getUnpaidInvoices($request->customer_id);

        return $this->success($invoices);
    }
}
