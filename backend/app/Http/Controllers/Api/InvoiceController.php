<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Customer;
use App\Http\Requests\Api\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Services\NumberGeneratorService;
use App\Services\FifoAllocatorService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @tags Invoice
 */
class InvoiceController extends Controller
{
    use ApiResponse;

    private NumberGeneratorService $numberGenerator;
    private FifoAllocatorService $fifoService;

    public function __construct(
        NumberGeneratorService $numberGenerator,
        FifoAllocatorService $fifoService
    ) {
        $this->numberGenerator = $numberGenerator;
        $this->fifoService = $fifoService;
    }

    /**
     * List invoices with filters.
     *
     * Returns paginated list of invoices with optional filtering.
     * Includes related customer and creator data.
     *
     * Filters:
     * - `customer_id` - Filter by customer
     * - `status` - Filter by status (active, cancelled)
     * - `date_from` / `date_to` - Date range filter
     * - `unpaid_only` - Show only invoices with balance > 0
     * - `per_page` - Pagination (omit for all)
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['customer', 'createdBy'])
            ->when($request->customer_id, fn($q, $id) => $q->where('customer_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->unpaid_only, fn($q) => $q->where('balance', '>', 0))
            ->orderByDesc('date')
            ->orderByDesc('id');

        $invoices = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return InvoiceResource::collection($invoices);
    }

    /**
     * Create new invoice.
     *
     * Creates a sales invoice with automatic FIFO inventory allocation.
     * Each item is matched to the oldest available shipment items.
     * Invoice number is auto-generated.
     *
     * Requires open daily report (working.day middleware).
     */
    public function store(StoreInvoiceRequest $request)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $this->numberGenerator->generate('invoice'),
                'customer_id' => $validated['customer_id'],
                'date' => $validated['date'],
                'type' => $validated['type'] ?? 'sale',
                'status' => 'active',
                'discount' => $validated['discount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Process items with FIFO
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $createdItems = $this->fifoService->allocateAndCreate(
                    $invoice->id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['cartons'] ?? 0
                );

                $subtotal += $createdItems->sum('subtotal');
            }

            // Update totals
            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal - ($validated['discount'] ?? 0),
                'balance' => $subtotal - ($validated['discount'] ?? 0),
            ]);

            return $this->success(
                new InvoiceResource($invoice->load('items.product', 'customer')),
                'تم إنشاء الفاتورة بنجاح',
                201
            );
        });
    }

    /**
     * Show single invoice.
     *
     * Returns detailed invoice with items, products, customer,
     * and creator information. Items include FIFO source shipment data.
     */
    public function show(Invoice $invoice)
    {
        return new InvoiceResource(
            $invoice->load(['items.product', 'items.shipmentItem', 'customer', 'createdBy'])
        );
    }

    /**
     * Cancel invoice.
     *
     * Cancels an active invoice if within the edit window (same day).
     * Restores FIFO inventory allocations automatically via observer.
     * Cannot cancel already-cancelled invoices.
     */
    public function cancel(Invoice $invoice)
    {
        // Check policy - edit window
        if (Gate::denies('cancel', $invoice)) {
            return $this->error(
                'INV_004',
                'الفاتورة خارج نافذة التعديل',
                'Invoice is outside the edit window',
                403
            );
        }

        if ($invoice->status === 'cancelled') {
            return $this->error(
                'INV_003',
                'الفاتورة ملغاة بالفعل',
                'Invoice is already cancelled',
                422
            );
        }

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ]);

        return $this->success(
            new InvoiceResource($invoice->fresh()),
            'تم إلغاء الفاتورة بنجاح'
        );
    }
}
