# ServerSideUp Docker PHP Infrastructure Setup

## Overview

This document describes the complete Docker infrastructure setup for the Aevov ecosystem using **serversideup/docker-php** images. The configuration provides a production-ready, secure PHP environment optimized for WordPress development and testing.

## System Requirements

### Hardware
- **CPU**: 2+ cores recommended
- **RAM**: 4GB minimum, 8GB recommended
- **Disk**: 10GB+ free space

### Software
- **Docker**: 20.10+ or Docker Desktop
- **Docker Compose**: 1.29+ or Docker Compose V2
- **OS**: Linux, macOS, or Windows with WSL2

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Aevov Infrastructure                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  WordPress   │  │    MySQL     │  │    Redis     │     │
│  │   PHP 8.3    │  │     8.0      │  │      7       │     │
│  │ FPM + NGINX  │  └──────────────┘  └──────────────┘     │
│  └──────────────┘                                           │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐                        │
│  │ phpMyAdmin   │  │   WP-CLI     │                        │
│  └──────────────┘  └──────────────┘                        │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## Features

### ServerSideUp PHP Image Benefits

1. **Security-First Design**
   - Runs as unprivileged user (not root)
   - Minimal attack surface
   - Regular security updates

2. **Production-Ready Defaults**
   - OPcache pre-configured
   - Optimized PHP settings
   - NGINX tuned for performance

3. **Pre-installed Tools**
   - Composer 2.x
   - Common PHP extensions
   - Health check endpoints

4. **Developer-Friendly**
   - Environment variable configuration
   - Hot-reload support
   - Comprehensive logging

### Included Services

| Service | Image | Port | Purpose |
|---------|-------|------|---------|
| WordPress | serversideup/php:8.3-fpm-nginx | 8080 | Main application |
| MySQL | mysql:8.0 | 3307 | Database |
| Redis | redis:7-alpine | 6380 | Object cache |
| phpMyAdmin | phpmyadmin:latest | 8082 | Database management |
| WP-CLI | wordpress:cli-php8.3 | - | WordPress CLI |

## Files Structure

```
Aevov1/
├── docker-compose.serverside.yml  # Main Docker Compose configuration
├── .env.serverside                # Environment template
├── .env                           # Active environment (created from template)
├── setup-serverside.sh            # Setup and management script
├── test-infrastructure.sh         # Testing and validation script
├── .docker-data/                  # Persistent data (created on first run)
│   ├── mysql/                     # MySQL data
│   └── redis/                     # Redis data
└── [plugin directories]           # Mounted into WordPress
```

## Quick Start

### 1. Prerequisites Check

Ensure Docker is running:

```bash
docker --version
docker-compose --version
```

### 2. Start Infrastructure

The easiest way to start:

```bash
chmod +x setup-serverside.sh
./setup-serverside.sh start
```

Or manually:

```bash
# Copy environment file
cp .env.serverside .env

# Create data directories
mkdir -p .docker-data/mysql .docker-data/redis
chmod -R 777 .docker-data

# Start services
docker-compose -f docker-compose.serverside.yml up -d

# Watch logs
docker-compose -f docker-compose.serverside.yml logs -f
```

### 3. Wait for Services

Services typically take 60-120 seconds to be fully ready:

```bash
# Check status
docker-compose -f docker-compose.serverside.yml ps

# Monitor WordPress startup
docker-compose -f docker-compose.serverside.yml logs -f wordpress
```

### 4. Access Services

Once ready:

- **WordPress**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8082
  - Username: `root`
  - Password: `rootpassword`

### 5. Setup WordPress

First-time WordPress installation:

```bash
# Using WP-CLI container
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp core install \
    --url="http://localhost:8080" \
    --title="Aevov Development" \
    --admin_user="admin" \
    --admin_password="admin" \
    --admin_email="admin@aevov.local" \
    --skip-email \
    --allow-root
```

Or visit http://localhost:8080 and follow the web installer.

## Testing Infrastructure

### Automated Testing

Run the comprehensive test suite:

```bash
chmod +x test-infrastructure.sh
./test-infrastructure.sh
```

This tests:
- Docker daemon connectivity
- Container health status
- Service connectivity (MySQL, Redis)
- HTTP endpoints
- PHP version and extensions
- Plugin mounting
- Network connectivity
- Resource usage

### Manual Testing

#### Test 1: MySQL Connection

```bash
docker-compose -f docker-compose.serverside.yml exec mysql \
  mysql -u wordpress -pwordpress wordpress -e "SELECT 'Connected!' AS status"
```

#### Test 2: Redis Connection

```bash
docker-compose -f docker-compose.serverside.yml exec redis redis-cli ping
```

#### Test 3: PHP Info

```bash
docker-compose -f docker-compose.serverside.yml exec wordpress php -v
docker-compose -f docker-compose.serverside.yml exec wordpress php -m
```

#### Test 4: List Plugins

```bash
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp plugin list --allow-root
```

#### Test 5: WordPress Health

```bash
curl -I http://localhost:8080
```

## Configuration

### Environment Variables

Edit `.env` to customize:

```bash
# Database
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress
MYSQL_PASSWORD=wordpress

# Ports
WORDPRESS_PORT=8080
MYSQL_PORT=3307
REDIS_PORT=6380
PHPMYADMIN_PORT=8082

# PHP Settings (managed by ServerSideUp image)
PHP_MEMORY_LIMIT=512M
PHP_MAX_EXECUTION_TIME=300
PHP_UPLOAD_MAX_FILE_SIZE=64M
```

### PHP Configuration

ServerSideUp images use environment variables for PHP configuration:

```yaml
# In docker-compose.serverside.yml
PHP_OPCACHE_ENABLE: "1"
PHP_OPCACHE_MEMORY_CONSUMPTION: "256"
PHP_MEMORY_LIMIT: "512M"
PHP_MAX_EXECUTION_TIME: "300"
PHP_DISPLAY_ERRORS: "On"  # Development only
```

### WordPress Configuration

Database and cache settings:

```yaml
WORDPRESS_DB_HOST: mysql:3306
WORDPRESS_DB_NAME: wordpress
WP_REDIS_HOST: redis
WP_REDIS_PORT: 6379
```

## Management Commands

### Start/Stop Services

```bash
# Start
./setup-serverside.sh start
# or
docker-compose -f docker-compose.serverside.yml up -d

# Stop
./setup-serverside.sh stop
# or
docker-compose -f docker-compose.serverside.yml down

# Restart
./setup-serverside.sh restart
```

### View Logs

```bash
# All services
docker-compose -f docker-compose.serverside.yml logs -f

# Specific service
docker-compose -f docker-compose.serverside.yml logs -f wordpress
docker-compose -f docker-compose.serverside.yml logs -f mysql

# Last 100 lines
docker-compose -f docker-compose.serverside.yml logs --tail=100 wordpress
```

### Execute Commands

```bash
# Access WordPress container
docker-compose -f docker-compose.serverside.yml exec wordpress bash

# Access MySQL
docker-compose -f docker-compose.serverside.yml exec mysql mysql -u root -p

# Run WP-CLI
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp [command] --allow-root
```

### Monitor Resources

```bash
# Container stats
docker stats $(docker-compose -f docker-compose.serverside.yml ps -q)

# Disk usage
docker system df
```

## Activating Aevov Plugins

### Via WP-CLI

```bash
# List all plugins
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp plugin list --allow-root

# Activate all Aevov plugins
for plugin in aevov-core aevov-application-forge aevov-chat-ui \
  aevov-chunk-registry aevov-cognitive-engine aevov-cubbit-cdn \
  aevov-cubbit-downloader aevov-demo-system aevov-diagnostic-network \
  aevov-embedding-engine aevov-image-engine aevov-language-engine \
  aevov-language-engine-v2 aevov-memory-core aevov-music-forge \
  aevov-neuro-architect aevov-onboarding-system aevov-physics-engine \
  aevov-playground aevov-reasoning-engine aevov-security \
  aevov-simulation-engine aevov-stream aevov-super-app-forge \
  aevov-testing-framework aevov-transcription-engine \
  aevov-unified-dashboard aevov-vision-depth \
  aps-tools bloom-chunk-scanner bloom-pattern-recognition \
  AevovPatternSyncProtocol; do
  docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
    wp plugin activate $plugin --allow-root 2>/dev/null && echo "✓ $plugin" || echo "✗ $plugin"
done
```

### Via WordPress Admin

1. Visit http://localhost:8080/wp-admin
2. Go to Plugins
3. Activate desired plugins

## Running Workflow Tests

```bash
# Inside WordPress container
docker-compose -f docker-compose.serverside.yml exec wordpress \
  php /var/www/html/wp-content/testing/workflow-test-runner.php

# With specific category
docker-compose -f docker-compose.serverside.yml exec wordpress \
  php /var/www/html/wp-content/testing/workflow-test-runner.php --category=pattern_creation

# With specific plugin
docker-compose -f docker-compose.serverside.yml exec wordpress \
  php /var/www/html/wp-content/testing/workflow-test-runner.php --plugin=aevov-core
```

## Troubleshooting

### Services Won't Start

```bash
# Check Docker status
docker info

# Check logs
docker-compose -f docker-compose.serverside.yml logs

# Check disk space
df -h

# Recreate containers
docker-compose -f docker-compose.serverside.yml down
docker-compose -f docker-compose.serverside.yml up -d --force-recreate
```

### WordPress Shows Database Error

```bash
# Check MySQL is running
docker-compose -f docker-compose.serverside.yml ps mysql

# Check MySQL logs
docker-compose -f docker-compose.serverside.yml logs mysql

# Test connection
docker-compose -f docker-compose.serverside.yml exec wordpress \
  php -r "echo mysqli_connect('mysql', 'wordpress', 'wordpress', 'wordpress') ? 'Connected' : mysqli_connect_error();"
```

### Plugins Not Showing

```bash
# Check mounts
docker-compose -f docker-compose.serverside.yml exec wordpress \
  ls -la /var/www/html/wp-content/plugins/

# Check permissions
docker-compose -f docker-compose.serverside.yml exec wordpress \
  ls -la /var/www/html/wp-content/

# Restart WordPress
docker-compose -f docker-compose.serverside.yml restart wordpress
```

### Port Already in Use

```bash
# Check what's using port 8080
lsof -i :8080
netstat -tuln | grep 8080

# Change port in .env
WORDPRESS_PORT=8081

# Restart
docker-compose -f docker-compose.serverside.yml down
docker-compose -f docker-compose.serverside.yml up -d
```

### Performance Issues

```bash
# Check resource usage
docker stats

# Increase MySQL memory (in docker-compose.serverside.yml)
--innodb_buffer_pool_size=1G

# Increase PHP memory (in .env)
PHP_MEMORY_LIMIT=1024M

# Restart services
docker-compose -f docker-compose.serverside.yml restart
```

## Cleaning Up

### Remove Containers (Keep Data)

```bash
docker-compose -f docker-compose.serverside.yml down
```

### Remove Everything (Including Data)

```bash
# Using script
./setup-serverside.sh clean

# Or manually
docker-compose -f docker-compose.serverside.yml down -v
rm -rf .docker-data
```

### Clean Docker System

```bash
# Remove unused images
docker image prune -a

# Remove unused volumes
docker volume prune

# Full cleanup
docker system prune -a --volumes
```

## Security Considerations

### Production Recommendations

1. **Change Default Passwords**
   ```bash
   # In .env
   MYSQL_ROOT_PASSWORD=your-secure-password
   MYSQL_PASSWORD=your-secure-password
   ```

2. **Disable Debug Mode**
   ```yaml
   # In docker-compose.serverside.yml
   WORDPRESS_DEBUG: "false"
   PHP_DISPLAY_ERRORS: "Off"
   ```

3. **Use HTTPS**
   - Add SSL termination proxy (Traefik, nginx)
   - Update WordPress URLs

4. **Restrict Access**
   - Don't expose MySQL/Redis ports publicly
   - Use firewall rules
   - Enable WordPress security plugins

5. **Regular Updates**
   ```bash
   docker-compose -f docker-compose.serverside.yml pull
   docker-compose -f docker-compose.serverside.yml up -d
   ```

## Advantages of ServerSideUp Images

### vs. Official WordPress Image

| Feature | ServerSideUp | Official WordPress |
|---------|--------------|-------------------|
| Runs as non-root | ✅ | ❌ |
| Pre-configured OPcache | ✅ | ❌ |
| Built-in health checks | ✅ | ❌ |
| Environment-based config | ✅ | Partial |
| Modern PHP versions | ✅ | Limited |
| Security updates | ✅ Fast | Slower |

### Key Benefits

1. **Security**: Unprivileged user reduces attack surface
2. **Performance**: OPcache and NGINX pre-optimized
3. **Simplicity**: Environment variables for all config
4. **Reliability**: Built-in health checks and monitoring
5. **Modern**: Latest PHP versions and best practices

## Performance Tuning

### OPcache Settings

```yaml
PHP_OPCACHE_ENABLE: "1"
PHP_OPCACHE_MEMORY_CONSUMPTION: "256"  # Increase for larger sites
PHP_OPCACHE_MAX_ACCELERATED_FILES: "20000"
PHP_OPCACHE_REVALIDATE_FREQ: "0"  # 0 for dev, 60+ for production
```

### MySQL Tuning

```yaml
--innodb_buffer_pool_size=1G  # 70-80% of available RAM
--innodb_log_file_size=256M
--max_connections=500
```

### Redis Configuration

```yaml
--maxmemory 512mb  # Adjust based on needs
--maxmemory-policy allkeys-lru
```

## Backup and Restore

### Backup

```bash
# Database
docker-compose -f docker-compose.serverside.yml exec mysql \
  mysqldump -u root -prootpassword wordpress > backup.sql

# Files
tar -czf plugins-backup.tar.gz aevov-* aps-tools bloom-* AevovPatternSyncProtocol

# Redis
docker-compose -f docker-compose.serverside.yml exec redis \
  redis-cli SAVE
```

### Restore

```bash
# Database
docker-compose -f docker-compose.serverside.yml exec -T mysql \
  mysql -u root -prootpassword wordpress < backup.sql

# Files
tar -xzf plugins-backup.tar.gz
```

## Support

For issues or questions:

- **GitHub Issues**: [Report bugs and request features](https://github.com/your-org/aevov/issues)
- **ServerSideUp Docs**: https://serversideup.net/open-source/docker-php/docs
- **Docker Docs**: https://docs.docker.com

## License

This configuration is part of the Aevov ecosystem and follows the same license (AGPL-3.0).
