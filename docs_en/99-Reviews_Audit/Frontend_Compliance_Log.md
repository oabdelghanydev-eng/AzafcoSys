# Frontend Compliance Log

Tracking Frontend compliance with Backend and Documentation.

**Last Updated:** 2025-12-13

---

## ✅ Fixed (2025-12-13)

### Types (src/types/index.ts)
- **Collection.distribution_method**
  - Previous: `'auto' | 'manual'`
  - Now: `'oldest_first' | 'newest_first' | 'manual'`
  
- **Collection.status**
  - Added: `'confirmed' | 'cancelled'`

- **Account interface** (NEW)
  - Added for proper type inference

- **Transfer interface** (NEW)
  - Added for proper type inference

### API Client (src/lib/api.ts)
- **deleteCollection → cancelCollection**
  - Previous: `DELETE /collections/{id}`
  - Now: `POST /collections/{id}/cancel`
  - Matches: BR-COL-007 (no deletion policy)

- **createCollection.distribution_method**
  - Updated to match backend values

### Collections List (src/app/collections/page.tsx)
- Updated distribution method display labels

### New Collection Form (src/app/collections/new/page.tsx)
- Added "Newest First (LIFO)" button
- Updated default to `oldest_first`
- Fixed all type references

---

## ✅ TypeScript Errors Fixed (2025-12-13)

| File | Issue | Fix |
|------|-------|-----|
| `accounts/page.tsx` | `unknown[]` type | Added `Account` type import |
| `transfers/page.tsx` | `extractData` type | Added `Account` type import |
| `returns/page.tsx` | `unknown[]` type | Added `Return` type import |
| `returns/new/page.tsx` | Missing Textarea | Created component |

### New Component Created
- `src/components/ui/textarea.tsx` - shadcn/ui style Textarea

---

## ✅ Improvements (2025-12-13)

### Error Handling System

#### API Client (src/lib/api.ts)
- **ApiResponseError class** (NEW)
  - Custom error class with `code`, `messageAr`, `messageEn`
  - `getMessage(locale)` method for bilingual support
  
- **Updated request method**
  - Now throws `ApiResponseError` for business errors
  - Preserves error codes from backend

#### Error Utilities (src/lib/errors.ts) (NEW FILE)
- `getErrorMessage(error, locale)` – Extract error message in specified language
- `getErrorCode(error)` – Extract error code from any error
- `isErrorCode(error, code)` – Check if error matches specific code
- `ErrorCodes` – Frontend mirror of backend error codes

#### Updated Components
- `collections/new/page.tsx` – Uses `getErrorMessage` for Arabic errors

---

## Files Modified

1. `src/types/index.ts` – Collection, Account, Transfer interfaces
2. `src/lib/api.ts` – cancelCollection + ApiResponseError
3. `src/lib/errors.ts` – NEW FILE (error utilities)
4. `src/app/collections/page.tsx` – Display labels
5. `src/app/collections/new/page.tsx` – Form options + error handling
6. `src/app/accounts/page.tsx` – Account type fix
7. `src/app/transfers/page.tsx` – Account type fix
8. `src/app/returns/page.tsx` – Return type fix
9. `src/components/ui/textarea.tsx` – NEW FILE
