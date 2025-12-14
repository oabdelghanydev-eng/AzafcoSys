<?php

namespace App\Http\Middleware;

use App\Services\DailyReportService;
use App\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureWorkingDay Middleware
 * 
 * Ensures there's an open daily report before allowing operations.
 * Apply to routes that require a working day (invoices, collections, expenses).
 */
class EnsureWorkingDay
{
    private DailyReportService $dailyReportService;

    public function __construct(DailyReportService $dailyReportService)
    {
        $this->dailyReportService = $dailyReportService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check for POST/PUT/DELETE (create/update operations)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $report = $this->dailyReportService->getCurrentOpenReport();

            if (!$report) {
                throw new BusinessException(
                    'DAY_004',
                    'يجب فتح يومية أولاً قبل إجراء أي عمليات',
                    'Must open a daily report before performing operations'
                );
            }

            // Inject working date into request for use in controllers
            $request->merge(['working_date' => $report->date]);
        }

        return $next($request);
    }
}
