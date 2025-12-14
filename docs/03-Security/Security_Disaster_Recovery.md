# Security & Backup Strategy - الأمان والنسخ الاحتياطي

## 📋 نظرة عامة

هذا الملف يوثق استراتيجيات الأمان والنسخ الاحتياطي للنظام.

---

## 🔐 الأمان (Security)

### 1. Authentication

| الآلية | التفاصيل |
|--------|----------|
| **Primary** | Google OAuth 2.0 |
| **Session** | Laravel Sanctum (Cookie-based SPA) |
| **Token Expiry** | 24 hours |
| **Refresh** | Automatic on activity |

### 2. Authorization (48 Permissions) <!-- تصحيح 2025-12-13 -->

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
    throw new \Exception("AUTH_003: الحساب مقفل");
}
```

### 5. Input Validation

```php
// جميع الـ FormRequest تتحقق من:
// - SQL Injection (Eloquent ORM يحمي تلقائياً)
// - XSS (Laravel escapes by default)
// - CSRF (Sanctum cookie)
// - Mass Assignment (fillable/guarded)
```

### 6. Audit Logging

```php
// كل عملية حساسة تُسجل
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

## 💾 استراتيجية النسخ الاحتياطي

### 1. Database Backup

#### تكرار النسخ:
| النوع | التكرار | الاحتفاظ |
|-------|---------|----------|
| Full Backup | يومياً (2:00 AM) | 30 يوم |
| Incremental | كل 6 ساعات | 7 أيام |
| Transaction Log | كل ساعة | 48 ساعة |

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

| المجلد | المحتوى | النسخ |
|--------|---------|-------|
| `/storage/app` | Uploaded files | يومياً |
| `/storage/logs` | Application logs | أسبوعياً |
| `.env` | Configuration | عند التغيير |

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

| Metric | Target | وصف |
|--------|--------|-----|
| **RPO** | 1 hour | Maximum data loss |
| **RTO** | 4 hours | Maximum downtime |

### Recovery Steps:

```
1. تحديد المشكلة
   └── Database corruption / Server failure / Security breach

2. إيقاف الخدمة (إذا لزم)
   └── php artisan down --message="صيانة طارئة"

3. استعادة آخر نسخة سليمة
   └── ./restore.sh backup_YYYYMMDD.sql.gz

4. التحقق من سلامة البيانات
   └── php artisan tinker
   └── Invoice::count(), Collection::count(), etc.

5. إعادة الخدمة
   └── php artisan up

6. مراجعة الـ Audit Logs
   └── تحديد آخر العمليات قبل المشكلة
```

---

## 🔐 Security Checklist

### Pre-Deployment:
- [ ] تغيير APP_DEBUG to false
- [ ] تغيير APP_ENV to production
- [ ] إنشاء APP_KEY جديد
- [ ] إعداد HTTPS (SSL)
- [ ] تفعيل Rate Limiting
- [ ] مراجعة الـ CORS settings

### Post-Deployment:
- [ ] اختبار الـ Lockout mechanism
- [ ] التحقق من الـ Audit Logs
- [ ] اختبار Backup/Restore
- [ ] مراجعة الـ Error Logging

### Monthly:
- [ ] مراجعة الـ Access Logs
- [ ] فحص الـ Permissions
- [ ] اختبار Restore من backup
- [ ] تحديث Dependencies

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

### Alerts (مقترح):
- CPU > 80% for 5 minutes
- Memory > 90%
- Disk > 85%
- Error rate > 1%
- Response time > 2s
