#!/bin/bash
# ============================================================================
# Aevov Development Environment Setup Script
# ============================================================================
# Automates the setup of the Docker development environment
#
# Usage:
#   ./docker/setup.sh [--reset] [--seed-data]
#
# Options:
#   --reset       Remove all containers and volumes before setup
#   --seed-data   Load sample data after setup
# ============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_header() {
    echo ""
    echo -e "${BLUE}============================================================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}============================================================================${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi

    print_success "Docker and Docker Compose are installed"
}

# Parse arguments
RESET=false
SEED_DATA=false

for arg in "$@"; do
    case $arg in
        --reset)
            RESET=true
            shift
            ;;
        --seed-data)
            SEED_DATA=true
            shift
            ;;
        *)
            ;;
    esac
done

# Main setup
print_header "Aevov Development Environment Setup"

# Check Docker installation
check_docker

# Reset if requested
if [ "$RESET" = true ]; then
    print_warning "Resetting environment (removing all containers and volumes)..."

    if docker-compose ps -q &> /dev/null; then
        docker-compose down -v
        print_success "Environment reset complete"
    else
        print_info "No running containers found"
    fi
fi

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    print_info "Creating .env file from docker/wordpress.env..."
    cat docker/wordpress.env docker/mysql.env > .env
    print_success ".env file created"
else
    print_info ".env file already exists"
fi

# Create required directories
print_info "Creating required directories..."
mkdir -p docker/mysql-init
mkdir -p reports
print_success "Directories created"

# Build and start containers
print_header "Building and Starting Containers"
docker-compose build --pull
docker-compose up -d

# Wait for services to be healthy
print_info "Waiting for services to be ready..."
sleep 10

# Check service health
print_info "Checking service health..."
for i in {1..30}; do
    if docker-compose ps | grep -q "healthy"; then
        print_success "Services are healthy"
        break
    fi
    if [ $i -eq 30 ]; then
        print_error "Services did not become healthy in time"
        docker-compose logs
        exit 1
    fi
    echo -n "."
    sleep 2
done
echo ""

# Setup WordPress
print_header "Setting Up WordPress"

print_info "Installing WordPress..."
docker-compose exec -T wordpress wp core install \
    --url="http://localhost:8080" \
    --title="Aevov Development Site" \
    --admin_user="admin" \
    --admin_password="admin" \
    --admin_email="admin@aevov.local" \
    --skip-email \
    --allow-root 2>/dev/null || print_warning "WordPress might already be installed"

print_success "WordPress installed"

# Activate plugins
print_header "Activating Aevov Plugins"

print_info "Activating all Aevov plugins..."
docker-compose exec -T wordpress bash -c '
    cd /var/www/html
    for plugin_dir in wp-content/plugins/aevov-*; do
        plugin=$(basename "$plugin_dir")
        wp plugin activate "$plugin" --allow-root 2>/dev/null && echo "✓ Activated: $plugin" || echo "✗ Failed: $plugin"
    done
    wp plugin activate aps-tools bloom-chunk-scanner bloom-pattern-recognition AevovPatternSyncProtocol --allow-root 2>/dev/null
' || print_warning "Some plugins might have failed to activate"

print_success "Plugin activation complete"

# Seed data if requested
if [ "$SEED_DATA" = true ]; then
    print_header "Loading Sample Data"

    print_info "Creating sample posts and pages..."
    docker-compose exec -T wordpress wp post create \
        --post_title="Welcome to Aevov" \
        --post_content="This is a sample post for testing the Aevov ecosystem." \
        --post_status=publish \
        --allow-root

    print_success "Sample data loaded"
fi

# Run initial tests
print_header "Running Initial Tests"

print_info "Running workflow tests..."
docker-compose exec -T wordpress php /var/www/html/wp-content/testing/workflow-test-runner.php --quick-test || print_warning "Some tests might have failed"

# Display access information
print_header "Setup Complete!"

echo ""
echo "Access Information:"
echo "=================="
echo ""
echo -e "  WordPress Site:    ${GREEN}http://localhost:8080${NC}"
echo -e "  WordPress Admin:   ${GREEN}http://localhost:8080/wp-admin${NC}"
echo -e "    Username:        ${YELLOW}admin${NC}"
echo -e "    Password:        ${YELLOW}admin${NC}"
echo ""
echo -e "  phpMyAdmin:        ${GREEN}http://localhost:8081${NC}"
echo -e "    Username:        ${YELLOW}root${NC}"
echo -e "    Password:        ${YELLOW}rootpassword${NC}"
echo ""
echo "Useful Commands:"
echo "================"
echo ""
echo "  View logs:             docker-compose logs -f"
echo "  Stop services:         docker-compose down"
echo "  Restart services:      docker-compose restart"
echo "  Run tests:             docker-compose exec wordpress aevov-test"
echo "  Access WordPress CLI:  docker-compose exec wordpress bash"
echo "  Run WP-CLI command:    docker-compose exec wordpress wp <command> --allow-root"
echo ""

print_success "Aevov development environment is ready!"
