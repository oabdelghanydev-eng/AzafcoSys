<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnModel;
use App\Http\Requests\Api\StoreReturnRequest;
use App\Services\ReturnService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @tags Return
 */
class ReturnController extends Controller
{
    use ApiResponse;

    private ReturnService $returnService;

    public function __construct(ReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    /**
     * List returns
     */
    public function index(Request $request)
    {
        $returns = ReturnModel::with(['customer'])
            ->when($request->customer_id, fn($q, $id) => $q->where('customer_id', $id))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->per_page ?? 15);

        return response()->json($returns);
    }

    /**
     * Create return
     */
    public function store(StoreReturnRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $return = $this->returnService->createReturn(
                $validated['customer_id'],
                $validated['items'],
                $validated['original_invoice_id'] ?? null,
                $validated['notes'] ?? null
            );

            return $this->success($return, 'تم إنشاء المرتجع بنجاح', 201);
        } catch (\Exception $e) {
            return $this->error(
                'RET_001',
                $e->getMessage(),
                'Return creation failed',
                422
            );
        }
    }

    /**
     * Show return
     */
    public function show(ReturnModel $return): JsonResponse
    {
        return $this->success(
            $return->load(['customer', 'items.product', 'originalInvoice'])
        );
    }

    /**
     * Cancel return
     */
    public function cancel(ReturnModel $return): JsonResponse
    {
        if ($return->status === 'cancelled') {
            return $this->error(
                'RET_002',
                'المرتجع ملغي بالفعل',
                'Return is already cancelled',
                422
            );
        }

        try {
            $this->returnService->cancelReturn($return);
            return $this->success($return->fresh(), 'تم إلغاء المرتجع بنجاح');
        } catch (\Exception $e) {
            return $this->error(
                'RET_003',
                $e->getMessage(),
                'Return cancellation failed',
                422
            );
        }
    }
}
