# Daily Reports - التقارير اليومية

## 📋 نظرة عامة

نظام التقارير اليومية يعتمد على **جلسة عمل يومية** - المستخدم يفتح اليومية مرة واحدة وكل العمليات تأخذ نفس التاريخ تلقائياً.

---

## 🔄 جلسة العمل اليومية (Working Day Session)

```
┌─────────────────────────────────────────────────────────────┐
│                    WORKING DAY SESSION                       │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1️⃣ بداية العمل:                                            │
│     ─────────────                                            │
│     المستخدم يختار: "فتح يومية 12 ديسمبر"                   │
│     → النظام يحفظ التاريخ في الـ Session                    │
│     → Opening Balance = آخر Closing Balance                 │
│                                                              │
│  2️⃣ أثناء العمل:                                            │
│     ─────────────                                            │
│     فاتورة ← date = 12 (تلقائي)                              │
│     تحصيل ← date = 12 (تلقائي)                               │
│     مصروف ← date = 12 (تلقائي)                               │
│     (بدون إدخال التاريخ في كل عملية)                        │
│                                                              │
│  3️⃣ نهاية العمل:                                            │
│     ─────────────                                            │
│     "إغلاق يومية 12" بصلاحية daily.close                    │
│     → حساب الإجماليات والرصيد الختامي                       │
│     → status = 'closed'                                      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 📅 التواريخ المتاحة للفتح

### الإعدادات

```php
// Settings Table
'backdated_days' => 2  // عدد الأيام المسموح فتحها بأثر رجعي
```

### المنطق

```
┌─────────────────────────────────────────────────────────────┐
│   📅 اليوم: 14 ديسمبر | Setting: backdated_days = 2         │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│   النطاق الزمني: 12 → 14 ديسمبر                             │
│                                                              │
│   التواريخ المتاحة للفتح:                                   │
│   ─────────────────────────                                  │
│   12 Dec: مغلق ❌ → غير متاح                                │
│   13 Dec: مغلق ❌ → غير متاح                                │
│   14 Dec: مفتوح/جديد ✅ → متاح                              │
│                                                              │
│   النتيجة: يمكن فتح يومية 14 ديسمبر فقط                     │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

> **قاعدة:** اليوم المغلق لا يمكن فتحه إلا بصلاحية `daily.reopen`

---

## 🔐 الصلاحيات

| الصلاحية | الوصف |
|----------|-------|
| `daily.close` | إغلاق اليومية |
| `daily.reopen` | إعادة فتح يومية مغلقة (للتصحيحات) |

---

## 📦 Service Layer

### DailyReportService

```php
class DailyReportService
{
    /**
     * Get available dates for opening
     * التواريخ المتاحة لفتح يومية
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
     * فتح جلسة عمل يومية
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
     * التاريخ الحالي للعمل
     */
    public function getWorkingDate(): ?string
    {
        return session('working_date');
    }
    
    /**
     * Close working day
     * إغلاق اليومية
     */
    public function closeWorkingDay(): void
    {
        $report = DailyReport::find(session('working_report_id'));
        
        if (!$report) {
            throw new BusinessException('DAY_002', 'لا توجد يومية مفتوحة', 'No open daily report');
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

### عند إنشاء فاتورة/تحصيل/مصروف

```php
// InvoiceController::store
public function store(Request $request)
{
    $workingDate = $this->dailyReportService->getWorkingDate();
    
    if (!$workingDate) {
        throw new BusinessException('DAY_003', 'يجب فتح يومية أولاً', 'Must open a daily report first');
    }
    
    $invoice = Invoice::create([
        'date' => $workingDate,  // ← تلقائي من الـ Session
        'customer_id' => $request->customer_id,
        ...
    ]);
}
```

---

## 📊 API Endpoints

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/api/daily/available` | - | التواريخ المتاحة للفتح |
| POST | `/api/daily/open` | - | فتح جلسة عمل |
| GET | `/api/daily/current` | - | اليومية الحالية المفتوحة |
| POST | `/api/daily/close` | `daily.close` | إغلاق اليومية |
| POST | `/api/daily/{date}/reopen` | `daily.reopen` | إعادة فتح |
| GET | `/api/daily/{date}` | - | تفاصيل يومية |

---

## ⚠️ قواعد العمل

| Rule ID | الوصف |
|---------|-------|
| BR-DAY-001 | يجب فتح يومية قبل أي عملية |
| BR-DAY-002 | كل العمليات تأخذ تاريخ اليومية المفتوحة تلقائياً |
| BR-DAY-003 | Opening Balance = آخر Closing Balance |
| BR-DAY-004 | لا يمكن فتح يومية مغلقة (إلا بـ daily.reopen) |
| BR-DAY-005 | التواريخ المتاحة = ضمن backdated_days + غير مغلقة |
| BR-DAY-006 | إغلاق اليومية يحسب الإجماليات تلقائياً |

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
| `Http/Middleware/EnsureWorkingDay.php` | التحقق من فتح يومية |
