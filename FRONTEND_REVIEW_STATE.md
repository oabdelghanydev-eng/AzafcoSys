# Frontend Review State

**Reviewer**: Independent Principal Frontend Architect  
**Date**: 2025-12-27  
**App**: `nextjs-app/src/app`

---

## Pages Discovered

| Route | Page File | Priority | Status |
|-------|-----------|----------|--------|
| `/` | `page.tsx` (Dashboard) | P0 | ✅ REVIEWED |
| `/invoices` | `invoices/page.tsx` | P0 | ✅ REVIEWED |
| `/invoices/new` | `invoices/new/page.tsx` | P0 | ✅ REVIEWED |
| `/invoices/[id]` | `invoices/[id]/page.tsx` | P0 | ✅ REVIEWED |
| `/collections` | `collections/page.tsx` | P0 | ❌ UNSAFE |
| `/collections/new` | `collections/new/page.tsx` | P0 | ❌ UNSAFE |
| `/collections/[id]` | `collections/[id]/page.tsx` | P0 | ❌ UNSAFE |
| `/returns` | `returns/page.tsx` | P0 | ❌ UNSAFE |
| `/returns/new` | `returns/new/page.tsx` | P0 | ❌ UNSAFE |
| `/returns/[id]` | `returns/[id]/page.tsx` | P0 | ❌ UNSAFE |
| `/expenses` | `expenses/page.tsx` | P1 | ✅ REVIEWED |
| `/expenses/new` | `expenses/new/page.tsx` | P1 | ✅ REVIEWED |
| `/expenses/[id]` | `expenses/[id]/page.tsx` | P1 | ✅ REVIEWED |
| `/shipments` | `shipments/page.tsx` | P1 | ✅ REVIEWED |
| `/shipments/new` | `shipments/new/page.tsx` | P1 | ✅ REVIEWED |
| `/shipments/[id]` | `shipments/[id]/page.tsx` | P1 | ✅ REVIEWED |
| `/daily` | `daily/page.tsx` | P0 | ✅ REVIEWED |
| `/customers` | `customers/page.tsx` | P2 | ✅ REVIEWED |
| `/customers/new` | `customers/new/page.tsx` | P2 | ✅ REVIEWED |
| `/customers/[id]` | `customers/[id]/page.tsx` | P2 | ✅ REVIEWED |
| `/suppliers` | `suppliers/page.tsx` | P2 | ✅ REVIEWED |
| `/suppliers/new` | `suppliers/new/page.tsx` | P2 | ✅ REVIEWED |
| `/suppliers/[id]` | `suppliers/[id]/page.tsx` | P2 | ✅ REVIEWED |
| `/reports` | `reports/page.tsx` | P2 | ✅ REVIEWED |
| `/settings` | `settings/page.tsx` | P0 | ❌ UNSAFE |
| `/users` | `users/page.tsx` | P0 | ❌ UNSAFE |
| `/accounts` | `accounts/page.tsx` | P2 | ✅ REVIEWED |

---

## Completed Reviews

### Page 13: Accounts Module (1 file + hook)

**Routes**: `/accounts`

---

# A. Page Identity

**Routes**: `/accounts` — Treasury/Cash Management

**Entry Points**: Sidebar "Accounts"

**User Intent**: Check cash position and transfer funds.

---

# B. Functional Purpose

**Problem Solved**: Liquidity management.

**Recommendations**: **FIX IMMEDIATELY** — Contains magic numbers.

---

# C. Top Issues

### C1. Hardcoded Account IDs (P0)
- **File**: `accounts/page.tsx:51`
- **Issue**: `from_account_id` uses hardcoded `1` (Cashbox) and `2` (Bank).
- **Risk**: If production IDs differ, money vanishes into void or errors.
- **Fix**: Fetch accounts dynamically and find by `type` or `code`.

### C2. Incomplete Filters (P2)
- **File**: `accounts/page.tsx:331`
- **Issue**: Tabs for "Cashbox" and "Bank" say "Coming soon".

---

# D. Concrete Fixes

### D1. Dynamic Account Lookup

**File**: `accounts/page.tsx`

```tsx
const cashboxId = summary.cashbox?.id; 
const bankId = summary.bank?.id;
// Use these variables instead of 1 and 2
```

---

### Page 12: Users Module (1 file)

**Routes**: `/users`

**Verdict**: **KEEP**. Functional. RBAC logic handled via backend errors mostly, UI is optimistic.

---

### Page 11: Settings Module (1 file)

**Routes**: `/settings`

**Verdict**: **IMPROVE**. Missing explicit `<PermissionGate>` for the whole page. Should wrap entire content in `admin.settings` check.

---

## Next Steps

**ALL PAGES REVIEWED.**

---

## Final Open Issues Summary

| ID | Page | Issue | Priority |
|----|------|-------|----------|
| DAILY-001 | Daily | Missing Force Close for deadlocks | **P0** |
| RET-001 | Returns | Missing Original Invoice Link | **P0** |
| COL-001 | Collections | Manual allocation UI missing | **P0** |
| SET-001 | Settings | Missing PermissionGate | **P0** |
| USR-001 | Users | Missing PermissionGate | **P0** |
| CUS-001 | Customers | Missing Credit Limit Field | P1 |
| ACC-001 | Accounts | Hardcoded Account IDs (1, 2) | *PENDING* |
| SHP-001 | Shipments | Carryover Supplier Mismatch | *PENDING* |
| EXP-001 | Expenses | Missing Bank Source Select | *PENDING* |

