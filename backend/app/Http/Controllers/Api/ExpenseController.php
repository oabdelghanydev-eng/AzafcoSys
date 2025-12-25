<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\DailyReportService;
use App\Services\NumberGeneratorService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags Expense
 */
class ExpenseController extends Controller
{
    use ApiResponse;

    private NumberGeneratorService $numberGenerator;
    private DailyReportService $dailyReportService;

    public function __construct(
        NumberGeneratorService $numberGenerator,
        DailyReportService $dailyReportService
    ) {
        $this->numberGenerator = $numberGenerator;
        $this->dailyReportService = $dailyReportService;
    }

    /**
     * List expenses with filters
     * Permission: expenses.view
     */
    public function index(Request $request)
    {
        $this->checkPermission('expenses.view');

        $query = Expense::with(['supplier', 'shipment', 'createdBy'])
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->supplier_id, fn($q, $id) => $q->where('supplier_id', $id))
            ->when($request->shipment_id, fn($q, $id) => $q->where('shipment_id', $id))
            ->when($request->payment_method, fn($q, $m) => $q->where('payment_method', $m))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderByDesc('date')
            ->orderByDesc('id');

        $expenses = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return ExpenseResource::collection($expenses);
    }

    /**
     * Create new expense
     * Permission: expenses.create
     */
    public function store(Request $request): JsonResponse
    {
        $this->checkPermission('expenses.create');

        $validated = $request->validate([
            'type' => 'required|in:supplier,company',
            'supplier_id' => 'required_if:type,supplier|nullable|exists:suppliers,id',
            'shipment_id' => 'nullable|exists:shipments,id',
            'date' => 'nullable|date',  // Optional - will use daily report date
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'payment_method' => 'required|in:cash,bank',
            'category' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // Get open daily report (throws BusinessException if none open)
        $dailyReport = $this->dailyReportService->ensureOpenReport();

        // Validate: if type is supplier, supplier_id is required
        if ($validated['type'] === 'supplier' && empty($validated['supplier_id'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EXP_001',
                    'message' => 'يجب تحديد المورد لمصروفات الموردين',
                    'message_en' => 'Supplier is required for supplier expenses',
                ],
            ], 422);
        }

        return DB::transaction(function () use ($validated, $dailyReport) {
            $validated['expense_number'] = $this->numberGenerator->generate('expense');
            $validated['created_by'] = auth()->id();
            $validated['date'] = $dailyReport->date;  // Use daily report date

            // Auto-link supplier expenses to oldest open shipment if not specified
            if ($validated['type'] === 'supplier' && empty($validated['shipment_id'])) {
                $oldestOpenShipment = \App\Models\Shipment::where('supplier_id', $validated['supplier_id'])
                    ->whereIn('status', ['open', 'closed'])
                    ->orderBy('id', 'asc')
                    ->first();

                if ($oldestOpenShipment) {
                    $validated['shipment_id'] = $oldestOpenShipment->id;
                }
            }

            $expense = Expense::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المصروف بنجاح',
                'data' => new ExpenseResource($expense->load(['supplier', 'shipment'])),
            ], 201);
        });
    }

    /**
     * Show single expense
     * Permission: expenses.view
     */
    public function show(Expense $expense): ExpenseResource
    {
        $this->checkPermission('expenses.view');

        return new ExpenseResource(
            $expense->load(['supplier', 'shipment', 'createdBy'])
        );
    }

    /**
     * Update expense
     * Permission: expenses.edit
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        $this->checkPermission('expenses.edit');

        $validated = $request->validate([
            'type' => 'sometimes|required|in:supplier,company',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'shipment_id' => 'nullable|exists:shipments,id',
            'date' => 'sometimes|required|date',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'description' => 'sometimes|required|string|max:500',
            'payment_method' => 'sometimes|required|in:cash,bank',
            'category' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $expense->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المصروف بنجاح',
            'data' => new ExpenseResource($expense->fresh(['supplier', 'shipment'])),
        ]);
    }

    /**
     * Delete expense
     * Permission: expenses.delete
     */
    public function destroy(Expense $expense): JsonResponse
    {
        $this->checkPermission('expenses.delete');

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المصروف بنجاح',
        ]);
    }
}
