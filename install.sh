#!/bin/bash

echo "🚀 Setting up Codemystify Types Generator..."

# Install the package
echo "📦 Installing package dependencies..."
composer install

# Install the types generator package
echo "🔧 Installing types generator..."
composer require codemystify/types-generator

# Publish the configuration
echo "📝 Publishing configuration..."
php artisan vendor:publish --tag=types-generator-config

# Create output directory based on config (default: resources/js/types/generated)
echo "📁 Creating output directory..."
OUTPUT_PATH=$(php artisan tinker --execute="echo config('types-generator.output.path');" 2>/dev/null | tail -1)
if [ -z "$OUTPUT_PATH" ] || [ "$OUTPUT_PATH" = "" ]; then
    OUTPUT_PATH="resources/js/types/generated"
fi
mkdir -p "$OUTPUT_PATH"

# Generate initial types
echo "⚙️ Generating initial types..."
php artisan generate:types

echo "✅ Setup complete!"
echo ""
echo "📖 Usage:"
echo "  php artisan generate:types          # Generate all types"
echo "  php artisan generate:types --group=events  # Generate specific group"
echo "  php artisan generate:types --watch  # Watch for changes"
echo ""
echo "🎯 Next steps:"
echo "1. Add #[GenerateTypes] attributes to your Resources/Controllers"
echo "2. Run 'php artisan generate:types' to generate TypeScript types"
echo "3. Import types in your frontend from the configured output path"
echo "4. Check config/types-generator.php to customize paths and settings"
