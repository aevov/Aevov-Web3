#!/bin/bash
# ============================================================================
# Aevov ServerSideUp Docker PHP Setup Script
# ============================================================================
# This script sets up and starts the complete Aevov infrastructure using
# serversideup/docker-php images.
#
# Usage:
#   ./setup-serverside.sh          # Start infrastructure
#   ./setup-serverside.sh down     # Stop infrastructure
#   ./setup-serverside.sh logs     # View logs
#   ./setup-serverside.sh clean    # Clean everything and start fresh
# ============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
COMPOSE_FILE="docker-compose.serverside.yml"
ENV_FILE=".env.serverside"
DATA_DIR=".docker-data"

# Functions
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

# Check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker first."
        exit 1
    fi
    print_success "Docker is running"
}

# Create required directories
create_directories() {
    print_info "Creating required directories..."
    mkdir -p "$DATA_DIR/mysql"
    mkdir -p "$DATA_DIR/redis"
    chmod -R 777 "$DATA_DIR"
    print_success "Directories created"
}

# Copy environment file if it doesn't exist
setup_env() {
    if [ ! -f .env ]; then
        print_info "Creating .env file from template..."
        cp "$ENV_FILE" .env
        print_success ".env file created"
    else
        print_info ".env file already exists"
    fi
}

# Start the infrastructure
start_infrastructure() {
    print_header "Starting Aevov Infrastructure with ServerSideUp Docker PHP"

    check_docker
    create_directories
    setup_env

    print_info "Pulling latest images..."
    docker-compose -f "$COMPOSE_FILE" pull

    print_info "Starting containers..."
    docker-compose -f "$COMPOSE_FILE" up -d

    print_success "Containers started!"

    print_header "Waiting for services to be ready..."

    # Wait for MySQL
    print_info "Waiting for MySQL to be ready..."
    timeout=60
    counter=0
    until docker-compose -f "$COMPOSE_FILE" exec -T mysql mysqladmin ping -h localhost -u root -prootpassword > /dev/null 2>&1 || [ $counter -eq $timeout ]; do
        sleep 1
        counter=$((counter + 1))
        echo -n "."
    done
    echo ""

    if [ $counter -eq $timeout ]; then
        print_error "MySQL failed to start within $timeout seconds"
        exit 1
    fi
    print_success "MySQL is ready"

    # Wait for WordPress
    print_info "Waiting for WordPress to be ready..."
    timeout=120
    counter=0
    until curl -f http://localhost:8080 > /dev/null 2>&1 || [ $counter -eq $timeout ]; do
        sleep 2
        counter=$((counter + 2))
        echo -n "."
    done
    echo ""

    if [ $counter -ge $timeout ]; then
        print_error "WordPress failed to start within $timeout seconds"
        print_info "Check logs with: docker-compose -f $COMPOSE_FILE logs wordpress"
    else
        print_success "WordPress is ready"
    fi

    print_header "Installation Complete!"
    echo ""
    echo -e "${GREEN}Access your services:${NC}"
    echo -e "  WordPress:   ${BLUE}http://localhost:8080${NC}"
    echo -e "  phpMyAdmin:  ${BLUE}http://localhost:8082${NC}"
    echo ""
    echo -e "${GREEN}Useful commands:${NC}"
    echo -e "  View logs:        ${YELLOW}docker-compose -f $COMPOSE_FILE logs -f${NC}"
    echo -e "  Stop services:    ${YELLOW}./setup-serverside.sh down${NC}"
    echo -e "  Restart services: ${YELLOW}docker-compose -f $COMPOSE_FILE restart${NC}"
    echo -e "  Run WP-CLI:       ${YELLOW}docker-compose -f $COMPOSE_FILE --profile tools run --rm wpcli wp plugin list --allow-root${NC}"
    echo ""
    echo -e "${GREEN}Container Information:${NC}"
    docker-compose -f "$COMPOSE_FILE" ps
}

# Stop the infrastructure
stop_infrastructure() {
    print_header "Stopping Aevov Infrastructure"
    docker-compose -f "$COMPOSE_FILE" down
    print_success "Infrastructure stopped"
}

# View logs
view_logs() {
    print_header "Viewing Logs (Ctrl+C to exit)"
    docker-compose -f "$COMPOSE_FILE" logs -f
}

# Clean everything
clean_infrastructure() {
    print_header "Cleaning Aevov Infrastructure"
    print_error "WARNING: This will remove all containers, volumes, and data!"
    read -p "Are you sure? (yes/no): " -r
    echo
    if [[ $REPLY == "yes" ]]; then
        docker-compose -f "$COMPOSE_FILE" down -v
        rm -rf "$DATA_DIR"
        print_success "Infrastructure cleaned"
    else
        print_info "Clean cancelled"
    fi
}

# Show status
show_status() {
    print_header "Infrastructure Status"
    docker-compose -f "$COMPOSE_FILE" ps
}

# Main script logic
case "${1:-start}" in
    start)
        start_infrastructure
        ;;
    stop|down)
        stop_infrastructure
        ;;
    logs)
        view_logs
        ;;
    clean)
        clean_infrastructure
        ;;
    status)
        show_status
        ;;
    restart)
        stop_infrastructure
        start_infrastructure
        ;;
    *)
        echo "Usage: $0 {start|stop|down|logs|clean|status|restart}"
        echo ""
        echo "Commands:"
        echo "  start    - Start the infrastructure (default)"
        echo "  stop     - Stop the infrastructure"
        echo "  down     - Stop the infrastructure (alias for stop)"
        echo "  logs     - View container logs"
        echo "  clean    - Remove all containers, volumes, and data"
        echo "  status   - Show infrastructure status"
        echo "  restart  - Restart the infrastructure"
        exit 1
        ;;
esac
