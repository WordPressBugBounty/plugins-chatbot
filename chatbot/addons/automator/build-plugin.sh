#!/bin/bash

# Exit on any error
set -e

# Configuration
PLUGIN_NAME="wpbot-automator"
BUILD_DIR="dist"
ZIP_FILE="${PLUGIN_NAME}.zip"

echo "🚀 Starting build process for ${PLUGIN_NAME}..."

# 1. Clean up previous builds
echo "🧹 Cleaning up old build files..."
rm -rf $BUILD_DIR
rm -f $ZIP_FILE

# 2. Build assets
echo "📦 Building assets..."
npm run build

# 3. Create temporary structure
echo "📁 Creating production structure..."
mkdir -p $BUILD_DIR/$PLUGIN_NAME

# 4. Copy necessary files
echo "📂 Copying files..."
cp -r includes $BUILD_DIR/$PLUGIN_NAME/
cp -r build $BUILD_DIR/$PLUGIN_NAME/
cp -r assets $BUILD_DIR/$PLUGIN_NAME/
cp wpbot-automator.php $BUILD_DIR/$PLUGIN_NAME/
cp readme.txt $BUILD_DIR/$PLUGIN_NAME/

# 5. Create Zip
echo "🤐 Generating zip file..."
cd $BUILD_DIR
zip -r ../$ZIP_FILE . -x "**/.DS_Store" "**/node_modules/*" "**/.git/*"
cd ..

# 6. Final Cleanup
echo "🧹 Cleaning up temporary files..."
rm -rf $BUILD_DIR

if [ -f "$ZIP_FILE" ]; then
    echo "✅ Success! Zip file created at: $(pwd)/$ZIP_FILE"
    ls -lh "$ZIP_FILE"
else
    echo "❌ Error: Zip file was not created."
    exit 1
fi
