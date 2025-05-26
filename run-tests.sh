#!/bin/bash

echo "🧪 Running Types Generator Package Tests..."

# Set up test environment
export APP_ENV=testing

# Run all tests
vendor/bin/pest tests/ --colors=always

# Run with coverage if requested
if [ "$1" = "--coverage" ]; then
    echo "📊 Generating coverage report..."
    vendor/bin/pest tests/ --coverage --coverage-html=coverage
fi

# Run specific test suites
if [ "$1" = "--unit" ]; then
    echo "🔬 Running Unit Tests..."
    vendor/bin/pest tests/Unit/ --colors=always
fi

if [ "$1" = "--feature" ]; then
    echo "🏗️ Running Feature Tests..."
    vendor/bin/pest tests/Feature/ --colors=always
fi

echo "✅ Test run completed!"
