# Security & Backup Strategy

## 📋 Overview

This file documents the security and backup strategies for the system.

---

## 🔐 Security

### 1. Authentication

| Mechanism | Details |
|--------|----------|
| **Primary** | Google OAuth 2.0 |
| **Session** | Laravel Sanctum (Cookie-based SPA) |
| **Token Expiry** | 24 hours |
| **Refresh** | Automatic on activity |

### 2. Authorization (48 Permissions) <!-- Correction 2025-12-13 -->

```php
// Permission Categories
'shipments.*'       // 6 permissions
'invoices.*'        // 6 permissions
'collections.*'     // 6 permissions
'expenses.*'        // 6 permissions
'customers.*'       // 4 permissions
'suppliers.*'       // 4 permissions
'reports.*'         // 6 permissions
'settings.*'        // 4 permissions
'users.*'           // 4 permissions
```

### 3. Rate Limiting

```php
// config/fortify.php or RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});

RateLimiter::for('reports', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id);
});
```

### 4. Account Lockout

```php
// LoginController
if ($user->failed_login_attempts >= 3) {
    $user->update([
        'is_locked' => true,
        'locked_at' => now(),
    ]);
    throw new \Exception("AUTH_003: Account locked");
}
```

### 5. Input Validation

```php
// All FormRequests validate:
// - SQL Injection (Eloquent ORM protects automatically)
// - XSS (Laravel escapes by default)
// - CSRF (Sanctum cookie)
// - Mass Assignment (fillable/guarded)
```

### 6. Audit Logging

```php
// Every sensitive operation is logged
AuditLog::create([
    'user_id' => auth()->id(),
    'model_type' => 'Invoice',
    'model_id' => $invoice->id,
    'action' => 'cancelled',
    'old_values' => $oldData,
    'new_values' => $newData,
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);
```

---

## 💾 Backup Strategy

### 1. Database Backup

#### Frequency:
| Type | Frequency | Retention |
|-------|---------|----------|
| Full Backup | Daily (2:00 AM) | 30 days |
| Incremental | Every 6 hours | 7 days |
| Transaction Log | Hourly | 48 hours |

#### Hostinger Backup:
```bash
# Automated via Hostinger Control Panel
- Daily automatic backup
- 7 days retention
- One-click restore
```

#### Manual Backup Script:
```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/user/backups"
DB_NAME="inventory_system"

# Create backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_$DATE.sql

# Compress
gzip $BACKUP_DIR/db_$DATE.sql

# Upload to cloud (optional)
# aws s3 cp $BACKUP_DIR/db_$DATE.sql.gz s3://bucket/backups/

# Remove old backups (older than 30 days)
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete

echo "Backup completed: db_$DATE.sql.gz"
```

### 2. File Backup

| Directory | Content | Scheduling |
|--------|---------|-------|
| `/storage/app` | Uploaded files | Daily |
| `/storage/logs` | Application logs | Weekly |
| `.env` | Configuration | On Change |

### 3. Restore Procedure

```bash
#!/bin/bash
# restore.sh

BACKUP_FILE=$1

# Decompress if needed
if [[ $BACKUP_FILE == *.gz ]]; then
    gunzip $BACKUP_FILE
    BACKUP_FILE="${BACKUP_FILE%.gz}"
fi

# Restore
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $BACKUP_FILE

# Clear cache
php artisan cache:clear
php artisan config:clear

echo "Restore completed from: $BACKUP_FILE"
```

---

## 🔄 Disaster Recovery Plan

### RTO & RPO

| Metric | Target | Description |
|--------|--------|-----|
| **RPO** | 1 hour | Maximum data loss |
| **RTO** | 4 hours | Maximum downtime |

### Recovery Steps:

```
1. Identify the issue
   └── Database corruption / Server failure / Security breach

2. Stop service (if strictly needed)
   └── php artisan down --message="Emergency Maintenance"

3. Restore last healthy backup
   └── ./restore.sh backup_YYYYMMDD.sql.gz

4. Verify data integrity
   └── php artisan tinker
   └── Invoice::count(), Collection::count(), etc.

5. Restore service
   └── php artisan up

6. Review Audit Logs
   └── Identify last operations before the issue
```

---

## 🔐 Security Checklist

### Pre-Deployment:
- [ ] Change APP_DEBUG to false
- [ ] Change APP_ENV to production
- [ ] Generate new APP_KEY
- [ ] Configure HTTPS (SSL)
- [ ] Enable Rate Limiting
- [ ] Review CORS settings

### Post-Deployment:
- [ ] Test Lockout mechanism
- [ ] Verify Audit Logs
- [ ] Test Backup/Restore
- [ ] Review Error Logging

### Monthly:
- [ ] Review Access Logs
- [ ] Audit Permissions
- [ ] Test Restore from backup
- [ ] Update Dependencies

---

## 📊 Monitoring

### Health Checks:
```php
// routes/api.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => DB::connection()->getPdo() ? 'connected' : 'error',
        'cache' => Cache::get('health_check', 'ok'),
        'storage' => is_writable(storage_path()) ? 'writable' : 'error',
    ]);
});
```

### Alerts (Proposed):
- CPU > 80% for 5 minutes
- Memory > 90%
- Disk > 85%
- Error rate > 1%
- Response time > 2s
