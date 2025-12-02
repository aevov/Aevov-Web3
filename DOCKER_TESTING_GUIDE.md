# Aevov Docker Testing Guide
**Using serversideup/docker-php for Production-Ready Testing**

---

## Table of Contents
1. [Quick Start](#quick-start)
2. [Docker Compose Files](#docker-compose-files)
3. [Running Tests](#running-tests)
4. [Plugin Testing](#plugin-testing)
5. [Performance Testing](#performance-testing)
6. [Security Testing](#security-testing)
7. [Troubleshooting](#troubleshooting)

---

## Quick Start

### Prerequisites
- Docker Engine 20.10+
- Docker Compose 1.29+
- 8GB RAM minimum
- 20GB free disk space

### Initial Setup

1. **Copy environment file:**
   ```bash
   cp .env.production.example .env.production
   ```

2. **Edit configuration:**
   ```bash
   nano .env.production
   # Update all API keys and passwords
   ```

3. **Start production environment:**
   ```bash
   docker-compose -f docker-compose.production.yml --env-file .env.production up -d
   ```

4. **Wait for services to be healthy:**
   ```bash
   docker-compose -f docker-compose.production.yml ps
   # All services should show "healthy"
   ```

5. **Access WordPress:**
   - URL: http://localhost:8080
   - Admin: http://localhost:8080/wp-admin
   - Username: `admin`
   - Password: `admin`

---

## Docker Compose Files

### `docker-compose.yml` (Development)
- Original development setup
- Uses custom Dockerfile
- Mounted volumes for live code editing
- Debug mode enabled

**Use for:** Plugin development, debugging

```bash
docker-compose up -d
```

### `docker-compose.production.yml` (Production/Testing)
- **Production-ready** with serversideup/docker-php
- Optimized for performance
- Security hardened
- Comprehensive testing capabilities

**Use for:** Production testing, CI/CD, performance testing

```bash
docker-compose -f docker-compose.production.yml up -d
```

---

## Running Tests

### 1. Automated Test Suite

Run the complete test suite (PHPUnit + Workflow tests):

```bash
# Run all tests
docker-compose -f docker-compose.production.yml run tests

# View test reports
ls -lah reports/
open reports/coverage/index.html     # Code coverage
open reports/workflow.html           # Workflow test results
```

### 2. Individual Plugin Testing

Test specific plugins:

```bash
# Enter WordPress container
docker-compose -f docker-compose.production.yml exec wordpress bash

# Run tests for specific plugin
cd wp-content/plugins/aevov-super-app-forge
php tests/test-app-ingestion.php

# Check plugin status
wp plugin list --allow-root
```

### 3. Workflow Testing

Run comprehensive workflow tests:

```bash
# Run workflow test runner
docker-compose -f docker-compose.production.yml exec wordpress \
  php /var/www/html/wp-content/testing/workflow-test-runner.php

# Run with specific categories
docker-compose -f docker-compose.production.yml exec wordpress \
  php /var/www/html/wp-content/testing/workflow-test-runner.php \
  --category=security

# Run with HTML report
docker-compose -f docker-compose.production.yml exec wordpress \
  php /var/www/html/wp-content/testing/workflow-test-runner.php \
  --report-format=html --output=/var/www/html/wp-content/testing/reports/workflow.html
```

### 4. Code Quality Checks

Run PHPCS and PHPStan:

```bash
# Run code quality checks
docker-compose -f docker-compose.production.yml run quality

# View reports
cat reports/phpcs.txt
cat reports/phpstan.txt
```

---

## Plugin Testing

### Testing Production-Ready Plugins (9 plugins)

These plugins can be tested immediately:

```bash
# Activate production-ready plugins
docker-compose -f docker-compose.production.yml exec wordpress bash -c "
  wp plugin activate bloom-pattern-recognition --allow-root
  wp plugin activate AevovPatternSyncProtocol --allow-root
  wp plugin activate aps-tools --allow-root
  wp plugin activate aevov-stream --allow-root
  wp plugin activate aevov-unified-dashboard --allow-root
  wp plugin activate aevov-demo-system --allow-root
  wp plugin activate aevov-onboarding-system --allow-root
  wp plugin activate aevov-diagnostic-network --allow-root
  wp plugin activate aevov-chunk-registry --allow-root
"

# Verify activation
docker-compose -f docker-compose.production.yml exec wordpress \
  wp plugin list --status=active --allow-root
```

### Testing Plugins with Issues

#### Aevov Super App Forge (22 stub methods)

```bash
# Check stub methods
docker-compose -f docker-compose.production.yml exec wordpress bash

cd /var/www/html/wp-content/plugins/aevov-super-app-forge
grep -n "return \[\]" includes/class-app-ingestion-engine.php
grep -n "return 0" includes/class-app-ingestion-engine.php
grep -n "return 50" includes/class-app-ingestion-engine.php

# Test ingestion (will fail due to stubs)
php tests/test-app-ingestion.php
```

#### Aevov Language Engine (missing OpenAI adapter)

```bash
# Check for missing adapter
docker-compose -f docker-compose.production.yml exec wordpress bash

ls -la /var/www/html/wp-content/plugins/aevov-language-engine/includes/
# Should show: class-openai-adapter.php is MISSING

# Verify plugin loads
wp plugin activate aevov-language-engine --allow-root 2>&1 | grep -i error
```

#### AROS (safety system stubs)

```bash
# WARNING: DO NOT TEST WITH PHYSICAL ROBOTS

# Check safety system stubs
docker-compose -f docker-compose.production.yml exec wordpress bash

cd /var/www/html/wp-content/plugins/AROS/aros-safety
cat class-emergency-stop.php      # Only 7 lines - STUB
cat class-collision-detector.php  # STUB
cat class-health-monitor.php      # STUB

# Count lines in safety files
wc -l aros-safety/*.php
```

### Testing AI API Integration Plugins

These require API keys in `.env.production`:

```bash
# Language Engine
docker-compose -f docker-compose.production.yml exec wordpress bash
wp eval 'echo getenv("OPENAI_API_KEY");' --allow-root

# Image Engine
docker-compose -f docker-compose.production.yml exec wordpress bash
wp eval 'echo getenv("STABILITY_API_KEY");' --allow-root

# Test API connectivity
php /var/www/html/wp-content/plugins/aevov-language-engine/tests/test-api-connection.php
```

---

## Performance Testing

### Load Testing WordPress with All Plugins

```bash
# Install Apache Bench in container
docker-compose -f docker-compose.production.yml exec wordpress bash
apt-get update && apt-get install -y apache2-utils

# Basic load test
ab -n 1000 -c 10 http://localhost:8080/

# Test specific endpoints
ab -n 100 -c 5 http://localhost:8080/wp-json/aps/v1/patterns
ab -n 100 -c 5 http://localhost:8080/wp-json/bloom/v1/analyze
```

### Database Performance

```bash
# Check slow queries
docker-compose -f docker-compose.production.yml exec mysql bash
mysql -u root -p${MYSQL_ROOT_PASSWORD} wordpress -e "
  SELECT * FROM mysql.slow_log ORDER BY query_time DESC LIMIT 10;
"

# Check table sizes
docker-compose -f docker-compose.production.yml exec mysql bash
mysql -u root -p${MYSQL_ROOT_PASSWORD} wordpress -e "
  SELECT
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
  FROM information_schema.TABLES
  WHERE table_schema = 'wordpress'
  ORDER BY (data_length + index_length) DESC;
"
```

### Redis Cache Performance

```bash
# Check Redis stats
docker-compose -f docker-compose.production.yml exec redis redis-cli INFO stats

# Monitor cache hit rate
docker-compose -f docker-compose.production.yml exec redis redis-cli INFO stats | grep -E "hits|misses"

# Monitor Redis memory
docker-compose -f docker-compose.production.yml exec redis redis-cli INFO memory | grep used_memory_human
```

### OPcache Performance

```bash
# Check OPcache status
docker-compose -f docker-compose.production.yml exec wordpress php -r "print_r(opcache_get_status());"

# Check OPcache configuration
docker-compose -f docker-compose.production.yml exec wordpress php -i | grep opcache
```

---

## Security Testing

### 1. Check for Stub Code (Production Blockers)

```bash
# Scan all plugins for stub methods
docker-compose -f docker-compose.production.yml exec wordpress bash

cd /var/www/html/wp-content/plugins

# Find all "return []" stubs
grep -r "return \[\];" */includes/*.php | wc -l

# Find all TODO/FIXME comments
grep -r "TODO\|FIXME" */includes/*.php

# Find hardcoded credentials
grep -ri "password\|api_key\|secret" */includes/*.php | grep -v "getenv\|get_option"
```

### 2. SQL Injection Testing

```bash
# Check for unsafe wpdb usage
docker-compose -f docker-compose.production.yml exec wordpress bash

cd /var/www/html/wp-content/plugins

# Find queries without wpdb->prepare()
grep -r "\$wpdb->query" */includes/*.php | grep -v "prepare"
grep -r "\$wpdb->get_results" */includes/*.php | grep -v "prepare"

# Should return ZERO results for production-ready plugins
```

### 3. XSS Vulnerability Testing

```bash
# Check for unescaped output
docker-compose -f docker-compose.production.yml exec wordpress bash

cd /var/www/html/wp-content/plugins

# Find potential XSS vulnerabilities
grep -r "echo \$" */includes/*.php | grep -v "esc_"
grep -r "print \$" */includes/*.php | grep -v "esc_"

# Check admin pages for escaping
grep -r "echo" */admin/*.php | grep -v "esc_\|wp_kses"
```

### 4. Nonce Verification Testing

```bash
# Check AJAX handlers for nonce verification
docker-compose -f docker-compose.production.yml exec wordpress bash

cd /var/www/html/wp-content/plugins

# Find AJAX handlers
grep -r "wp_ajax_" */includes/*.php

# Verify they have nonce checks
grep -A 10 "wp_ajax_" */includes/*.php | grep "wp_verify_nonce"

# Count nonce verifications (should be 90+)
grep -r "wp_verify_nonce" */includes/*.php | wc -l
```

### 5. Capability Check Testing

```bash
# Check for capability checks in admin functions
docker-compose -f docker-compose.production.yml exec wordpress bash

cd /var/www/html/wp-content/plugins

# Find admin menu functions
grep -r "add_menu_page\|add_submenu_page" */includes/*.php

# Verify capability checks exist
grep -r "current_user_can\|check_admin_referer" */includes/*.php | wc -l
```

### 6. File Upload Security

```bash
# Check file upload handlers
docker-compose -f docker-compose.production.yml exec wordpress bash

cd /var/www/html/wp-content/plugins

# Find file upload code
grep -r "move_uploaded_file\|wp_handle_upload" */includes/*.php

# Check for file type validation
grep -A 5 "wp_handle_upload" */includes/*.php | grep "allowed_types\|mime"
```

---

## Troubleshooting

### Services Won't Start

```bash
# Check service status
docker-compose -f docker-compose.production.yml ps

# View logs
docker-compose -f docker-compose.production.yml logs mysql
docker-compose -f docker-compose.production.yml logs redis
docker-compose -f docker-compose.production.yml logs wordpress

# Check health status
docker-compose -f docker-compose.production.yml exec mysql mysqladmin ping
docker-compose -f docker-compose.production.yml exec redis redis-cli PING
```

### WordPress Installation Fails

```bash
# Reset WordPress
docker-compose -f docker-compose.production.yml down -v
docker volume rm aevov_wordpress_data
docker-compose -f docker-compose.production.yml up -d

# Manual installation
docker-compose -f docker-compose.production.yml exec wordpress bash
wp core install \
  --url="http://localhost:8080" \
  --title="Aevov Testing" \
  --admin_user="admin" \
  --admin_password="admin" \
  --admin_email="admin@aevov.local" \
  --allow-root
```

### Plugin Activation Fails

```bash
# Check plugin syntax errors
docker-compose -f docker-compose.production.yml exec wordpress bash
php -l /var/www/html/wp-content/plugins/plugin-name/plugin-name.php

# Check WordPress error log
docker-compose -f docker-compose.production.yml exec wordpress tail -f /var/log/php_errors.log

# Try activating with debug info
wp plugin activate plugin-name --allow-root 2>&1
```

### Database Connection Issues

```bash
# Verify MySQL is running
docker-compose -f docker-compose.production.yml exec mysql bash
mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "SHOW DATABASES;"

# Test connection from WordPress
docker-compose -f docker-compose.production.yml exec wordpress bash
mysql -h mysql -u wordpress -pwordpress wordpress -e "SELECT 1;"
```

### Redis Connection Issues

```bash
# Test Redis connection
docker-compose -f docker-compose.production.yml exec redis redis-cli PING

# Test from WordPress
docker-compose -f docker-compose.production.yml exec wordpress bash
apt-get update && apt-get install -y redis-tools
redis-cli -h redis PING
```

### Permission Issues

```bash
# Fix WordPress permissions
docker-compose -f docker-compose.production.yml exec wordpress bash
chown -R www-data:www-data /var/www/html/wp-content
chmod -R 755 /var/www/html/wp-content
```

### Out of Memory

```bash
# Increase PHP memory limit
docker-compose -f docker-compose.production.yml exec wordpress bash
echo "memory_limit = 1G" > /usr/local/etc/php/conf.d/memory.ini
```

---

## Testing Checklist

Use this checklist to verify comprehensive testing:

### Plugin Activation Testing
- [ ] Activate BLOOM Pattern Recognition first
- [ ] Activate AevovPatternSyncProtocol second
- [ ] Activate APS Tools third
- [ ] Activate all 9 production-ready plugins
- [ ] Check for PHP errors in logs
- [ ] Verify database tables created

### Functionality Testing
- [ ] Test pattern creation and storage
- [ ] Test data synchronization
- [ ] Test API endpoints (REST API)
- [ ] Test admin interfaces
- [ ] Test cross-plugin communication
- [ ] Test caching (Redis)
- [ ] Test background jobs (cron)

### Security Testing
- [ ] SQL injection tests (wpdb->prepare usage)
- [ ] XSS tests (output escaping)
- [ ] CSRF tests (nonce verification)
- [ ] Capability checks on admin functions
- [ ] File upload validation
- [ ] API key encryption verification
- [ ] Rate limiting on API endpoints

### Performance Testing
- [ ] Load test homepage (1000 requests)
- [ ] Load test API endpoints
- [ ] Check database slow queries
- [ ] Verify Redis cache hit rate > 80%
- [ ] Check OPcache effectiveness
- [ ] Monitor memory usage under load
- [ ] Test with all 34 plugins activated

### Code Quality Testing
- [ ] Run PHPCS (WordPress Coding Standards)
- [ ] Run PHPStan (Level 6)
- [ ] Check for stub code
- [ ] Check for TODO/FIXME comments
- [ ] Verify all tests pass (414+ tests)
- [ ] Check code coverage > 90%

### Production Readiness Testing
- [ ] No stub methods in production plugins
- [ ] All API integrations functional
- [ ] Error handling comprehensive
- [ ] Logging configured
- [ ] Monitoring active
- [ ] Backup configured
- [ ] Documentation complete

---

## Additional Resources

- **Production Readiness Report:** `PRODUCTION_READINESS_REPORT.md`
- **Main Documentation:** `README.md`
- **Developer Docs:** `DEVELOPER_DOCS.md`
- **User Guide:** `USER_GUIDE.md`
- **Docker PHP Docs:** https://serversideup.net/open-source/docker-php/

---

## Support

If you encounter issues:

1. Check logs: `docker-compose -f docker-compose.production.yml logs`
2. Review this troubleshooting guide
3. Check production readiness report for known issues
4. Consult plugin-specific documentation

---

**Last Updated:** 2025-11-21
