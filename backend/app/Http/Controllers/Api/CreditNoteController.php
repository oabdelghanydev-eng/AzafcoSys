<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\CreditNoteService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags CreditNote
 */
class CreditNoteController extends Controller
{
    use ApiResponse;

    private CreditNoteService $creditNoteService;

    public function __construct(CreditNoteService $creditNoteService)
    {
        $this->creditNoteService = $creditNoteService;
    }

    /**
     * List all credit/debit notes
     * Permission: credit_notes.view
     */
    public function index(Request $request)
    {
        $this->checkPermission('credit_notes.view');

        $query = CreditNote::with(['customer', 'invoice', 'createdBy'])
            ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->from_date, fn($q, $v) => $q->where('date', '>=', $v))
            ->when($request->to_date, fn($q, $v) => $q->where('date', '<=', $v))
            ->orderByDesc('date')
            ->orderByDesc('id');

        return $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();
    }

    /**
     * Create credit note (reduces customer balance)
     * Permission: credit_notes.create
     */
    public function storeCredit(Request $request): JsonResponse
    {
        $this->checkPermission('credit_notes.create');

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $creditNote = $this->creditNoteService->createCreditNote($validated);

        return $this->success(
            $creditNote->load(['customer', 'invoice']),
            'تم إنشاء إشعار دائن بنجاح',
            201
        );
    }

    /**
     * Create debit note (increases customer balance)
     * Permission: credit_notes.create
     */
    public function storeDebit(Request $request): JsonResponse
    {
        $this->checkPermission('credit_notes.create');

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $debitNote = $this->creditNoteService->createDebitNote($validated);

        return $this->success(
            $debitNote->load(['customer', 'invoice']),
            'تم إنشاء إشعار مدين بنجاح',
            201
        );
    }

    /**
     * Create price adjustment note for an invoice
     * Permission: credit_notes.create
     */
    public function storePriceAdjustment(Request $request, Invoice $invoice): JsonResponse
    {
        $this->checkPermission('credit_notes.create');

        $validated = $request->validate([
            'adjustments' => 'required|array|min:1',
            'adjustments.*.product_name' => 'required|string',
            'adjustments.*.old_price' => 'required|numeric|min:0',
            'adjustments.*.new_price' => 'required|numeric|min:0',
            'adjustments.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $creditNote = $this->creditNoteService->createPriceAdjustment(
            $invoice,
            $validated['adjustments']
        );

        return $this->success(
            $creditNote->load(['customer', 'invoice']),
            'تم إنشاء تسوية الأسعار بنجاح',
            201
        );
    }

    /**
     * Show single credit/debit note
     * Permission: credit_notes.view
     */
    public function show(CreditNote $creditNote): JsonResponse
    {
        $this->checkPermission('credit_notes.view');

        return $this->success(
            $creditNote->load(['customer', 'invoice', 'createdBy', 'cancelledBy'])
        );
    }

    /**
     * Cancel a credit/debit note
     * Permission: credit_notes.cancel
     */
    public function cancel(CreditNote $creditNote): JsonResponse
    {
        $this->checkPermission('credit_notes.cancel');

        $creditNote = $this->creditNoteService->cancel($creditNote);

        return $this->success($creditNote, 'تم إلغاء الإشعار بنجاح');
    }

    /**
     * Get customer's credit notes
     * Permission: credit_notes.view
     */
    public function customerNotes(int $customerId)
    {
        $this->checkPermission('credit_notes.view');

        return CreditNote::where('customer_id', $customerId)
            ->with('invoice')
            ->orderByDesc('date')
            ->get();
    }
}
