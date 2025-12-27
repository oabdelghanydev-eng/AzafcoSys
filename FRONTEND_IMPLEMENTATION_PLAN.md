# Frontend Safety Implementation Plan

**Date**: 2025-12-27  
**Author**: Principal Frontend Architect  
**Branch**: `fix/critical-safety`  
**Status**: PENDING APPROVAL

---

## 1. Executive Summary

This plan addresses **7 P0 critical issues** and **3 P1 scalability issues** identified in the adversarial frontend review. The goal is to eliminate data corruption vectors (magic stock, deceptive allocation), close privilege escalation paths (unguarded admin pages), and prevent operational deadlocks (force close). Additionally, we establish foundational safeguards (idempotency, decimal-safe math) to prevent residual risks.

**Total estimated effort**: 8 working days across 3 sprints.

---

## 2. Goals & Non-Goals

### Goals
- Eliminate "Magic Stock" returns vulnerability
- Implement true Manual Allocation for collections
- Guard `/settings` and `/users` with PermissionGate
- Add Force Close capability with audit trail
- Add idempotency keys to all financial mutations
- Implement decimal-safe client-side money handling
- Add virtualized selects for large datasets
- Establish global pagination pattern
- Set up monitoring and Sentry instrumentation

### Non-Goals
- Backend refactoring (assumed correct, frontend-only scope)
- Multi-supplier/multi-bank support (accepted constraints)
- Full e2e test coverage (focus on critical paths only)
- Performance optimization beyond virtualization

---

## 3. Sprint Plan

### Sprint 1: Security & Correctness (3 days)

**Objective**: Eliminate data corruption and privilege escalation risks.

| ID | Deliverable | Owner |
|----|-------------|-------|
| T-001 | Returns: Invoice link + derived items + caps | Frontend |
| T-002 | Collections: Manual Allocation UI | Frontend |
| T-003 | Settings/Users: PermissionGate wrapper | Frontend |
| T-004 | Daily: Force Close (Admin-only) | Frontend + Backend |
| T-005 | Idempotency keys (X-Request-ID) | Frontend |
| T-006 | Decimal-safe money utility | Frontend |

**Exit Criteria**:
- All P0 pages pass manual QA
- No "Magic Stock" possible via UI
- Manual allocation sums validated
- Unauthorized access to /settings returns fallback

---

### Sprint 2: Scalability (3 days)

**Objective**: Prevent browser crashes with large datasets.

| ID | Deliverable | Owner |
|----|-------------|-------|
| T-007 | Virtualized selects (cmdk) | Frontend |
| T-008 | Global pagination | Frontend |
| T-009 | Stale-read UX flow | Frontend |
| T-011 | Dynamic account lookup | Frontend |

**Exit Criteria**:
- Product selector handles 5000 items without lag
- List pages paginate at 50 items
- Transfer uses dynamic account IDs

---

### Sprint 3: Testing & Monitoring (2 days)

**Objective**: Establish test infrastructure and monitoring.

| ID | Deliverable | Owner |
|----|-------------|-------|
| T-010 | Monitoring & Sentry tags | Frontend + DevOps |
| T-012 | Playwright e2e setup | QA |
| T-013 | Unit tests for utilities | Frontend |
| T-014 | Documentation updates | Frontend |

**Exit Criteria**:
- Playwright runs in CI
- Sentry captures safety-related events
- All docs updated

---

## 4. Ordered Task Backlog

### T-001: Returns Invoice Linking (P0)

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Effort** | L (1 day) |
| **Owner** | Frontend |
| **Files** | `src/app/returns/new/page.tsx`, `src/hooks/api/use-returns.ts`, `src/hooks/api/use-invoices.ts` |
| **Feature Flag** | `ff_returns_invoice_link` |

**Change Description**:
1. Add Invoice selector (required) before items can be added
2. Fetch invoice items when invoice selected
3. Replace free-form product selector with invoice-item selector
4. Cap return qty ≤ sold qty, price = original price

```tsx
// src/app/returns/new/page.tsx - Add invoice selection
const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);
const { data: invoiceItems } = useInvoiceItems(selectedInvoice?.id);

// Derive available items from invoice
const availableItems = useMemo(() => 
  invoiceItems?.map(item => ({
    ...item,
    maxReturnQty: item.quantity - (item.returned_qty || 0),
  })) ?? [], 
[invoiceItems]);
```

**Tests**:
- Unit: `returns.test.ts` - validate qty cap logic
- E2E: Cannot submit return without invoice selection

**Acceptance Criteria**:
- [ ] Invoice selector is required field
- [ ] Only invoice items appear in item dropdown
- [ ] Return qty capped at (sold - already returned)
- [ ] Price field is read-only (derived from invoice)
- [ ] Sentry tag: `return.invoice_linked=true`

**Rollback**: Disable `ff_returns_invoice_link` flag

---

### T-002: Collections Manual Allocation (P0)

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Effort** | L (1 day) |
| **Owner** | Frontend |
| **Files** | `src/app/collections/new/page.tsx`, `src/hooks/api/use-collections.ts` |
| **Feature Flag** | `ff_collections_manual_alloc` |

**Change Description**:
1. When allocation_type = 'manual', show allocation table
2. Table lists unpaid invoices with input for amount
3. Validate: sum(allocations) === payment_amount
4. Use Decimal.js for comparison

```tsx
// Show allocation table when manual selected
{allocationType === 'manual' && (
  <AllocationTable 
    invoices={unpaidInvoices} 
    allocations={allocations}
    onChange={setAllocations}
    totalRequired={paymentAmount}
  />
)}

// Validation
const allocationSum = allocations.reduce(
  (sum, a) => sum.plus(new Decimal(a.amount)), 
  new Decimal(0)
);
if (!allocationSum.equals(new Decimal(paymentAmount))) {
  setError('Sum of allocations must equal payment amount');
  return;
}
```

**Tests**:
- Unit: Decimal comparison edge cases (0.1 + 0.2)
- E2E: Submit blocked when sum !== amount

**Acceptance Criteria**:
- [ ] Manual mode shows allocation table
- [ ] Each invoice row has amount input
- [ ] Sum validation uses decimal-safe comparison
- [ ] Error message shown when sum mismatches
- [ ] Sentry tag: `collection.allocation_type=manual`

**Rollback**: Disable `ff_collections_manual_alloc`

---

### T-003: Settings/Users Permission Gates (P0)

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Effort** | S (2 hours) |
| **Owner** | Frontend |
| **Files** | `src/app/settings/page.tsx`, `src/app/users/page.tsx` |
| **Feature Flag** | None (security fix) |

**Change Description**:
Wrap entire page content in PermissionGate with fallback.

```tsx
// src/app/settings/page.tsx
import { PermissionGate } from '@/components/shared/permission-gate';

export default function SettingsPage() {
  return (
    <PermissionGate 
      permission="admin.settings" 
      fallback={<UnauthorizedPage />}
    >
      {/* existing content */}
    </PermissionGate>
  );
}
```

**Tests**:
- Unit: PermissionGate renders fallback when unauthorized
- Manual: Login as non-admin, navigate to /settings, verify blocked

**Acceptance Criteria**:
- [ ] /settings shows UnauthorizedPage for non-admins
- [ ] /users shows UnauthorizedPage for non-admins
- [ ] Backend confirms policy enforcement (double-check)

**Rollback**: N/A (security cannot be rolled back)

---

### T-004: Daily Force Close (P0)

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Effort** | M (4 hours) |
| **Owner** | Frontend + Backend |
| **Files** | `src/app/daily/page.tsx`, `src/hooks/api/use-daily-report.ts` |
| **Feature Flag** | `ff_force_close` |

**Change Description**:
1. Add "Force Close" button visible only to admins
2. Button opens dialog requiring reason text
3. Calls backend `/api/daily-reports/{id}/force-close`
4. Reason stored in audit log

```tsx
<PermissionGate permission="admin.force_close">
  <AlertDialog>
    <AlertDialogTrigger asChild>
      <Button variant="destructive">Force Close</Button>
    </AlertDialogTrigger>
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Force Close Day</AlertDialogTitle>
      </AlertDialogHeader>
      <Textarea 
        value={reason} 
        onChange={(e) => setReason(e.target.value)}
        placeholder="Reason for force close..."
        required
      />
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction 
          onClick={handleForceClose}
          disabled={!reason.trim()}
        >
          Force Close
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</PermissionGate>
```

**Backend Requirement**: Add `/api/daily-reports/{id}/force-close` endpoint.

**Tests**:
- Manual: Admin can force close stuck day with reason
- E2E: Button not visible to non-admins

**Acceptance Criteria**:
- [ ] Force Close button only for admins
- [ ] Reason field required (cannot submit empty)
- [ ] Reason logged in backend audit
- [ ] Sentry tag: `daily.force_close=true`

**Rollback**: Disable `ff_force_close`

---

### T-005: Idempotency Keys (P0)

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Effort** | M (4 hours) |
| **Owner** | Frontend |
| **Files** | `src/lib/api/client.ts`, `src/lib/utils.ts` |
| **Feature Flag** | None (infra) |

**Change Description**:
1. Generate UUID for each mutation request
2. Send as X-Request-ID header
3. Backend uses for idempotency

```ts
// src/lib/utils.ts
export function generateRequestId(): string {
  return crypto.randomUUID();
}

// src/lib/api/client.ts - add to POST/PUT/DELETE
headers: {
  'Content-Type': 'application/json',
  'X-Request-ID': generateRequestId(),
}
```

**Tests**:
- Unit: generateRequestId produces valid UUIDs
- Integration: Headers sent on mutation requests

**Acceptance Criteria**:
- [ ] All POST/PUT/DELETE requests include X-Request-ID
- [ ] Each request has unique ID
- [ ] Backend logs request ID

**Rollback**: N/A (always safe)

---

### T-006: Decimal-Safe Money Handling (P0)

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Effort** | M (4 hours) |
| **Owner** | Frontend |
| **Files** | `src/lib/money.ts` (NEW), `package.json` |
| **Feature Flag** | None |

**Change Description**:
1. Add decimal.js dependency
2. Create money utility module
3. Use for all client-side money calculations

```ts
// src/lib/money.ts (NEW)
import Decimal from 'decimal.js';

Decimal.set({ precision: 20, rounding: Decimal.ROUND_HALF_UP });

export function money(value: number | string): Decimal {
  return new Decimal(value || 0);
}

export function sumMoney(values: (number | string)[]): Decimal {
  return values.reduce(
    (sum, v) => sum.plus(money(v)), 
    new Decimal(0)
  );
}

export function moneyEquals(a: number | string, b: number | string): boolean {
  return money(a).equals(money(b));
}
```

**Tests**:
- Unit: `money.test.ts` - floating point edge cases

**Acceptance Criteria**:
- [ ] 0.1 + 0.2 === 0.3 passes
- [ ] All allocation comparisons use Decimal
- [ ] No floating point errors in UI

---

### T-007: Virtualized Selects (P1)

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Effort** | M (4 hours) |
| **Owner** | Frontend |
| **Files** | `src/components/shared/virtualized-select.tsx` (NEW) |
| **Feature Flag** | `ff_virtualized_selects` |

**Acceptance Criteria**:
- [ ] 5000 products load without browser freeze
- [ ] Search filters virtualized list
- [ ] Keyboard navigation works

---

### T-008: Global Pagination (P1)

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Effort** | M (4 hours) |
| **Owner** | Frontend |
| **Files** | All list pages, `src/components/shared/pagination.tsx` (NEW) |
| **Feature Flag** | `ff_pagination` |

**Acceptance Criteria**:
- [ ] Lists default to 50 items per page
- [ ] Pagination controls visible
- [ ] URL reflects current page

---

### T-009: Stale-Read UX (P1)

| Field | Value |
|-------|-------|
| **Priority** | P1 |
| **Effort** | S (2 hours) |
| **Owner** | Frontend |
| **Files** | Form pages |

**Acceptance Criteria**:
- [ ] 409 responses show "Data changed" modal
- [ ] User can refresh and retry

---

### T-010: Monitoring Setup (P0)

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Effort** | M (4 hours) |
| **Owner** | Frontend + DevOps |
| **Files** | `src/lib/monitoring.ts` (NEW) |

**Metrics**:
- `return_value_ratio`: return_amount / invoice_amount (alert if > 1.0)
- `manual_allocation_mismatch`: count of validation failures
- `force_close_usage`: count per day (alert if > 0)

---

### T-011: Dynamic Account Lookup (P0)

| Field | Value |
|-------|-------|
| **Priority** | P0 |
| **Effort** | S (2 hours) |
| **Owner** | Frontend |
| **Files** | `src/app/accounts/page.tsx` |

**Change Description**:
```tsx
// Before
const from_account_id = direction === 'to-bank' ? 1 : 2;

// After
const cashboxId = summary?.cashbox?.id;
const bankId = summary?.bank?.id;
const from_account_id = direction === 'to-bank' ? cashboxId : bankId;
```

**Acceptance Criteria**:
- [ ] No hardcoded account IDs in codebase
- [ ] Transfers work with dynamic IDs

---

## 5. PR Strategy

**Branch naming**: `review/fix/<short-desc>`

**PR size**: Max 400 lines changed per PR

**Required reviewers**: Frontend Lead, Backend Lead (for T-004), QA

---

## 6. Testing & QA

### Test Infrastructure Setup

**Dependencies to add**:
```json
"devDependencies": {
  "vitest": "^1.0.0",
  "@testing-library/react": "^14.0.0",
  "@playwright/test": "^1.40.0"
}
```

### Example Playwright Scenarios

**Returns Flow**:
```ts
test('cannot create return without invoice', async ({ page }) => {
  await page.goto('/returns/new');
  await page.selectOption('[data-testid="customer-select"]', 'Customer A');
  await expect(page.locator('[data-testid="add-item-btn"]')).toBeDisabled();
});
```

**Collections Flow**:
```ts
test('manual allocation must sum to payment', async ({ page }) => {
  await page.goto('/collections/new');
  await page.click('[data-testid="manual-allocation"]');
  await page.locator('[data-testid="allocation-0"]').fill('50');
  await page.locator('[data-testid="amount"]').fill('100');
  await page.click('[data-testid="submit"]');
  await expect(page.locator('.error')).toContainText('must equal');
});
```

---

## 7. Deployment & Rollout

### Feature Flags

| Flag | Default | Description |
|------|---------|-------------|
| `ff_returns_invoice_link` | false | Enable invoice linking for returns |
| `ff_collections_manual_alloc` | false | Enable manual allocation UI |
| `ff_force_close` | false | Enable force close button |
| `ff_virtualized_selects` | false | Enable virtualized selects |
| `ff_pagination` | false | Enable pagination |

### Canary Rollout

1. **Day 1**: Enable for 5% of users
2. **Day 2**: Enable for 25% if healthy
3. **Day 3**: Enable for 100%

---

## 8. Documentation

### Files to Update

| File | Status | Action |
|------|--------|--------|
| `FRONTEND_REVIEW_STATE.md` | EXISTS | Mark P0s as FIXED |
| `PENDING_FRONTEND_NOTES.md` | MISSING | CREATE |
| `FRONTEND_MAINTENANCE_GAPS.md` | MISSING | CREATE |

---

## 9. Final Acceptance Checklist

### Security
- [ ] /settings returns 403 for non-admin
- [ ] /users returns 403 for non-admin
- [ ] Force Close requires admin permission

### Data Integrity
- [ ] Returns link to invoices
- [ ] Return qty ≤ invoice qty
- [ ] Manual allocation sum === payment amount

### Operational Safety
- [ ] Force Close available with reason
- [ ] Idempotency key sent on all mutations

### Tests
- [ ] Unit tests pass
- [ ] E2E tests pass critical paths
- [ ] Manual QA sign-off

---

## User Review Required

> [!IMPORTANT]
> **Backend Dependency**: T-004 (Force Close) requires new backend endpoint.

> [!WARNING]
> **Breaking Change**: Returns will require invoice selection.

> [!CAUTION]
> **Test Infrastructure**: No existing tests found. Budget time for setup.

---

**Approval Required Before Execution**
