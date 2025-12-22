# Component Inventory - قائمة المكونات

> Generated: 2025-12-22  
> Status: Phase 1 Output

---

## 📦 Atoms (المكونات الأساسية)

### Button
```tsx
interface ButtonProps {
  variant: 'primary' | 'secondary' | 'outline' | 'ghost' | 'destructive';
  size: 'sm' | 'md' | 'lg' | 'icon';
  loading?: boolean;
  disabled?: boolean;
  children: React.ReactNode;
  onClick?: () => void;
}
```

### Input
```tsx
interface InputProps {
  type: 'text' | 'email' | 'password' | 'number';
  inputMode?: 'text' | 'decimal' | 'numeric';
  placeholder?: string;
  error?: string;
  disabled?: boolean;
}
```

### Select
```tsx
interface SelectProps {
  options: { value: string; label: string }[];
  placeholder?: string;
  searchable?: boolean;
  error?: string;
}
```

### Badge
```tsx
interface BadgeProps {
  variant: 'default' | 'success' | 'warning' | 'error' | 'info';
  children: React.ReactNode;
}
```

### Avatar
```tsx
interface AvatarProps {
  src?: string;
  name: string;
  size: 'sm' | 'md' | 'lg';
}
```

### Spinner
```tsx
interface SpinnerProps {
  size: 'sm' | 'md' | 'lg';
}
```

---

## 🧩 Molecules (المكونات المركبة)

### FormField
```tsx
interface FormFieldProps {
  label: string;
  error?: string;
  hint?: string;
  required?: boolean;
  children: React.ReactNode;
}
```

### MoneyInput
```tsx
interface MoneyInputProps {
  value: number;
  onChange: (value: number) => void;
  currency?: string; // default: "ر.ق"
  error?: string;
}
```

### QuantityInput
```tsx
interface QuantityInputProps {
  value: number;
  onChange: (value: number) => void;
  min?: number;
  max?: number;
  step?: number;
}
```

### CustomerSelect
```tsx
interface CustomerSelectProps {
  value?: number;
  onChange: (customerId: number) => void;
  error?: string;
}
```

### SupplierSelect
```tsx
interface SupplierSelectProps {
  value?: number;
  onChange: (supplierId: number) => void;
  error?: string;
}
```

### ProductSelect
```tsx
interface ProductSelectProps {
  value?: number;
  onChange: (productId: number) => void;
  showStock?: boolean;
  error?: string;
}
```

### DateRangePicker
```tsx
interface DateRangePickerProps {
  from?: Date;
  to?: Date;
  onChange: (range: { from: Date; to: Date }) => void;
}
```

### SearchBar
```tsx
interface SearchBarProps {
  placeholder?: string;
  value: string;
  onChange: (value: string) => void;
  debounceMs?: number; // default: 300
}
```

### Pagination
```tsx
interface PaginationProps {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
}
```

### StatCard
```tsx
interface StatCardProps {
  title: string;
  value: string | number;
  icon?: React.ReactNode;
  trend?: { value: number; direction: 'up' | 'down' };
  href?: string;
}
```

---

## 🏗️ Organisms (المكونات الكبيرة)

### DataTable
```tsx
interface DataTableProps<T> {
  columns: ColumnDef<T>[];
  data: T[];
  loading?: boolean;
  pagination?: PaginationState;
  onPaginationChange?: (pagination: PaginationState) => void;
  sorting?: SortingState;
  onSortingChange?: (sorting: SortingState) => void;
  onRowClick?: (row: T) => void;
  emptyState?: React.ReactNode;
}
```

### DataTableFilters
```tsx
interface DataTableFiltersProps {
  children: React.ReactNode;
  onReset: () => void;
}
```

### Form
```tsx
interface FormProps<T> {
  schema: z.ZodSchema<T>;
  defaultValues?: Partial<T>;
  onSubmit: (data: T) => Promise<void>;
  children: React.ReactNode;
}
```

### FormWizard
```tsx
interface FormWizardProps {
  steps: { title: string; content: React.ReactNode }[];
  onComplete: () => void;
}
```

### Sidebar
```tsx
interface SidebarProps {
  collapsed: boolean;
  onCollapse: (collapsed: boolean) => void;
}
```

### Header
```tsx
interface HeaderProps {
  user: User;
  workingDate?: string;
  onMenuClick: () => void;
}
```

### Modal
```tsx
interface ModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: string;
  children: React.ReactNode;
}
```

### Sheet (Slide Panel)
```tsx
interface SheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  side: 'left' | 'right';
  title: string;
  children: React.ReactNode;
}
```

### ConfirmDialog
```tsx
interface ConfirmDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: string;
  confirmText?: string;
  cancelText?: string;
  variant?: 'default' | 'destructive';
  onConfirm: () => void;
}
```

---

## 🎭 States (حالات العرض)

### LoadingState
```tsx
interface LoadingStateProps {
  message?: string; // default: "جاري التحميل..."
  fullPage?: boolean;
}
```

### EmptyState
```tsx
interface EmptyStateProps {
  icon?: React.ReactNode;
  title: string;
  description?: string;
  action?: {
    label: string;
    href?: string;
    onClick?: () => void;
  };
}
```

### ErrorState
```tsx
interface ErrorStateProps {
  title?: string; // default: "حدث خطأ"
  message: string;
  code?: string;
  retry?: () => void;
}
```

---

## 📄 Templates (القوالب)

### ListPageTemplate
```tsx
interface ListPageTemplateProps {
  title: string;
  createAction?: {
    label: string;
    href: string;
    permission: string;
  };
  filters?: React.ReactNode;
  children: React.ReactNode; // DataTable
}
```

### FormPageTemplate
```tsx
interface FormPageTemplateProps {
  title: string;
  backHref: string;
  children: React.ReactNode; // Form
}
```

### DetailPageTemplate
```tsx
interface DetailPageTemplateProps {
  title: string;
  backHref: string;
  actions?: React.ReactNode;
  children: React.ReactNode;
}
```

---

## 🛡️ Permission Components

### PermissionGate
```tsx
interface PermissionGateProps {
  permission: string | string[];
  mode?: 'all' | 'any'; // default: 'all'
  fallback?: React.ReactNode;
  children: React.ReactNode;
}
```

### SidebarItem
```tsx
interface SidebarItemProps {
  href: string;
  icon: React.ReactNode;
  label: string;
  permission?: string;
  badge?: number;
}
```

---

## 📋 Business Components

### InvoiceItemsTable
```tsx
interface InvoiceItemsTableProps {
  items: InvoiceItem[];
  editable?: boolean;
  onAdd?: () => void;
  onRemove?: (index: number) => void;
  onUpdate?: (index: number, item: Partial<InvoiceItem>) => void;
}
```

### AllocationsList
```tsx
interface AllocationsListProps {
  allocations: CollectionAllocation[];
}
```

### ShipmentStockPicker
```tsx
interface ShipmentStockPickerProps {
  productId: number;
  quantity: number;
  onChange: (shipmentItemId: number) => void;
}
```

### TransactionsList
```tsx
interface TransactionsListProps {
  transactions: Transaction[];
  type: 'cashbox' | 'bank';
}
```

### CustomerStatementTable
```tsx
interface CustomerStatementTableProps {
  transactions: StatementTransaction[];
  customer: Customer;
}
```
