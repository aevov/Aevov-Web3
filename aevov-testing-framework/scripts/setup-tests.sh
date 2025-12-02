#!/bin/bash
# Setup script for Aevov Testing Framework
# This script initializes the WordPress test environment with multisite support

set -e

echo "🚀 Setting up Aevov Testing Framework..."

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL..."
until docker exec aevov_mysql mysqladmin ping -h"localhost" --silent; do
    echo "   MySQL not ready yet, waiting..."
    sleep 2
done
echo "✅ MySQL is ready!"

# Create test database
echo "📊 Creating test database..."
docker exec aevov_mysql mysql -u root -proot_password -e "
    DROP DATABASE IF EXISTS wordpress_test;
    CREATE DATABASE wordpress_test;
    GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'%';
    FLUSH PRIVILEGES;
"
echo "✅ Test database created!"

# Check if WordPress test library exists, if not install it
echo "📚 Setting up WordPress test library..."
if [ ! -d "wordpress-tests-lib" ]; then
    echo "   Installing WordPress test library..."
    svn co https://develop.svn.wordpress.org/tags/6.4/tests/phpunit/includes/ wordpress-tests-lib/includes/
    svn co https://develop.svn.wordpress.org/tags/6.4/tests/phpunit/data/ wordpress-tests-lib/data/

    # Create wp-tests-config.php
    cat > wordpress-tests-lib/wp-tests-config.php << 'EOF'
<?php
/* Path to the WordPress codebase */
define( 'ABSPATH', '/app/wordpress/' );

/* Test database settings */
define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress_password' );
define( 'DB_HOST', 'mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/* WordPress table prefix */
$table_prefix = 'wptests_';

/* Enable multisite */
define( 'WP_TESTS_MULTISITE', true );

/* WordPress debugging */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

/* WordPress security keys (test values) */
define( 'AUTH_KEY',         'test-auth-key' );
define( 'SECURE_AUTH_KEY',  'test-secure-auth-key' );
define( 'LOGGED_IN_KEY',    'test-logged-in-key' );
define( 'NONCE_KEY',        'test-nonce-key' );
define( 'AUTH_SALT',        'test-auth-salt' );
define( 'SECURE_AUTH_SALT', 'test-secure-auth-salt' );
define( 'LOGGED_IN_SALT',   'test-logged-in-salt' );
define( 'NONCE_SALT',       'test-nonce-salt' );
EOF

    echo "✅ WordPress test library installed!"
else
    echo "✅ WordPress test library already exists!"
fi

# Install PHPUnit Polyfills if not exists
echo "📦 Installing PHPUnit Polyfills..."
if [ ! -d "../AevovPatternSyncProtocol/vendor/yoast/phpunit-polyfills" ]; then
    docker exec -w /app/wordpress/wp-content/plugins/AevovPatternSyncProtocol aevov_phpunit composer require --dev yoast/phpunit-polyfills || true
fi

echo ""
echo "✅ Setup complete!"
echo ""
echo "🧪 You can now run tests with:"
echo "   ./scripts/run-tests.sh"
echo ""
echo "Or run specific test suites:"
echo "   ./scripts/run-tests.sh PhysicsEngine"
echo "   ./scripts/run-tests.sh Security"
echo "   ./scripts/run-tests.sh --testsuite AIML"
echo ""
