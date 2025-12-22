# Frontend Architecture - نظام إدارة المبيعات

> **Version:** 1.0  
> **Date:** 2025-12-22  
> **Status:** Phase 1 Output

---

## 📐 Technology Stack

| Layer | Technology | Version |
|-------|------------|---------|
| **Framework** | Next.js | 14.x (App Router) |
| **Language** | TypeScript | 5.x (strict mode) |
| **Styling** | Tailwind CSS | 3.x |
| **Components** | shadcn/ui | Latest |
| **Server State** | TanStack Query | 5.x |
| **Client State** | Zustand | 4.x |
| **Forms** | React Hook Form + Zod | Latest |
| **Icons** | Lucide React | Latest |
| **Animation** | Framer Motion | 10.x |

---

## 📁 Folder Structure

```
frontend/
├── src/
│   ├── app/                          # Next.js App Router
│   │   ├── (auth)/                   # Auth routes (no layout)
│   │   │   ├── login/page.tsx
│   │   │   └── layout.tsx
│   │   ├── (dashboard)/              # Protected routes
│   │   │   ├── layout.tsx            # Dashboard layout
│   │   │   ├── page.tsx              # Dashboard home
│   │   │   ├── daily/                # Daily report
│   │   │   ├── invoices/             # Invoices CRUD
│   │   │   ├── collections/          # Collections CRUD
│   │   │   ├── shipments/            # Shipments CRUD
│   │   │   ├── customers/            # Customers CRUD
│   │   │   ├── suppliers/            # Suppliers CRUD
│   │   │   ├── expenses/             # Expenses CRUD
│   │   │   ├── returns/              # Returns CRUD
│   │   │   ├── accounts/             # Treasury
│   │   │   ├── reports/              # Reports
│   │   │   ├── settings/             # Settings
│   │   │   └── users/                # User management
│   │   ├── layout.tsx                # Root layout
│   │   └── globals.css               # Global styles
│   │
│   ├── components/
│   │   ├── ui/                       # shadcn/ui base components
│   │   ├── forms/                    # Form components
│   │   │   ├── form-field.tsx
│   │   │   ├── money-input.tsx
│   │   │   ├── quantity-input.tsx
│   │   │   └── customer-select.tsx
│   │   ├── tables/                   # Table components
│   │   │   ├── data-table.tsx
│   │   │   ├── table-pagination.tsx
│   │   │   └── table-filters.tsx
│   │   ├── layout/                   # Layout components
│   │   │   ├── sidebar.tsx
│   │   │   ├── header.tsx
│   │   │   └── mobile-nav.tsx
│   │   └── shared/                   # Shared components
│   │       ├── loading-state.tsx
│   │       ├── empty-state.tsx
│   │       ├── error-state.tsx
│   │       ├── stat-card.tsx
│   │       └── confirm-dialog.tsx
│   │
│   ├── hooks/
│   │   ├── api/                      # API hooks
│   │   │   ├── use-auth.ts
│   │   │   ├── use-invoices.ts
│   │   │   ├── use-collections.ts
│   │   │   ├── use-shipments.ts
│   │   │   ├── use-customers.ts
│   │   │   ├── use-suppliers.ts
│   │   │   ├── use-expenses.ts
│   │   │   ├── use-daily-report.ts
│   │   │   └── use-accounts.ts
│   │   └── utils/                    # Utility hooks
│   │       ├── use-permission.ts
│   │       ├── use-media-query.ts
│   │       └── use-debounce.ts
│   │
│   ├── lib/
│   │   ├── api/                      # API client
│   │   │   ├── client.ts
│   │   │   ├── endpoints.ts
│   │   │   └── types.ts
│   │   ├── validations/              # Zod schemas
│   │   │   ├── invoice.schema.ts
│   │   │   ├── collection.schema.ts
│   │   │   └── shipment.schema.ts
│   │   ├── utils/                    # Utilities
│   │   │   ├── formatters.ts
│   │   │   ├── constants.ts
│   │   │   └── cn.ts
│   │   └── errors/                   # Error handling
│   │       ├── codes.ts
│   │       └── handler.ts
│   │
│   ├── stores/                       # Zustand stores
│   │   ├── auth-store.ts
│   │   └── ui-store.ts
│   │
│   ├── types/                        # TypeScript types
│   │   └── index.ts
│   │
│   └── styles/
│       └── tokens.css                # Design tokens
│
├── public/
│   └── logo.png
│
├── .env.local
├── next.config.js
├── tailwind.config.ts
└── tsconfig.json
```

---

## 🎨 Design System

### Color Palette

```css
:root {
  /* Primary - Blue (Financial Trust) */
  --primary-50: 239 246 255;   /* #EFF6FF */
  --primary-100: 219 234 254;  /* #DBEAFE */
  --primary-200: 191 219 254;  /* #BFDBFE */
  --primary-300: 147 197 253;  /* #93C5FD */
  --primary-400: 96 165 250;   /* #60A5FA */
  --primary-500: 59 130 246;   /* #3B82F6 - Primary */
  --primary-600: 37 99 235;    /* #2563EB - Hover */
  --primary-700: 29 78 216;    /* #1D4ED8 */
  --primary-800: 30 64 175;    /* #1E40AF */
  --primary-900: 30 58 138;    /* #1E3A8A */
  
  /* Semantic Colors */
  --success: 34 197 94;        /* #22C55E - Green */
  --warning: 245 158 11;       /* #F59E0B - Amber */
  --error: 239 68 68;          /* #EF4444 - Red */
  --info: 59 130 246;          /* #3B82F6 - Blue */
  
  /* Neutrals */
  --neutral-50: 250 250 250;
  --neutral-100: 245 245 245;
  --neutral-200: 229 229 229;
  --neutral-300: 212 212 212;
  --neutral-400: 163 163 163;
  --neutral-500: 115 115 115;
  --neutral-600: 82 82 82;
  --neutral-700: 64 64 64;
  --neutral-800: 38 38 38;
  --neutral-900: 23 23 23;
  
  /* Background */
  --background: 255 255 255;
  --foreground: 23 23 23;
  --muted: 245 245 245;
  --muted-foreground: 115 115 115;
  
  /* Card */
  --card: 255 255 255;
  --card-foreground: 23 23 23;
  
  /* Border */
  --border: 229 229 229;
  --ring: 59 130 246;
}

/* Dark Mode */
.dark {
  --background: 23 23 23;
  --foreground: 250 250 250;
  --muted: 38 38 38;
  --muted-foreground: 163 163 163;
  --card: 38 38 38;
  --card-foreground: 250 250 250;
  --border: 64 64 64;
}
```

### Typography

```css
:root {
  /* Font Family - Arabic First */
  --font-sans: 'Cairo', 'Tajawal', 'Segoe UI', sans-serif;
  --font-mono: 'Fira Code', 'Consolas', monospace;
  
  /* Type Scale */
  --text-xs: 0.75rem;      /* 12px */
  --text-sm: 0.875rem;     /* 14px */
  --text-base: 1rem;       /* 16px */
  --text-lg: 1.125rem;     /* 18px */
  --text-xl: 1.25rem;      /* 20px */
  --text-2xl: 1.5rem;      /* 24px */
  --text-3xl: 1.875rem;    /* 30px */
  --text-4xl: 2.25rem;     /* 36px */
  
  /* Line Heights */
  --leading-none: 1;
  --leading-tight: 1.25;
  --leading-snug: 1.375;
  --leading-normal: 1.5;
  --leading-relaxed: 1.625;
  --leading-loose: 2;
  
  /* Font Weights */
  --font-normal: 400;
  --font-medium: 500;
  --font-semibold: 600;
  --font-bold: 700;
}
```

### Spacing & Sizing

```css
:root {
  /* Spacing Scale (8px base) */
  --space-0: 0;
  --space-1: 0.25rem;      /* 4px */
  --space-2: 0.5rem;       /* 8px */
  --space-3: 0.75rem;      /* 12px */
  --space-4: 1rem;         /* 16px */
  --space-5: 1.25rem;      /* 20px */
  --space-6: 1.5rem;       /* 24px */
  --space-8: 2rem;         /* 32px */
  --space-10: 2.5rem;      /* 40px */
  --space-12: 3rem;        /* 48px */
  --space-16: 4rem;        /* 64px */
  
  /* Container */
  --container-sm: 640px;
  --container-md: 768px;
  --container-lg: 1024px;
  --container-xl: 1280px;
  
  /* Sidebar */
  --sidebar-width: 280px;
  --sidebar-collapsed: 64px;
  
  /* Touch Target (Mobile) */
  --touch-target: 44px;
}
```

### Components Tokens

```css
:root {
  /* Border Radius */
  --radius-sm: 0.25rem;    /* 4px */
  --radius-md: 0.375rem;   /* 6px */
  --radius-lg: 0.5rem;     /* 8px */
  --radius-xl: 0.75rem;    /* 12px */
  --radius-2xl: 1rem;      /* 16px */
  --radius-full: 9999px;
  
  /* Shadows */
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
  --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
  
  /* Transitions */
  --transition-fast: 150ms;
  --transition-normal: 200ms;
  --transition-slow: 300ms;
  --ease-default: cubic-bezier(0.4, 0, 0.2, 1);
  
  /* Z-Index Scale */
  --z-dropdown: 50;
  --z-sticky: 100;
  --z-modal: 200;
  --z-popover: 300;
  --z-toast: 400;
}
```

### Breakpoints

```css
/* Mobile-First Breakpoints */
--screen-sm: 640px;   /* Small tablets */
--screen-md: 768px;   /* Tablets */
--screen-lg: 1024px;  /* Desktop */
--screen-xl: 1280px;  /* Large desktop */
--screen-2xl: 1536px; /* Extra large */
```

---

## 🔐 Permission-Based UI

### Permission Check Hook

```typescript
// hooks/utils/use-permission.ts

export function usePermission(permission: string): boolean;
export function usePermissions(permissions: string[]): boolean;
export function useAnyPermission(permissions: string[]): boolean;
```

### UI Rendering Patterns

```tsx
// ❌ Hide element completely
{hasPermission('invoices.create') && (
  <Button>فاتورة جديدة</Button>
)}

// ❌ Show disabled state
<Button disabled={!hasPermission('invoices.create')}>
  فاتورة جديدة
</Button>

// ❌ Hide menu item
<SidebarItem 
  href="/invoices/new" 
  permission="invoices.create"
/>
```

### Permission Groups

```typescript
const PERMISSIONS = {
  INVOICES: ['invoices.view', 'invoices.create', 'invoices.edit', 'invoices.cancel'],
  COLLECTIONS: ['collections.view', 'collections.create', 'collections.edit', 'collections.cancel'],
  SHIPMENTS: ['shipments.view', 'shipments.create', 'shipments.edit', 'shipments.close'],
  EXPENSES: ['expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete'],
  DAILY: ['daily.close', 'daily.reopen'],
  TREASURY: ['cashbox.view', 'cashbox.deposit', 'cashbox.withdraw', 'cashbox.transfer'],
  ADMIN: ['users.view', 'users.create', 'users.edit', 'users.delete', 'settings.edit'],
};
```

---

## 🚨 State Management

### Server State (React Query)

```typescript
// Query configuration
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000,      // 5 minutes
      gcTime: 10 * 60 * 1000,        // 10 minutes
      retry: 3,
      refetchOnWindowFocus: false,
    },
  },
});

// Query keys factory
const queryKeys = {
  invoices: {
    all: ['invoices'] as const,
    list: (filters: InvoiceFilters) => [...queryKeys.invoices.all, 'list', filters] as const,
    detail: (id: string) => [...queryKeys.invoices.all, 'detail', id] as const,
  },
  // ... similar for other entities
};
```

### Client State (Zustand)

```typescript
// stores/auth-store.ts
interface AuthStore {
  user: User | null;
  token: string | null;
  setAuth: (user: User, token: string) => void;
  logout: () => void;
  hasPermission: (permission: string) => boolean;
}

// stores/ui-store.ts
interface UIStore {
  sidebarOpen: boolean;
  setSidebarOpen: (open: boolean) => void;
  workingDate: string | null;
  setWorkingDate: (date: string) => void;
}
```

---

## 📱 Mobile-First Strategy

### Critical Mobile Pages

| Page | Priority | Special Requirements |
|------|----------|---------------------|
| Create Invoice | 🔴 HIGH | Numeric keyboard, step flow |
| Create Collection | 🔴 HIGH | Amount input, customer picker |
| Create Expense | 🔴 HIGH | Simple form, quick submit |
| Add Shipment | 🔴 HIGH | Product selection, quantities |
| Account Transfer | 🔴 HIGH | From/To selector, amount |

### Mobile Form Patterns

```tsx
// Numeric keyboard trigger
<Input 
  type="text" 
  inputMode="decimal"
  pattern="[0-9]*"
/>

// Touch-friendly buttons (min 44px)
<Button className="h-11 min-w-[44px]" />

// Sticky submit
<div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t">
  <Button className="w-full">حفظ</Button>
</div>

// Step flow for complex forms
<FormWizard steps={['العميل', 'الأصناف', 'المراجعة']} />
```

---

## 🎯 Data Density Strategy

| Context | Display | Reason |
|---------|---------|--------|
| Desktop list | Full DataTable | High data needs |
| Mobile list | Card layout | Scannable |
| Dashboard | Stat cards | Quick overview |
| Reports | Dense table | Printable |

```tsx
// Responsive switching
<div className="hidden md:block">
  <DataTable data={invoices} />
</div>
<div className="md:hidden">
  <InvoiceCards data={invoices} />
</div>
```

---

## 🛡️ Error Handling Strategy

### Error Types

| Type | UI Pattern | Action |
|------|------------|--------|
| Validation | Inline messages | Fix and retry |
| Network | Toast + Retry button | Retry |
| Business Logic | Toast with details | Show error code |
| Session Expired | Modal + Redirect | Re-login |
| Rate Limited | Toast + Wait timer | Wait |

### Error Message Mapping

```typescript
// lib/errors/codes.ts
export const ERROR_MESSAGES: Record<string, { ar: string; en: string }> = {
  'INV_001': { ar: 'لا يمكن حذف الفواتير', en: 'Cannot delete invoices' },
  'INV_002': { ar: 'لا يمكن تقليل القيمة أقل من المدفوع', en: 'Cannot reduce below paid' },
  'COL_001': { ar: 'لا يمكن حذف التحصيلات', en: 'Cannot delete collections' },
  'AUTH_002': { ar: 'غير مصرح بهذه العملية', en: 'Not authorized' },
  // ... all codes from API_Reference.md
};
```

---

## 🔄 Cache Invalidation Strategy

```typescript
// After create invoice
onSuccess: () => {
  queryClient.invalidateQueries({ queryKey: ['invoices'] });
  queryClient.invalidateQueries({ queryKey: ['dashboard'] });
  queryClient.invalidateQueries({ queryKey: ['customers', customerId] });
};

// After create collection  
onSuccess: () => {
  queryClient.invalidateQueries({ queryKey: ['collections'] });
  queryClient.invalidateQueries({ queryKey: ['invoices'] }); // paid_amount changed
  queryClient.invalidateQueries({ queryKey: ['customers', customerId] }); // balance changed
  queryClient.invalidateQueries({ queryKey: ['accounts'] }); // cashbox/bank changed
};
```

---

## 📊 API Client Architecture

```typescript
// lib/api/client.ts
class ApiClient {
  private baseUrl: string;
  
  constructor() {
    this.baseUrl = process.env.NEXT_PUBLIC_API_URL!;
  }
  
  // CSRF handling for subdomain auth
  async csrf(): Promise<void>;
  
  // Type-safe requests
  async get<T>(endpoint: string, params?: object): Promise<T>;
  async post<T>(endpoint: string, data: unknown): Promise<T>;
  async put<T>(endpoint: string, data: unknown): Promise<T>;
  async delete<T>(endpoint: string): Promise<T>;
  
  // Built-in error handling
  private handleError(error: unknown): never;
  
  // Retry mechanism
  private withRetry<T>(fn: () => Promise<T>, retries?: number): Promise<T>;
}

export const api = new ApiClient();
```

---

## ✅ Checklist for Phase 2

- [ ] Project setup with all dependencies
- [ ] Design tokens in CSS
- [ ] shadcn/ui components installed
- [ ] Layout (Sidebar + Header) RTL-ready
- [ ] Auth flow (Login + Token storage)
- [ ] API client with CSRF
- [ ] Permission hook implemented
- [ ] Error handling system
- [ ] Dashboard page
- [ ] Daily report page
- [ ] Invoices CRUD
- [ ] Collections CRUD
- [ ] Shipments CRUD
- [ ] All other pages
