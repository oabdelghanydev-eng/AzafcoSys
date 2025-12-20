<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShipmentRequest;
use App\Http\Requests\Api\UpdateShipmentRequest;
use App\Http\Resources\ShipmentItemResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\NumberGeneratorService;
use App\Services\ShipmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags Shipment
 */
class ShipmentController extends Controller
{
    use ApiResponse;

    private NumberGeneratorService $numberGenerator;

    private ShipmentService $shipmentService;

    public function __construct(
        NumberGeneratorService $numberGenerator,
        ShipmentService $shipmentService
    ) {
        $this->numberGenerator = $numberGenerator;
        $this->shipmentService = $shipmentService;
    }

    /**
     * List shipments with filters.
     * Permission: shipments.view
     */
    public function index(Request $request)
    {
        $this->checkPermission('shipments.view');

        $query = Shipment::with(['supplier'])
            ->when($request->supplier_id, fn($q, $id) => $q->where('supplier_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderByDesc('date')
            ->orderByDesc('id');

        $shipments = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return ShipmentResource::collection($shipments);
    }

    /**
     * Create new shipment.
     * Permission: shipments.create
     */
    public function store(StoreShipmentRequest $request)
    {
        $this->checkPermission('shipments.create');

        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $shipment = Shipment::create([
                'number' => $this->numberGenerator->generate('shipment'),
                'supplier_id' => $validated['supplier_id'],
                'date' => $validated['date'],
                'status' => 'open',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $totalCost = 0;

            foreach ($validated['items'] as $item) {
                $unitCost = $item['unit_cost'] ?? 0;
                // Total cost = cartons × unit_cost (cost per carton)
                $itemCost = $item['cartons'] * $unitCost;

                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'product_id' => $item['product_id'],
                    'weight_per_unit' => $item['weight_per_unit'],
                    'weight_label' => $item['weight_label'] ?? null,
                    'cartons' => $item['cartons'],
                    'sold_cartons' => 0,
                    'carryover_in_cartons' => 0,
                    'carryover_out_cartons' => 0,
                    'unit_cost' => $unitCost,
                    'total_cost' => $itemCost,
                ]);

                $totalCost += $itemCost;
            }

            $shipment->update(['total_cost' => $totalCost]);

            return $this->success(
                new ShipmentResource($shipment->load('items.product')),
                'تم إنشاء الشحنة بنجاح',
                201
            );
        });
    }


    /**
     * Show shipment details.
     * Permission: shipments.view
     */
    public function show(Shipment $shipment)
    {
        $this->checkPermission('shipments.view');

        return new ShipmentResource($shipment->load(['items.product', 'supplier']));
    }

    /**
     * Update shipment.
     * Permission: shipments.edit
     * Only open shipments can be updated.
     */
    public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $this->checkPermission('shipments.edit');

        // Only open shipments can be updated
        if ($shipment->status !== 'open') {
            return $this->error(
                'SHP_009',
                'لا يمكن تعديل شحنة غير مفتوحة',
                'Can only update open shipments',
                422
            );
        }

        return DB::transaction(function () use ($request, $shipment) {
            $validated = $request->validated();

            // Update shipment fields
            $shipment->update([
                'date' => $validated['date'] ?? $shipment->date,
                'notes' => $validated['notes'] ?? $shipment->notes,
            ]);

            // Update items if provided
            if (isset($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $item = ShipmentItem::where('id', $itemData['id'])
                        ->where('shipment_id', $shipment->id)
                        ->firstOrFail();

                    // Update cartons count
                    if (isset($itemData['cartons'])) {
                        if ($itemData['cartons'] < $item->sold_cartons) {
                            throw new BusinessException(
                                'SHP_010',
                                "لا يمكن تقليل الكراتين أقل من المباع ({$item->sold_cartons})",
                                "Cannot reduce cartons below sold amount ({$item->sold_cartons})"
                            );
                        }
                        $item->cartons = $itemData['cartons'];
                    }

                    if (isset($itemData['weight_per_unit'])) {
                        $item->weight_per_unit = $itemData['weight_per_unit'];
                    }

                    $item->save();
                }
            }

            return $this->success(
                new ShipmentResource($shipment->fresh(['items.product'])),
                'تم تحديث الشحنة بنجاح'
            );
        });
    }


    /**
     * Delete shipment.
     * Permission: shipments.delete
     */
    public function destroy(Shipment $shipment): JsonResponse
    {
        $this->checkPermission('shipments.delete');

        // Check if shipment has related invoice items
        $hasInvoices = $shipment->items()
            ->whereHas('invoiceItems')
            ->exists();

        if ($hasInvoices) {
            return $this->error(
                'SHP_001',
                'لا يمكن حذف شحنة لها فواتير مرتبطة',
                'Cannot delete shipment with related invoices',
                422
            );
        }

        if ($shipment->status === 'settled') {
            return $this->error(
                'SHP_002',
                'لا يمكن حذف شحنة مُصفاة',
                'Cannot delete settled shipment',
                422
            );
        }

        $shipment->delete();

        return $this->success(null, 'تم حذف الشحنة بنجاح');
    }

    /**
     * Close shipment.
     * Permission: shipments.close
     */
    public function close(Shipment $shipment): JsonResponse
    {
        $this->checkPermission('shipments.close');

        if ($shipment->status !== 'open') {
            return $this->error(
                'SHP_004',
                'الشحنة ليست مفتوحة',
                'Shipment is not open',
                422
            );
        }

        $shipment->update(['status' => 'closed']);

        return $this->success(
            new ShipmentResource($shipment->fresh()),
            'تم إغلاق الشحنة بنجاح'
        );
    }

    /**
     * Settle shipment with carryover.
     *
     * Finalizes shipment and transfers remaining quantities to next shipment.
     * If no remaining quantities, settles immediately.
     * Requires next_shipment_id if carryover needed.
     *
     * Returns settlement report with profit/loss calculations.
     */
    public function settle(Request $request, Shipment $shipment): JsonResponse
    {
        if ($shipment->status === 'settled') {
            return $this->error(
                'SHP_003',
                'الشحنة مُصفاة بالفعل',
                'Shipment is already settled',
                422
            );
        }

        // Check if there are remaining cartons
        $hasRemaining = $shipment->items->contains(fn($item) => $item->remaining_cartons > 0);

        if ($hasRemaining) {
            // Carryover required - need next_shipment_id
            $request->validate([
                'next_shipment_id' => 'required|exists:shipments,id',
            ]);

            $nextShipment = Shipment::findOrFail($request->next_shipment_id);

            if ($nextShipment->status !== 'open') {
                return $this->error(
                    'SHP_005',
                    'الشحنة التالية يجب أن تكون مفتوحة',
                    'Next shipment must be open',
                    422
                );
            }

            try {
                $this->shipmentService->settle($shipment, $nextShipment);
            } catch (\Exception $e) {
                return $this->error(
                    'SHP_006',
                    $e->getMessage(),
                    'Settlement failed',
                    422
                );
            }
        } else {
            // No remaining - just settle
            $shipment->update([
                'status' => 'settled',
                'settled_at' => now(),
                'settled_by' => auth()->id(),
            ]);
        }

        // Generate settlement report
        $report = $this->shipmentService->generateSettlementReport($shipment->fresh());

        return $this->success($report, 'تم تصفية الشحنة بنجاح');
    }

    /**
     * Unsettle shipment.
     *
     * Reverses settlement and restores carryover items.
     * Only works on settled shipments.
     * Used to correct settlement errors.
     */
    public function unsettle(Shipment $shipment): JsonResponse
    {
        if ($shipment->status !== 'settled') {
            return $this->error(
                'SHP_007',
                'الشحنة ليست مُصفاة',
                'Shipment is not settled',
                422
            );
        }

        try {
            $this->shipmentService->unsettle($shipment);

            return $this->success(
                new ShipmentResource($shipment->fresh()),
                'تم إلغاء تصفية الشحنة بنجاح'
            );
        } catch (\Exception $e) {
            return $this->error(
                'SHP_008',
                $e->getMessage(),
                'Unsettle failed',
                422
            );
        }
    }

    /**
     * Get settlement report.
     *
     * Returns financial summary for shipment including:
     * - Total cost, total sales, profit/loss
     * - Per-item breakdown with quantities and margins
     */
    public function settlementReport(Shipment $shipment): JsonResponse
    {
        $report = $this->shipmentService->generateSettlementReport($shipment);

        return $this->success($report);
    }

    /**
     * Get current stock.
     *
     * Returns FIFO inventory breakdown by product.
     * Shows remaining quantities per shipment item for stock tracking.
     * Filter by product_id optional.
     */
    public function stock(Request $request): JsonResponse
    {
        $items = ShipmentItem::with(['product', 'shipment:id,number,date'])
            ->whereHas('shipment', fn($q) => $q->whereIn('status', ['open', 'closed']))
            ->when($request->product_id, fn($q, $id) => $q->where('product_id', $id))
            ->orderBy('product_id')
            ->orderBy('created_at')
            ->get()
            ->filter(fn($item) => $item->remaining_cartons > 0); // Use accessor

        // Group by product
        $grouped = $items->groupBy('product_id')->map(function ($productItems) {
            $first = $productItems->first();

            return [
                'product_id' => $first->product_id,
                'product_name' => $first->product->name,
                'total_quantity' => $productItems->sum('remaining_cartons'), // Sum cartons
                'items' => ShipmentItemResource::collection($productItems),
            ];
        })->values();

        return $this->success($grouped);
    }
}
