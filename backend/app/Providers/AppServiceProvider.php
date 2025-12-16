<?php

namespace App\Providers;

use App\Models\Collection;
use App\Models\CollectionAllocation;
// Models
use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Observers\CollectionAllocationObserver;
use App\Observers\CollectionObserver;
// Observers
use App\Observers\ExpenseObserver;
use App\Observers\InvoiceObserver;
use App\Observers\ReturnObserver;
use App\Observers\ShipmentItemObserver;
use App\Observers\ShipmentObserver;
use App\Policies\CollectionPolicy;
use App\Policies\DailyReportPolicy;
// Policies
use App\Policies\InvoicePolicy;
use App\Policies\ShipmentPolicy;
use App\Policies\UserPolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
// Scramble API Documentation
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Tag;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Observers
        Invoice::observe(InvoiceObserver::class);
        Collection::observe(CollectionObserver::class);
        CollectionAllocation::observe(CollectionAllocationObserver::class);
        Shipment::observe(ShipmentObserver::class);
        ShipmentItem::observe(ShipmentItemObserver::class);
        ReturnModel::observe(ReturnObserver::class);
        Expense::observe(ExpenseObserver::class);

        // Configure Rate Limiters (2025 Security Best Practice)
        $this->configureRateLimiting();

        // Register Policies
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Shipment::class, ShipmentPolicy::class);
        Gate::policy(DailyReport::class, DailyReportPolicy::class);

        // Configure Scramble API Documentation with Bearer Token Authentication
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                // Security Scheme - Bearer Token
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );

                // API Info - Contact & License
                $openApi->info->contact = [
                    'name' => 'API Support',
                    'email' => 'support@system.local',
                ];
                $openApi->info->license = [
                    'name' => 'Proprietary',
                    'url' => 'https://system.local/license',
                ];

                // Tag Descriptions for Endpoint Grouping
                $openApi->tags = [
                    new Tag('Auth', 'Authentication endpoints - Login, logout, and user info'),
                    new Tag('Dashboard', 'Dashboard statistics and recent activity'),
                    new Tag('Invoice', 'Sales invoices with FIFO inventory allocation'),
                    new Tag('Collection', 'Payment collections and invoice distribution'),
                    new Tag('Shipment', 'Inventory shipments, closing, and settlement'),
                    new Tag('Return', 'Product returns and refunds'),
                    new Tag('Account', 'Treasury accounts - Cashbox and bank summary'),
                    new Tag('Cashbox', 'Cash operations - Deposits and withdrawals'),
                    new Tag('Bank', 'Bank operations - Deposits and withdrawals'),
                    new Tag('Transfer', 'Fund transfers between accounts'),
                    new Tag('DailyReport', 'Daily working sessions - Open, close, and reports'),
                    new Tag('Report', 'PDF reports - Daily closing and settlement'),
                    new Tag('Customer', 'Customer management'),
                    new Tag('Supplier', 'Supplier management'),
                    new Tag('Product', 'Product catalog'),
                    new Tag('Expense', 'Expense tracking'),
                    new Tag('Setting', 'System settings and configuration'),
                    new Tag('User', 'User management and permissions'),
                ];
            });
    }

    /**
     * Configure rate limiters (2025 Security Best Practice)
     */
    protected function configureRateLimiting(): void
    {
        // API Rate Limiter - 60 requests per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Login Rate Limiter - 5 attempts per minute (prevent brute force)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->input('email') . '|' . $request->ip()
            )->response(function () {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_004',
                        'message' => 'تم تجاوز الحد المسموح. انتظر دقيقة.',
                        'message_en' => 'Too many attempts. Wait one minute.',
                    ],
                ], 429);
            });
        });

        // Collections Rate Limiter - 30 per minute (prevent rapid duplicate payments)
        RateLimiter::for('collections', function (Request $request) {
            return Limit::perMinute(30)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Sensitive Operations - 10 per minute
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
