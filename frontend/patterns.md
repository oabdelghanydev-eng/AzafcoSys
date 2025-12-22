# UX Patterns - أنماط تجربة المستخدم

> Generated: 2025-12-22  
> Status: Phase 1 Output

---

## 🔄 Loading States

### Spinner Only (Simple)
```tsx
// استخدامه في كل حالات التحميل
<LoadingState message="جاري التحميل..." />

// Full page
<LoadingState message="جاري التحميل..." fullPage />

// Button loading
<Button loading>حفظ</Button>
```

### Pattern
- No skeleton loading needed
- Simple spinner for all cases
- Always show Arabic message

---

## 📭 Empty States

### Standard Pattern
```tsx
<EmptyState
  icon={<FileX className="h-12 w-12" />}
  title="لا توجد فواتير"
  description="أنشئ فاتورة جديدة للبدء"
  action={{
    label: "فاتورة جديدة",
    href: "/invoices/new",
  }}
/>
```

### Examples by Page

| Page | Icon | Title | Action |
|------|------|-------|--------|
| Invoices | FileX | لا توجد فواتير | فاتورة جديدة |
| Collections | Wallet | لا توجد تحصيلات | تحصيل جديد |
| Shipments | Package | لا توجد شحنات | شحنة جديدة |
| Customers | Users | لا يوجد عملاء | عميل جديد |
| Expenses | CreditCard | لا توجد مصروفات | مصروف جديد |

---

## 🚨 Error States

### API Errors

```tsx
<ErrorState
  title="حدث خطأ"
  message={error.message}
  code={error.code}
  retry={() => refetch()}
/>
```

### Error Types & Handling

| Type | UI Pattern | Action |
|------|------------|--------|
| **Validation (422)** | Inline field errors | Fix and retry |
| **Not Found (404)** | Full page error | Go back |
| **Unauthorized (401)** | Redirect to login | Re-authenticate |
| **Forbidden (403)** | Toast + message | Contact admin |
| **Server Error (500)** | Error state + retry | Retry button |
| **Network Error** | Toast + offline indicator | Retry |
| **Rate Limited (429)** | Toast with countdown | Wait |

### Inline Validation Pattern
```tsx
<FormField label="الاسم" error={errors.name?.message}>
  <Input {...register('name')} />
</FormField>
// Error displays below input in red
```

### Toast Notifications
```tsx
// Error toast
toast.error("فشل حفظ الفاتورة", {
  description: "حدث خطأ في الاتصال",
  action: { label: "إعادة المحاولة", onClick: retry },
});

// Success toast
toast.success("تم حفظ الفاتورة بنجاح");

// Warning toast
toast.warning("تنبيه: السعر يختلف عن المعتاد");
```

---

## 💬 Confirmation Dialogs

### When to Use
- ✅ Cancel invoice/collection
- ✅ Delete shipment
- ✅ Close/Reopen daily report
- ❌ Normal save actions
- ❌ Navigation

### Pattern
```tsx
<ConfirmDialog
  open={open}
  onOpenChange={setOpen}
  title="إلغاء الفاتورة"
  description="هل أنت متأكد من إلغاء هذه الفاتورة؟ لا يمكن التراجع عن هذا الإجراء."
  variant="destructive"
  confirmText="نعم، إلغاء"
  cancelText="لا، تراجع"
  onConfirm={handleCancel}
/>
```

---

## 📱 Mobile Form Patterns

### Numeric Input
```tsx
// Trigger numeric keyboard
<Input
  type="text"
  inputMode="decimal"
  pattern="[0-9]*"
  placeholder="0.00"
/>
```

### Step Flow (Wizard)
```tsx
// For complex forms on mobile
<FormWizard
  steps={[
    { title: "العميل", content: <CustomerStep /> },
    { title: "الأصناف", content: <ItemsStep /> },
    { title: "المراجعة", content: <ReviewStep /> },
  ]}
  onComplete={handleSubmit}
/>
```

### Sticky Submit
```tsx
// Always accessible submit button
<div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t md:relative md:p-0 md:border-0">
  <Button className="w-full md:w-auto">حفظ</Button>
</div>
```

### Touch Targets
```tsx
// Minimum 44px for all interactive elements
<Button className="h-11 min-h-[44px]">
  إضافة
</Button>

<Checkbox className="h-6 w-6" />
```

---

## 📊 Data Display Patterns

### Money Formatting
```tsx
// Always: thousands separator + 2 decimals + currency after
formatMoney(1234.5); // "1,234.50 ر.ق"

// In JSX
<span className="font-mono">{formatMoney(invoice.total)}</span>
```

### Quantity Formatting
```tsx
// Thousands separator, no decimals
formatQuantity(1234); // "1,234"
```

### Date Formatting
```tsx
// API format: YYYY-MM-DD
// Display format: depends on context
formatDate("2025-12-20"); // "20 ديسمبر 2025"
formatDateShort("2025-12-20"); // "20/12/2025"
```

### Status Badges
```tsx
// Invoice status
<Badge variant={invoice.status === 'active' ? 'success' : 'error'}>
  {invoice.status === 'active' ? 'نشطة' : 'ملغاة'}
</Badge>

// Shipment status
const shipmentStatusColors = {
  open: 'info',
  closed: 'warning', 
  settled: 'success',
};
```

---

## 🔐 Permission-Based UI

### Hide Completely
```tsx
<PermissionGate permission="invoices.create">
  <Button>فاتورة جديدة</Button>
</PermissionGate>
```

### Disabled State
```tsx
<Button disabled={!hasPermission('invoices.cancel')}>
  إلغاء
</Button>
```

### Hide with Fallback
```tsx
<PermissionGate 
  permission="reports.daily" 
  fallback={<UpgradePrompt />}
>
  <DailyReport />
</PermissionGate>
```

---

## 📋 Table Patterns

### Column Types

```tsx
// Text column
{ header: "العميل", accessorKey: "customer.name" }

// Money column
{ 
  header: "الإجمالي", 
  accessorKey: "total",
  cell: ({ getValue }) => formatMoney(getValue())
}

// Date column
{
  header: "التاريخ",
  accessorKey: "date",
  cell: ({ getValue }) => formatDateShort(getValue())
}

// Status column
{
  header: "الحالة",
  accessorKey: "status",
  cell: ({ getValue }) => <StatusBadge status={getValue()} />
}

// Actions column
{
  id: "actions",
  cell: ({ row }) => <RowActions row={row} />
}
```

### Row Actions
```tsx
<DropdownMenu>
  <DropdownMenuTrigger>
    <Button variant="ghost" size="icon">
      <MoreHorizontal />
    </Button>
  </DropdownMenuTrigger>
  <DropdownMenuContent>
    <DropdownMenuItem>عرض</DropdownMenuItem>
    <DropdownMenuItem>تعديل</DropdownMenuItem>
    <DropdownMenuSeparator />
    <DropdownMenuItem className="text-destructive">
      إلغاء
    </DropdownMenuItem>
  </DropdownMenuContent>
</DropdownMenu>
```

---

## 🔍 Search & Filter Patterns

### Search with Debounce
```tsx
const [search, setSearch] = useDebounce("", 300);

<SearchBar
  placeholder="بحث بالاسم أو الرقم..."
  value={search}
  onChange={setSearch}
/>
```

### Filters Bar
```tsx
<DataTableFilters onReset={resetFilters}>
  <DateRangePicker from={from} to={to} onChange={setDateRange} />
  <CustomerSelect value={customerId} onChange={setCustomerId} />
  <Select 
    options={statusOptions} 
    value={status} 
    onChange={setStatus} 
  />
</DataTableFilters>
```

---

## 🌙 Session Handling

### Session Expired
```tsx
// Detect 401, show modal, redirect to login
useEffect(() => {
  if (error?.status === 401) {
    setShowSessionExpired(true);
  }
}, [error]);

<Modal open={showSessionExpired} onOpenChange={() => {}}>
  <p>انتهت الجلسة، يرجى تسجيل الدخول مرة أخرى</p>
  <Button onClick={() => router.push('/login')}>
    تسجيل الدخول
  </Button>
</Modal>
```

### Working Day Check
```tsx
// Before any operation, check if day is open
const { data: currentDay } = useDailyReport();

if (!currentDay?.report) {
  return <NoDayOpenState onOpen={openDay} />;
}
```

---

## ♿ Accessibility Patterns

### Focus Management
```tsx
// Focus first field on form mount
useEffect(() => {
  firstFieldRef.current?.focus();
}, []);

// Focus error field on validation fail
useEffect(() => {
  if (errors) {
    const firstError = Object.keys(errors)[0];
    document.querySelector(`[name="${firstError}"]`)?.focus();
  }
}, [errors]);
```

### Screen Reader
```tsx
// Announce loading
<span className="sr-only" aria-live="polite">
  {loading ? "جاري التحميل" : "تم التحميل"}
</span>

// Describe icons
<Button>
  <Plus className="h-4 w-4" aria-hidden />
  <span>إضافة</span>
</Button>

// Labeled buttons
<Button aria-label="إلغاء الفاتورة">
  <X className="h-4 w-4" />
</Button>
```

### Keyboard Navigation
```tsx
// Escape to close modals
useEffect(() => {
  const handleEsc = (e) => {
    if (e.key === 'Escape') onClose();
  };
  document.addEventListener('keydown', handleEsc);
  return () => document.removeEventListener('keydown', handleEsc);
}, []);
```
