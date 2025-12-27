# DevOps & CI/CD - Infrastructure & Deployment

## 📋 Overview

This file documents the deployment and CI/CD strategy for the system.

---

## 🏗️ Environments

| Environment | Purpose | URL |
|--------|-------|-----|
| **Local** | Development | localhost:8000 / localhost:3000 |
| **Staging** | Testing | staging.yoursite.com |
| **Production** | Live | app.yoursite.com |

---

## 📁 Project Structure (Hostinger)

```
/home/u123456789/
├── public_html/              # Frontend (Next.js static)
│   ├── _next/
│   ├── index.html
│   └── ...
│
├── api/                      # Backend (Laravel)
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/               # Symlink to public_html/api
│   ├── storage/
│   ├── .env
│   └── ...
│
├── backups/                  # Backup files
│   ├── daily/
│   └── manual/
│
└── logs/                     # Application logs
```

---

## 🚀 Deployment Process

### 1. Backend Deployment (Laravel)

```bash
#!/bin/bash
# deploy-backend.sh

# 1. Pull latest code
cd /home/user/api
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run database migrations
php artisan migrate --force

# 4. Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Restart queue workers (if any)
# php artisan queue:restart

# 6. Clear application cache
php artisan cache:clear

echo "Backend deployment completed!"
```

### 2. Frontend Deployment (Next.js)

```bash
#!/bin/bash
# deploy-frontend.sh

# 1. Build locally or in CI
npm run build

# 2. Export static files
npm run export

# 3. Upload to Hostinger
rsync -avz --delete out/ user@hostname:/home/user/public_html/

echo "Frontend deployment completed!"
```

---

## 🔄 CI/CD Pipeline (GitHub Actions)

### .github/workflows/deploy.yml

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

env:
  SSH_HOST: ${{ secrets.SSH_HOST }}
  SSH_USER: ${{ secrets.SSH_USER }}
  SSH_KEY: ${{ secrets.SSH_PRIVATE_KEY }}

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, mysql, redis
          
      - name: Install Backend Dependencies
        run: |
          cd backend
          composer install --prefer-dist --no-progress
          
      - name: Run Backend Tests
        run: |
          cd backend
          php artisan test
          
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: frontend/package-lock.json
          
      - name: Install Frontend Dependencies
        run: |
          cd frontend
          npm ci
          
      - name: Build Frontend
        run: |
          cd frontend
          npm run build

  deploy-backend:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy to Server
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ env.SSH_HOST }}
          username: ${{ env.SSH_USER }}
          key: ${{ env.SSH_KEY }}
          script: |
            cd /home/user/api
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan cache:clear

  deploy-frontend:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          
      - name: Build
        run: |
          cd frontend
          npm ci
          npm run build
          
      - name: Deploy to Server
        uses: burnett01/rsync-deployments@5.2
        with:
          switches: -avzr --delete
          path: frontend/out/
          remote_path: /home/user/public_html/
          remote_host: ${{ env.SSH_HOST }}
          remote_user: ${{ env.SSH_USER }}
          remote_key: ${{ env.SSH_KEY }}
```

---

## 📋 Deployment Checklist

### Pre-Deployment
- [ ] All tests passing
- [ ] Code reviewed and approved
- [ ] Database migrations tested locally
- [ ] Environment variables updated
- [ ] Backup created

### Deployment
- [ ] Pull latest code
- [ ] Install/update dependencies
- [ ] Run migrations
- [ ] Clear caches
- [ ] Verify deployment

### Post-Deployment
- [ ] Smoke test critical endpoints
- [ ] Check error logs
- [ ] Monitor performance
- [ ] Notify team

---

## 🔙 Rollback Procedure

### Quick Rollback (Git)
```bash
# Rollback to previous commit
cd /home/user/api
git revert HEAD --no-edit
git push origin main

# Or reset to specific commit
git reset --hard <commit_hash>
git push origin main --force
```

### Database Rollback
```bash
# Rollback last migration
php artisan migrate:rollback --step=1

# Rollback to specific batch
php artisan migrate:rollback --batch=5

# Full restore from backup
mysql -u user -p database < backup_file.sql
```

### Emergency Maintenance Mode
```bash
# Enable maintenance mode
php artisan down --message="We are performing system maintenance" --retry=60

# Do your fixes...

# Disable maintenance mode
php artisan up
```

---

## 📊 Health Monitoring

### Uptime Monitoring
```bash
# Cron job for health check
*/5 * * * * curl -s https://api.yoursite.com/api/health > /dev/null || echo "API Down" | mail -s "Alert" admin@example.com
```

### Log Monitoring
```bash
# Check for errors in last hour
tail -n 1000 /home/user/api/storage/logs/laravel.log | grep -i error
```

### Database Monitoring
```sql
-- Active connections
SHOW STATUS LIKE 'Threads_connected';

-- Slow queries
SHOW GLOBAL STATUS LIKE 'Slow_queries';
```

---

## 🔐 Secrets Management

### Environment Variables (Hostinger)
```bash
# .env (Production)
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yoursite.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=inventory_system
DB_USERNAME=****
DB_PASSWORD=****

CACHE_DRIVER=redis
SESSION_DRIVER=redis

GOOGLE_CLIENT_ID=****
GOOGLE_CLIENT_SECRET=****
```

### GitHub Secrets
| Secret Name | Description |
|-------------|-------------|
| `SSH_HOST` | Server IP/hostname |
| `SSH_USER` | SSH username |
| `SSH_PRIVATE_KEY` | SSH private key |
| `PRODUCTION_ENV` | .env file content |

---

## 🔗 Related Documentation

- [Security_Disaster_Recovery.md](../03-Security/Security_Disaster_Recovery.md) - Backup strategy
- [Performance_Tuning.md](Performance_Tuning.md) - Caching config
