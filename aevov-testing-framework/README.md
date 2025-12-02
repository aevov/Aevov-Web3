# Aevov Massive Testing Framework

Comprehensive testing framework for all Aevov systems with 280+ tests covering every major component.

## Overview

This testing framework provides extensive test coverage for the entire Aevov ecosystem, including:

- **Physics Engine** - 40 tests
- **Security** - 30 tests
- **Media Engines** (Image/Music/Transcription) - 45 tests
- **AI/ML** (NeuroArchitect/Cognitive/Language) - 73 tests
- **Infrastructure** (Memory/Simulation/Embedding) - 43 tests
- **Applications** (App Forge/SuperApp/Integration) - 45 tests
- **Performance Benchmarks** - 15 tests

**Total: 291 comprehensive tests**

## Test Infrastructure

### Core Components

- **BaseAevovTestCase** - Base test class with performance tracking, cleanup, and helper methods
- **TestDataFactory** - Creates realistic test data for all Aevov systems
- **PerformanceProfiler** - Tracks and analyzes performance metrics
- **TestReportGenerator** - Generates HTML and JSON test reports

## 🚀 Running Tests with Docker (Recommended)

The framework includes a complete Docker environment with WordPress multisite support for **real, actual testing**!

### Quick Start

```bash
# One command to build, start, setup, and test everything
make quickstart

# Or step by step:
make build    # Build containers
make up       # Start containers
make setup    # Initialize test environment
make test     # Run all 291 tests
```

### Run Specific Test Suites

```bash
make test-physics         # Physics Engine (40 tests)
make test-security        # Security (30 tests)
make test-media           # Media Engines (45 tests)
make test-aiml            # AI/ML (73 tests)
make test-infrastructure  # Infrastructure (43 tests)
make test-applications    # Applications (45 tests)
make test-performance     # Performance benchmarks (15 tests)
```

### Advanced Testing

```bash
make test-verbose         # Verbose output
make test-coverage        # Generate code coverage
make logs                 # View container logs
make shell                # Open test container shell
```

📚 **See [DOCKER-TESTING-GUIDE.md](./DOCKER-TESTING-GUIDE.md) for complete Docker documentation**

## Running Tests Locally (Without Docker)

### All Tests

```bash
cd aevov-testing-framework
phpunit
```

### Specific Test Suites

```bash
phpunit --testsuite PhysicsEngine
phpunit --testsuite Security
phpunit --testsuite MediaEngines
phpunit --testsuite AIML
phpunit --testsuite Infrastructure
phpunit --testsuite Applications
phpunit --testsuite Performance
```

### Individual Test Files

```bash
phpunit tests/PhysicsEngine/PhysicsEngineTest.php
phpunit tests/Security/SecurityTest.php
phpunit tests/AIML/NeuroArchitectTest.php
```

## Test Categories

### 1. Physics Engine Tests (40 tests)

**PhysicsEngineTest.php** - 20 tests
- Newtonian solver (basic motion, gravity)
- Collision detection (rigid body, no collision)
- Soft body simulation
- Fluid dynamics (SPH)
- Force fields (gravity wells)
- Constraints (distance, spring, hinge)
- World generation (procedural terrain, biomes)
- Performance tests

**PhysicsAPITest.php** - 20 tests
- REST API endpoints for all physics operations
- Authentication and authorization
- Parameter validation
- Simulation state management
- Concurrent simulations
- Performance metrics

### 2. Security Tests (30 tests)

**SecurityTest.php** - 30 tests
- Permission checks (can_manage_aevov, can_edit_aevov, can_read_aevov)
- Input sanitization (text, HTML, SQL, URL, email)
- XSS prevention (output, attributes, JavaScript)
- SQL injection prevention (prepared statements)
- CSRF protection (nonce generation/verification)
- File upload validation
- Password strength
- Session security
- Rate limiting
- Secure headers

### 3. Media Engines Tests (45 tests)

**MediaEnginesTest.php** - 45 tests

**Image Engine (15 tests):**
- Job creation and validation
- Dimensions, prompts, seeds
- Style presets and upscaling
- CDN integration

**Music Forge (15 tests):**
- Composition parameters
- Tempo, key, scale validation
- Instrument selection
- Mood and complexity
- Export formats

**Transcription Engine (15 tests):**
- Job creation and processing
- Language and model support
- Timestamps and diarization
- Confidence scores
- Output formats

### 4. AI/ML Tests (73 tests)

**NeuroArchitectTest.php** - 25 tests
- Blueprint creation and validation
- Evolution (crossover, mutation, selection)
- Fitness calculation
- Pattern recognition
- Model composition
- Performance tracking
- API endpoints

**CognitiveEngineTest.php** - 20 tests
- Reasoning task types (deductive, inductive, abductive)
- Inference engine
- Knowledge base queries
- Decision making
- Analogical and causal reasoning
- Uncertainty handling
- Goal-directed behavior
- Planning and problem decomposition

**LanguageEngineTest.php** - 28 tests
- Text analysis and tokenization
- Sentiment analysis
- Entity extraction and NER
- Keyword extraction
- Language detection
- POS tagging
- Text summarization and generation
- Question answering
- Translation
- Spell/grammar checking

### 5. Infrastructure Tests (43 tests)

**AevovInfrastructureTest.php** - 43 tests

**Memory Core (10 tests):**
- Read/write operations
- Address validation
- Data serialization
- Capacity limits
- API endpoints

**Simulation Engine (10 tests):**
- Simulation creation and types
- Time step validation
- Fitness evaluation
- State persistence
- Worker job processing

**Embedding Engine (10 tests):**
- Embedding generation
- Similarity calculations
- Batch processing
- Normalization
- Search and clustering

**Chunk Registry & CDN (13 tests):**
- Chunk types and registration
- CDN integration
- Presigned URLs
- Data compression/encryption
- Error handling

### 6. Application & Integration Tests (45 tests)

**ApplicationsIntegrationTest.php** - 45 tests

**Application Forge (15 tests):**
- Application configuration
- Feature configuration
- Deployment and validation
- Templates and blueprints
- Monitoring and scaling

**SuperApp (5 tests):**
- Multi-app orchestration
- Multi-tenancy
- Shared state management

**Integration Tests (25 tests):**
- Cross-system workflows
- API endpoint chaining
- Event propagation
- Resource sharing
- Load balancing and failover
- Distributed systems
- Metrics aggregation

### 7. Performance Benchmarks (15 tests)

**PerformanceBenchmarks.php** - 15 tests
- Blueprint generation (<10ms avg)
- Physics calculations (<1ms avg)
- Embedding generation (<5ms avg)
- Memory operations (<5ms avg)
- Database queries (<10ms avg)
- API endpoints (<100ms)
- Text processing (<1ms avg)
- Vector operations (<0.5ms avg)
- JSON serialization (<2ms avg)
- Cache operations (<5ms avg)
- Collision detection (<50ms for 100 bodies)
- Full workflow performance
- Concurrent operations

## WordPress Multisite Support

The framework is configured to test on WordPress multisite installations:

```xml
<const name="WP_TESTS_MULTISITE" value="1"/>
```

This ensures all plugins work correctly in multisite environments.

## Test Reports

Tests automatically generate performance metrics and can produce comprehensive reports:

### HTML Reports

Located in `reports/` directory with:
- Pass/fail statistics
- Performance metrics
- Visual charts and graphs

### JSON Reports

Machine-readable reports for CI/CD integration.

## Performance Tracking

Every test automatically tracks:
- Execution time
- Memory usage
- Peak memory
- Database query count

## 🐳 Docker Environment

The framework includes a complete Docker setup with:

- **WordPress 6.4** with PHP 8.1 (multisite enabled)
- **MySQL 8.0** with persistent storage
- **Nginx** web server (port 8081)
- **PHPUnit** test runner with all dependencies

### Container Architecture

```
┌─────────────────────────────────────────┐
│  aevov_phpunit (Test Runner)            │
│  - PHPUnit 9.x                          │
│  - WP-CLI                               │
│  - All Aevov plugins                    │
│  - WordPress test library               │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│  aevov_wordpress (PHP 8.1 FPM)          │
│  - Multisite enabled                    │
│  - All Aevov plugins mounted            │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│  aevov_mysql (MySQL 8.0)                │
│  - wordpress_db (main)                  │
│  - wordpress_test (testing)             │
└─────────────────────────────────────────┘
```

### Quick Reference

```bash
make quickstart   # Complete setup + run tests
make up           # Start environment
make test         # Run all tests
make shell        # Open test container
make logs         # View logs
make clean        # Clean everything
```

📖 Full documentation: [DOCKER-TESTING-GUIDE.md](./DOCKER-TESTING-GUIDE.md)

## CI/CD Integration

### GitHub Actions

```yaml
name: Aevov Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Build and test
        run: |
          make build
          make up
          make setup
          make test
```

### GitLab CI

```yaml
test:
  stage: test
  image: docker:latest
  services:
    - docker:dind
  script:
    - make quickstart
```

## Test Coverage Goals

- **Unit Tests**: Individual functions and methods
- **Integration Tests**: Plugin interactions
- **Workflow Tests**: End-to-end scenarios
- **Performance Tests**: Benchmarking and optimization
- **Security Tests**: Vulnerability prevention

## Adding New Tests

1. Create test file in appropriate directory
2. Extend `BaseAevovTestCase`
3. Use `TestDataFactory` for test data
4. Add to PHPUnit configuration
5. Run tests to verify

Example:

```php
<?php
use AevovTesting\Infrastructure\BaseAevovTestCase;
use AevovTesting\Infrastructure\TestDataFactory;

class MyNewTest extends BaseAevovTestCase {
    public function test_my_feature() {
        $data = TestDataFactory::createBlueprint();
        $this->assertIsArray($data);
    }
}
```

## Best Practices

1. Use descriptive test names
2. Test one concept per test method
3. Use test data factories for consistency
4. Clean up test data in tearDown
5. Track performance metrics
6. Test both success and failure cases
7. Validate security at every endpoint

## Troubleshooting

### Tests Not Running

```bash
# Check PHPUnit installation
phpunit --version

# Verify bootstrap file
cat tests/bootstrap.php
```

### Database Errors

```bash
# Reset test database
mysql -u wordpress -p < reset-test-db.sql
```

### Permission Issues

```bash
# Fix file permissions
chmod -R 755 tests/
```

## Contributing

When adding new Aevov components:

1. Create corresponding test suite
2. Achieve >80% code coverage
3. Add performance benchmarks
4. Document test scenarios
5. Update this README

## License

MIT License - Part of the Aevov ecosystem
