# Performance & Caching Strategy

## 📋 Overview

System performance optimization and caching strategies.

---

## 🚀 Database Optimization

### 1. Indexes Strategy

```sql
-- Invoices
CREATE INDEX idx_invoices_customer_status ON invoices(customer_id, status);
CREATE INDEX idx_invoices_date ON invoices(date);
CREATE INDEX idx_invoices_balance ON invoices(balance) WHERE balance > 0;

-- Collections
CREATE INDEX idx_collections_customer_date ON collections(customer_id, date);
CREATE INDEX idx_collections_payment ON collections(payment_method);

-- Shipments
CREATE INDEX idx_shipments_status_date ON shipments(status, date);
CREATE INDEX idx_shipments_supplier ON shipments(supplier_id);

-- Shipment Items (FIFO Critical)
-- ⚠️ FIFO uses shipments.date not created_at - needs JOIN
CREATE INDEX idx_items_fifo ON shipment_items(product_id, remaining_quantity);
CREATE INDEX idx_items_shipment ON shipment_items(shipment_id);
```

### 2. Query Optimization

```php
// ❌ N+1 Problem
$invoices = Invoice::all();
foreach ($invoices as $invoice) {
    echo $invoice->customer->name;      // N queries
    echo $invoice->items->count();      // N queries
}

// ✅ Eager Loading
$invoices = Invoice::with([
    'customer:id,name,phone',
    'items:id,invoice_id,product_id,quantity',
    'items.product:id,name',
    'allocations:id,invoice_id,amount',
])->get();
```

### 3. Pagination

```php
// Always paginate large datasets
public function index(Request $request)
{
    return Invoice::query()
        ->with(['customer:id,name', 'items'])
        ->when($request->status, fn($q, $s) => $q->where('status', $s))
        ->when($request->date, fn($q, $d) => $q->whereDate('date', $d))
        ->orderByDesc('date')
        ->paginate($request->per_page ?? 25);
}
```

---

## 💾 Caching Strategy

### 1. Cache Configuration

```php
// config/cache.php (Production)
'default' => env('CACHE_DRIVER', 'redis'),

// .env (Production)
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 2. What to Cache

| Data | TTL | Reason |
|----------|-----|-------|
| Settings | 1 hour | Rarely changes |
| Products | 24 hours | Static |
| Suppliers | 1 hour | Rarely changes |
| Dashboard Stats | 5 minutes | Frequently changes |
| User Permissions | 1 hour | On login |

### 3. Cache Implementation

```php
// SettingsService
class SettingsService
{
    public function get(string $key, $default = null)
    {
        return Cache::remember("settings.{$key}", 3600, function () use ($key, $default) {
            return Setting::where('key', $key)->value('value') ?? $default;
        });
    }
    
    public function set(string $key, $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("settings.{$key}");
    }
}

// ProductsController
public function index()
{
    return Cache::remember('products.all', 86400, function () {
        return Product::all();
    });
}

// DashboardController
public function stats()
{
    return Cache::remember('dashboard.stats', 300, function () {
        return [
            'today_sales' => Invoice::whereDate('date', today())->sum('total'),
            'today_collections' => Collection::whereDate('date', today())->sum('amount'),
            'pending_invoices' => Invoice::where('balance', '>', 0)->count(),
            'open_shipments' => Shipment::where('status', 'open')->count(),
        ];
    });
}
```

### 4. Cache Invalidation

```php
// Observer Pattern for Cache Invalidation
class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        Cache::forget('dashboard.stats');
    }
    
    public function updated(Invoice $invoice): void
    {
        Cache::forget('dashboard.stats');
        Cache::forget("customer.{$invoice->customer_id}.balance");
    }
}
```

---

## ⚡ API Performance

### 1. Response Compression

```php
// app/Http/Middleware/CompressResponse.php
public function handle($request, Closure $next)
{
    $response = $next($request);
    
    if ($this->shouldCompress($request, $response)) {
        $content = gzencode($response->getContent(), 9);
        $response->setContent($content);
        $response->header('Content-Encoding', 'gzip');
    }
    
    return $response;
}
```

### 2. API Resource Optimization

```php
// Sparse Fieldsets
// GET /api/invoices?fields=id,invoice_number,total

public function index(Request $request)
{
    $query = Invoice::query();
    
    if ($fields = $request->fields) {
        $query->select(explode(',', $fields));
    }
    
    return InvoiceResource::collection($query->paginate());
}
```

### 3. Database Query Logging (Development)

```php
// AppServiceProvider (Development Only)
if (app()->environment('local')) {
    DB::listen(function ($query) {
        if ($query->time > 100) { // > 100ms
            Log::warning('Slow Query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
            ]);
        }
    });
}
```

---

## 📊 Performance Metrics

### Target Metrics

| Metric | Target | Acceptable |
|--------|--------|------------|
| API Response Time | < 200ms | < 500ms |
| Database Query Time | < 50ms | < 100ms |
| Page Load Time | < 2s | < 3s |
| Queries per Request | < 10 | < 20 |

### Monitoring

```php
// Middleware for Performance Logging
class MeasurePerformance
{
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        
        $response = $next($request);
        
        $duration = (microtime(true) - $start) * 1000;
        
        $response->header('X-Response-Time', round($duration, 2) . 'ms');
        
        if ($duration > 500) {
            Log::warning('Slow Request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'duration' => $duration,
            ]);
        }
        
        return $response;
    }
}
```

---

## 🔄 Eager Loading Guide

### Invoice Module
```php
Invoice::with([
    'customer:id,name,phone,balance',
    'items.product:id,name',
    'items.shipmentItem:id,shipment_id,weight_per_unit',
    'items.shipmentItem.shipment:id,number',
    'allocations.collection:id,receipt_number,date',
    'createdBy:id,name',
])
```

### Shipment Module
```php
Shipment::with([
    'supplier:id,name',
    'items.product:id,name',
    'items.invoiceItems:id,shipment_item_id,quantity',
    'carryoversOut:id,from_shipment_id,quantity,reason',
    'carryoversIn:id,to_shipment_id,quantity,reason',
])
```

### Collection Module
```php
Collection::with([
    'customer:id,name,balance',
    'allocations.invoice:id,invoice_number,total,balance',
    'createdBy:id,name',
])
```

---

## 🔗 Related Documentation

- [Database_Schema.md](../00-Core/Database_Schema.md) - Indexes definitions
- [Backend_Implementation.md](../02-Technical_Specs/Backend_Implementation.md) - Implementation details
