# 🚀 Deployment Guide - Azafco System

> **Last Updated:** December 2025  
> **Version:** 1.0.0  
> **Stack:** Laravel 11 + Next.js 15

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Requirements](#server-requirements)
3. [Backend Deployment](#backend-deployment)
4. [Frontend Deployment](#frontend-deployment)
5. [Database Setup](#database-setup)
6. [Environment Configuration](#environment-configuration)
7. [Security Checklist](#security-checklist)
8. [Post-Deployment Verification](#post-deployment-verification)
9. [Troubleshooting](#troubleshooting)

---

## 1. Prerequisites

### Required Software
```
PHP >= 8.2
Composer >= 2.6
Node.js >= 18.17
npm >= 9.0
MySQL >= 8.0
Nginx or Apache
```

### PHP Extensions
```
bcmath, ctype, curl, dom, fileinfo, gd, intl, 
json, mbstring, openssl, pdo_mysql, tokenizer, xml, zip
```

---

## 2. Server Requirements

### Minimum Specifications
| Resource | Development | Production |
|----------|-------------|------------|
| CPU | 2 cores | 4+ cores |
| RAM | 2GB | 4GB+ |
| Storage | 20GB | 50GB+ SSD |
| Bandwidth | 10Mbps | 100Mbps+ |

### Recommended Stack
```
Ubuntu 22.04 LTS
Nginx 1.24+
MySQL 8.0+
PHP-FPM 8.2+
Supervisor (for queues)
Certbot (SSL)
```

---

## 3. Backend Deployment

### Step 1: Clone Repository
```bash
cd /var/www
git clone <repository-url> azafco
cd azafco/backend
```

### Step 2: Install Dependencies
```bash
composer install --optimize-autoloader --no-dev
```

### Step 3: Set Permissions
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Step 4: Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```

### Step 5: Run Migrations
```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder  # If available
```

### Step 6: Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Step 7: Create Storage Link
```bash
php artisan storage:link
```

### Nginx Configuration (Backend)
```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/azafco/backend/public;
    
    index index.php;
    
    charset utf-8;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 4. Frontend Deployment

### Step 1: Navigate to Frontend
```bash
cd /var/www/azafco/nextjs-app
```

### Step 2: Install Dependencies
```bash
npm ci --production
```

### Step 3: Configure Environment
```bash
cp .env.example .env.local
```

Edit `.env.local`:
```env
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
```

### Step 4: Build Production
```bash
npm run build
```

### Step 5: Start with PM2
```bash
npm install pm2 -g
pm2 start npm --name "azafco-frontend" -- start
pm2 save
pm2 startup
```

### Nginx Configuration (Frontend)
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

---

## 5. Database Setup

### Create Database
```sql
CREATE DATABASE azafco_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'azafco_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON azafco_system.* TO 'azafco_user'@'localhost';
FLUSH PRIVILEGES;
```

### Import Existing Data (if migrating)
```bash
mysql -u azafco_user -p azafco_system < backup.sql
```

### Run Migrations
```bash
php artisan migrate --force
```

---

## 6. Environment Configuration

### Backend `.env` (Production)
```env
APP_NAME="Azafco System"
APP_ENV=production
APP_KEY=base64:GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

# Locale
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=azafco_system
DB_USERNAME=azafco_user
DB_PASSWORD=STRONG_PASSWORD_HERE

# Session (use database in production)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true

# Cache
CACHE_STORE=database

# Queue
QUEUE_CONNECTION=database

# Security
BCRYPT_ROUNDS=12

# CORS (Frontend URL)
SANCTUM_STATEFUL_DOMAINS=yourdomain.com
```

### Frontend `.env.local` (Production)
```env
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
```

---

## 7. Security Checklist

### ✅ Pre-Deployment
- [ ] `APP_DEBUG=false` in production
- [ ] Strong database passwords
- [ ] SSL certificates installed
- [ ] Firewall configured (ports 80, 443 only)
- [ ] SSH key authentication only
- [ ] Rate limiting enabled

### ✅ Post-Deployment
- [ ] Test API authentication
- [ ] Verify CORS settings
- [ ] Check PDF generation
- [ ] Test all report downloads
- [ ] Verify file permissions

### SSL Setup (Certbot)
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d api.yourdomain.com
```

---

## 8. Post-Deployment Verification

### Backend Health Check
```bash
curl -s https://api.yourdomain.com/api/health | jq
```

Expected:
```json
{
  "status": "ok",
  "database": "connected",
  "cache": "working"
}
```

### Frontend Check
```bash
curl -I https://yourdomain.com
```

Expected: `HTTP/2 200`

### Run Tests (Optional)
```bash
cd /var/www/azafco/backend
php artisan test --parallel
```

### Check Logs
```bash
# Laravel logs
tail -f /var/www/azafco/backend/storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/error.log

# PM2 logs
pm2 logs azafco-frontend
```

---

## 9. Troubleshooting

### Common Issues

#### 500 Internal Server Error
```bash
# Check permissions
sudo chown -R www-data:www-data storage bootstrap/cache

# Check logs
cat storage/logs/laravel.log | tail -50

# Clear cache
php artisan config:clear
php artisan cache:clear
```

#### CORS Errors
```bash
# Verify SANCTUM_STATEFUL_DOMAINS in .env
# Clear config cache
php artisan config:clear
php artisan config:cache
```

#### PDF Generation Fails
```bash
# Check GD extension
php -m | grep gd

# Check write permissions
ls -la storage/app
```

#### Database Connection Refused
```bash
# Check MySQL status
sudo systemctl status mysql

# Test connection
mysql -u azafco_user -p -e "SELECT 1;"
```

---

## 📞 Support

| Issue Type | Action |
|------------|--------|
| Bug Report | Check logs first |
| Security Issue | Immediate escalation |
| Performance | Check MySQL slow query log |

---

## 📌 Quick Reference

```bash
# Backend Commands
php artisan migrate          # Run migrations
php artisan cache:clear      # Clear cache
php artisan config:cache     # Cache config
php artisan queue:work       # Process queue

# Frontend Commands
npm run build                # Build production
pm2 restart azafco-frontend  # Restart app
pm2 logs                     # View logs

# Server Commands
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql
```

---

**🎉 Deployment Complete!**
