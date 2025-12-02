# Aevov Deployment Procedure

Complete step-by-step guide for deploying Aevov to production.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Setup](#server-setup)
3. [Environment Configuration](#environment-configuration)
4. [Deployment](#deployment)
5. [WordPress Setup](#wordpress-setup)
6. [SSL Configuration](#ssl-configuration)
7. [Verification](#verification)
8. [Monitoring Setup](#monitoring-setup)
9. [Backup Configuration](#backup-configuration)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 4 cores | 8 cores |
| RAM | 8 GB | 16 GB |
| Storage | 100 GB SSD | 250 GB SSD |
| OS | Ubuntu 22.04 LTS | Ubuntu 22.04 LTS |

### Required Software

- Docker 24.0+
- Docker Compose 2.20+
- Git
- OpenSSL (for generating secrets)

### Required Accounts/API Keys

| Service | Purpose | Required |
|---------|---------|----------|
| OpenAI | Language Engine AI | Yes (for AI features) |
| Stability AI | Image Generation | Optional |
| Cubbit | Decentralized Storage | Optional |
| Pinecone | Vector Database | Optional |
| Sentry | Error Tracking | Recommended |

---

## Server Setup

### Step 1: Install Docker

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Add user to docker group
sudo usermod -aG docker $USER

# Install Docker Compose
sudo apt install docker-compose-plugin -y

# Verify installation
docker --version
docker compose version
```

### Step 2: Configure Firewall

```bash
# Enable UFW
sudo ufw enable

# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow Docker internal network (optional, for debugging)
# sudo ufw allow from 172.16.0.0/12

# Verify
sudo ufw status
```

### Step 3: Clone Repository

```bash
# Navigate to deployment directory
cd /opt

# Clone the repository
sudo git clone https://github.com/Jesse-wakandaisland/Aevov1.git aevov
sudo chown -R $USER:$USER aevov
cd aevov
```

---

## Environment Configuration

### Step 4: Generate Secure Credentials

```bash
# Generate MySQL root password
echo "MYSQL_ROOT_PASSWORD: $(openssl rand -base64 32)"

# Generate MySQL user password
echo "MYSQL_PASSWORD: $(openssl rand -base64 32)"

# Generate JWT secret
echo "JWT_SECRET: $(openssl rand -base64 64)"

# Save these somewhere secure before proceeding!
```

### Step 5: Configure Environment File

```bash
# Copy the template (already created)
# If not present, copy from example:
# cp .env.production.example .env.production

# Edit the configuration
nano .env.production
```

**Required changes in `.env.production`:**

```bash
# 1. Replace database passwords
MYSQL_ROOT_PASSWORD=<paste-generated-password>
MYSQL_PASSWORD=<paste-generated-password>

# 2. Set your production URL
APP_URL=https://your-domain.com

# 3. Set JWT secret
JWT_SECRET=<paste-generated-secret>

# 4. Add OpenAI API key (for AI features)
OPENAI_API_KEY=sk-your-actual-api-key

# 5. Configure email (optional but recommended)
MAIL_HOST=smtp.your-provider.com
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@your-domain.com
```

### Step 6: Secure the Environment File

```bash
# Set restrictive permissions
chmod 600 .env.production

# Verify permissions
ls -la .env.production
# Should show: -rw------- 1 user user ... .env.production
```

---

## Deployment

### Step 7: Create Required Directories

```bash
# Create reports directory
mkdir -p reports

# Create MySQL init directory (if needed)
mkdir -p docker/mysql-init

# Set permissions
chmod 755 reports
```

### Step 8: Pull Docker Images

```bash
# Pull all required images
docker compose -f docker-compose.production.yml pull
```

### Step 9: Start Services

```bash
# Start all services
docker compose -f docker-compose.production.yml --env-file .env.production up -d

# Watch the startup logs
docker compose -f docker-compose.production.yml logs -f
# Press Ctrl+C to exit logs (services continue running)
```

### Step 10: Verify Container Health

```bash
# Check all containers are running
docker compose -f docker-compose.production.yml ps

# Expected output - all should show "healthy" or "running":
# NAME                        STATUS
# aevov-prod-mysql           healthy
# aevov-prod-redis           healthy
# aevov-prod-wordpress       healthy
# aevov-prod-workflow-engine healthy
```

---

## WordPress Setup

### Step 11: Initialize WordPress

```bash
# Run the WP-CLI setup container
docker compose -f docker-compose.production.yml --profile tools run --rm wpcli
```

This will:
- Install WordPress core
- Create admin user (change password immediately!)
- Activate all Aevov plugins in correct order

### Step 12: Access WordPress Admin

1. Open browser: `http://your-server-ip:8080/wp-admin`
2. Login with default credentials:
   - Username: `admin`
   - Password: `admin`
3. **IMMEDIATELY change the admin password:**
   - Go to Users > Your Profile
   - Set a strong password
   - Click "Update Profile"

### Step 13: Verify Plugin Activation

1. Go to Plugins > Installed Plugins
2. Verify all Aevov plugins are activated:
   - aevov-core (must be first)
   - aevov-ai-core
   - aevov-language-engine
   - aevov-workflow-engine
   - ... (all 34 plugins)

### Step 14: Configure Aevov Settings

1. Go to Aevov > Settings (or each plugin's settings page)
2. Enter API keys for enabled engines
3. Configure storage settings if using Cubbit
4. Test each engine connection

---

## SSL Configuration

### Option A: Using Let's Encrypt with Certbot

```bash
# Install Certbot
sudo apt install certbot -y

# Stop services temporarily
docker compose -f docker-compose.production.yml stop

# Generate certificate
sudo certbot certonly --standalone -d your-domain.com

# Certificates will be at:
# /etc/letsencrypt/live/your-domain.com/fullchain.pem
# /etc/letsencrypt/live/your-domain.com/privkey.pem
```

### Option B: Using Reverse Proxy (Recommended)

Create an nginx reverse proxy configuration:

```bash
# Install nginx
sudo apt install nginx -y

# Create site configuration
sudo nano /etc/nginx/sites-available/aevov
```

Add this configuration:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;

    # HSTS
    add_header Strict-Transport-Security "max-age=63072000" always;

    # WordPress
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Workflow Engine
    location /workflow/ {
        proxy_pass http://127.0.0.1:3000/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/aevov /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Step 15: Update Environment for SSL

```bash
# Edit environment
nano .env.production

# Change:
APP_URL=https://your-domain.com
SSL_MODE=on

# Restart services
docker compose -f docker-compose.production.yml --env-file .env.production up -d
```

---

## Verification

### Step 16: Run Test Suite

```bash
# Run all tests
docker compose -f docker-compose.production.yml --profile testing run --rm tests

# Expected: All 414 tests passing
```

### Step 17: Run Quality Checks

```bash
# Run code quality checks
docker compose -f docker-compose.production.yml --profile quality run --rm quality
```

### Step 18: Verify API Endpoints

```bash
# Test WordPress REST API
curl -s https://your-domain.com/wp-json/wp/v2/posts | head

# Test Aevov API (if configured)
curl -s https://your-domain.com/wp-json/aevov/v1/status

# Test Workflow Engine
curl -s https://your-domain.com:3000/api/health
```

### Step 19: Functional Testing

1. **Language Engine Test:**
   - Navigate to Aevov > Language Engine
   - Enter a test prompt
   - Verify AI response

2. **Workflow Engine Test:**
   - Access Workflow Engine UI (port 3000)
   - Create a simple workflow
   - Execute and verify

3. **Storage Test (if Cubbit configured):**
   - Upload a test file
   - Verify retrieval

---

## Monitoring Setup

### Step 20: Enable Monitoring Container

```bash
# Create monitoring directory
mkdir -p .monitoring

# Create health check script
cat > .monitoring/health-check.php << 'EOF'
<?php
$health = [
    'timestamp' => date('c'),
    'services' => []
];

// Check MySQL
$mysql = @mysqli_connect('mysql', 'wordpress', getenv('MYSQL_PASSWORD'), 'wordpress');
$health['services']['mysql'] = $mysql ? 'healthy' : 'unhealthy';
if ($mysql) mysqli_close($mysql);

// Check Redis
$redis = @fsockopen('redis', 6379, $errno, $errstr, 5);
$health['services']['redis'] = $redis ? 'healthy' : 'unhealthy';
if ($redis) fclose($redis);

// Check WordPress
$wp = @file_get_contents('http://wordpress:8080/');
$health['services']['wordpress'] = $wp !== false ? 'healthy' : 'unhealthy';

echo json_encode($health, JSON_PRETTY_PRINT);
EOF

# Start monitoring
docker compose -f docker-compose.production.yml --profile monitoring up -d monitoring
```

### Step 21: Configure Sentry (Recommended)

1. Create account at [sentry.io](https://sentry.io)
2. Create new PHP project
3. Copy DSN
4. Update `.env.production`:
   ```
   SENTRY_DSN=https://xxx@xxx.ingest.sentry.io/xxx
   ```
5. Restart services

### Step 22: Set Up Uptime Monitoring

Configure an external uptime monitor (UptimeRobot, Pingdom, etc.) to check:
- `https://your-domain.com` (main site)
- `https://your-domain.com/wp-json/wp/v2/posts` (API health)
- `https://your-domain.com:3000` (Workflow Engine)

---

## Backup Configuration

### Step 23: Database Backup Script

```bash
# Create backup script
cat > /opt/aevov/backup.sh << 'EOF'
#!/bin/bash
set -e

BACKUP_DIR="/opt/aevov/backups"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

mkdir -p $BACKUP_DIR

# Backup MySQL
docker exec aevov-prod-mysql mysqldump \
    -u root -p"${MYSQL_ROOT_PASSWORD}" \
    --all-databases \
    --single-transaction \
    > "$BACKUP_DIR/db_$DATE.sql"

# Compress
gzip "$BACKUP_DIR/db_$DATE.sql"

# Backup WordPress uploads
docker cp aevov-prod-wordpress:/var/www/html/wp-content/uploads \
    "$BACKUP_DIR/uploads_$DATE"
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" \
    -C "$BACKUP_DIR" "uploads_$DATE"
rm -rf "$BACKUP_DIR/uploads_$DATE"

# Clean old backups
find $BACKUP_DIR -name "*.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup completed: $DATE"
EOF

chmod +x /opt/aevov/backup.sh
```

### Step 24: Schedule Automated Backups

```bash
# Add to crontab
crontab -e

# Add this line for daily backups at 2 AM:
0 2 * * * /opt/aevov/backup.sh >> /var/log/aevov-backup.log 2>&1
```

### Step 25: Test Backup Restoration

```bash
# Test restore (on a test system, not production!)
gunzip -c backups/db_YYYYMMDD_HHMMSS.sql.gz | \
    docker exec -i aevov-prod-mysql mysql -u root -p"${MYSQL_ROOT_PASSWORD}"
```

---

## Troubleshooting

### Common Issues

#### Containers Won't Start

```bash
# Check logs
docker compose -f docker-compose.production.yml logs mysql
docker compose -f docker-compose.production.yml logs wordpress

# Common causes:
# - Port conflicts (change ports in .env.production)
# - Insufficient memory (increase server RAM)
# - Permission issues (check file ownership)
```

#### Database Connection Failed

```bash
# Verify MySQL is running
docker compose -f docker-compose.production.yml exec mysql mysqladmin ping -h localhost

# Check credentials
docker compose -f docker-compose.production.yml exec mysql \
    mysql -u wordpress -p"${MYSQL_PASSWORD}" -e "SELECT 1"
```

#### WordPress Shows White Screen

```bash
# Enable debug mode temporarily
# Edit .env.production:
WORDPRESS_DEBUG=true
WORDPRESS_DEBUG_DISPLAY=true

# Restart and check logs
docker compose -f docker-compose.production.yml --env-file .env.production up -d
docker compose -f docker-compose.production.yml logs wordpress
```

#### Plugin Activation Errors

```bash
# Manually activate plugins via WP-CLI
docker compose -f docker-compose.production.yml exec wordpress \
    wp plugin activate aevov-core --allow-root

# Check plugin status
docker compose -f docker-compose.production.yml exec wordpress \
    wp plugin list --allow-root
```

#### Redis Connection Issues

```bash
# Test Redis connection
docker compose -f docker-compose.production.yml exec redis redis-cli ping
# Expected: PONG

# Check Redis logs
docker compose -f docker-compose.production.yml logs redis
```

### Useful Commands Reference

```bash
# View all container status
docker compose -f docker-compose.production.yml ps

# Restart all services
docker compose -f docker-compose.production.yml restart

# Restart specific service
docker compose -f docker-compose.production.yml restart wordpress

# View logs (all)
docker compose -f docker-compose.production.yml logs -f

# View logs (specific service)
docker compose -f docker-compose.production.yml logs -f wordpress

# Execute command in container
docker compose -f docker-compose.production.yml exec wordpress bash

# Stop all services
docker compose -f docker-compose.production.yml down

# Stop and remove volumes (CAUTION: destroys data)
docker compose -f docker-compose.production.yml down -v

# Update images
docker compose -f docker-compose.production.yml pull
docker compose -f docker-compose.production.yml up -d
```

---

## Quick Reference Card

### Deployment Commands

| Action | Command |
|--------|---------|
| Start | `docker compose -f docker-compose.production.yml --env-file .env.production up -d` |
| Stop | `docker compose -f docker-compose.production.yml down` |
| Restart | `docker compose -f docker-compose.production.yml restart` |
| Logs | `docker compose -f docker-compose.production.yml logs -f` |
| Status | `docker compose -f docker-compose.production.yml ps` |
| Run Tests | `docker compose -f docker-compose.production.yml --profile testing run --rm tests` |
| WP-CLI | `docker compose -f docker-compose.production.yml --profile tools run --rm wpcli` |

### Important URLs

| Service | URL |
|---------|-----|
| WordPress | `https://your-domain.com` |
| WordPress Admin | `https://your-domain.com/wp-admin` |
| Workflow Engine | `https://your-domain.com:3000` |
| API Status | `https://your-domain.com/wp-json/wp/v2/` |
| phpMyAdmin | `http://localhost:8081` (tools profile only) |

### File Locations

| File | Purpose |
|------|---------|
| `.env.production` | Production environment configuration |
| `docker-compose.production.yml` | Docker Compose configuration |
| `DEPLOYMENT-CHECKLIST.md` | Pre-deployment checklist |
| `reports/` | Test reports and coverage |
| `backups/` | Database and file backups |

---

*Last Updated: 2025-11-30*
*Version: 1.0.0*
