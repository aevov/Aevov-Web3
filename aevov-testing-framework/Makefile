# Aevov Testing Framework Makefile
# Quick commands for Docker and testing

.PHONY: help build up down restart setup test test-% clean logs shell

# Default target
.DEFAULT_GOAL := help

help: ## Show this help message
	@echo "Aevov Testing Framework - Available Commands:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""

build: ## Build Docker containers
	@echo "🏗️  Building containers..."
	docker-compose build

up: ## Start all containers
	@echo "🚀 Starting containers..."
	docker-compose up -d
	@echo "✅ Containers started!"
	@echo "   WordPress: http://localhost:8081"
	@echo "   MySQL: localhost:3306"

down: ## Stop all containers
	@echo "🛑 Stopping containers..."
	docker-compose down

restart: down up ## Restart all containers

setup: up ## Setup test environment (run after first up)
	@echo "⚙️  Setting up test environment..."
	@chmod +x scripts/setup-tests.sh
	@./scripts/setup-tests.sh

test: ## Run all tests
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh

test-physics: ## Run Physics Engine tests
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh PhysicsEngine

test-security: ## Run Security tests
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh Security

test-media: ## Run Media Engines tests
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh MediaEngines

test-aiml: ## Run AI/ML tests
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh AIML

test-infrastructure: ## Run Infrastructure tests
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh Infrastructure

test-applications: ## Run Applications tests
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh Applications

test-performance: ## Run Performance benchmarks
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh Performance

test-verbose: ## Run all tests with verbose output
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh --verbose

test-coverage: ## Run tests with code coverage
	@chmod +x scripts/run-tests.sh
	@./scripts/run-tests.sh --coverage-html reports/coverage

clean: ## Clean up containers and volumes
	@echo "🧹 Cleaning up..."
	docker-compose down -v
	@echo "✅ Cleaned!"

logs: ## Show logs from all containers
	docker-compose logs -f

logs-phpunit: ## Show PHPUnit container logs
	docker-compose logs -f phpunit

logs-mysql: ## Show MySQL container logs
	docker-compose logs -f mysql

shell: ## Open shell in PHPUnit container
	docker exec -it aevov_phpunit /bin/bash

shell-mysql: ## Open MySQL shell
	docker exec -it aevov_mysql mysql -u wordpress -pwordpress_password wordpress_test

status: ## Show container status
	@echo "📊 Container Status:"
	@docker-compose ps

# Quick start workflow
quickstart: build up setup test ## Build, start, setup, and run tests

# Development workflow
dev: up ## Start development environment
	@echo "🔧 Development environment ready!"
	@echo "   Run 'make test' to run tests"
	@echo "   Run 'make shell' to open container shell"
	@echo "   Run 'make logs' to view logs"
