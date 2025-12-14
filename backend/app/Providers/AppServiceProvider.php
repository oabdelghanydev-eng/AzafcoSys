<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

// Models
use App\Models\Invoice;
use App\Models\Collection;
use App\Models\CollectionAllocation;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ReturnModel;
use App\Models\Expense;
use App\Models\User;
use App\Models\DailyReport;

// Observers
use App\Observers\InvoiceObserver;
use App\Observers\CollectionObserver;
use App\Observers\CollectionAllocationObserver;
use App\Observers\ShipmentObserver;
use App\Observers\ShipmentItemObserver;
use App\Observers\ReturnObserver;
use App\Observers\ExpenseObserver;

// Policies
use App\Policies\InvoicePolicy;
use App\Policies\CollectionPolicy;
use App\Policies\UserPolicy;
use App\Policies\ShipmentPolicy;
use App\Policies\DailyReportPolicy;

// Scramble API Documentation
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Tag;

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
}
