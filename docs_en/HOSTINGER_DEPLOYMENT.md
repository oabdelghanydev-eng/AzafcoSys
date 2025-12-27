# 🚀 Hostinger Cloud Startup - Production Deployment Guide

> **Azafco Billing & Reports System**  
> **Stack:** Laravel 11 (API) + Next.js 15 (Frontend)

---

## 📋 Overview

### Architecture
```
┌─────────────────────────────────────────────────────────┐
│                  Hostinger Cloud Startup                 │
│                                                          │
│   ┌──────────────┐         ┌──────────────────────┐     │
│   │   Frontend   │         │      Backend API     │     │
│   │   Next.js    │   ──►   │      Laravel 11      │     │
│   │   :3000      │         │      :80/443         │     │
│   └──────────────┘         └──────────────────────┘     │
│         │                           │                    │
│         └───────────┬───────────────┘                    │
│                     │                                    │
│              ┌──────▼──────┐                             │
│              │   MySQL 8   │                             │
│              │   Database  │                             │
│              └─────────────┘                             │
└─────────────────────────────────────────────────────────┘

Domains:
  • yourdomain.com       → Next.js Frontend
  • api.yourdomain.com   → Laravel Backend API
```

### Hostinger Cloud Startup Specs
| Resource | Value |
|----------|-------|
| vCPU | 2 cores |
| RAM | 4 GB |
| NVMe Storage | 100 GB |
| Bandwidth | 4 TB |
| OS | Ubuntu 22.04 |

---

## 🔐 Part 1: Initial Server Setup

### Step 1.1: Access hPanel
1. Log into [Hostinger hPanel](https://hpanel.hostinger.com)
2. Navigate to **Cloud → Your Cloud Startup**
3. Find your **SSH credentials** in the dashboard

### Step 1.2: Connect via SSH
```bash
ssh root@YOUR_SERVER_IP
```

### Step 1.3: Create Non-Root User (Security Best Practice)
```bash
# Create new user
adduser azafco
usermod -aG sudo azafco

# Setup SSH key for new user
mkdir -p /home/azafco/.ssh
cp ~/.ssh/authorized_keys /home/azafco/.ssh/
chown -R azafco:azafco /home/azafco/.ssh
chmod 700 /home/azafco/.ssh
chmod 600 /home/azafco/.ssh/authorized_keys

# Switch to new user
su - azafco
```

### Step 1.4: Update System
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl wget unzip
```

---

## 📦 Part 2: Install Required Software

### Step 2.1: Install PHP 8.2+ & Extensions
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl \
    php8.2-bcmath php8.2-soap php8.2-dom php8.2-tokenizer
```

### Step 2.2: Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### Step 2.3: Install MySQL 8.0
```bash
sudo apt install -y mysql-server

# Secure MySQL installation
sudo mysql_secure_installation
# Answer: Y, set password, Y, Y, Y, Y
```

### Step 2.4: Install Nginx
```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

### Step 2.5: Install Node.js 18 LTS
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
node --version  # Should be v18.x.x
npm --version
```

### Step 2.6: Install PM2 (Process Manager)
```bash
sudo npm install -g pm2
pm2 --version
```

---

## 🗄️ Part 3: Database Setup

### Step 3.1: Create Database & User
```bash
sudo mysql
```

In MySQL console:
```sql
CREATE DATABASE azafco_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'azafco_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON azafco_system.* TO 'azafco_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> ⚠️ **Save this password!** You'll need it for `.env` configuration.

---

## 🔧 Part 4: Deploy Laravel Backend

### Step 4.1: Create Directory & Clone
```bash
sudo mkdir -p /var/www/azafco
sudo chown -R azafco:azafco /var/www/azafco
cd /var/www/azafco

git clone YOUR_GITHUB_REPO_URL .
# Or upload via SFTP
```

### Step 4.2: Install PHP Dependencies
```bash
cd /var/www/azafco/backend
composer install --optimize-autoloader --no-dev
```

### Step 4.3: Configure Environment
```bash
cp .env.example .env
nano .env
```

Edit `.env` with production values:
```env
APP_NAME="Azafco System"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

# Locale
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

# Database (use your password from Step 3.1)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=azafco_system
DB_USERNAME=azafco_user
DB_PASSWORD=YOUR_STRONG_PASSWORD_HERE

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true

# Cache & Queue
CACHE_STORE=database
QUEUE_CONNECTION=database

# Security
BCRYPT_ROUNDS=12

# CORS - Allow frontend domain
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,www.yourdomain.com
```

### Step 4.4: Generate Key & Run Migrations
```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder  # If you have one
```

### Step 4.5: Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/azafco/backend/storage
sudo chown -R www-data:www-data /var/www/azafco/backend/bootstrap/cache
sudo chmod -R 775 /var/www/azafco/backend/storage
sudo chmod -R 775 /var/www/azafco/backend/bootstrap/cache
```

### Step 4.6: Create Storage Link
```bash
php artisan storage:link
```

### Step 4.7: Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

## ⚛️ Part 5: Deploy Next.js Frontend

### Step 5.1: Navigate to Frontend
```bash
cd /var/www/azafco/nextjs-app
```

### Step 5.2: Install Node Dependencies
```bash
npm ci --production
```

### Step 5.3: Configure Environment
```bash
nano .env.local
```

Add:
```env
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
```

### Step 5.4: Build Production
```bash
npm run build
```

### Step 5.5: Start with PM2
```bash
pm2 start npm --name "azafco-frontend" -- start
pm2 save
pm2 startup systemd
# Follow the command output to enable startup
```

### Step 5.6: Verify Running
```bash
pm2 status
# Should show "azafco-frontend" as "online"
```

---

## 🌐 Part 6: Nginx Configuration

### Step 6.1: Backend API Configuration
```bash
sudo nano /etc/nginx/sites-available/api.yourdomain.com
```

Paste:
```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/azafco/backend/public;
    
    index index.php;
    charset utf-8;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Increase timeout for PDF generation
    fastcgi_read_timeout 300;
    
    client_max_body_size 50M;
}
```

### Step 6.2: Frontend Configuration
```bash
sudo nano /etc/nginx/sites-available/yourdomain.com
```

Paste:
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    
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
        proxy_read_timeout 60s;
    }
}
```

### Step 6.3: Enable Sites & Test
```bash
sudo ln -s /etc/nginx/sites-available/api.yourdomain.com /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/yourdomain.com /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Reload
sudo systemctl reload nginx
```

---

## 🔒 Part 7: SSL Certificates (Let's Encrypt)

### Step 7.1: Install Certbot
```bash
sudo apt install -y certbot python3-certbot-nginx
```

### Step 7.2: Get SSL Certificates
```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com -d api.yourdomain.com
```

Follow the prompts:
- Enter email
- Agree to terms
- Choose to redirect HTTP to HTTPS (option 2)

### Step 7.3: Auto-Renewal Test
```bash
sudo certbot renew --dry-run
```

---

## 🛡️ Part 8: Firewall Configuration

### Step 8.1: Configure UFW
```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

---

## ✅ Part 9: Verification & Testing

### Step 9.1: Test Backend API
```bash
curl -s https://api.yourdomain.com/api/health | jq
```

Expected:
```json
{
  "status": "ok"
}
```

### Step 9.2: Test Frontend
Open browser: `https://yourdomain.com`

Should display login page.

### Step 9.3: Test Login
1. Navigate to login page
2. Enter admin credentials
3. Verify dashboard loads

### Step 9.4: Test PDF Generation
1. Go to Reports
2. Generate any PDF
3. Verify file downloads

---

## 📝 Part 10: Hostinger-Specific Settings

### Step 10.1: Enable hPanel Backups
1. Go to hPanel → Cloud → Backups
2. Enable **Daily Automatic Backups**
3. Set retention to 7 days

### Step 10.2: Configure hPanel Monitoring
1. Go to hPanel → Cloud → Server Monitor
2. Enable CPU/RAM/Disk alerts
3. Set thresholds (e.g., 80% warning)

### Step 10.3: Point DNS in hPanel
1. Go to hPanel → Domains → DNS Zone
2. Add/Update records:

```
Type    Host              Value
A       @                 YOUR_SERVER_IP
A       www               YOUR_SERVER_IP
A       api               YOUR_SERVER_IP
```

---

## 🔄 Maintenance Commands

### Update Application
```bash
cd /var/www/azafco
git pull origin master

# Backend
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
cd ../nextjs-app
npm ci --production
npm run build
pm2 restart azafco-frontend
```

### View Logs
```bash
# Laravel logs
tail -f /var/www/azafco/backend/storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/error.log

# Frontend logs
pm2 logs azafco-frontend
```

### Clear Caches
```bash
cd /var/www/azafco/backend
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

---

## 🚨 Troubleshooting

### Common Issues

#### 502 Bad Gateway
```bash
# Check if PHP-FPM is running
sudo systemctl status php8.2-fpm

# Restart if needed
sudo systemctl restart php8.2-fpm
```

#### Permission Denied
```bash
sudo chown -R www-data:www-data /var/www/azafco/backend/storage
sudo chmod -R 775 /var/www/azafco/backend/storage
```

#### Frontend Not Loading
```bash
# Check PM2 status
pm2 status

# Restart if needed
pm2 restart azafco-frontend
```

#### Database Connection Failed
```bash
# Test MySQL connection
mysql -u azafco_user -p -e "SELECT 1;"

# Check MySQL status
sudo systemctl status mysql
```

---

## 📞 Quick Reference

| Command | Purpose |
|---------|---------|
| `pm2 status` | Check frontend status |
| `pm2 restart azafco-frontend` | Restart frontend |
| `sudo systemctl restart nginx` | Restart web server |
| `sudo systemctl restart php8.2-fpm` | Restart PHP |
| `php artisan config:cache` | Cache Laravel config |

---

## 🎉 Deployment Complete!

Your Azafco System is now running on Hostinger Cloud Startup!

| URL | Purpose |
|-----|---------|
| `https://yourdomain.com` | Frontend |
| `https://api.yourdomain.com` | Backend API |

---

**Deployed:** _______________  
**By:** _______________  
**Server IP:** _______________
