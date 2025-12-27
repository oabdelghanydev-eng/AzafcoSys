# Changes Report 2025-12-19

## Changes Summary

### 1. Changing Field Names in Invoice API

**New Fields:**
| Field | Type | Description |
|-------|------|-------|
| `cartons` | integer, required | Number of cartons sold |
| `total_weight` | numeric, required | Total weight (from scale) |
| `price` | numeric, required | Price per Kg |

**Request Example:**
```json
{
  "customer_id": 1,
  "date": "2025-12-19",
  "items": [{
    "product_id": 1,
    "cartons": 3,
    "total_weight": 73.0,
    "price": 50.0
  }]
}
```

### 2. Daily Report Modification

- **Invoices Table**: Shows Cartons + Unit Weight + Price/Kg + Total Amount
- **Remaining Stock**: Shows Remaining Cartons × Unit Weight = Expected Weight
- **Daily Shortage**: (Cartons × Unit Weight) - Actual Weight

### 3. Modified Files

- `app/Http/Requests/Api/StoreInvoiceRequest.php`
- `app/Http/Controllers/Api/InvoiceController.php`
- `app/Services/Reports/DailyClosingReportService.php`
- `resources/views/reports/daily-closing.blade.php`
- 5 Test files

---

## Tests

```
Tests: 171 passed, 3 skipped, 0 failed ✅
```

---

## Note for Future Review

Shipment handling is by **cartons**:
- Cannot sell more cartons than available in shipment
- If wanting to sell more, must carryover and add item from another shipment
- Shortage is calculated at shipment settlement: Incoming Weight - Sold Weight
