<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Cleanup when uninstalling local_digisign plugin.
 *
 * This will:
 * - delete records from local_digisign_sub (if present),
 * - remove any files stored in users' private file area under /digisign/,
 * - clear plugin config settings.
 *
 * Moodle will also drop DB tables defined in db/install.xml automatically,
 * but we explicitly remove records and files to ensure no leftover files remain.
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

    // 2) Remove files from users' private file areas under /digisign/
    try {
        $fs = get_file_storage();
        // Get all user IDs
        $userids = $DB->get_fieldset_select('user', 'id', '', null);
        if (!empty($userids)) {
            foreach ($userids as $userid) {
                // user context
                $context = context_user::instance($userid);
                // component 'user', filearea 'private', itemid 0, filepath '/digisign/'
                // delete_area_files deletes all files in area; we narrow by filepath
                // delete_area_files signature: delete_area_files($contextid, $component, $filearea, $itemid = 0)
                // We cannot pass filepath, so remove all files in that area and itemid (0),
                // then re-create any other files if necessary is responsibility of site admin.
                // Safer alternative: find files in that area with filepath '/digisign/' and delete them.
                $files = $fs->get_area_files($context->id, 'user', 'private', 0, 'itemid, filepath, filename', false);
                foreach ($files as $file) {
                    // only delete files saved under /digisign/
                    if ($file->get_filepath() === '/digisign/') {
                        $fs->delete_file($file);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Continue even if file deletion fails for some users.
    }

    // 3) Clear stored plugin config values
    // Use set_config to reset values; Moodle will remove plugin config entries on uninstall
    // but set them to empty/0 just in case.
    try {
        set_config('api_key', '', 'local_digisign');
        set_config('api_url', '', 'local_digisign');
        set_config('store_local_copy', 0, 'local_digisign');
    } catch (Exception $e) {
        // ignore
    }

    return true;
}