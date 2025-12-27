# API Error Codes

## 📋 Overview

This file documents all error codes used in the API.

---

## 🔢 HTTP Status Codes

| Code | Meaning | Usage |
|------|--------|----------|
| `200` | OK | Successful operation |
| `201` | Created | Created successfully |
| `204` | No Content | Deleted/Cancelled successfully |
| `400` | Bad Request | Invalid data |
| `401` | Unauthorized | Not logged in |
| `403` | Forbidden | Not authorized |
| `404` | Not Found | Resource not found |
| `422` | Validation Error | Data validation failed |
| `429` | Too Many Requests | Rate limit exceeded |
| `500` | Server Error | Internal server error |

---

## 📑 Custom Error Codes

### Response Structure
```json
{
    "success": false,
    "error": {
        "code": "INV_001",
        "message": "Error message in Arabic",
        "message_en": "Error message in English",
        "details": {}
    }
}
```

---

## 📄 Invoice Errors (INV)

| Code | Message | Reason |
|-------|---------|-------|
| `INV_001` | Cannot delete invoices | Deletion forbidden, use cancellation |
| `INV_002` | Cannot reduce total below paid amount | `new_total < paid_amount` |
| `INV_003` | Cannot reactivate cancelled invoice | Attempt to change status from cancelled |
| `INV_004` | Invoice outside edit window | Exceeded `edit_window_days` |
| `INV_005` | Requested quantity not available | FIFO check failed |
| `INV_006` | Invoice must have at least one item | `items.length = 0` |
| `INV_008` | Cannot cancel paid invoice | `paid_amount > 0` |

---

## 💰 Collection Errors (COL)

| Code | Message | Reason |
|-------|---------|-------|
| `COL_001` | Cannot delete collections | Deletion forbidden |
| `COL_002` | Amount must be greater than zero | `amount <= 0` |
| `COL_003` | Invoice does not belong to this customer | Manual allocation error |
| `COL_004` | Collection outside edit window | Exceeded `edit_window_days` |
| `COL_005` | Total allocation exceeds collection amount | Over-allocation |
| `COL_006` | Amount exceeds invoice balance | Allocation exceeds balance |

---

## 📦 Return Errors (RET)

| Code | Message | Reason |
|-------|---------|-------|
| `RET_001` | No open shipment to receive return | No open shipment |

---

## 📦 Shipment Errors (SHP)

| Code | Message | Reason |
|-------|---------|-------|
| `SHP_001` | Cannot delete shipment with linked invoices | FK constraint |
| `SHP_002` | Cannot edit settled shipment | `status = settled` |
| `SHP_003` | Shipment already settled | Attempt to settle twice |
| `SHP_004` | Next shipment must be open | Selected shipment not open |
| `SHP_005` | Cannot unsettle - Quantity sold | Unsettle safety check |
| `SHP_009` | Cannot edit non-open shipment | Update only for open shipments |
| `SHP_010` | Cannot reduce quantity below sold quantity | `new_qty < sold_qty` |
| `SHP_006` | Cannot carryover to same shipment | Self carryover |
| `SHP_007` | Shipment is not settled | Unsettle on non-settled |

---

## 🔐 Auth Errors (AUTH)

| Code | Message | Reason |
|-------|---------|-------|
| `AUTH_001` | Not logged in | Token missing/expired |
| `AUTH_002` | unauthorized operation | No permission |
| `AUTH_003` | Account locked | `is_locked = true` |
| `AUTH_004` | Login failed | Invalid credentials |
| `AUTH_005` | Too many failed attempts | Failed attempts limit |

---

## ⚙️ System Errors (SYS)

| Code | Message | Reason |
|-------|---------|-------|
| `SYS_001` | Server Error | Uncaught exception |
| `SYS_002` | Too Many Requests | Rate limit exceeded |
| `SYS_003` | Service Temporarily Unavailable | Maintenance mode |

---

## 🏠 Additional Endpoints

### Health Check
| Method | Endpoint | Description |
|--------|----------|-------|
| `GET` | `/api/health` | API Health Check |

### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------|
| `GET` | `/api/dashboard` | Dashboard Summary |
| `GET` | `/api/dashboard/activity` | Recent Activities |

### Treasury (Cashbox & Bank)
| Method | Endpoint | Description |
|--------|----------|-------|
| `GET` | `/api/cashbox` | Cashbox Balance |
| `GET` | `/api/cashbox/transactions` | Cashbox Transactions |
| `POST` | `/api/cashbox/deposit` | Deposit to Cashbox |
| `POST` | `/api/cashbox/withdraw` | Withdraw from Cashbox |
| `GET` | `/api/bank` | Bank Balance |
| `GET` | `/api/bank/transactions` | Bank Transactions |
| `POST` | `/api/bank/deposit` | Deposit to Bank |
| `POST` | `/api/bank/withdraw` | Withdraw from Bank |

### Accounts & Transfers
| Method | Endpoint | Description |
|--------|----------|-------|
| `GET` | `/api/accounts` | List Accounts |
| `GET` | `/api/accounts/summary` | Balance Summary |
| `GET/POST` | `/api/transfers` | Transfers |

### Shipments
| Method | Endpoint | Description |
|--------|----------|-------|
| `GET` | `/api/shipments` | List Shipments |
| `POST` | `/api/shipments` | Create Shipment |
| `GET` | `/api/shipments/{id}` | Shipment Details |
| `PUT` | `/api/shipments/{id}` | Update Shipment (Open only) |
| `DELETE` | `/api/shipments/{id}` | Delete Shipment |
| `POST` | `/api/shipments/{id}/close` | Close Shipment |
| `POST` | `/api/shipments/{id}/settle` | Settle Shipment |
| `POST` | `/api/shipments/{id}/unsettle` | Unsettle Shipment |
| `GET` | `/api/shipments/stock` | Available Stock |
| `GET` | `/api/shipments/{id}/settlement-report` | Settlement Report |

### Inventory
| Method | Endpoint | Description |
|--------|----------|-------|
| `GET` | `/api/permissions` | Available Permissions |

---

## 📊 Rate Limiting

| Endpoint | Limit | Window |
|----------|------|---------|
| `/api/auth/*` | 10 requests | per minute |
| `/api/*` (authenticated) | 60 requests | per minute |
| `/api/reports/*` | 10 requests | per minute |

### Response Headers
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1702425600
```

---

## 🔄 Retry Logic

For retryable errors:
```javascript
const retryableErrors = [429, 500, 502, 503, 504];

async function fetchWithRetry(url, options, maxRetries = 3) {
    for (let i = 0; i < maxRetries; i++) {
        try {
            const response = await fetch(url, options);
            if (!retryableErrors.includes(response.status)) {
                return response;
            }
            await delay(Math.pow(2, i) * 1000); // Exponential backoff
        } catch (error) {
            if (i === maxRetries - 1) throw error;
        }
    }
}
```

---

## 🌐 Localization

Errors return in two languages:
```json
{
    "error": {
        "code": "INV_002",
        "message": "لا يمكن تقليل القيمة أقل من المدفوع",
        "message_en": "Cannot reduce total below paid amount"
    }
}
```

Using Header:
```
Accept-Language: ar  // Arabic
Accept-Language: en  // English
```
