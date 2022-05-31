#!/bin/bash

# Set the working directory to the repository root.
PROJECT_ROOT_DIR="$(git rev-parse --show-toplevel)"

# Set the working directory to the package root.
PACKAGE_DIR="$PROJECT_ROOT_DIR/package"

# Set the working directory to dist root.
DIST_DIR="$PACKAGE_DIR/dist"

# Set the working directory to the update tester.
TESTER_DIR="$DIST_DIR/cloudinary-update-tester"

# Get started.
echo -e "\033[1;32m>> Start new build package.\033[0m"

# Ensure we start from scratch.
rm -rf "$DIST_DIR"
mkdir -p "$TESTER_DIR"

# Prepare the update tester plugin.
cp "$PACKAGE_DIR/package.php" "$TESTER_DIR/cloudinary-update-tester.php"

# Change to project root directory.
cd "$PROJECT_ROOT_DIR" || exit

# Build the release.
npm install
npm run package

# All good.
echo -e "☁️  \033[1;32mNew build package files is complete.\033[0m ☀️"
