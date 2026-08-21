#!/usr/bin/env bash

OUTPUT=$(find . -type f -name "*.php" ! -path "./vendor/*" ! -path "./node_modules/*" ! -path "./shops/*" ! -path "./review/*" ! -path "./Test/*" \
    -exec vendor/bin/phpmd {} text phpmd.xml \;)

if [ -n "$OUTPUT" ]; then
    echo "PHPMD reported issues:"
    echo "$OUTPUT"
    exit 1
else
    echo "No issues found."
fi
