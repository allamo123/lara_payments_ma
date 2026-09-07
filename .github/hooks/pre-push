#!/bin/sh

echo ""
echo "======================================"
echo " Running tests before push..."
echo "======================================"
echo "🚀 PRE-PUSH HOOK IS RUNNING"

composer test

TEST_RESULT=$?

echo ""

if [ $TEST_RESULT -ne 0 ]; then
    echo "======================================"
    echo " ❌ Tests failed!"
    echo " Push aborted."
    echo "======================================"
    exit 1
fi

echo "======================================"
echo " ✅ All tests passed!"
echo " Push allowed."
echo "======================================"
echo ""

exit 0