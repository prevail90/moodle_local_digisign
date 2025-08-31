<?php
/**
 * Test script to verify plugin settings functionality
 * 
 * This script tests if the plugin settings are being saved and retrieved correctly.
 * Run this script from the plugin directory.
 */

// Include Moodle config
if (file_exists(__DIR__ . '/../../config.php')) {
    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->dirroot . '/local/digisign/lib.php');
} else {
    echo "❌ ERROR: This script must be run from within a Moodle environment.\n";
    echo "Please run this script from: /path/to/moodle/local/digisign/\n\n";
    exit(1);
}

echo "Plugin Settings Test\n";
echo "===================\n\n";

// Test 1: Check current settings
echo "1. Current Settings\n";
echo "-------------------\n";

$cfg = local_digisign_get_config();
echo "API Key: " . (empty($cfg->api_key) ? 'NOT SET' : substr($cfg->api_key, 0, 8) . '...') . "\n";
echo "API URL: " . $cfg->api_url . "\n";
echo "Timeout: " . $cfg->timeout . "\n\n";

// Test 2: Test setting values
echo "2. Testing Setting Values\n";
echo "-------------------------\n";

// Test setting a value
$test_value = 'test_value_' . time();
set_config('test_setting', $test_value, 'local_digisign');
echo "Set test setting: $test_value\n";

// Test retrieving the value
$retrieved = get_config('local_digisign', 'test_setting');
echo "Retrieved test setting: $retrieved\n";

if ($retrieved === $test_value) {
    echo "✅ Setting save/retrieve works correctly\n";
} else {
    echo "❌ Setting save/retrieve failed\n";
}

// Clean up test setting
unset_config('test_setting', 'local_digisign');
echo "Cleaned up test setting\n\n";

// Test 3: Check database records
echo "3. Database Records\n";
echo "------------------\n";

global $DB;

$config_records = $DB->get_records('config_plugins', ['plugin' => 'local_digisign']);
echo "Found " . count($config_records) . " config records for local_digisign:\n";

foreach ($config_records as $record) {
    $value = $record->name === 'api_key' ? substr($record->value, 0, 8) . '...' : $record->value;
    echo "  - {$record->name}: $value\n";
}

echo "\n";

// Test 4: Test admin settings page
echo "4. Admin Settings Page\n";
echo "----------------------\n";

// Check if settings page is properly registered
$admin_tree = $ADMIN->locate('local_digisign_settings');
if ($admin_tree) {
    echo "✅ Settings page is properly registered in admin tree\n";
    echo "Settings page name: " . $admin_tree->name . "\n";
} else {
    echo "❌ Settings page not found in admin tree\n";
}

echo "\n";

// Test 5: Test form submission simulation
echo "5. Form Submission Test\n";
echo "----------------------\n";

// Simulate setting a value through the admin interface
$test_api_key = 'test_api_key_' . time();
set_config('api_key', $test_api_key, 'local_digisign');

// Verify it was saved
$saved_key = get_config('local_digisign', 'api_key');
if ($saved_key === $test_api_key) {
    echo "✅ Form submission simulation successful\n";
} else {
    echo "❌ Form submission simulation failed\n";
}

// Restore original value if it existed
if (!empty($cfg->api_key)) {
    set_config('api_key', $cfg->api_key, 'local_digisign');
    echo "Restored original API key\n";
} else {
    unset_config('api_key', 'local_digisign');
    echo "Cleaned up test API key\n";
}

echo "\n";

echo "Test Summary:\n";
echo "=============\n";
echo "If all tests passed, the settings should work correctly.\n";
echo "If the 'Changes saved' banner isn't showing, it might be a UI issue.\n";
echo "Try:\n";
echo "1. Clearing browser cache\n";
echo "2. Purging Moodle caches: php admin/cli/purge_caches.php\n";
echo "3. Checking browser console for JavaScript errors\n\n";
