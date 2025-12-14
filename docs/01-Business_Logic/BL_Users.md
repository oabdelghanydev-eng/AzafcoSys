# User Management - إدارة المستخدمين

## 📋 نظرة عامة

نظام إدارة المستخدمين والصلاحيات بناءً على JSON Array permissions.

---

## 👥 نموذج المستخدم

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NULL,      -- للمستخدمين بدون Google
    google_id VARCHAR(255) NULL,
    avatar VARCHAR(255) NULL,
    
    -- Permissions
    permissions JSON DEFAULT '[]',    -- Array of permission codes
    is_admin BOOLEAN DEFAULT FALSE,   -- Admin يتجاوز كل الصلاحيات
    
    -- Security
    failed_login_attempts TINYINT DEFAULT 0,
    is_locked BOOLEAN DEFAULT FALSE,
    locked_at TIMESTAMP NULL,
    locked_by BIGINT NULL
);
```

---

## 🔐 نظام الصلاحيات

### هيكل الصلاحيات (48 صلاحية)

```
┌─────────────────────────────────────────────────────────┐
│                    Permission Format                     │
│                                                          │
│  module.action                                           │
│  ───────────────                                         │
│  invoices.create                                         │
│  invoices.view                                           │
│  invoices.edit                                           │
│  invoices.delete  ← مُعطل (Observer يمنع)               │
│  invoices.cancel  ← البديل                              │
└─────────────────────────────────────────────────────────┘
```

### قائمة الصلاحيات

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
| **corrections** | approve ← جديد |

---

## 🔄 User Lifecycle

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   CREATE    │────▶│   ACTIVE    │────▶│   LOCKED    │
│  (Admin)    │     │             │     │ (3 فشل)     │
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

## 📝 قواعد العمل

### BR-USR-001: إنشاء مستخدم
| الحقل | القيمة |
|-------|--------|
| **الوصف** | فقط Admin يمكنه إنشاء مستخدمين |
| **القاعدة** | `user.is_admin = true OR hasPermission('users.create')` |
| **الحقول المطلوبة** | name, email |
| **الحقول الاختيارية** | password, permissions, is_admin |

### BR-USR-002: تعديل صلاحيات
| الحقل | القيمة |
|-------|--------|
| **الوصف** | تعديل صلاحيات مستخدم |
| **القاعدة** | `hasPermission('users.edit')` |
| **القيد** | لا يمكن للمستخدم تعديل صلاحياته الخاصة |

### BR-USR-003: قفل الحساب التلقائي
| الحقل | القيمة |
|-------|--------|
| **الوصف** | بعد 3 محاولات فاشلة |
| **القاعدة** | `if (failed_attempts >= 3) lock()` |
| **الأثر** | is_locked = true, locked_at = now() |

### BR-USR-004: فتح الحساب
| الحقل | القيمة |
|-------|--------|
| **الوصف** | فقط Admin يمكنه فتح حساب مقفل |
| **القاعدة** | `hasPermission('users.unlock')` |
| **الأثر** | is_locked = false, failed_attempts = 0 |

### BR-USR-005: منع حذف Admin الأخير
| الحقل | القيمة |
|-------|--------|
| **الوصف** | النظام يجب أن يحتوي على Admin واحد على الأقل |
| **القاعدة** | `if (User::where('is_admin', true)->count() <= 1) throw` |

### BR-USR-006: منع حذف النفس
| الحقل | القيمة |
|-------|--------|
| **الوصف** | لا يمكن للمستخدم حذف نفسه |
| **القاعدة** | `if ($user->id === auth()->id()) throw` |

---

## 🧮 خدمة الصلاحيات

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
| GET | `/api/users` | `users.view` | قائمة المستخدمين |
| GET | `/api/users/{id}` | `users.view` | تفاصيل مستخدم |
| POST | `/api/users` | `users.create` | إنشاء مستخدم |
| PUT | `/api/users/{id}` | `users.edit` | تعديل مستخدم |
| DELETE | `/api/users/{id}` | `users.delete` | حذف مستخدم |

### User Actions

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| POST | `/api/users/{id}/lock` | `users.edit` | قفل حساب |
| POST | `/api/users/{id}/unlock` | `users.unlock` | فتح حساب |
| PUT | `/api/users/{id}/permissions` | `users.edit` | تعديل صلاحيات |
| PUT | `/api/users/{id}/password` | `users.edit` | تغيير كلمة المرور |

### Permissions Reference

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/api/permissions` | `users.view` | قائمة الصلاحيات المتاحة |

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

| Code | Arabic | English |
|------|--------|---------|
| USR_001 | البريد الإلكتروني مسجل مسبقاً | Email already exists |
| USR_002 | لا يمكن حذف نفسك | Cannot delete yourself |
| USR_003 | لا يمكن حذف آخر Admin | Cannot delete last admin |
| USR_004 | الحساب مقفل | Account is locked |
| USR_005 | لا يمكن تعديل صلاحياتك | Cannot modify own permissions |
| USR_006 | مستخدم غير موجود | User not found |

---

## 📁 Files

| File | Purpose |
|------|---------|
| `Models/User.php` | User model |
| `Http/Controllers/Api/UserController.php` | Users CRUD |
| `Http/Requests/CreateUserRequest.php` | Validation |
| `Http/Requests/UpdateUserRequest.php` | Validation |
| `Policies/UserPolicy.php` | Authorization |
