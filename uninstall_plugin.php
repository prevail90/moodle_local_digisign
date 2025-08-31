<?php
/**
 * Manual Plugin Uninstall Script
 * 
 * This script manually removes the local_digisign plugin from Moodle
 * when the normal uninstall process isn't working.
 * 
 * WARNING: This is a destructive operation. Make sure you want to remove the plugin.
 * Run this script from the plugin directory.
 */

// Include Moodle config
if (file_exists(__DIR__ . '/../../config.php')) {
    require_once(__DIR__ . '/../../config.php');
} else {
    echo "❌ ERROR: This script must be run from within a Moodle environment.\n";
    echo "Please run this script from: /path/to/moodle/local/digisign/\n\n";
    exit(1);
}

echo "Manual Plugin Uninstall Script\n";
echo "==============================\n\n";

echo "⚠️  WARNING: This will completely remove the local_digisign plugin!\n";
echo "This includes:\n";
echo "- All plugin settings\n";
echo "- All submission records\n";
echo "- Plugin database tables\n";
echo "- Plugin files (including vendor directory)\n\n";

echo "Are you sure you want to continue? (yes/no): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) !== 'yes') {
    echo "Uninstall cancelled.\n";
    exit(0);
}

echo "\nStarting manual uninstall...\n\n";

global $DB;

try {
    // 1. Remove plugin settings
    echo "1. Removing plugin settings...\n";
    $DB->delete_records('config_plugins', ['plugin' => 'local_digisign']);
    echo "   ✅ Plugin settings removed\n";

    // 2. Remove submission records
    echo "2. Removing submission records...\n";
    if ($DB->get_manager()->table_exists(new xmldb_table('local_digisign_sub'))) {
        $DB->delete_records('local_digisign_sub', []);
        echo "   ✅ Submission records removed\n";
    } else {
        echo "   ℹ️  Submission table doesn't exist\n";
    }

    // 3. Drop plugin tables
    echo "3. Dropping plugin tables...\n";
    $manager = $DB->get_manager();
    if ($manager->table_exists(new xmldb_table('local_digisign_sub'))) {
        $manager->drop_table(new xmldb_table('local_digisign_sub'));
        echo "   ✅ local_digisign_sub table dropped\n";
    } else {
        echo "   ℹ️  local_digisign_sub table doesn't exist\n";
    }

    // 4. Clear plugin cache
    echo "4. Clearing plugin cache...\n";
    core_plugin_manager::reset_caches();
    echo "   ✅ Plugin cache cleared\n";

    // 5. Remove plugin from installed plugins list
    echo "5. Removing from installed plugins...\n";
    $DB->delete_records('config', ['name' => 'local_digisign']);
    echo "   ✅ Plugin removed from installed list\n";

    // 6. Clear any cached capabilities
    echo "6. Clearing capability cache...\n";
    $DB->delete_records('config', ['name' => 'local_digisign:view']);
    $DB->delete_records('config', ['name' => 'local_digisign:manage']);
    echo "   ✅ Capability cache cleared\n";

    echo "\n✅ Manual uninstall completed successfully!\n\n";

    echo "Next Steps:\n";
    echo "===========\n";
    echo "1. Delete the plugin files manually:\n";
    echo "   rm -rf " . __DIR__ . "\n";
    echo "2. Visit your Moodle admin panel and purge caches\n";
    echo "3. Restart your web server if needed\n\n";

    echo "The plugin should now be completely removed from Moodle.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR during uninstall: " . $e->getMessage() . "\n";
    echo "You may need to manually clean up the database.\n";
    exit(1);
}
