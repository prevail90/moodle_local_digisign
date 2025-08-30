<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Helpers for Docuseal integration.
 */

/**
 * Get plugin config object.
 *
 * @return object
 */
function local_digisign_get_config() {
    return (object)[
        'api_key' => get_config('local_digisign', 'api_key'),
        'api_url' => get_config('local_digisign', 'api_url') ?: 'https://api.docuseal.com',
        'store_local_copy' => get_config('local_digisign', 'store_local_copy')
    ];
}

/**
 * Fetch templates from Docuseal.
 *
 * Returns decoded JSON array or empty array on failure.
 */
function local_digisign_fetch_templates() {
    $cfg = local_digisign_get_config();
    $url = rtrim($cfg->api_url, '/') . '/v1/templates';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'AuthToken: ' . $cfg->api_key,
        'Accept: application/json'
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300 && $resp) {
        $data = json_decode($resp, true);
        return is_array($data) ? $data : [];
    }
    return [];
}

/**
 * Create a submission for a template for a user.
 *
 * Returns decoded API response (array) or null.
 */
function local_digisign_create_submission($templateid, $useremail) {
    global $USER;
    $cfg = local_digisign_get_config();
    $url = rtrim($cfg->api_url, '/') . '/v1/submissions';

    $payload = json_encode([
        'template_id' => (int)$templateid,
        'send_email' => false,
        'submitters' => [
            ['role' => 'Signer', 'email' => $useremail]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'AuthToken: ' . $cfg->api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300 && $resp) {
        return json_decode($resp, true);
    }
    return null;
}

/**
 * Download signed PDF for a submission id.
 *
 * Returns raw bytes (string) or null.
 */
function local_digisign_download_signed_pdf($submissionid) {
    $cfg = local_digisign_get_config();
    $url = rtrim($cfg->api_url, '/') . '/v1/submissions/' . urlencode($submissionid) . '/download';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'AuthToken: ' . $cfg->api_key
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300 && $resp) {
        return $resp;
    }
    return null;
}

/**
 * Save file bytes into user's private file area.
 *
 * Returns stored file_record or false on error.
 */
function local_digisign_save_signed_file_for_user($userid, $filename, $contents) {
    $fs = get_file_storage();
    $context = context_user::instance($userid);
    $filerecord = [
        'contextid' => $context->id,
        'component' => 'user',
        'filearea'  => 'private',
        'itemid'    => 0,
        'filepath'  => '/digisign/',
        'filename'  => $filename
    ];
    return $fs->create_file_from_string($filerecord, $contents);
}

/**
 * Record a submission in local DB table.
 */
function local_digisign_record_submission($userid, $templateid, $templateslug, $submissionid, $submitterslug, $status='created') {
    global $DB;
    $now = time();
    $record = new stdClass();
    $record->userid = $userid;
    $record->templateid = $templateid ?: 0;
    $record->templateslug = $templateslug ?: '';
    $record->submissionid = $submissionid ?: '';
    $record->submitterslug = $submitterslug ?: '';
    $record->status = $status;
    $record->timecreated = $now;
    $record->timemodified = $now;
    return $DB->insert_record('local_digisign_sub', $record);
}

/**
 * Mark submission complete.
 */
function local_digisign_mark_complete($submissionid) {
    global $DB;
    $rec = $DB->get_record('local_digisign_sub', ['submissionid' => $submissionid]);
    if ($rec) {
        $rec->status = 'completed';
        $rec->timemodified = time();
        $DB->update_record('local_digisign_sub', $rec);
        return true;
    }
    return false;
}