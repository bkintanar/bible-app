#!/bin/bash

echo "Running Bible App Test Suite with Pest"
echo "======================================"

# Run configuration tests
echo "1. Testing Bible Configuration..."
./vendor/bin/pest --filter="BibleConfigTest" --compact

# Run service tests
echo "2. Testing Translation Service..."
./vendor/bin/pest --filter="TranslationServiceTest" --compact

# Run parser tests
echo "3. Testing OSIS Parsers..."
./vendor/bin/pest tests/Unit/Parsers/ --compact

# Run core reader tests (limited to avoid timeout)
echo "4. Testing OsisReader (basic functionality)..."
./vendor/bin/pest --filter="getBooks|getBibleInfo" --compact

# Run feature tests (limited)
echo "5. Testing Web Routes (basic)..."
./vendor/bin/pest --filter="Home page" --compact

echo "======================================"
echo "Test suite completed!"
