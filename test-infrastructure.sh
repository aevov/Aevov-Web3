#!/bin/bash
# ============================================================================
# Aevov Infrastructure Testing Script
# ============================================================================
# This script tests the complete Aevov infrastructure to ensure all services
# are running correctly and can communicate with each other.
# ============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

COMPOSE_FILE="docker-compose.serverside.yml"

print_header() {
    echo -e "${BLUE}============================================================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}============================================================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

print_test() {
    echo -e "${NC}Testing: $1...${NC}"
}

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

run_test() {
    local test_name="$1"
    local test_command="$2"

    print_test "$test_name"

    if eval "$test_command" > /dev/null 2>&1; then
        print_success "$test_name"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        print_error "$test_name"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

print_header "Aevov Infrastructure Testing Suite"

# Test 1: Docker is running
run_test "Docker daemon is running" "docker info"

# Test 2: Containers are running
print_test "Checking container status"
echo ""
docker-compose -f "$COMPOSE_FILE" ps
echo ""

run_test "MySQL container is running" "docker-compose -f $COMPOSE_FILE ps | grep mysql | grep -q Up"
run_test "Redis container is running" "docker-compose -f $COMPOSE_FILE ps | grep redis | grep -q Up"
run_test "WordPress container is running" "docker-compose -f $COMPOSE_FILE ps | grep wordpress | grep -q Up"
run_test "phpMyAdmin container is running" "docker-compose -f $COMPOSE_FILE ps | grep phpmyadmin | grep -q Up"

# Test 3: MySQL connectivity
run_test "MySQL is accessible" "docker-compose -f $COMPOSE_FILE exec -T mysql mysqladmin ping -h localhost -u root -prootpassword"

# Test 4: Redis connectivity
run_test "Redis is accessible" "docker-compose -f $COMPOSE_FILE exec -T redis redis-cli ping | grep -q PONG"

# Test 5: WordPress HTTP accessibility
run_test "WordPress HTTP endpoint responds" "curl -f http://localhost:8080"

# Test 6: phpMyAdmin HTTP accessibility
run_test "phpMyAdmin HTTP endpoint responds" "curl -f http://localhost:8082"

# Test 7: PHP version check
print_test "Checking PHP version"
PHP_VERSION=$(docker-compose -f "$COMPOSE_FILE" exec -T wordpress php -v | head -n 1)
echo "  $PHP_VERSION"
print_success "PHP version check"
TESTS_PASSED=$((TESTS_PASSED + 1))

# Test 8: PHP extensions
print_test "Checking required PHP extensions"
REQUIRED_EXTENSIONS=(
    "mysqli"
    "pdo_mysql"
    "redis"
    "opcache"
    "zip"
    "gd"
    "intl"
    "mbstring"
    "xml"
    "json"
    "curl"
)

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if docker-compose -f "$COMPOSE_FILE" exec -T wordpress php -m | grep -q "^$ext$"; then
        echo -e "  ${GREEN}✓${NC} $ext"
    else
        echo -e "  ${RED}✗${NC} $ext (missing)"
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
done
TESTS_PASSED=$((TESTS_PASSED + 1))

# Test 9: WordPress database connection
print_test "WordPress database connection"
if docker-compose -f "$COMPOSE_FILE" exec -T mysql mysql -u wordpress -pwordpress wordpress -e "SELECT 1" > /dev/null 2>&1; then
    print_success "WordPress can connect to MySQL"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    print_error "WordPress cannot connect to MySQL"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# Test 10: Check mounted plugin directories
print_test "Checking mounted plugin directories"
PLUGIN_COUNT=$(docker-compose -f "$COMPOSE_FILE" exec -T wordpress ls -1 /var/www/html/wp-content/plugins/ 2>/dev/null | wc -l || echo "0")
echo "  Found $PLUGIN_COUNT plugin directories mounted"
if [ "$PLUGIN_COUNT" -gt 20 ]; then
    print_success "Plugin directories are mounted (found $PLUGIN_COUNT)"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    print_error "Plugin directories may not be properly mounted (found $PLUGIN_COUNT, expected 29+)"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# Test 11: Network connectivity between containers
run_test "WordPress can reach MySQL" "docker-compose -f $COMPOSE_FILE exec -T wordpress ping -c 1 mysql"
run_test "WordPress can reach Redis" "docker-compose -f $COMPOSE_FILE exec -T wordpress ping -c 1 redis"

# Test 12: Disk space
print_test "Checking disk space usage"
df -h | grep -E "Filesystem|/var/lib/docker"
print_success "Disk space check"
TESTS_PASSED=$((TESTS_PASSED + 1))

# Test 13: Memory usage
print_test "Checking container memory usage"
docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}" \
    $(docker-compose -f "$COMPOSE_FILE" ps -q)
print_success "Memory usage check"
TESTS_PASSED=$((TESTS_PASSED + 1))

# Summary
print_header "Test Summary"
echo ""
echo -e "${GREEN}Tests Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Tests Failed: $TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    print_success "All tests passed! Infrastructure is healthy."
    echo ""
    echo -e "${GREEN}Next steps:${NC}"
    echo -e "  1. Access WordPress: ${BLUE}http://localhost:8080${NC}"
    echo -e "  2. Complete WordPress setup"
    echo -e "  3. Activate Aevov plugins"
    echo -e "  4. Run workflow tests: ${YELLOW}php testing/workflow-test-runner.php${NC}"
    exit 0
else
    print_error "Some tests failed. Please review the output above."
    echo ""
    echo -e "${YELLOW}Debugging commands:${NC}"
    echo -e "  View all logs:          ${BLUE}docker-compose -f $COMPOSE_FILE logs${NC}"
    echo -e "  View WordPress logs:    ${BLUE}docker-compose -f $COMPOSE_FILE logs wordpress${NC}"
    echo -e "  View MySQL logs:        ${BLUE}docker-compose -f $COMPOSE_FILE logs mysql${NC}"
    echo -e "  Execute into WordPress: ${BLUE}docker-compose -f $COMPOSE_FILE exec wordpress bash${NC}"
    exit 1
fi
