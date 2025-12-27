# User Management

## 📋 Overview

User and permissions management system based on JSON Array permissions.

---

## 👥 User Model

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NULL,      -- For users without Google
    google_id VARCHAR(255) NULL,
    avatar VARCHAR(255) NULL,
    
    -- Permissions
    permissions JSON DEFAULT '[]',    -- Array of permission codes
    is_admin BOOLEAN DEFAULT FALSE,   -- Admin overrides all permissions
    
    -- Security
    failed_login_attempts TINYINT DEFAULT 0,
    is_locked BOOLEAN DEFAULT FALSE,
    locked_at TIMESTAMP NULL,
    locked_by BIGINT NULL
);
```

---

## 🔐 Permissions System

### Permissions Structure (48 permissions)

```
┌─────────────────────────────────────────────────────────┐
│                    Permission Format                     │
│                                                          │
│  module.action                                           │
│  ───────────────                                         │
│  invoices.create                                         │
│  invoices.view                                           │
│  invoices.edit                                           │
│  invoices.delete  ← Disabled (Observer blocks)          │
│  invoices.cancel  ← Alternative                         │
│  invoices.export_pdf (New)                              │
│  invoices.export_excel (New)                            │
└─────────────────────────────────────────────────────────┘
```

### Permissions List

| Module | Permissions |
|--------|-------------|
| **invoices** | view, create, edit, delete, cancel |
| **collections** | view, create, edit, delete, cancel |
| **expenses** | view, create, edit, delete |
| **shipments** | view, create, edit, delete, close |
| **inventory** | view, adjust, wastage |
| **cashbox** | view, deposit, withdraw, transfer |
| **bank** | view, deposit, withdraw, transfer |
| **customers** | view, create, edit, delete |
| **reports** | daily, settlement, customers, suppliers, inventory, export_pdf, export_excel, share |
| **daily** | close, reopen |
| **users** | view, create, edit, delete, unlock |
| **settings** | view, edit |
| **corrections** | approve ← New |

---

## 🔄 User Lifecycle

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   CREATE    │────▶│   ACTIVE    │────▶│   LOCKED    │
│  (Admin)    │     │             │     │ (3 fails)   │
└─────────────┘     └──────┬──────┘     └──────┬──────┘
                           │                   │
                           │                   ▼
                           │            ┌─────────────┐
                           │            │   UNLOCK    │
                           │            │  (Admin)    │
                           │            └──────┬──────┘
                           │                   │
                           │◀──────────────────┘
                           ▼
                    ┌─────────────┐
                    │   DELETE    │
                    │  (Soft)     │
                    └─────────────┘
```

---

## 📝 Business Rules

### BR-USR-001: Create User
| Field | Value |
|-------|-------|
| **Description** | Only Admin can create users |
| **Rule** | `user.is_admin = true OR hasPermission('users.create')` |
| **Required Fields** | name, email |
| **Optional Fields** | password, permissions, is_admin |

### BR-USR-002: Edit Permissions
| Field | Value |
|-------|-------|
| **Description** | Edit user permissions |
| **Rule** | `hasPermission('users.edit')` |
| **Constraint** | Cannot edit own permissions |

### BR-USR-003: Auto Lock Account
| Field | Value |
|-------|-------|
| **Description** | After 3 failed attempts |
| **Rule** | `if (failed_attempts >= 3) lock()` |
| **Effect** | is_locked = true, locked_at = now() |

### BR-USR-004: Unlock Account
| Field | Value |
|-------|-------|
| **Description** | Only Admin can unlock |
| **Rule** | `hasPermission('users.unlock')` |
| **Effect** | is_locked = false, failed_attempts = 0 |

### BR-USR-005: Prevent Deleting Last Admin
| Field | Value |
|-------|-------|
| **Description** | System must have at least one Admin |
| **Rule** | `if (User::where('is_admin', true)->count() <= 1) throw` |

### BR-USR-006: Prevent Self Deletion
| Field | Value |
|-------|-------|
| **Description** | User cannot delete themselves |
| **Rule** | `if ($user->id === auth()->id()) throw` |

---

## 🧮 Permissions Service

```php
// User Model
class User extends Authenticatable
{
    // Check single permission
    public function hasPermission(string $permission): bool
    {
        if ($this->is_admin) {
            return true;
        }
        return in_array($permission, $this->permissions ?? []);
    }

    // Check any of multiple permissions
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->is_admin) {
            return true;
        }
        return !empty(array_intersect($permissions, $this->permissions ?? []));
    }

    // Check all permissions
    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->is_admin) {
            return true;
        }
        return empty(array_diff($permissions, $this->permissions ?? []));
    }
}
```

---

## 📊 API Endpoints

### Users CRUD

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/api/users` | `users.view` | List users |
| GET | `/api/users/{id}` | `users.view` | User details |
| POST | `/api/users` | `users.create` | Create user |
| PUT | `/api/users/{id}` | `users.edit` | Update user |
| DELETE | `/api/users/{id}` | `users.delete` | Delete user |

### User Actions

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| POST | `/api/users/{id}/lock` | `users.edit` | Lock account |
| POST | `/api/users/{id}/unlock` | `users.unlock` | Unlock account |
| PUT | `/api/users/{id}/permissions` | `users.edit` | Update permissions |
| PUT | `/api/users/{id}/password` | `users.edit` | Change password |

### Permissions Reference

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/api/permissions` | `users.view` | List available permissions |

---

## 🔐 Validation Rules

### CreateUserRequest

```php
[
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
    'password' => 'nullable|string|min:8',
    'permissions' => 'nullable|array',
    'permissions.*' => 'string|in:' . implode(',', $validPermissions),
    'is_admin' => 'boolean',
]
```

### UpdateUserRequest

```php
[
    'name' => 'sometimes|string|max:255',
    'email' => 'sometimes|email|unique:users,email,' . $userId,
    'permissions' => 'nullable|array',
    'permissions.*' => 'string|in:' . implode(',', $validPermissions),
    'is_admin' => 'boolean',
]
```

### UpdatePasswordRequest

```php
[
    'password' => 'required|string|min:8|confirmed',
]
```

---

## 🔗 Error Codes

| Code | Original | Description |
|------|--------|---------|
| USR_001 | Email already exists | Email already in use |
| USR_002 | Cannot delete yourself | Self-deletion protection |
| USR_003 | Cannot delete last admin | System integrity protection |
| USR_004 | Account is locked | Login prevention |
| USR_005 | Cannot modify own permissions | Security protection |
| USR_006 | User not found | Invalid ID |

---

## 📁 Files

| File | Purpose |
|------|---------|
| `Models/User.php` | User model |
| `Http/Controllers/Api/UserController.php` | Users CRUD |
| `Http/Requests/CreateUserRequest.php` | Validation |
| `Http/Requests/UpdateUserRequest.php` | Validation |
| `Policies/UserPolicy.php` | Authorization |
