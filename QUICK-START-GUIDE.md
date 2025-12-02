# Aevov Ecosystem - Quick Start Guide

Everything you need to find quickly in one place.

## 📁 Directory Structure (Reorganized for Easy Access)

### Root Level Directories

```
├── testing/              # All testing infrastructure (414 workflow tests)
├── documentation/        # All project documentation  
├── reports/             # Test results and visualizations
├── tools/               # Utility scripts and tools
```

### Core Plugins (The Main Three)

```
├── AevovPatternSyncProtocol/    # Main pattern sync system
├── bloom-pattern-recognition/   # Bloom AI pattern recognition
├── aps-tools/                   # APS utilities and tools
```

### Sister Plugins (26 Total)

```
├── aevov-application-forge/     # Application generation
├── aevov-chat-ui/               # Chat interface
├── aevov-chunk-registry/        # Chunk management
├── aevov-cognitive-engine/      # Cognitive processing
├── aevov-cubbit-cdn/            # Cubbit CDN integration
├── aevov-cubbit-downloader/     # Cubbit downloads
├── aevov-demo-system/           # Demo system
├── aevov-diagnostic-network/    # Diagnostics
├── aevov-embedding-engine/      # Embedding generation
├── aevov-image-engine/          # Image processing
├── aevov-language-engine/       # Language processing
├── aevov-language-engine-v2/    # Language v2
├── aevov-memory-core/           # Memory management
├── aevov-music-forge/           # Music generation
├── aevov-neuro-architect/       # Neural architecture
├── aevov-onboarding-system/     # User onboarding
├── aevov-physics-engine/        # Physics simulation
├── aevov-playground/            # Testing playground
├── aevov-reasoning-engine/      # Reasoning engine
├── aevov-security/              # Security features
├── aevov-simulation-engine/     # Simulation engine
├── aevov-stream/                # Streaming features
├── aevov-super-app-forge/       # Super app generation
├── aevov-transcription-engine/  # Transcription
├── aevov-vision-depth/          # Vision & behavioral intelligence
├── bloom-chunk-scanner/         # Chunk scanning
```

## 🚀 Quick Actions

### Run All Tests (414 Tests)
```bash
cd testing
php workflow-test-runner.php
```

### Run Sister Plugin Tests
```bash
cd testing  
php test-sister-plugins.php
```

### View Interactive Ecosystem Map
```bash
# Open in browser
open reports/AEVOV-ECOSYSTEM-MAP.html
```

### Start Docker Test Environment
```bash
cd testing
docker-compose up
```

## 📊 Latest Test Results

**Last Execution:** 2025-11-19 07:45:04

**Workflow Tests:** 414/414 passed (100% pass rate) ✅
**Bugs Found:** 0
**Test Categories:** 16 comprehensive categories
**Total Plugins:** 29 (3 core + 26 sister)
**Status:** Production Ready

📊 **[View Full Test Execution Summary](testing/WORKFLOW-TEST-EXECUTION-SUMMARY.md)**

## 📖 Documentation Quick Links

| Document | Location | Description |
|----------|----------|-------------|
| User Guide | `documentation/USER_GUIDE.md` | Getting started |
| Developer Docs | `documentation/DEVELOPER_DOCS.md` | Development guide |
| Roadmap | `documentation/ROADMAP.md` | Project roadmap |
| White Paper | `documentation/white-paper.md` | Technical overview |
| Bug Tracking | `documentation/BUG-FIX-TODO.md` | Current bugs & fixes |

## 🔬 Test Categories (16 Categories, 414 Tests)

1. **Plugin Activation** (52 tests) - Plugin combination testing
2. **Pattern Creation** (29 tests) - Pattern workflows
3. **Data Synchronization** (29 tests) - APS-Bloom sync
4. **API Integration** (29 tests) - REST API testing
5. **Database Operations** (4 tests) - DB integrity
6. **User Workflows** (4 tests) - User experience
7. **Cross-Plugin Communication** (51 tests) - Inter-plugin
8. **Performance & Load** (4 tests) - Performance
9. **Error Handling & Recovery** (85 tests) - Error scenarios
10. **Security & Vulnerability** (35 tests) - Security testing
11. **Data Integrity** (35 tests) - Data validation
12. **Concurrency & Race Conditions** (8 tests) - Concurrency
13. **Resource Management** (13 tests) - Resource cleanup
14. **Edge Cases & Boundaries** (15 tests) - Edge cases
15. **Complex Integration** (13 tests) - Multi-plugin scenarios
16. **Stress Testing** (12 tests) - Breaking points

## 🎯 Common Tasks

### Add a New Plugin
1. Place plugin in root directory
2. Follow naming: `aevov-plugin-name/`
3. Main file: `aevov-plugin-name.php`
4. Run tests: `php testing/test-sister-plugins.php`

### Run Specific Test Category
```php
// Edit testing/workflow-test-runner.php
// Comment out unwanted categories in run_all_workflow_tests()
```

### View Test Results
```bash
cat testing/workflow-test-results.json | jq .
```

### Update Documentation
```bash
cd documentation
# Edit relevant .md file
```

## 🏗️ Architecture Overview

```
Main Three Plugins
    ├── AevovPatternSyncProtocol (APS)
    │   └── Pattern synchronization & Byzantine consensus
    ├── bloom-pattern-recognition (Bloom)
    │   └── AI pattern recognition & tensor processing  
    └── aps-tools
        └── Utilities for APS ecosystem

Sister Plugins (26)
    └── Integrate with Main Three
        ├── Use APS for pattern sync
        ├── Use Bloom for AI processing
        └── Use APS Tools for utilities
```

## 📝 Notes

- All tests pass at 100% (414/414 workflow tests)
- Zero critical bugs in latest test run
- Docker environment available for isolated testing
- Interactive visualization shows all plugin relationships
- Comprehensive error handling and recovery testing included

## 🔗 External Resources

- WordPress Testing Library: `testing/wordpress-tests-lib/`
- Docker Compose: `testing/docker-compose.yml`
- PHPUnit Config: `testing/phpunit.xml`

---

**Last Updated:** Test run with 414 comprehensive workflow tests  
**Status:** All systems operational, 100% pass rate
