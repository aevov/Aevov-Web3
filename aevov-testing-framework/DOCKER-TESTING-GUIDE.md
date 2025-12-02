# Docker Testing Guide - Aevov Testing Framework

Complete guide for running Aevov tests in Docker with WordPress multisite support.

## 🚀 Quick Start

```bash
# 1. Build and start containers
make build
make up

# 2. Setup test environment
make setup

# 3. Run all tests
make test
```

That's it! The framework is now running comprehensive tests against a real WordPress multisite installation.

## 📋 Prerequisites

- Docker (20.10+)
- Docker Compose (1.29+)
- Make (optional, for convenience commands)

## 🏗️ Architecture

The testing environment consists of 4 Docker containers:

1. **aevov_wordpress** - WordPress 6.4 with PHP 8.1 (multisite enabled)
2. **aevov_mysql** - MySQL 8.0 database server
3. **aevov_nginx** - Nginx web server (accessible at http://localhost:8081)
4. **aevov_phpunit** - PHPUnit test runner with all dependencies

All containers are connected via `aevov_network` bridge network.

## 🔧 Container Details

### PHPUnit Container

Built from `Dockerfile.phpunit`, includes:
- PHP 8.1 CLI
- PHPUnit 9.x
- WP-CLI
- Composer
- All required PHP extensions
- WordPress test library
- All Aevov plugins mounted

### WordPress Container

Configured with:
- Multisite enabled (path-based)
- All Aevov plugins available
- Debug mode enabled for testing
- FPM for performance

### MySQL Container

Configured with:
- Persistent volume storage
- Separate test database
- Exposed on port 3306
- Native password authentication

## 📦 Available Commands

### Using Make (Recommended)

```bash
# Container management
make build          # Build all containers
make up             # Start containers
make down           # Stop containers
make restart        # Restart containers
make status         # Show container status
make clean          # Remove containers and volumes

# Setup and testing
make setup          # Initialize test environment
make test           # Run all 291 tests
make quickstart     # Build + up + setup + test (all in one)

# Run specific test suites
make test-physics         # Physics Engine tests (40)
make test-security        # Security tests (30)
make test-media           # Media Engines tests (45)
make test-aiml            # AI/ML tests (73)
make test-infrastructure  # Infrastructure tests (43)
make test-applications    # Applications tests (45)
make test-performance     # Performance benchmarks (15)

# Advanced testing
make test-verbose         # Verbose output
make test-coverage        # Generate code coverage report

# Debugging
make logs                 # View all logs
make logs-phpunit         # View PHPUnit logs
make logs-mysql           # View MySQL logs
make shell                # Open shell in PHPUnit container
make shell-mysql          # Open MySQL CLI
```

### Using Scripts Directly

```bash
# Setup
chmod +x scripts/*.sh
./scripts/setup-tests.sh

# Run tests
./scripts/run-tests.sh                    # All tests
./scripts/run-tests.sh PhysicsEngine      # Specific suite
./scripts/run-tests.sh --verbose          # Verbose output
./scripts/run-tests.sh --filter test_name # Specific test
```

### Using Docker Commands

```bash
# Start environment
docker-compose up -d

# Run tests
docker exec -it aevov_phpunit phpunit

# Run specific suite
docker exec -it aevov_phpunit phpunit --testsuite PhysicsEngine

# View logs
docker-compose logs -f phpunit

# Shell access
docker exec -it aevov_phpunit bash
```

## 🧪 Test Suites

### 1. Physics Engine (40 tests)
```bash
make test-physics
```
Tests: Newtonian mechanics, collision detection, SLAM, world generation, API endpoints

### 2. Security (30 tests)
```bash
make test-security
```
Tests: Authentication, XSS/SQL injection prevention, CSRF, input sanitization

### 3. Media Engines (45 tests)
```bash
make test-media
```
Tests: Image generation, music composition, audio transcription

### 4. AI/ML (73 tests)
```bash
make test-aiml
```
Tests: NeuroArchitect evolution, cognitive reasoning, language processing

### 5. Infrastructure (43 tests)
```bash
make test-infrastructure
```
Tests: Memory core, simulation engine, embedding engine, chunk registry

### 6. Applications (45 tests)
```bash
make test-applications
```
Tests: Application forge, SuperApp, system integration workflows

### 7. Performance Benchmarks (15 tests)
```bash
make test-performance
```
Benchmarks: All system performance metrics with timing analysis

## 🌐 WordPress Multisite Configuration

The testing environment includes WordPress multisite support:

```php
// Configured in docker-compose.yml
define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);  // Path-based multisite
define('DOMAIN_CURRENT_SITE', 'localhost');
```

Tests automatically run against multisite installation to ensure compatibility.

## 📊 Test Reports

### HTML Coverage Report
```bash
make test-coverage
# Opens reports/coverage/index.html
```

### Performance Reports
Performance metrics are automatically tracked and stored in `reports/` directory:
- `test-report-YYYY-MM-DD-HHmmss.html` - Visual report
- `test-report-YYYY-MM-DD-HHmmss.json` - Machine-readable data

## 🔍 Debugging Tests

### View Real-Time Logs
```bash
# All containers
make logs

# PHPUnit only
make logs-phpunit

# Follow specific test
docker-compose logs -f phpunit | grep "PhysicsEngine"
```

### Interactive Shell
```bash
# Open shell in test container
make shell

# Run tests manually
phpunit --testsuite PhysicsEngine --verbose

# Check PHP version
php -v

# Verify WordPress installation
wp core version
```

### Database Inspection
```bash
# Open MySQL CLI
make shell-mysql

# Check test database
SHOW DATABASES;
USE wordpress_test;
SHOW TABLES;
```

### Debug Individual Tests
```bash
# Run single test with debug output
docker exec -it aevov_phpunit phpunit \
  --filter test_newtonian_solver_basic_motion \
  --debug
```

## 📝 Troubleshooting

### Containers Won't Start
```bash
# Check Docker status
docker ps -a

# View error logs
docker-compose logs

# Rebuild containers
make clean
make build
make up
```

### Database Connection Errors
```bash
# Verify MySQL is running
docker ps | grep aevov_mysql

# Check database exists
make shell-mysql
SHOW DATABASES;

# Recreate test database
make setup
```

### Tests Failing
```bash
# Verify setup completed
./scripts/setup-tests.sh

# Check PHPUnit version
docker exec aevov_phpunit phpunit --version

# Verify WordPress test library
docker exec aevov_phpunit ls -la /app/wordpress-tests-lib
```

### Permission Issues
```bash
# Fix script permissions
chmod +x scripts/*.sh

# Fix ownership
sudo chown -R $USER:$USER aevov-testing-framework/
```

## 🔄 Continuous Integration

### GitHub Actions Example
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Build containers
        run: make build

      - name: Start environment
        run: make up

      - name: Setup tests
        run: make setup

      - name: Run tests
        run: make test
```

### GitLab CI Example
```yaml
test:
  stage: test
  image: docker:latest
  services:
    - docker:dind
  script:
    - make quickstart
```

## 📈 Performance Benchmarks

Expected performance on standard hardware:

- **Physics calculations**: < 1ms per iteration
- **Blueprint generation**: < 10ms average
- **Database queries**: < 10ms per query
- **API endpoints**: < 100ms response time
- **Total test suite**: ~2-5 minutes for all 291 tests

## 🚨 Health Checks

Verify system health:

```bash
# Container status
make status

# MySQL connectivity
docker exec aevov_mysql mysqladmin ping -h localhost

# WordPress test library
docker exec aevov_phpunit test -d /app/wordpress-tests-lib && echo "OK"

# PHPUnit available
docker exec aevov_phpunit which phpunit
```

## 🎯 Best Practices

1. **Always run `make setup` after first start**
2. **Use `make quickstart` for clean test runs**
3. **Run specific suites during development** (faster iteration)
4. **Check logs when tests fail** (`make logs-phpunit`)
5. **Clean environment periodically** (`make clean`)
6. **Monitor performance benchmarks** for regressions

## 📚 Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Handbook](https://make.wordpress.org/core/handbook/testing/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Main README](./README.md) - Test suite details

## 🆘 Support

If you encounter issues:

1. Check this guide's troubleshooting section
2. Review container logs: `make logs`
3. Verify setup: `./scripts/setup-tests.sh`
4. Clean and rebuild: `make clean && make quickstart`

---

**Happy Testing! 🧪**

All 291 tests are ready to run against a real WordPress multisite installation.
