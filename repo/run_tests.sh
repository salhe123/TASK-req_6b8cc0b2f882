#!/bin/bash
set -euo pipefail

echo "========================================="
echo " Building test image..."
echo "========================================="
docker build -f Dockerfile.test -t precision-portal-tests .

echo ""
echo "========================================="
echo " Running all tests in Docker..."
echo "========================================="
docker run --rm precision-portal-tests

echo ""
echo "========================================="
echo " All tests passed!"
echo "========================================="
