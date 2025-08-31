<?php
/**
 * Composer-based DocuSeal SDK Installation Script
 * 
 * This script helps install the DocuSeal PHP SDK using Composer
 * within the plugin directory.
 * Run this script from the plugin directory.
 */

echo "Composer-based DocuSeal SDK Installation\n";
echo "=========================================\n\n";

// Check if we're in the right directory
if (!file_exists(__DIR__ . '/composer.json')) {
    echo "❌ ERROR: composer.json not found in current directory.\n";
    echo "Please run this script from the plugin directory.\n\n";
    exit(1);
}

// Check if Composer is available
echo "1. Checking Composer availability...\n";
$composer_output = shell_exec('composer --version 2>&1');
if (strpos($composer_output, 'Composer version') === false) {
    echo "   ❌ Composer is not installed or not in PATH.\n";
    echo "   Please install Composer first:\n";
    echo "   curl -sS https://getcomposer.org/installer | php\n";
    echo "   sudo mv composer.phar /usr/local/bin/composer\n\n";
    exit(1);
}
echo "   ✅ Composer is available: " . trim($composer_output) . "\n";

// Check if vendor directory already exists
if (file_exists(__DIR__ . '/vendor/docusealco/docuseal-php/src/Api.php')) {
    echo "\n2. Checking existing installation...\n";
    echo "   ✅ DocuSeal SDK is already installed!\n";
    echo "   Location: " . __DIR__ . '/vendor/docusealco/docuseal-php/\n\n';
    exit(0);
}

echo "\n2. Installing DocuSeal SDK via Composer...\n";
echo "   Running: composer install\n";

// Run composer install
$output = shell_exec('composer install 2>&1');
echo $output;

// Check if installation was successful
if (file_exists(__DIR__ . '/vendor/docusealco/docuseal-php/src/Api.php')) {
    echo "\n3. Verifying installation...\n";
    echo "   ✅ DocuSeal SDK installed successfully!\n";
    echo "   Location: " . __DIR__ . '/vendor/docusealco/docuseal-php/\n';
    
    // Test if the SDK works
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        if (class_exists('\\Docuseal\\Api')) {
            echo "   ✅ SDK class loaded successfully!\n";
        } else {
            echo "   ⚠️  SDK class could not be loaded\n";
        }
    }
    
    echo "\nInstallation Summary:\n";
    echo "====================\n";
    echo "SDK Location: " . __DIR__ . '/vendor/docusealco/docuseal-php/\n';
    echo "Autoloader: " . __DIR__ . '/vendor/autoload.php\n';
    echo "Plugin will now use the SDK when available, with cURL fallback.\n\n";
    
} else {
    echo "\n❌ ERROR: SDK installation failed!\n";
    echo "Please check the Composer output above for errors.\n\n";
    exit(1);
}

echo "Next Steps:\n";
echo "===========\n";
echo "1. Test the plugin: Visit your Moodle site and go to the Digisign plugin\n";
echo "2. If you still have issues, run: php test_connection.php\n";
echo "3. Check the plugin settings for API key and URL configuration\n\n";

echo "To update the SDK in the future, run:\n";
echo "composer update docusealco/docuseal-php\n\n";
