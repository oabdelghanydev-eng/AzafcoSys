# 📝 Production Deployment Checklist

## Pre-Deployment

### 1. Code Preparation
- [ ] All tests passing (`php artisan test`)
- [ ] Frontend builds successfully (`npm run build`)
- [ ] No console errors or warnings
- [ ] Git repository up to date

### 2. Server Preparation
- [ ] Server provisioned with required specs
- [ ] PHP 8.2+ installed with extensions
- [ ] MySQL 8.0+ installed
- [ ] Nginx installed and configured
- [ ] Node.js 18+ installed
- [ ] PM2 installed globally

### 3. Security Preparation
- [ ] SSL certificates obtained
- [ ] Firewall configured
- [ ] SSH keys configured
- [ ] Strong passwords generated

---

## Deployment Steps

### Backend (Laravel)
```bash
# 1. Clone & Setup
git clone <repo> /var/www/azafco
cd /var/www/azafco/backend

# 2. Install dependencies
composer install --optimize-autoloader --no-dev

# 3. Environment
cp .env.example .env
php artisan key:generate
# Edit .env with production values

# 4. Permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 5. Database
php artisan migrate --force

# 6. Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### Frontend (Next.js)
```bash
# 1. Navigate
cd /var/www/azafco/nextjs-app

# 2. Dependencies
npm ci --production

# 3. Environment
echo "NEXT_PUBLIC_API_URL=https://api.yourdomain.com" > .env.local

# 4. Build
npm run build

# 5. Start
pm2 start npm --name "azafco-frontend" -- start
pm2 save
```

---

## Post-Deployment Verification

### API Health
```bash
curl https://api.yourdomain.com/api/health
# Expected: {"status":"ok"}
```

### Frontend Status
```bash
curl -I https://yourdomain.com
# Expected: HTTP/2 200
```

### Login Test
- [ ] Admin login works
- [ ] Token persists on refresh
- [ ] Logout clears session

### Core Features
- [ ] Dashboard loads with data
- [ ] Create new invoice
- [ ] Create collection
- [ ] Generate PDF report
- [ ] View customer statement

### PDF Reports
- [ ] Daily Closing Report
- [ ] Customer Statement
- [ ] Shipment Settlement
- [ ] All report pages load

---

## Environment Variables Checklist

### Backend `.env`
```
✅ APP_ENV=production
✅ APP_DEBUG=false
✅ APP_KEY=generated
✅ APP_URL=https://api.yourdomain.com
✅ DB_* credentials set
✅ SESSION_SECURE_COOKIE=true
✅ SANCTUM_STATEFUL_DOMAINS=yourdomain.com
```

### Frontend `.env.local`
```
✅ NEXT_PUBLIC_API_URL=https://api.yourdomain.com
```

---

## SSL Setup
```bash
sudo certbot --nginx -d yourdomain.com -d api.yourdomain.com
```

---

## Rollback Plan

### If deployment fails:
```bash
# 1. Revert to previous version
cd /var/www/azafco
git checkout <previous-commit>

# 2. Reinstall dependencies
composer install --no-dev
npm ci --production

# 3. Rebuild frontend
npm run build

# 4. Restart services
php artisan config:cache
pm2 restart azafco-frontend
sudo systemctl restart nginx
```

---

## Monitoring

### Check Logs
```bash
# Laravel
tail -f /var/www/azafco/backend/storage/logs/laravel.log

# Nginx
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Frontend
pm2 logs azafco-frontend
```

### Database Backup
```bash
# Daily backup (add to crontab)
mysqldump -u azafco_user -p azafco_system > /backup/db_$(date +%Y%m%d).sql
```

---

## Contact for Issues

| Priority | Response Time |
|----------|---------------|
| Critical (site down) | Immediate |
| High (feature broken) | < 4 hours |
| Medium (minor issue) | < 24 hours |
| Low (enhancement) | Next sprint |

---

**Date Deployed:** _______________  
**Deployed By:** _______________  
**Verified By:** _______________
