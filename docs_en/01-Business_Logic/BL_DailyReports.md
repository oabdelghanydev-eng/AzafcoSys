# Daily Reports

## 📋 Overview

The Daily Reports system depends on a **Daily Working Session** - The user opens the day once and all operations automatically take the same date.

---

## 🔄 Working Day Session

```
┌─────────────────────────────────────────────────────────────┐
│                    WORKING DAY SESSION                       │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1️⃣ Start of Work:                                          │
│     ─────────────                                            │
│     User selects: "Open Day 12 Dec"                         │
│     → System saves date in Session                          │
│     → Opening Balance = Last Closing Balance                │
│                                                              │
│  2️⃣ During Work:                                            │
│     ─────────────                                            │
│     Invoice ← date = 12 (Automatic)                          │
│     Collection ← date = 12 (Automatic)                       │
│     Expense ← date = 12 (Automatic)                          │
│     (Date is not entered in each operation)                  │
│                                                              │
│  3️⃣ End of Work:                                            │
│     ─────────────                                            │
│     "Close Day 12" with permission daily.close              │
│     → Calculate Totals and Closing Balance                  │
│     → status = 'closed'                                      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 📅 Dates Available for Opening

### Settings

```php
// Settings Table
'backdated_days' => 2  // Number of days allowed to open retroactively
```

### Logic

```
┌─────────────────────────────────────────────────────────────┐
│   📅 Today: 14 Dec | Setting: backdated_days = 2            │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│   Time Range: 12 → 14 Dec                                   │
│                                                              │
│   Dates Available for Opening:                              │
│   ─────────────────────────                                  │
│   12 Dec: Closed ❌ → Not Available                         │
│   13 Dec: Closed ❌ → Not Available                         │
│   14 Dec: Open/New ✅ → Available                           │
│                                                              │
│   Result: Only 14 Dec can be opened                         │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

> **Rule:** Currently closed day cannot be opened except with `daily.reopen` permission.

---

## 🔐 Permissions

| Permission | Description |
|----------|-------|
| `daily.close` | Close daily report |
| `daily.reopen` | Reopen closed daily report (for corrections) |

---

## 📦 Service Layer

### DailyReportService

```php
class DailyReportService
{
    /**
     * Get available dates for opening
     */
    public function getAvailableDates(): array
    {
        $backdatedDays = (int) Setting::getValue('backdated_days', 2);
        $startDate = today()->subDays($backdatedDays);
        $endDate = today();
        
        $dates = [];
        
        for ($date = clone $startDate; $date <= $endDate; $date->addDay()) {
            $report = DailyReport::where('date', $date->toDateString())->first();
            
            // Available if: no report OR report is open
            if (!$report || $report->status === 'open') {
                $dates[] = [
                    'date' => $date->toDateString(),
                    'status' => $report ? 'open' : 'new',
                ];
            }
        }
        
        return $dates;
    }
    
    /**
     * Open/Resume working day
     */
    public function openWorkingDay(string $date): DailyReport
    {
        // Validate date is available
        $this->validateDateAvailable($date);
        
        // Create or get existing report
        $report = DailyReport::firstOrCreate(
            ['date' => $date],
            [
                'cashbox_opening' => $this->getLastClosingBalance('cashbox'),
                'bank_opening' => $this->getLastClosingBalance('bank'),
                'status' => 'open',
            ]
        );
        
        // Store in session
        session(['working_date' => $date]);
        session(['working_report_id' => $report->id]);
        
        return $report;
    }
    
    /**
     * Get current working date
     */
    public function getWorkingDate(): ?string
    {
        return session('working_date');
    }
    
    /**
     * Close working day
     */
    public function closeWorkingDay(): void
    {
        $report = DailyReport::find(session('working_report_id'));
        
        if (!$report) {
            throw new BusinessException('DAY_002', 'No open daily report', 'No open daily report');
        }
        
        // Calculate closing balances from operations
        $report->update([
            'total_sales' => $this->calculateDayTotal($report->date, 'sales'),
            'total_collections_cash' => $this->calculateDayTotal($report->date, 'collections_cash'),
            'total_expenses_cash' => $this->calculateDayTotal($report->date, 'expenses_cash'),
            'cashbox_closing' => $this->calculateClosingBalance($report, 'cashbox'),
            'bank_closing' => $this->calculateClosingBalance($report, 'bank'),
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => auth()->id(),
        ]);
        
        // Clear session
        session()->forget(['working_date', 'working_report_id']);
    }
}
```

---

## 🔗 Integration with Operations

### On Create Invoice/Collection/Expense

```php
// InvoiceController::store
public function store(Request $request)
{
    $workingDate = $this->dailyReportService->getWorkingDate();
    
    if (!$workingDate) {
        throw new BusinessException('DAY_003', 'Must open a daily report first', 'Must open a daily report first');
    }
    
    $invoice = Invoice::create([
        'date' => $workingDate,  // ← Automatic from Session
        'customer_id' => $request->customer_id,
        ...
    ]);
}
```

---

## 📊 API Endpoints

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/api/daily/available` | - | available dates for opening |
| POST | `/api/daily/open` | - | Open working session |
| GET | `/api/daily/current` | - | Current open day |
| POST | `/api/daily/close` | `daily.close` | Close day |
| POST | `/api/daily/{date}/reopen` | `daily.reopen` | Reopen |
| GET | `/api/daily/{date}` | - | Day details |

---

## ⚠️ Business Rules

| Rule ID | Description |
|---------|-------|
| BR-DAY-001 | Must open day before any operation |
| BR-DAY-002 | All operations take open day date automatically |
| BR-DAY-003 | Opening Balance = Last Closing Balance |
| BR-DAY-004 | Cannot open closed day (except with daily.reopen) |
| BR-DAY-005 | Available dates = within backdated_days + not closed |
| BR-DAY-006 | Day closing calculates totals automatically |

---

## 🗄️ Database Schema

```sql
CREATE TABLE daily_reports (
    id BIGINT PRIMARY KEY,
    date DATE UNIQUE NOT NULL,
    
    -- Opening Balances
    cashbox_opening DECIMAL(15,2) NOT NULL,
    bank_opening DECIMAL(15,2) NOT NULL,
    
    -- Day Totals (calculated on close)
    total_sales DECIMAL(15,2) DEFAULT 0,
    total_collections_cash DECIMAL(15,2) DEFAULT 0,
    total_collections_bank DECIMAL(15,2) DEFAULT 0,
    total_expenses_cash DECIMAL(15,2) DEFAULT 0,
    total_expenses_bank DECIMAL(15,2) DEFAULT 0,
    
    -- Closing Balances
    cashbox_closing DECIMAL(15,2),
    bank_closing DECIMAL(15,2),
    
    -- Status
    status ENUM('open', 'closed') DEFAULT 'open',
    closed_at TIMESTAMP NULL,
    closed_by BIGINT NULL
);
```

---

## 📁 Files

| File | Purpose |
|------|---------|
| `Models/DailyReport.php` | Model |
| `Services/DailyReportService.php` | Business Logic + Session |
| `Http/Controllers/Api/DailyReportController.php` | API |
| `Http/Middleware/EnsureWorkingDay.php` | Verify open day |
