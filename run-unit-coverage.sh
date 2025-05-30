#!/bin/bash

echo "🔍 Running Unit Tests Coverage..."
echo "================================"

# Run unit tests with coverage - fast and reliable
./vendor/bin/pest --coverage tests/Unit --coverage-html=coverage/unit

echo ""
echo "✅ Unit Coverage Complete!"
echo "💡 This covers all core business logic without hanging issues."
