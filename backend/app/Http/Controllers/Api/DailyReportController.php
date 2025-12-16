<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Services\DailyReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * DailyReportController
 *
 * Handles daily report operations - open, close, reopen
 */
/**
 * @tags DailyReport
 */
class DailyReportController extends Controller
{
    use ApiResponse;

    private DailyReportService $dailyReportService;

    public function __construct(DailyReportService $dailyReportService)
    {
        $this->dailyReportService = $dailyReportService;
    }

    /**
     * Get available dates for opening
     * التواريخ المتاحة لفتح يومية
     */
    public function available(): JsonResponse
    {
        $dates = $this->dailyReportService->getAvailableDates();

        return $this->success([
            'dates' => $dates,
            'current_open' => $this->dailyReportService->getCurrentOpenReport(),
        ], 'التواريخ المتاحة');
    }

    /**
     * Get current open daily report
     * اليومية المفتوحة حالياً
     */
    public function current(): JsonResponse
    {
        $report = $this->dailyReportService->getCurrentOpenReport();

        if (! $report) {
            return $this->success([
                'report' => null,
                'message' => 'لا توجد يومية مفتوحة',
            ]);
        }

        return $this->success([
            'report' => $report,
            'working_date' => $report->date,
        ]);
    }

    /**
     * Open a daily report
     * فتح يومية
     */
    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|date_format:Y-m-d',
        ]);

        $report = $this->dailyReportService->openDay($request->date);

        return $this->success([
            'report' => $report,
            'working_date' => $report->date,
        ], 'تم فتح اليومية بنجاح', 201);
    }

    /**
     * Get daily report details
     * تفاصيل يومية
     */
    public function show(string $date): JsonResponse
    {
        $report = DailyReport::where('date', $date)->first();

        if (! $report) {
            return $this->error('DAY_006', 'اليومية غير موجودة', 'Daily report not found', 404);
        }

        return $this->success($report);
    }

    /**
     * Close daily report
     * إغلاق اليومية
     */
    public function close(Request $request): JsonResponse
    {
        if (! Gate::allows('close', DailyReport::class)) {
            throw new BusinessException('AUTH_003', 'ليس لديك صلاحية إغلاق اليومية', 'Permission denied');
        }

        $report = $this->dailyReportService->getCurrentOpenReport();

        if (! $report) {
            return $this->error('DAY_004', 'لا توجد يومية مفتوحة', 'No open daily report', 422);
        }

        $report = $this->dailyReportService->closeDay($report);

        return $this->success($report, 'تم إغلاق اليومية بنجاح');
    }

    /**
     * Reopen a closed daily report
     * إعادة فتح يومية مغلقة
     */
    public function reopen(string $date): JsonResponse
    {
        if (! Gate::allows('reopen', DailyReport::class)) {
            throw new BusinessException('AUTH_003', 'ليس لديك صلاحية إعادة فتح اليومية', 'Permission denied');
        }

        $report = DailyReport::where('date', $date)->first();

        if (! $report) {
            return $this->error('DAY_006', 'اليومية غير موجودة', 'Daily report not found', 404);
        }

        $report = $this->dailyReportService->reopenDay($report);

        return $this->success($report, 'تم إعادة فتح اليومية بنجاح');
    }
}
