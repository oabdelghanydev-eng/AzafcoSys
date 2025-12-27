# Reports Business Logic

## 📋 Overview

This file documents the main reports in the system:
1. **Daily Closing Report** - Sent automatically when closing the day
2. **Shipment Settlement Report** - Created when settling a shipment

---

## 📊 Daily Closing Report

### Description
A comprehensive report containing all daily operations with totals and balances.

### When Created
- Automatically on day close (`POST /api/daily/close`)
- Saved as PDF
- Sent via Telegram (Phase 2)

---

### Report Structure

```
┌─────────────────────────────────────────────────────────────────┐
│              Daily Closing Report - 14 December 2025            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1️⃣ Sales Invoices                                             │
│  ─────────────────                                              │
│  | # | Inv Num      | Client | Item  | Qty    | Unit Wgt  | Total Wgt   | Total    |
│  |---|--------------|--------|-------|--------|-----------|-------------|----------|
│  | 1 | INV-2412-001 | Ahmed  | Itm 1 | 50     | 3 kg      | 150 kg      | 1,500    |
│  | 2 | INV-2412-001 | Ahmed  | Itm 2 | 30     | 2.5 kg    | 75 kg       | 750      |
│  | 3 | INV-2412-002 | Mohmd  | Itm 1 | 100    | 3 kg      | 300 kg      | 3,000    |
│  |---|--------------|--------|-------|--------|-----------|-------------|----------|
│  | Total            |        |       | 180    |           | 525 kg      | 5,250    |
│                                                                 │
│  2️⃣ Collections                                                │
│  ───────────                                                    │
│  | # | Receipt Num | Client | Amount | Method      |            │
│  |---|-------------|--------|--------|-------------|            │
│  | 1 | REC-001     | Ahmed  | 3000   | Cash        |            │
│  | 2 | REC-002     | Mohmd  | 3500   | Bank        |            │
│  |---|-------------|--------|--------|-------------|            │
│  | Total Cash: 3000  | Total Bank: 3500  | Total: 6500    |     │
│                                                                 │
│  3️⃣ Expenses                                                   │
│  ───────────                                                    │
│  | # | Description | Type   | Amount | Method      |            │
│  |---|-------------|--------|--------|-------------|            │
│  | 1 | Fuel        | Comp   | 500    | Cash        |            │
│  | 2 | Transport   | Supp   | 200    | Cash        |            │
│  |---|-------------|--------|--------|-------------|            │
│  | Total: 700   | Cash: 700   | Bank: 0     |                   │
│                                                                 │
│  4️⃣ Transfers                                                  │
│  ───────────                                                    │
│  | # | From  | To    | Amount |                                 │
│  |---|-------|-------|--------|                                 │
│  | 1 | Safe  | Bank  | 2000   |                                 │
│                                                                 │
│  5️⃣ Incoming Shipments                                         │
│  ─────────────────                                              │
│  | # | Shipment Num | Supplier | Items   |                         │
│  |---|--------------|--------|---------|                         │
│  | 1 | SHP-001      | Mohmd  | 5       |                         │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│                         Totals                                  │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  Total Sales:            8,500 EGP                              │
│  Total Collections:      6,500 EGP                              │
│  Total Expenses:         700 EGP                                │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│                          Balances                               │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  Market Balance (Debts):       25,000 EGP                       │
│  Cashbox Balance:               5,300 EGP                       │
│  Bank Balance:                 15,500 EGP                       │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│                     Remaining Stock                             │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  | Item       | Remaining Qty   | Remaining Wgt |               │
│  |------------|-----------------|---------------|               │
│  | Item 1     | 150 Cartons     | 450 kg        |               │
│  | Item 2     | 200 Cartons     | 600 kg        |               │
│  |------------|-----------------|---------------|               │
│  | Total      | 350 Cartons     | 1050 kg       |               │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│                     Important Notes                             │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  - Please ensure all cash is deposited.                         │
│  - Review pending collections.                                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### Data Requirements

```php
class DailyClosingReportData
{
    public string $date;
    
    // 1. Sales Invoices (Item Level)
    public Collection $invoiceItems; // InvoiceItem[] with invoice, customer, product
    public float $totalQuantity;
    public float $totalWeight;
    public float $totalSales;
    
    // 2. Collections
    public Collection $collections; // Collection[]
    public float $totalCollectionsCash;
    public float $totalCollectionsBank;
    public float $totalCollections;
    
    // 3. Expenses
    public Collection $expenses; // Expense[]
    public float $totalExpensesCash;
    public float $totalExpensesBank;
    public float $totalExpensesCompany;
    public float $totalExpensesSupplier;
    public float $totalExpenses;
    
    // 4. Transfers
    public Collection $transfers; // Transfer[]
    
    // 5. Incoming Shipments
    public Collection $newShipments; // Shipment[]
    
    // 6. Balances
    public float $marketBalance;  // SUM(customers.balance) WHERE balance > 0
    public float $cashboxBalance;
    public float $bankBalance;
    
    // 7. Remaining Stock
    public Collection $remainingStock; // [product => remaining_qty, remaining_weight]
}
```

---

### Data Query

```php
class DailyReportService
{
    public function generateDailyClosingReport(string $date): DailyClosingReportData
    {
        $data = new DailyClosingReportData();
        $data->date = $date;
        
        // 1. Invoice Items for the day (Product Level)
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
        
        // 2. Collections
        $data->collections = Collection::where('date', $date)
            ->with('customer')
            ->get();
        $data->totalCollectionsCash = $data->collections
            ->where('payment_method', 'cash')->sum('amount');
        $data->totalCollectionsBank = $data->collections
            ->where('payment_method', 'bank')->sum('amount');
        $data->totalCollections = $data->collections->sum('amount');
        
        // 3. Expenses
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
        
        // 4. Transfers
        $data->transfers = Transfer::whereDate('created_at', $date)->get();
        
        // 5. New Shipments
        $data->newShipments = Shipment::where('date', $date)
            ->with('supplier')
            ->get();
        
        // 6. Balances
        $data->marketBalance = Customer::where('balance', '>', 0)->sum('balance');
        $data->cashboxBalance = Account::cashbox()->first()?->balance ?? 0;
        $data->bankBalance = Account::bank()->first()?->balance ?? 0;
        
        // 7. Remaining Stock (From open shipments)
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

## 📊 Shipment Settlement Report

### Description
Detailed report when settling a shipment, including all financial calculations.

### When Created
- On Settlement (`POST /api/shipments/{id}/settle`)
- Saved as PDF

---

### Report Structure

```
┌─────────────────────────────────────────────────────────────────┐
│              Shipment Settlement Report SHP-2412-001            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1️⃣ Basic Info                                                 │
│  ─────────────────                                              │
│  Shipment Num:    SHP-2412-001                                  │
│  Supplier:        Mohamed Ahmed                                 │
│  Arrival Date:    01/12/2025                                    │
│  Settle Date:     14/12/2025                                    │
│  Duration:        14 Days                                       │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  2️⃣ Sales by Product                                           │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  | Item  | Qty    | Weight | Total Sales     | Avg Price   |    │
│  |-------|--------|--------|-----------------|-------------|    │
│  | Itm 1 | 100    | 300    | 15,000          | 50          |    │
│  | Itm 2 | 150    | 450    | 22,500          | 50          |    │
│  |-------|--------|--------|-----------------|-------------|    │
│  | Total | 250    | 750    | 37,500          | -           |    │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  3️⃣ Previous Shipment Returns                                  │
│  ═══════════════════════════════════════════════════════════    │
│  (Returns that occurred after closing previous shipment)        │
│                                                                 │
│  | Item  | Qty    | Weight | Value  |                           │
│  |-------|--------|--------|--------|                           │
│  | Itm 1 | 10     | 30     | 1,500  |                           │
│  |-------|--------|--------|--------|                           │
│  | Total | 10     | 30     | 1,500  |                           │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  4️⃣ Inventory Movement                                         │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  Incoming:                                                      │
│  | Item  | Qty In         | Weight In    |                      │
│  |-------|----------------|--------------|                      │
│  | Itm 1 | 200            | 600          |                      │
│  | Itm 2 | 250            | 750          |                      │
│                                                                 │
│  Carried Over From Previous:                                    │
│  | Item  | Qty    | Weight |                                    │
│  |-------|--------|--------|                                    │
│  | Itm 1 | 20     | 60     |                                    │
│                                                                 │
│  Returned From Previous:                                        │
│  | Item  | Qty    | Weight |                                    │
│  |-------|--------|--------|                                    │
│  | Itm 1 | 10     | 30     |                                    │
│                                                                 │
│  Sold:                                                          │
│  | Item  | Qty    | Weight |                                    │
│  |-------|--------|--------|                                    │
│  | Itm 1 | 200    | 600    |                                    │
│  | Itm 2 | 200    | 600    |                                    │
│                                                                 │
│  Carried Over To Next:                                          │
│  | Item  | Qty    | Weight |                                    │
│  |-------|--------|--------|                                    │
│  | Itm 1 | 30     | 90     |                                    │
│  | Itm 2 | 50     | 150    |                                    │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  5️⃣ Weight Difference                                          │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  Total In:  (Incoming + CarryOver + Returned) = 690 kg          │
│  Total Out: (Sold + CarryOver Next) = 690 kg                    │
│  ──────────────────────────────────────────────                 │
│  Diff: 0 kg ✅                                                  │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  6️⃣ Supplier Expenses                                          │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  | # | Date    | Desc  | Amount |                               │
│  |---|---------|-------|--------|                               │
│  | 1 | 02/12   | Trans | 500    |                               │
│  | 2 | 05/12   | Labor | 300    |                               │
│  |---|---------|-------|--------|                               │
│  | Total Supp Expenses: 800 EGP                                 │
│                                                                 │
│  ═══════════════════════════════════════════════════════════    │
│  7️⃣ Supplier Financial Account                                 │
│  ═══════════════════════════════════════════════════════════    │
│                                                                 │
│  Total Sales:                        37,500 EGP                 │
│  (-) Previous Returns:               -1,500 EGP                 │
│  ──────────────────────────────────────────────                 │
│  Net Sales:                          36,000 EGP                 │
│  (-) Company Commission (6%):        -2,160 EGP                 │
│  (-) Supplier Expenses:                -800 EGP                 │
│  (+) Previous Balance:                1,000 EGP                 │
│  (-) Supplier Payments:             -10,000 EGP                 │
│  ──────────────────────────────────────────────                 │
│  Final Supplier Balance:             24,040 EGP                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### Data Requirements

```php
class ShipmentSettlementReportData
{
    // 1. Basic Info
    public Shipment $shipment;
    public Supplier $supplier;
    public string $arrivalDate;
    public string $settlementDate;
    public int $durationDays;
    
    // 2. Sales By Product
    public Collection $salesByProduct; // [product_id => qty, weight, total, avg_price]
    public float $totalSalesAmount;
    public float $totalSoldQuantity;
    public float $totalSoldWeight;
    
    // 3. Previous Shipment Returns
    public Collection $previousShipmentReturns; // Carryover where reason='late_return'
    public float $totalReturnsQuantity;
    public float $totalReturnsWeight;
    public float $totalReturnsValue;
    
    // 4. Inventory Movement
    public Collection $incomingItems;      // Incoming
    public Collection $carryoverIn;        // Carried Over In
    public Collection $returnsIn;          // Returned In
    public Collection $soldItems;          // Sold
    public Collection $carryoverOut;       // Carried Over Out
    
    // 5. Weight Difference
    public float $totalWeightIn;
    public float $totalWeightOut;
    public float $weightDifference;
    
    // 6. Supplier Expenses
    public Collection $supplierExpenses;
    public float $totalSupplierExpenses;
    
    // 7. Financial Account
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

### Data Query

```php
class ShipmentSettlementReportService
{
    private const COMPANY_COMMISSION_RATE = 0.06; // 6%
    
    public function generate(Shipment $shipment): ShipmentSettlementReportData
    {
        $data = new ShipmentSettlementReportData();
        
        // 1. Basic Info
        $data->shipment = $shipment;
        $data->supplier = $shipment->supplier;
        $data->arrivalDate = $shipment->date->format('Y-m-d');
        $data->settlementDate = now()->format('Y-m-d');
        $data->durationDays = $shipment->date->diffInDays(now());
        
        // 2. Sales By Product
        $data->salesByProduct = $this->getSalesByProduct($shipment);
        $data->totalSalesAmount = $data->salesByProduct->sum('total');
        $data->totalSoldQuantity = $data->salesByProduct->sum('quantity');
        $data->totalSoldWeight = $data->salesByProduct->sum('weight');
        
        // 3. Previous Shipment Returns
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
        
        // 4. Inventory Movement
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
        
        // 5. Weight Difference
        $data->totalWeightIn = $this->calculateTotalWeightIn($data);
        $data->totalWeightOut = $this->calculateTotalWeightOut($data);
        $data->weightDifference = $data->totalWeightIn - $data->totalWeightOut;
        
        // 6. Supplier Expenses
        $data->supplierExpenses = Expense::where('shipment_id', $shipment->id)
            ->where('type', 'supplier')
            ->get();
        $data->totalSupplierExpenses = $data->supplierExpenses->sum('amount');
        
        // 7. Financial Account (Correct Order)
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

## 🔐 Permissions

| Report | Permission |
|---------|----------|
| Daily Closing Report | `reports.daily` |
| Shipment Settlement Report | `reports.shipment_settlement` |

---

## 📁 Files

| File | Purpose |
|------|---------|
| `Services/DailyClosingReportService.php` | Aggregate daily report data |
| `Services/ShipmentSettlementReportService.php` | Aggregate settlement data |
| `Services/PdfGeneratorService.php` | PDF Generation |
| `Http/Controllers/Api/ReportController.php` | API endpoints |

---

## 📊 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------|
| `GET` | `/api/reports/daily/{date}` | View Daily Report |
| `GET` | `/api/reports/daily/{date}/pdf` | Download PDF |
| `GET` | `/api/reports/shipment/{id}/settlement` | View Settlement Report |
| `GET` | `/api/reports/shipment/{id}/settlement/pdf` | Download PDF |

---

## 📦 Required Settings

```php
// Add to settings table
'company_commission_rate' => 6,  // Company Commission Rate %
```

---

## ⚠️ Business Rules

| Rule ID | Description |
|---------|-------|
| BR-RPT-001 | Daily Closing Report created automatically on close |
| BR-RPT-002 | Settlement Report created on shipment settlement |
| BR-RPT-003 | Company Commission = Total Sales × 6% |
| BR-RPT-004 | Previous Returns = Carryover where reason='late_return' |
| BR-RPT-005 | Supplier Transfers deducted from balance |
