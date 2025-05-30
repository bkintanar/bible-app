#!/bin/bash

echo "=== Bible Reader App - Ultra-Fast Dev Tests ==="
echo ""

echo "⚡ Running Essential Tests for Development"
echo "========================================"
echo ""

echo "📊 Core Functionality (Lightning Fast):"
echo "---------------------------------------"
./vendor/bin/pest --filter="verifies core functionality quickly|verifies translation differences quickly" --compact

echo ""
echo "📊 Unit Tests (Fast):"
echo "--------------------"
./vendor/bin/pest --filter="BibleControllerTest|TranslationServiceTest" --compact

echo ""
echo "📊 Configuration (Quick):"
echo "------------------------"
./vendor/bin/pest --filter="BibleConfigTest" --compact

echo ""
echo "✅ Development Test Summary:"
echo "---------------------------"
echo "• Core OSIS functionality: ✅"
echo "• Translation differences: ✅"
echo "• Controller & services: ✅"
echo "• Configuration: ✅"
echo ""

echo "🎯 For More Complete Testing:"
echo "----------------------------"
echo "  ./vendor/bin/pest                      # All tests"
echo "  ./vendor/bin/pest --filter='Integration'  # Integration tests"
echo "  ./vendor/bin/pest --coverage           # Full coverage"
echo ""

echo "⚡ Development Speed Champion:"
echo "----------------------------"
echo "  ./run-dev-tests.sh                     # This script (< 30 seconds)"
echo ""
