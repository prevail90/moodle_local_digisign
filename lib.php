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
 * Try to load composer autoload from plugin vendor/ if present.
 */
function local_digisign_ensure_autoload() {
    static $loaded = null;
    if ($loaded === null) {
        $vendor = __DIR__ . '/vendor/autoload.php';
        if (file_exists($vendor)) {
            require_once($vendor);
            $loaded = true;
        } else {
            $loaded = false;
        }
    }
    return $loaded;
}

/**
 * Fetch templates from Docuseal.
 *
 * Returns an array of template records (the "data" array from Docuseal response), or [] on failure.
 *
 * @param int $limit optional limit to pass to API
 * @return array
 */
function local_digisign_fetch_templates($limit = 100) {
    $cfg = local_digisign_get_config();

    // Try to load composer autoload so the SDK class is available.
    local_digisign_ensure_autoload();

    // 1) Prefer SDK if available.
    if (class_exists('\\Docuseal\\Api')) {
        try {
            $client = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
            $resp = $client->listTemplates(['limit' => (int)$limit]);

            if (is_array($resp) && array_key_exists('data', $resp) && is_array($resp['data'])) {
                return $resp['data'];
            }
            if (is_array($resp)) {
                // SDK might return templates directly.
                return $resp;
            }
        } catch (Exception $e) {
            debugging('local_digisign: Docuseal SDK error while listing templates: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // fall through to HTTP fallback
        }
    }

    // 2) Fallback: plain HTTP GET to /templates
    $base = rtrim($cfg->api_url, '/');
    $url = $base . '/templates';
    if (!empty($limit)) {
        $url .= '?limit=' . (int)$limit;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [
        'AuthToken: ' . $cfg->api_key,
        'Accept: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $resp = curl_exec($ch);
    $curlerr = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        debugging('local_digisign: curl error while fetching templates: ' . $curlerr, DEBUG_DEVELOPER);
        return [];
    }

    $decoded = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugging('local_digisign: json decode error: ' . json_last_error_msg() . ' resp: ' . substr($resp, 0, 1000), DEBUG_DEVELOPER);
        return [];
    }

    if (is_array($decoded) && array_key_exists('data', $decoded) && is_array($decoded['data'])) {
        return $decoded['data'];
    }

    if (is_array($decoded)) {
        return $decoded;
    }

    debugging('local_digisign: unexpected templates response code=' . intval($code), DEBUG_DEVELOPER);
    return [];
}

/**
 * Create a submission for a template for a user.
 *
 * Returns decoded API response (array) or null.
 */
function local_digisign_create_submission($templateid, $useremail) {
    $cfg = local_digisign_get_config();

    // Try SDK first
    if (local_digisign_ensure_autoload() && class_exists('\\Docuseal\\Api')) {
        try {
            $client = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
            $payload = [
                'template_id' => (int)$templateid,
                'send_email' => false,
                'submitters' => [
                    ['role' => 'Signer', 'email' => $useremail]
                ]
            ];
            // SDK method per docs: createSubmission
            $resp = $client->createSubmission($payload);
            if (is_array($resp)) {
                return $resp;
            }
        } catch (Exception $e) {
            debugging('local_digisign: Docuseal SDK create submission error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // fall through to HTTP fallback
        }
    }

    // HTTP fallback (existing behaviour)
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
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300 && $resp) {
        return json_decode($resp, true);
    }
    debugging('local_digisign: create_submission failed code=' . intval($code) . ' resp=' . substr($resp ?: '', 0, 500), DEBUG_DEVELOPER);
    return null;
}

/**
 * Download signed PDF for a submission id.
 *
 * Returns raw bytes (string) or null.
 */
function local_digisign_download_signed_pdf($submissionid) {
    $cfg = local_digisign_get_config();

    // If SDK available, try to get submission documents and download first document url.
    if (local_digisign_ensure_autoload() && class_exists('\\Docuseal\\Api')) {
        try {
            $client = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
            if (method_exists($client, 'getSubmissionDocuments')) {
                $docs = $client->getSubmissionDocuments((int)$submissionid);
                if (is_array($docs) && !empty($docs)) {
                    // SDK may return ['data' => [...] ] or array; handle both.
                    $items = is_array($docs['data'] ?? null) ? $docs['data'] : (is_array($docs) ? $docs : []);
                    if (!empty($items) && is_array($items[0]) && !empty($items[0]['url'])) {
                        $url = $items[0]['url'];
                        // Download the document using curl and AuthToken header
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['AuthToken: ' . $cfg->api_key]);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                        $pdf = curl_exec($ch);
                        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        if ($code >= 200 && $code < 300 && $pdf !== false) {
                            return $pdf;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            debugging('local_digisign: SDK getSubmissionDocuments error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // fall back to download endpoint
        }
    }

    // Fallback: attempt download endpoint
    $url = rtrim($cfg->api_url, '/') . '/v1/submissions/' . urlencode($submissionid) . '/download';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'AuthToken: ' . $cfg->api_key
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300 && $resp !== false) {
        return $resp;
    }
    debugging('local_digisign: download_signed_pdf failed code=' . intval($code), DEBUG_DEVELOPER);
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