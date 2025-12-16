<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAlert;
use App\Services\AlertDetectionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @tags Alerts
 */
class AlertController extends Controller
{
    use \App\Traits\ApiResponse;

    private AlertDetectionService $detectionService;

    public function __construct(AlertDetectionService $detectionService)
    {
        $this->detectionService = $detectionService;
    }

    /**
     * List all alerts with filters
     * Permission: alerts.view
     */
    public function index(Request $request)
    {
        $this->checkPermission('alerts.view');

        $query = AiAlert::query()
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->severity, fn($q, $s) => $q->where('severity', $s))
            ->when($request->boolean('unread_only'), fn($q) => $q->unread())
            ->when($request->boolean('unresolved_only'), fn($q) => $q->unresolved())
            ->orderByDesc('created_at');

        $alerts = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Get dashboard summary
     * Permission: alerts.view
     */
    public function summary(): JsonResponse
    {
        $this->checkPermission('alerts.view');

        return response()->json([
            'success' => true,
            'data' => $this->detectionService->getDashboardSummary(),
        ]);
    }

    /**
     * Run detection manually
     * Permission: alerts.create
     */
    public function runDetection(): JsonResponse
    {
        $this->checkPermission('alerts.create');

        $alerts = $this->detectionService->runAllDetections();

        return response()->json([
            'success' => true,
            'message' => 'تم فحص التنبيهات بنجاح',
            'data' => [
                'price_anomalies' => count($alerts['price_anomalies']),
                'shipment_delays' => count($alerts['shipment_delays']),
                'overdue_customers' => count($alerts['overdue_customers']),
            ],
        ]);
    }

    /**
     * Mark alert as read
     */
    public function markAsRead(AiAlert $alert): JsonResponse
    {
        $this->checkPermission('alerts.view');

        $alert->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديد التنبيه كمقروء',
        ]);
    }

    /**
     * Resolve alert
     * Permission: alerts.resolve
     */
    public function resolve(AiAlert $alert): JsonResponse
    {
        $this->checkPermission('alerts.resolve');

        $alert->resolve();

        return response()->json([
            'success' => true,
            'message' => 'تم حل التنبيه',
        ]);
    }

    /**
     * Delete alert
     * Permission: alerts.delete
     */
    public function destroy(AiAlert $alert): JsonResponse
    {
        $this->checkPermission('alerts.delete');

        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التنبيه',
        ]);
    }
}
