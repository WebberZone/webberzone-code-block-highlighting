#!/bin/bash
# Build script for creating distribution zip
# Only includes production files and runtime dependencies

set -e

PLUGIN_SLUG="webberzone-code-block-highlighting"
BUILD_DIR="build"
TEMP_DIR="$BUILD_DIR/$PLUGIN_SLUG"

echo "Creating distribution zip for $PLUGIN_SLUG..."

# Clean build directory
rm -rf "$BUILD_DIR"
mkdir -p "$TEMP_DIR"

# Copy plugin files (excluding dev/build artifacts and all of vendor)
echo "Copying plugin files..."
rsync -av --exclude-from=- . "$TEMP_DIR/" <<EOF
.*
.git/
.github/
node_modules/
phpcompat-tools/
phpunit/
/build/
vendor/
dev-helpers/
dev-tools/
wporg-assets/
test-tools/
docs/
build-assets.js
*.zip
*.dist
*.yml
*.neon
composer.json
composer.lock
package.json
package-lock.json
phpstan-bootstrap.php
build-zip.sh
build-prism.js
build-prism.min.js
CODE_OF_CONDUCT.md
CONTRIBUTING.md
ISSUE_TEMPLATE.md
PULL_REQUEST_TEMPLATE.md
CLAUDE.md
AGENTS.md
EOF

# Copy required vendor dependencies (everything in vendor/ is excluded above,
# so production runtime deps must be copied back in explicitly). Dev-only files
# such as .github workflow folders are stripped from the copies.
echo "Copying vendor dependencies..."
mkdir -p "$TEMP_DIR/vendor"

# highlight.php (server-side syntax highlighter; loaded via its own PSR-0
# autoloader, not the Composer autoloader).
if [ -d "vendor/scrivo/highlight.php" ]; then
    mkdir -p "$TEMP_DIR/vendor/scrivo"
    rsync -a --exclude='.github' --exclude='.git*' --exclude='README.md' --exclude='CONTRIBUTING.md' --exclude='AUTHORS.txt' --exclude='.php-cs-fixer.dist.php' vendor/scrivo/highlight.php "$TEMP_DIR/vendor/scrivo/"
else
    echo "Error: vendor/scrivo/highlight.php directory not found. Run 'composer install' first." >&2
    exit 1
fi

# Create zip
echo "Creating zip file..."
cd "$BUILD_DIR"
zip -r "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG/" -q

echo "✓ Distribution zip created: $BUILD_DIR/$PLUGIN_SLUG.zip"
cd ..

# Show zip contents summary
echo ""
echo "Zip contents summary:"
unzip -l "$BUILD_DIR/$PLUGIN_SLUG.zip" | tail -1
