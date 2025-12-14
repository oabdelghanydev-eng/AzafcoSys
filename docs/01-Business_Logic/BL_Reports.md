# Reports Business Logic - منطق التقارير

## 📋 نظرة عامة

هذا الملف يوثق التقارير الرئيسية في النظام:
1. **تقرير إغلاق اليومية** - يُرسل تلقائياً عند إغلاق اليوم
2. **تقرير تصفية الشحنة** - يُنشأ عند تصفية شحنة

---

## 📊 تقرير إغلاق اليومية (Daily Closing Report)

### الوصف
تقرير شامل يحتوي على كل عمليات اليوم مع الإجماليات والأرصدة.

### متى يُنشأ
- تلقائياً عند إغلاق اليومية (`POST /api/daily/close`)
- يُحفظ كـ PDF
- يُرسل عبر Telegram (Phase 2)

---

### هيكل التقرير

```
┌─────────────────────────────────────────────────────────────────┐
│              تقرير إغلاق اليومية - 14 ديسمبر 2025               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1️⃣ فواتير المبيعات                                            │
│  ─────────────────                                              │
│  | # | رقم الفاتورة | العميل | الصنف | الكمية | وزن الوحدة | إجمالي الوزن | الإجمالي |
│  |---|--------------|--------|-------|--------|-----------|-------------|----------|
│  | 1 | INV-2412-001 | أحمد   | صنف 1 | 50     | 3 كجم     | 150 كجم     | 1,500    |
│  | 2 | INV-2412-001 | أحمد   | صنف 2 | 30     | 2.5 كجم   | 75 كجم      | 750      |
│  | 3 | INV-2412-002 | محمد   | صنف 1 | 100    | 3 كجم     | 300 كجم     | 3,000    |
│  |---|--------------|--------|-------|--------|-----------|-------------|----------|
│  | إجمالي          |        |       | 180    |           | 525 كجم     | 5,250    |
│                                                                 │
│  2️⃣ التحصيلات                                                  │
│  ───────────                                                    │
│  | # | رقم الإيصال | العميل | المبلغ | طريقة الدفع |            │
│  |---|-------------|--------|--------|-------------|            │
│  | 1 | REC-001     | أحمد   | 3000   | نقدي        |            │
│  | 2 | REC-002     | محمد   | 3500   | بنكي        |            │
│  |---|-------------|--------|--------|-------------|            │
│  | إجمالي نقدي: 3000 | إجمالي بنكي: 3500 | الإجمالي: 6500 |     │
│                                                                 │
│  3️⃣ المصروفات                                                  │
│  ───────────                                                    │
│  | # | الوصف       | النوع  | المبلغ | طريقة الدفع |            │
│  |---|-------------|--------|--------|-------------|            │
│  | 1 | وقود        | شركة   | 500    | نقدي        |            │
│  | 2 | نقل         | مورد   | 200    | نقدي        |            │
│  |---|-------------|--------|--------|-------------|            │
│  | إجمالي: 700 | نقدي: 700 | بنكي: 0 |                          │
│                                                                 │
│  4️⃣ التحويلات                                                  │
│  ───────────                                                    │
│  | # | من    | إلى   | المبلغ |                                 │
│  |---|-------|-------|--------|                                 │
│  | 1 | خزنة  | بنك   | 2000   |                                 │
│                                                                 │
│  5️⃣ الشحنات الواردة                                            │
│  ─────────────────                                              │
│  | # | رقم الشحنة | المورد | الأصناف |                         │
│  |---|------------|--------|---------|                         │
│  | 1 | SHP-001    | محمد   | 5       |                         │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│                         الإجماليات                              │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  إجمالي المبيعات:        8,500 ج.م                              │
│  إجمالي التحصيلات:       6,500 ج.م                              │
│  إجمالي المصروفات:       700 ج.م                                │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│                          الأرصدة                                │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  رصيد السوق (ديون العملاء):    25,000 ج.م                       │
│  رصيد الخزنة:                   5,300 ج.م                       │
│  رصيد البنك:                   15,500 ج.م                       │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│                     المخزون المتبقي                             │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  | الصنف      | الكمية المتبقية | الوزن المتبقي |               │
│  |------------|-----------------|---------------|               │
│  | الصنف الأول | 150 كرتونة      | 450 كجم       |               │
│  | الصنف الثاني| 200 كرتونة      | 600 كجم       |               │
│  |------------|-----------------|---------------|               │
│  | الإجمالي   | 350 كرتونة      | 1050 كجم      |               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### البيانات المطلوبة

```php
class DailyClosingReportData
{
    public string $date;
    
    // 1. فواتير المبيعات (على مستوى البند)
    public Collection $invoiceItems; // InvoiceItem[] with invoice, customer, product
    public float $totalQuantity;
    public float $totalWeight;
    public float $totalSales;
    
    // 2. التحصيلات
    public Collection $collections; // Collection[]
    public float $totalCollectionsCash;
    public float $totalCollectionsBank;
    public float $totalCollections;
    
    // 3. المصروفات
    public Collection $expenses; // Expense[]
    public float $totalExpensesCash;
    public float $totalExpensesBank;
    public float $totalExpensesCompany;
    public float $totalExpensesSupplier;
    public float $totalExpenses;
    
    // 4. التحويلات
    public Collection $transfers; // Transfer[]
    
    // 5. الشحنات الواردة
    public Collection $newShipments; // Shipment[]
    
    // 6. الأرصدة
    public float $marketBalance;  // SUM(customers.balance) WHERE balance > 0
    public float $cashboxBalance;
    public float $bankBalance;
    
    // 7. المخزون المتبقي
    public Collection $remainingStock; // [product => remaining_qty, remaining_weight]
}
```

---

### Query للبيانات

```php
class DailyReportService
{
    public function generateDailyClosingReport(string $date): DailyClosingReportData
    {
        $data = new DailyClosingReportData();
        $data->date = $date;
        
        // 1. بنود الفواتير لليوم (على مستوى الصنف)
        $data->invoiceItems = InvoiceItem::whereHas('invoice', function ($q) use ($date) {
                $q->where('date', $date)->where('status', 'active');
            })
            ->with(['invoice.customer', 'product', 'shipmentItem'])
            ->get()
            ->map(function ($item) {
                return [
                    'invoice_number' => $item->invoice->invoice_number,
                    'customer_name' => $item->invoice->customer->name,
                    'product_name' => $item->product->name_ar,
                    'quantity' => $item->quantity,
                    'weight_per_unit' => $item->shipmentItem->weight_per_unit,
                    'total_weight' => $item->quantity * $item->shipmentItem->weight_per_unit,
                    'subtotal' => $item->subtotal,
                ];
            });
        $data->totalQuantity = $data->invoiceItems->sum('quantity');
        $data->totalWeight = $data->invoiceItems->sum('total_weight');
        $data->totalSales = $data->invoiceItems->sum('subtotal');
        
        // 2. تحصيلات اليوم
        $data->collections = Collection::where('date', $date)
            ->with('customer')
            ->get();
        $data->totalCollectionsCash = $data->collections
            ->where('payment_method', 'cash')->sum('amount');
        $data->totalCollectionsBank = $data->collections
            ->where('payment_method', 'bank')->sum('amount');
        $data->totalCollections = $data->collections->sum('amount');
        
        // 3. مصروفات اليوم
        $data->expenses = Expense::where('date', $date)->get();
        $data->totalExpensesCash = $data->expenses
            ->where('payment_method', 'cash')->sum('amount');
        $data->totalExpensesBank = $data->expenses
            ->where('payment_method', 'bank')->sum('amount');
        $data->totalExpensesCompany = $data->expenses
            ->where('type', 'company')->sum('amount');
        $data->totalExpensesSupplier = $data->expenses
            ->where('type', 'supplier')->sum('amount');
        $data->totalExpenses = $data->expenses->sum('amount');
        
        // 4. تحويلات اليوم
        $data->transfers = Transfer::whereDate('created_at', $date)->get();
        
        // 5. شحنات جديدة
        $data->newShipments = Shipment::where('date', $date)
            ->with('supplier')
            ->get();
        
        // 6. الأرصدة
        $data->marketBalance = Customer::where('balance', '>', 0)->sum('balance');
        $data->cashboxBalance = Account::cashbox()->first()?->balance ?? 0;
        $data->bankBalance = Account::bank()->first()?->balance ?? 0;
        
        // 7. المخزون المتبقي (من الشحنات المفتوحة)
        $data->remainingStock = ShipmentItem::whereHas('shipment', function ($q) {
                $q->whereIn('status', ['open', 'closed']);
            })
            ->where('remaining_quantity', '>', 0)
            ->selectRaw('
                product_id,
                SUM(remaining_quantity) as total_quantity,
                SUM(remaining_quantity * weight_per_unit) as total_weight
            ')
            ->groupBy('product_id')
            ->with('product')
            ->get();
        
        return $data;
    }
}
```

---

## 📊 تقرير تصفية الشحنة (Shipment Settlement Report)

### الوصف
تقرير تفصيلي عند تصفية شحنة يشمل كل الحسابات المالية.

### متى يُنشأ
- عند تصفية الشحنة (`POST /api/shipments/{id}/settle`)
- يُحفظ كـ PDF

---

### هيكل التقرير

```
┌─────────────────────────────────────────────────────────────────┐
│              تقرير تصفية الشحنة رقم SHP-2412-001                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1️⃣ بيانات أساسية                                              │
│  ─────────────────                                              │
│  رقم الشحنة:      SHP-2412-001                                  │
│  المورد:          محمد أحمد                                     │
│  تاريخ الوصول:    01/12/2025                                    │
│  تاريخ التصفية:   14/12/2025                                    │
│  مدة الشحنة:      14 يوم                                        │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  2️⃣ المبيعات لكل صنف                                           │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  | الصنف | الكمية | الوزن | إجمالي المبيعات | متوسط السعر |     │
│  |-------|--------|-------|-----------------|-------------|     │
│  | صنف 1 | 100    | 300   | 15,000          | 50          |     │
│  | صنف 2 | 150    | 450   | 22,500          | 50          |     │
│  |-------|--------|-------|-----------------|-------------|     │
│  | الإجمالي | 250 | 750   | 37,500          | -           |     │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  3️⃣ مرتجعات الشحنة السابقة                                      │
│  ═══════════════════════════════════════════════════════════    │
│  (المرتجعات التي حصلت بعد إغلاق الشحنة السابقة)                 │
│                                                                 │
│  | الصنف | الكمية | الوزن | القيمة |                            │
│  |-------|--------|-------|--------|                            │
│  | صنف 1 | 10     | 30    | 1,500  |                            │
│  |-------|--------|-------|--------|                            │
│  | الإجمالي | 10  | 30    | 1,500  |                            │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  4️⃣ حركة المخزون                                               │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  الوارد:                                                        │
│  | الصنف | الكمية الواردة | الوزن الوارد |                      │
│  |-------|----------------|--------------|                      │
│  | صنف 1 | 200            | 600          |                      │
│  | صنف 2 | 250            | 750          |                      │
│                                                                 │
│  المُرحل من الشحنة السابقة:                                     │
│  | الصنف | الكمية | الوزن |                                     │
│  |-------|--------|-------|                                     │
│  | صنف 1 | 20     | 60    |                                     │
│                                                                 │
│  المرتجع من الشحنة السابقة:                                     │
│  | الصنف | الكمية | الوزن |                                     │
│  |-------|--------|-------|                                     │
│  | صنف 1 | 10     | 30    |                                     │
│                                                                 │
│  المباع:                                                        │
│  | الصنف | الكمية | الوزن |                                     │
│  |-------|--------|-------|                                     │
│  | صنف 1 | 200    | 600   |                                     │
│  | صنف 2 | 200    | 600   |                                     │
│                                                                 │
│  المُرحل للشحنة التالية:                                        │
│  | الصنف | الكمية | الوزن |                                     │
│  |-------|--------|-------|                                     │
│  | صنف 1 | 30     | 90    |                                     │
│  | صنف 2 | 50     | 150   |                                     │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  5️⃣ فرق الوزن                                                  │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  إجمالي الداخل:  (وارد + مرحل + مرتجع) = 690 كجم                │
│  إجمالي الخارج:  (مباع + مرحل للتالية) = 690 كجم                │
│  ──────────────────────────────────────────────                 │
│  الفرق: 0 كجم ✅                                                │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  6️⃣ مصروفات المورد                                             │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  | # | التاريخ | الوصف | المبلغ |                               │
│  |---|---------|-------|--------|                               │
│  | 1 | 02/12   | نقل   | 500    |                               │
│  | 2 | 05/12   | عمال  | 300    |                               │
│  |---|---------|-------|--------|                               │
│  | إجمالي مصروفات المورد: 800 ج.م                               │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  7️⃣ الحساب المالي للمورد                                       │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  إجمالي المبيعات:                    37,500 ج.م                 │
│  (-) مرتجعات الشحنة السابقة:         -1,500 ج.م                 │
│  ──────────────────────────────────────────────                 │
│  صافي المبيعات:                      36,000 ج.م                 │
│  (-) عمولة الشركة (6%):              -2,160 ج.م                 │
│  (-) مصروفات المورد:                   -800 ج.م                 │
│  (+) رصيد سابق:                       1,000 ج.م                 │
│  (-) دفعات للمورد:                  -10,000 ج.م                 │
│  ──────────────────────────────────────────────                 │
│  الرصيد النهائي للمورد:              24,040 ج.م                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### البيانات المطلوبة

```php
class ShipmentSettlementReportData
{
    // 1. بيانات أساسية
    public Shipment $shipment;
    public Supplier $supplier;
    public string $arrivalDate;
    public string $settlementDate;
    public int $durationDays;
    
    // 2. المبيعات لكل صنف
    public Collection $salesByProduct; // [product_id => qty, weight, total, avg_price]
    public float $totalSalesAmount;
    public float $totalSoldQuantity;
    public float $totalSoldWeight;
    
    // 3. مرتجعات الشحنة السابقة
    public Collection $previousShipmentReturns; // Carryover where reason='late_return'
    public float $totalReturnsQuantity;
    public float $totalReturnsWeight;
    public float $totalReturnsValue;
    
    // 4. حركة المخزون
    public Collection $incomingItems;      // الوارد
    public Collection $carryoverIn;        // المرحل من السابقة
    public Collection $returnsIn;          // المرتجع من السابقة
    public Collection $soldItems;          // المباع
    public Collection $carryoverOut;       // المرحل للتالية
    
    // 5. فرق الوزن
    public float $totalWeightIn;
    public float $totalWeightOut;
    public float $weightDifference;
    
    // 6. مصروفات المورد
    public Collection $supplierExpenses;
    public float $totalSupplierExpenses;
    
    // 7. الحساب المالي
    public float $totalSales;
    public float $previousReturnsDeduction;
    public float $netSales;                 // totalSales - previousReturnsDeduction
    public float $companyCommission;        // netSales * 6%
    public float $supplierExpensesDeduction;
    public float $previousBalance;
    public float $supplierPayments;         // expenses.type='supplier_payment'
    public float $finalSupplierBalance;
}
```

---

### Query للبيانات

```php
class ShipmentSettlementReportService
{
    private const COMPANY_COMMISSION_RATE = 0.06; // 6%
    
    public function generate(Shipment $shipment): ShipmentSettlementReportData
    {
        $data = new ShipmentSettlementReportData();
        
        // 1. بيانات أساسية
        $data->shipment = $shipment;
        $data->supplier = $shipment->supplier;
        $data->arrivalDate = $shipment->date->format('Y-m-d');
        $data->settlementDate = now()->format('Y-m-d');
        $data->durationDays = $shipment->date->diffInDays(now());
        
        // 2. المبيعات لكل صنف
        $data->salesByProduct = $this->getSalesByProduct($shipment);
        $data->totalSalesAmount = $data->salesByProduct->sum('total');
        $data->totalSoldQuantity = $data->salesByProduct->sum('quantity');
        $data->totalSoldWeight = $data->salesByProduct->sum('weight');
        
        // 3. مرتجعات الشحنة السابقة
        $previousShipment = $this->getPreviousShipment($shipment);
        if ($previousShipment) {
            $data->previousShipmentReturns = Carryover::where('from_shipment_id', $previousShipment->id)
                ->where('reason', 'late_return')
                ->where('to_shipment_id', $shipment->id)
                ->with('product')
                ->get();
            $data->totalReturnsQuantity = $data->previousShipmentReturns->sum('quantity');
            // Calculate weight and value...
        }
        
        // 4. حركة المخزون
        $data->incomingItems = $shipment->items()
            ->where('carryover_in_quantity', 0) // Only direct incoming
            ->get();
        
        $data->carryoverIn = Carryover::where('to_shipment_id', $shipment->id)
            ->where('reason', 'end_of_shipment')
            ->get();
            
        $data->returnsIn = Carryover::where('to_shipment_id', $shipment->id)
            ->where('reason', 'late_return')
            ->get();
            
        $data->carryoverOut = Carryover::where('from_shipment_id', $shipment->id)
            ->where('reason', 'end_of_shipment')
            ->get();
        
        // 5. فرق الوزن
        $data->totalWeightIn = $this->calculateTotalWeightIn($data);
        $data->totalWeightOut = $this->calculateTotalWeightOut($data);
        $data->weightDifference = $data->totalWeightIn - $data->totalWeightOut;
        
        // 6. مصروفات المورد
        $data->supplierExpenses = Expense::where('shipment_id', $shipment->id)
            ->where('type', 'supplier')
            ->get();
        $data->totalSupplierExpenses = $data->supplierExpenses->sum('amount');
        
        // 7. الحساب المالي (الترتيب الصحيح)
        $data->totalSales = $data->totalSalesAmount;
        $data->previousReturnsDeduction = $this->calculateReturnsValue($data->previousShipmentReturns ?? collect());
        $data->netSales = $data->totalSales - $data->previousReturnsDeduction;
        $data->companyCommission = $data->netSales * self::COMPANY_COMMISSION_RATE;
        $data->supplierExpensesDeduction = $data->totalSupplierExpenses;
        $data->previousBalance = $shipment->supplier->balance;
        $data->supplierPayments = $this->getSupplierPayments($shipment);
        
        $data->finalSupplierBalance = 
            $data->netSales
            - $data->companyCommission
            - $data->supplierExpensesDeduction
            + $data->previousBalance
            - $data->supplierPayments;
        
        return $data;
    }
    
    private function getSalesByProduct(Shipment $shipment): Collection
    {
        return DB::table('invoice_items')
            ->join('shipment_items', 'invoice_items.shipment_item_id', '=', 'shipment_items.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'shipment_items.product_id', '=', 'products.id')
            ->where('shipment_items.shipment_id', $shipment->id)
            ->where('invoices.status', 'active')
            ->selectRaw('
                products.id as product_id,
                products.name_ar as product_name,
                SUM(invoice_items.quantity) as quantity,
                SUM(invoice_items.quantity * shipment_items.weight_per_unit) as weight,
                SUM(invoice_items.subtotal) as total,
                AVG(invoice_items.unit_price) as avg_price
            ')
            ->groupBy('products.id', 'products.name_ar')
            ->get();
    }
    
    private function getSupplierPayments(Shipment $shipment): float
    {
        // Get transfers/payments to supplier during shipment period
        return Expense::where('supplier_id', $shipment->supplier_id)
            ->where('type', 'supplier_payment')
            ->whereBetween('date', [$shipment->date, now()])
            ->sum('amount');
    }
}
```

---

## 🔐 الصلاحيات

| التقرير | الصلاحية |
|---------|----------|
| تقرير إغلاق اليومية | `reports.daily` |
| تقرير تصفية الشحنة | `reports.shipment_settlement` |

---

## 📁 Files

| File | Purpose |
|------|---------|
| `Services/DailyClosingReportService.php` | جمع بيانات التقرير اليومي |
| `Services/ShipmentSettlementReportService.php` | جمع بيانات تصفية الشحنة |
| `Services/PdfGeneratorService.php` | توليد PDF |
| `Http/Controllers/Api/ReportController.php` | API endpoints |

---

## 📊 API Endpoints

| Method | Endpoint | الوصف |
|--------|----------|-------|
| `GET` | `/api/reports/daily/{date}` | عرض تقرير يومي |
| `GET` | `/api/reports/daily/{date}/pdf` | تحميل PDF |
| `GET` | `/api/reports/shipment/{id}/settlement` | عرض تقرير تصفية |
| `GET` | `/api/reports/shipment/{id}/settlement/pdf` | تحميل PDF |

---

## 📦 Settings المطلوبة

```php
// إضافة في settings table
'company_commission_rate' => 6,  // نسبة عمولة الشركة %
```

---

## ⚠️ قواعد العمل

| Rule ID | الوصف |
|---------|-------|
| BR-RPT-001 | تقرير إغلاق اليومية يُنشأ تلقائياً عند الإغلاق |
| BR-RPT-002 | تقرير التصفية يُنشأ عند تصفية الشحنة |
| BR-RPT-003 | عمولة الشركة = إجمالي المبيعات × 6% |
| BR-RPT-004 | مرتجعات الشحنة السابقة = Carryover where reason='late_return' |
| BR-RPT-005 | تحويلات المورد تُخصم من رصيده |
