<?php
/**
 * Manual DocuSeal SDK Installation Script
 * 
 * This script helps install the DocuSeal PHP SDK manually if Composer is not available.
 * Run this script from the plugin directory.
 */

echo "DocuSeal SDK Manual Installation\n";
echo "================================\n\n";

// Check if we're in the right directory
if (!file_exists(__DIR__ . '/lib.php')) {
    echo "❌ ERROR: This script must be run from the plugin directory.\n";
    echo "Please run this script from: /path/to/moodle/local/digisign/\n\n";
    exit(1);
}

// Check if SDK is already installed
if (file_exists(__DIR__ . '/vendor/docusealco/docuseal-php/src/Api.php')) {
    echo "✅ DocuSeal SDK is already installed!\n";
    echo "Location: " . __DIR__ . '/vendor/docusealco/docuseal-php/\n\n';
    exit(0);
}

echo "1. Creating vendor directory structure...\n";
$vendor_dir = __DIR__ . '/vendor';
$sdk_dir = $vendor_dir . '/docusealco/docuseal-php';

if (!is_dir($vendor_dir)) {
    mkdir($vendor_dir, 0755, true);
    echo "   Created: $vendor_dir\n";
}

if (!is_dir($sdk_dir)) {
    mkdir($sdk_dir, 0755, true);
    echo "   Created: $sdk_dir\n";
}

echo "\n2. Downloading DocuSeal SDK files...\n";

// SDK files to download
$files = [
    'src/Api.php' => 'https://raw.githubusercontent.com/docusealco/docuseal-php/main/src/Api.php',
    'src/Client.php' => 'https://raw.githubusercontent.com/docusealco/docuseal-php/main/src/Client.php',
    'composer.json' => 'https://raw.githubusercontent.com/docusealco/docuseal-php/main/composer.json'
];

foreach ($files as $file => $url) {
    $file_path = $sdk_dir . '/' . $file;
    $dir_path = dirname($file_path);
    
    if (!is_dir($dir_path)) {
        mkdir($dir_path, 0755, true);
    }
    
    echo "   Downloading: $file\n";
    $content = file_get_contents($url);
    
    if ($content === false) {
        echo "   ❌ Failed to download: $file\n";
        continue;
    }
    
    if (file_put_contents($file_path, $content) === false) {
        echo "   ❌ Failed to save: $file\n";
        continue;
    }
    
    echo "   ✅ Downloaded: $file\n";
}

echo "\n3. Creating autoloader...\n";

// Create a simple autoloader
$autoload_content = '<?php
/**
 * Simple autoloader for DocuSeal SDK
 */

spl_autoload_register(function ($class) {
    // DocuSeal namespace
    if (strpos($class, \'Docuseal\\\') === 0) {
        $file = __DIR__ . \'/docusealco/docuseal-php/src/\' . str_replace(\'\\\\\', \'/\', $class) . \'.php\';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});
';

$autoload_file = $vendor_dir . '/autoload.php';
if (file_put_contents($autoload_file, $autoload_content) === false) {
    echo "   ❌ Failed to create autoloader\n";
} else {
    echo "   ✅ Created autoloader: $autoload_file\n";
}

echo "\n4. Testing SDK installation...\n";

// Test if the SDK works
require_once $autoload_file;

if (class_exists('\\Docuseal\\Api')) {
    echo "   ✅ SDK class loaded successfully!\n";
    echo "   ✅ Manual installation completed!\n\n";
} else {
    echo "   ❌ SDK class could not be loaded\n";
    echo "   Please check the installation and try again.\n\n";
}

echo "Installation Summary:\n";
echo "====================\n";
echo "SDK Location: $sdk_dir\n";
echo "Autoloader: $autoload_file\n";
echo "Plugin will now use the SDK when available, with cURL fallback.\n\n";

echo "Next Steps:\n";
echo "===========\n";
echo "1. Test the plugin: Visit your Moodle site and go to the Digisign plugin\n";
echo "2. If you still have issues, run: php test_connection.php\n";
echo "3. Check the plugin settings for API key and URL configuration\n\n";
