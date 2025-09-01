<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade script for local_digisign plugin.
 *
 * @package   local_digisign
 * @copyright 2025 OTA Training Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for local_digisign plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Success/Failure.
 */
function xmldb_local_digisign_upgrade($oldversion) {
    global $CFG, $DB;

    $result = true;
    $dbman = $DB->get_manager();

    // Upgrade from version 2025083100 to 2025083101
    if ($oldversion < 2025083101) {
        // Add any new fields or tables here if needed
        // Example: Add a new field to existing table
        // $table = new xmldb_table('local_digisign_sub');
        // $field = new xmldb_field('newfield', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'existingfield');
        // if (!$dbman->field_exists($table, $field)) {
        //     $dbman->add_field($table, $field);
        // }
        
        upgrade_plugin_savepoint(true, 2025083101, 'local', 'digisign');
    }

    // Upgrade from version 2025083101 to 2025083102
    if ($oldversion < 2025083102) {
        // Add any new fields or tables here if needed
        
        upgrade_plugin_savepoint(true, 2025083102, 'local', 'digisign');
    }

    // Upgrade from version 2025083102 to 2025083103
    if ($oldversion < 2025083103) {
        // Add any new fields or tables here if needed
        
        upgrade_plugin_savepoint(true, 2025083103, 'local', 'digisign');
    }

    // Upgrade from version 2025083103 to 2025083104
    if ($oldversion < 2025083104) {
        // Add any new fields or tables here if needed
        
        upgrade_plugin_savepoint(true, 2025083104, 'local', 'digisign');
    }

    // Upgrade from version 2025083104 to 2025083105
    if ($oldversion < 2025083105) {
        // Add indexes for better performance
        $table = new xmldb_table('local_digisign_sub');
        
        // Add index on submissionid
        $index = new xmldb_index('submissionid', XMLDB_INDEX_NOTUNIQUE, array('submissionid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Add index on status
        $index = new xmldb_index('status', XMLDB_INDEX_NOTUNIQUE, array('status'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Add index on timecreated
        $index = new xmldb_index('timecreated', XMLDB_INDEX_NOTUNIQUE, array('timecreated'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        upgrade_plugin_savepoint(true, 2025083105, 'local', 'digisign');
    }

    // Upgrade from version 2025083105 to 2025083106
    if ($oldversion < 2025083106) {
        // Add foreign key constraint for userid if it doesn't exist
        $table = new xmldb_table('local_digisign_sub');
        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        
        // Check if the key exists by trying to add it and catching the exception
        try {
            $dbman->add_key($table, $key);
        } catch (Exception $e) {
            // Key already exists, which is fine
        }
        
        upgrade_plugin_savepoint(true, 2025083106, 'local', 'digisign');
    }

    // Upgrade from version 2025083106 to 2025083107
    if ($oldversion < 2025083107) {
        // This version includes UI fixes and debugging improvements
        // No database changes needed, just cache clearing
        
        upgrade_plugin_savepoint(true, 2025083107, 'local', 'digisign');
    }

    // Upgrade from version 2025083107 to 2025083108
    if ($oldversion < 2025083108) {
        // This version includes new signing page with direct DocuSeal integration
        // No database changes needed, just cache clearing
        
        upgrade_plugin_savepoint(true, 2025083108, 'local', 'digisign');
    }

    // Upgrade from version 2025083108 to 2025083109
    if ($oldversion < 2025083109) {
        // This version includes final fixes and improvements
        // Add navigation menu item to custommenuitems
        local_digisign_ensure_navigation_menu();
        
        upgrade_plugin_savepoint(true, 2025083109, 'local', 'digisign');
    }

    // Upgrade from version 2025083109 to 2025083110
    if ($oldversion < 2025083110) {
        // This version includes navigation menu fixes
        // Add navigation menu item to custommenuitems
        local_digisign_ensure_navigation_menu();
        
        upgrade_plugin_savepoint(true, 2025083110, 'local', 'digisign');
    }

    // Upgrade from version 2025083110 to 2025083111
    if ($oldversion < 2025083111) {
        // This version includes simplified sign.php implementation
        // No database changes needed, just cache clearing
        
        upgrade_plugin_savepoint(true, 2025083111, 'local', 'digisign');
    }

    // Upgrade from version 2025083111 to 2025083112
    if ($oldversion < 2025083112) {
        // This version includes API-based submission creation with redirect
        // No database changes needed, just cache clearing
        
        upgrade_plugin_savepoint(true, 2025083112, 'local', 'digisign');
    }

    // Upgrade from version 2025083112 to 2025083113
    if ($oldversion < 2025083113) {
        // This version includes AJAX-based submission creation without sign.php
        // No database changes needed, just cache clearing
        
        upgrade_plugin_savepoint(true, 2025083113, 'local', 'digisign');
    }

    // Upgrade from version 2025083113 to 2025083114
    if ($oldversion < 2025083114) {
        // This version fixes URL pattern to use /d/{template_slug} for new submissions
        // No database changes needed, just cache clearing
        
        upgrade_plugin_savepoint(true, 2025083114, 'local', 'digisign');
    }

    // Upgrade from version 2025083114 to 2025083115
    if ($oldversion < 2025083115) {
        // This version adds JavaScript module loading and fixes AMD module issues
        // No database changes needed, just cache clearing
        
        upgrade_plugin_savepoint(true, 2025083115, 'local', 'digisign');
    }

    // Always clear caches after any upgrade
    try {
        // Clear plugin manager caches
        core_plugin_manager::reset_caches();
        
        // Clear navigation caches
        navigation_cache::destroy_volatile_caches();
        
        // Clear all caches
        purge_all_caches();
        
    } catch (Exception $e) {
        // Silently fail if cache clearing fails
    }

    return $result;
}
