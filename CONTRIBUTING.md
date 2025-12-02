# Contributing to Aevov

Thank you for your interest in contributing to the Aevov ecosystem! This document provides guidelines and instructions for contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [How to Contribute](#how-to-contribute)
- [Coding Standards](#coding-standards)
- [Testing Requirements](#testing-requirements)
- [Pull Request Process](#pull-request-process)
- [Commit Message Guidelines](#commit-message-guidelines)
- [Documentation](#documentation)
- [Community](#community)

## Code of Conduct

By participating in this project, you agree to abide by our Code of Conduct:

- **Be respectful**: Treat everyone with respect and kindness
- **Be collaborative**: Work together and help each other
- **Be inclusive**: Welcome diverse perspectives and experiences
- **Be professional**: Maintain professionalism in all interactions

## Getting Started

### Prerequisites

Before you begin, ensure you have:

- PHP 7.4 or higher
- Node.js 18 or higher
- Docker (recommended)
- Git
- A GitHub account

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:

```bash
git clone https://github.com/YOUR-USERNAME/aevov.git
cd aevov
```

3. Add the upstream repository:

```bash
git remote add upstream https://github.com/aevov/aevov.git
```

## Development Setup

### Option 1: Docker (Recommended)

```bash
# Start the development environment
./docker/setup.sh

# Access WordPress container
docker-compose exec wordpress bash

# Run WP-CLI commands
docker-compose exec wordpress wp plugin list --allow-root
```

### Option 2: Local Setup

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy plugins to WordPress installation
cp -r aevov-* /path/to/wordpress/wp-content/plugins/

# Activate plugins
wp plugin activate aevov-core
```

### Verify Installation

```bash
# Run tests to verify everything works
./bin/aevov test

# Check code quality
composer lint
npm run lint
```

## How to Contribute

### Types of Contributions

We welcome various types of contributions:

- 🐛 **Bug fixes**
- ✨ **New features**
- 📝 **Documentation improvements**
- 🎨 **UI/UX enhancements**
- ⚡ **Performance improvements**
- ✅ **Test coverage**
- 🌐 **Translations**

### Finding Issues to Work On

- Check the [issue tracker](https://github.com/aevov/aevov/issues)
- Look for issues labeled `good first issue` or `help wanted`
- Comment on the issue to indicate you're working on it
- Ask questions if anything is unclear

### Proposing New Features

Before starting work on a major feature:

1. Open an issue to discuss the feature
2. Wait for feedback from maintainers
3. Once approved, create a feature branch and start development

## Coding Standards

### PHP Standards

We follow **WordPress Coding Standards** with some modifications:

```bash
# Check PHP code style
composer phpcs

# Auto-fix PHP issues
composer phpcbf

# Run static analysis
composer phpstan
```

**Key PHP guidelines:**

- Use tabs for indentation
- Follow PSR-12 naming conventions
- Document all public functions with PHPDoc
- Maximum line length: 120 characters
- Always sanitize and escape output

**Example:**

```php
<?php
/**
 * Process user data
 *
 * @param int    $user_id User ID
 * @param string $action  Action to perform
 * @return bool True on success, false on failure
 */
function aevov_process_user( $user_id, $action ) {
    // Validate input
    $user_id = absint( $user_id );
    $action  = sanitize_key( $action );

    // Verify permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }

    // Process action
    // ...

    return true;
}
```

### JavaScript Standards

We use **ESLint** and **Prettier**:

```bash
# Check JavaScript code style
npm run lint:js

# Auto-fix JavaScript issues
npm run lint:js:fix

# Format with Prettier
npm run format
```

**Key JavaScript guidelines:**

- Use ES6+ features
- Use semicolons
- Single quotes for strings
- 2 spaces for indentation
- Maximum line length: 100 characters

**Example:**

```javascript
/**
 * Fetch user data from API
 *
 * @param {number} userId - The user ID
 * @returns {Promise<Object>} User data object
 */
async function fetchUserData(userId) {
  try {
    const response = await fetch(`/wp-json/aevov/v1/users/${userId}`);

    if (!response.ok) {
      throw new Error('Failed to fetch user data');
    }

    return await response.json();
  } catch (error) {
    console.error('Error fetching user:', error);
    throw error;
  }
}
```

### CSS/SCSS Standards

```bash
# Check CSS
npm run lint:css

# Auto-fix CSS
npm run lint:css:fix
```

**Key CSS guidelines:**

- Use BEM methodology for class names
- Mobile-first responsive design
- Prefer CSS Grid and Flexbox
- Use CSS custom properties for theming

## Testing Requirements

### Running Tests

```bash
# All tests
./bin/aevov test

# Specific test category
./bin/aevov test --category=api_integration

# PHPUnit tests
composer test

# JavaScript tests
npm test

# With coverage
composer test:coverage
npm run test:coverage
```

### Writing Tests

Every new feature or bug fix **must** include tests:

**PHP Test Example:**

```php
<?php
class Test_Aevov_Core extends WP_UnitTestCase {

    public function test_feature_works() {
        // Arrange
        $user_id = $this->factory->user->create();

        // Act
        $result = aevov_process_user( $user_id, 'activate' );

        // Assert
        $this->assertTrue( $result );
        $this->assertEquals( 'active', get_user_meta( $user_id, 'aevov_status', true ) );
    }
}
```

**JavaScript Test Example:**

```javascript
describe('fetchUserData', () => {
  it('should fetch user data successfully', async () => {
    // Arrange
    const userId = 123;
    const mockData = { id: 123, name: 'Test User' };

    global.fetch = jest.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve(mockData),
      })
    );

    // Act
    const result = await fetchUserData(userId);

    // Assert
    expect(result).toEqual(mockData);
    expect(fetch).toHaveBeenCalledWith('/wp-json/aevov/v1/users/123');
  });
});
```

### Test Coverage Requirements

- **Minimum coverage**: 80% for new code
- **Target coverage**: 90%+ overall
- All public APIs must be tested
- Critical paths must have integration tests

## Pull Request Process

### Before Submitting

1. **Update from upstream:**

```bash
git fetch upstream
git rebase upstream/main
```

2. **Run all checks:**

```bash
# Linting
composer lint
npm run lint

# Tests
./bin/aevov test
composer test
npm test

# Build
npm run build
```

3. **Update documentation** if needed

### Creating the Pull Request

1. Push your branch to your fork:

```bash
git push origin feature/your-feature-name
```

2. Go to GitHub and create a Pull Request

3. Fill out the PR template completely:

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Comments added for complex code
- [ ] Documentation updated
- [ ] No new warnings generated
- [ ] Tests added/updated
- [ ] All tests passing
```

4. Wait for review and address feedback

### PR Review Process

- At least one maintainer must approve
- All CI checks must pass
- No merge conflicts
- Documentation must be up to date
- Tests must pass with 80%+ coverage

## Commit Message Guidelines

We follow the **Conventional Commits** specification:

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `test`: Adding or updating tests
- `chore`: Build process or tooling changes
- `ci`: CI/CD changes

### Examples

```bash
# Simple commit
git commit -m "feat(core): add user activation feature"

# With body
git commit -m "fix(api): resolve authentication issue

The authentication endpoint was not properly validating
JWT tokens. This commit adds proper validation and
error handling.

Fixes #123"

# Breaking change
git commit -m "feat(api)!: redesign authentication API

BREAKING CHANGE: The /auth endpoint now requires a
different request format. See migration guide for details."
```

## Documentation

### Code Documentation

- **PHP**: Use PHPDoc for all public functions/methods
- **JavaScript**: Use JSDoc for all exported functions
- **CSS**: Use comments for complex selectors

### User Documentation

When adding features that affect users:

1. Update relevant documentation in `documentation/`
2. Add examples and screenshots
3. Update the CHANGELOG.md

### API Documentation

For new API endpoints:

1. Add PHPDoc to the endpoint handler
2. Regenerate API docs: `php api-documentation.php`
3. Add example requests/responses

## Community

### Communication Channels

- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: Questions and community discussions
- **Discord**: Real-time chat and collaboration
- **Email**: For private matters: maintainers@aevov.dev

### Getting Help

- Check existing documentation first
- Search closed issues for similar problems
- Ask in GitHub Discussions
- Join our Discord for real-time help

### Recognition

Contributors are recognized in:

- The project README
- Release notes
- Annual contributor list
- Special contributor badge (for significant contributions)

## Release Process

Maintainers follow this process for releases:

1. Update version numbers
2. Update CHANGELOG.md
3. Run full test suite
4. Create release tag
5. Build distribution packages
6. Publish to WordPress.org (if applicable)
7. Create GitHub release with notes

## Questions?

If you have questions about contributing:

- Open a discussion on GitHub
- Ask in Discord
- Email: jesse@aevov.ai

---

**Thank you for contributing to Aevov! 🎉**

Every contribution, no matter how small, helps make Aevov better for everyone.
