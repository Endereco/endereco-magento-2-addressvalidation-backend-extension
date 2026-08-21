#!/usr/bin/env bash

# PHP versions covered: composer.json requires ^8.1 with no upper bound; the
# currently-supported Magento lines (see playground.sh) span PHP 8.1 (2.4.6)
# through 8.5 (2.4.9).
versions=("8.1" "8.2" "8.3" "8.4" "8.5")

# Alle PHP-Dateien des Moduls – Verzeichnisse ohne Modulcode ausgeschlossen
php_files=$(find . -type f -name "*.php" ! -path "./vendor/*" ! -path "./node_modules/*" ! -path "./shops/*" ! -path "./docker/*" ! -path "./dev/*" ! -path "./review/*")

syntax_error_found=0

for version in "${versions[@]}"; do
    if [ $syntax_error_found -eq 1 ]; then
        break
    fi
    echo "Testing with PHP ${version} ..."
    for file in $php_files; do
        if [ $syntax_error_found -eq 1 ]; then
            break
        fi
        output=$(docker run --rm -v "$(pwd)":/app -w /app php:${version}-cli php -l "$file" 2>&1)
        if [[ $output == *"Errors parsing"* ]]; then
            echo "Syntax error in ${file} with PHP ${version}: ${output}"
            syntax_error_found=1
        fi
    done
done

if [ $syntax_error_found -eq 1 ]; then
    echo "Syntax errors were found."
    exit 1
else
    echo "No syntax errors found."
fi
