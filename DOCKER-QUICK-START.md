# Quick Start: ServerSideUp Docker PHP Testing

## TL;DR - Get Running in 3 Commands

```bash
./setup-serverside.sh start      # Start infrastructure
./test-infrastructure.sh         # Verify everything works
```

Then visit:
- WordPress: http://localhost:8080
- phpMyAdmin: http://localhost:8082

## What You Get

✅ **WordPress** with PHP 8.3 FPM + NGINX (production-ready)
✅ **MySQL 8.0** with optimized settings
✅ **Redis 7** for object caching
✅ **phpMyAdmin** for database management
✅ **WP-CLI** for command-line WordPress management
✅ **All 29 Aevov plugins** mounted and ready to activate

## System State

Based on the infrastructure assessment performed on 2025-11-22:

### Current Environment
- ✅ Docker 28.2.2 installed
- ✅ Docker Compose 1.29.2 installed
- ✅ Ubuntu 24.04.3 LTS
- ✅ 12GB RAM available
- ✅ 30GB disk space available

### Configuration Files Created

1. **docker-compose.serverside.yml** - Complete Docker infrastructure
2. **.env.serverside** - Environment template (copied to .env)
3. **setup-serverside.sh** - Automated setup and management
4. **test-infrastructure.sh** - Comprehensive testing suite
5. **DOCKER-PHP-SETUP.md** - Complete documentation

## Why ServerSideUp Docker PHP?

### vs. Standard WordPress Image

| Benefit | Impact |
|---------|--------|
| 🔒 Runs as unprivileged user | Better security |
| ⚡ Pre-configured OPcache | ~3x faster PHP |
| 🎯 Environment-based config | Easier management |
| 🏥 Built-in health checks | Better monitoring |
| 🔧 Modern PHP 8.3 | Latest features |

## First-Time Setup

### 1. Start Services

```bash
chmod +x setup-serverside.sh
./setup-serverside.sh start
```

Wait 60-120 seconds for services to be ready.

### 2. Setup WordPress

Visit http://localhost:8080 and complete the web installer, or use WP-CLI:

```bash
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp core install \
    --url="http://localhost:8080" \
    --title="Aevov Testing" \
    --admin_user="admin" \
    --admin_password="admin" \
    --admin_email="admin@aevov.local" \
    --skip-email \
    --allow-root
```

### 3. Activate Plugins

```bash
# List all available plugins
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp plugin list --allow-root

# Activate core plugins
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp plugin activate aevov-core aps-tools bloom-pattern-recognition \
  AevovPatternSyncProtocol --allow-root
```

### 4. Run Tests

```bash
# Test infrastructure
./test-infrastructure.sh

# Run Aevov workflow tests
docker-compose -f docker-compose.serverside.yml exec wordpress \
  php /var/www/html/wp-content/testing/workflow-test-runner.php
```

## Daily Usage

```bash
# Start everything
./setup-serverside.sh start

# View logs
docker-compose -f docker-compose.serverside.yml logs -f wordpress

# Stop everything
./setup-serverside.sh stop

# Check status
docker-compose -f docker-compose.serverside.yml ps
```

## Common Tasks

### Access Containers

```bash
# WordPress/PHP container
docker-compose -f docker-compose.serverside.yml exec wordpress bash

# MySQL
docker-compose -f docker-compose.serverside.yml exec mysql bash
```

### Run WP-CLI Commands

```bash
# List users
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp user list --allow-root

# Update WordPress
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp core update --allow-root

# Search and replace URLs
docker-compose -f docker-compose.serverside.yml --profile tools run --rm wpcli \
  wp search-replace 'http://localhost:8080' 'http://mynewurl.com' --allow-root
```

### Database Operations

```bash
# Access MySQL CLI
docker-compose -f docker-compose.serverside.yml exec mysql \
  mysql -u wordpress -pwordpress wordpress

# Import database
docker-compose -f docker-compose.serverside.yml exec -T mysql \
  mysql -u wordpress -pwordpress wordpress < backup.sql

# Export database
docker-compose -f docker-compose.serverside.yml exec mysql \
  mysqldump -u wordpress -pwordpress wordpress > backup.sql
```

## Troubleshooting

### Port Already in Use?

Edit `.env` and change ports:
```bash
WORDPRESS_PORT=8081
MYSQL_PORT=3308
PHPMYADMIN_PORT=8083
```

### Services Won't Start?

```bash
# Check logs
docker-compose -f docker-compose.serverside.yml logs

# Force recreate
docker-compose -f docker-compose.serverside.yml down
docker-compose -f docker-compose.serverside.yml up -d --force-recreate
```

### Plugins Not Showing?

```bash
# Check if mounted
docker-compose -f docker-compose.serverside.yml exec wordpress \
  ls -la /var/www/html/wp-content/plugins/

# Restart WordPress
docker-compose -f docker-compose.serverside.yml restart wordpress
```

## Testing Environments

### Note on Current Environment

The initial setup was performed in a nested container environment which has limitations for running Docker-in-Docker. For full functionality, **deploy this configuration on**:

- ✅ Physical machine with Docker installed
- ✅ VM (VirtualBox, VMware, etc.)
- ✅ Cloud instance (EC2, DigitalOcean, etc.)
- ✅ Docker Desktop (Mac/Windows)
- ❌ Container environments (has kernel limitations)

## Performance Monitoring

```bash
# Container resource usage
docker stats $(docker-compose -f docker-compose.serverside.yml ps -q)

# Disk usage
docker system df

# PHP info
docker-compose -f docker-compose.serverside.yml exec wordpress php -i

# MySQL status
docker-compose -f docker-compose.serverside.yml exec mysql \
  mysql -u root -prootpassword -e "SHOW STATUS"
```

## Cleanup

```bash
# Stop but keep data
docker-compose -f docker-compose.serverside.yml down

# Remove everything including data
./setup-serverside.sh clean
```

## Next Steps

1. ✅ Configuration created and documented
2. ⏭️ Test on proper VM/machine
3. ⏭️ Activate Aevov plugins
4. ⏭️ Run workflow tests
5. ⏭️ Configure API keys for AI services
6. ⏭️ Test all plugin functionality

## Full Documentation

See **DOCKER-PHP-SETUP.md** for:
- Complete architecture details
- Advanced configuration options
- Security hardening guide
- Performance tuning
- Backup/restore procedures
- Production deployment guide

## Support

- **Documentation**: [DOCKER-PHP-SETUP.md](./DOCKER-PHP-SETUP.md)
- **ServerSideUp Docs**: https://serversideup.net/open-source/docker-php
- **GitHub Issues**: Report any problems or suggestions
