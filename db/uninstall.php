<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Cleanup when uninstalling local_digisign plugin.
 *
 * This will:
 * - delete records from local_digisign_sub (if present),
 * - clear plugin config settings,
 * - remove capability assignments,
 * - remove custom menu items from custommenuitems setting.
 *
 * Moodle will also drop DB tables defined in db/install.xml automatically,
 * but we explicitly remove records to ensure clean uninstall.
 *
 * @return bool true on success
 */
function xmldb_local_digisign_uninstall() {
    global $DB;

    // 1) Delete submission records (if table still exists).
    try {
        if ($DB->get_manager()->table_exists(new xmldb_table('local_digisign_sub'))) {
            $DB->delete_records('local_digisign_sub', []); // remove all rows
        }
    } catch (Exception $e) {
        // If table doesn't exist or other error, continue with cleanup.
    }

    // 2) Clear stored plugin config values
    try {
        set_config('api_key', '', 'local_digisign');
        set_config('api_url', '', 'local_digisign');
        set_config('timeout', '', 'local_digisign');
    } catch (Exception $e) {
        // ignore
    }

    // 3) Remove capability assignments
    try {
        $capabilities = ['local/digisign:view', 'local/digisign:manage'];
        foreach ($capabilities as $capability) {
            $DB->delete_records('role_capabilities', ['capability' => $capability]);
        }
    } catch (Exception $e) {
        // ignore
    }

    // 4) Remove our custom menu item from custommenuitems
    try {
        $current_custommenuitems = get_config('core', 'custommenuitems');
        if ($current_custommenuitems) {
            // Split into lines and filter out our menu items
            $lines = explode("\n", $current_custommenuitems);
            $filtered_lines = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip empty lines and our digisign menu items
                if (!empty($line) && strpos($line, '/local/digisign') === false) {
                    $filtered_lines[] = $line;
                }
            }
            
            // Reconstruct the custommenuitems without our items
            $new_custommenuitems = implode("\n", $filtered_lines);
            set_config('custommenuitems', $new_custommenuitems, 'core');
        }
    } catch (Exception $e) {
        // ignore
    }

    // 5) Clear all relevant caches
    try {
        // Clear plugin manager caches
        core_plugin_manager::reset_caches();
        
        // Clear navigation caches
        navigation_cache::destroy_volatile_caches();
        
        // Clear all caches
        purge_all_caches();
        
    } catch (Exception $e) {
        // ignore
    }

    return true;
}