#!/bin/bash
# Test runner script for Aevov Testing Framework
# Usage: ./scripts/run-tests.sh [test-suite-name] [additional-phpunit-args]

set -e

CONTAINER_NAME="aevov_phpunit"

# Check if container is running
if ! docker ps | grep -q $CONTAINER_NAME; then
    echo "❌ Error: PHPUnit container is not running"
    echo "   Please start it with: docker-compose up -d phpunit"
    exit 1
fi

# Default to running all tests
TEST_SUITE=""
PHPUNIT_ARGS=""

# Parse arguments
if [ $# -gt 0 ]; then
    if [[ "$1" == --* ]]; then
        # Argument starts with --, pass all to PHPUnit
        PHPUNIT_ARGS="$@"
    else
        # First argument is test suite name
        TEST_SUITE="--testsuite $1"
        shift
        PHPUNIT_ARGS="$@"
    fi
fi

echo "🧪 Running Aevov Tests..."
echo "   Container: $CONTAINER_NAME"
echo "   Test Suite: ${TEST_SUITE:-All}"
echo "   Extra Args: ${PHPUNIT_ARGS:-None}"
echo ""

# Run PHPUnit in container
docker exec -it $CONTAINER_NAME phpunit \
    $TEST_SUITE \
    $PHPUNIT_ARGS \
    --colors=always

EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo ""
    echo "✅ All tests passed!"
else
    echo ""
    echo "❌ Tests failed with exit code: $EXIT_CODE"
fi

exit $EXIT_CODE
